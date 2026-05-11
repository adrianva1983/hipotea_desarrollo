<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Entity\KommoWebhook;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Entity\Expediente as ExpedienteEntidad;
use AppBundle\Entity\Fase as FaseEntidad;
use AppBundle\Entity\Hito as HitoEntidad;
use AppBundle\Entity\HitoExpediente as HitoExpedienteEntidad;
use AppBundle\Entity\GrupoCamposHito as GrupoCamposHitoEntidad;
use AppBundle\Entity\GrupoHitoExpediente as GrupoHitoExpedienteEntidad;
use AppBundle\Entity\CampoHito as CampoHitoEntidad;
use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client as GuzzleClient;

class KommoController extends Controller
{
    /**
     * 🔑 MAPEO MAESTRO: Claves extraídas → IDs de campos en Hipotea
     * Permite rellenar CUALQUIER campo dinámicamente
     * Estructura: 'clave_extraida' => [campos_id_1, campo_id_2, ...]
     */
    private function obtenerMapeoClavesACampos(): array
    {
        return [
            // Datos personales
            'nombre_completo' => [192, 693],
            'nombre' => [693],
            'apellidos' => [694],
            'dni' => [194],
            'email' => [696, 407],
            'telefono' => [695, 408],
            'provincia' => [689, 458],
            'nacionalidad' => [195, 247, 509, 570],
            'fecha_nacimiento' => [196, 508],
            'estado_civil' => [198, 507],
            
            // Datos laborales
            'empresa' => [220, 545],
            'puesto' => [222, 539],
            'tipo_contrato' => [221, 549], // Nota: algunos también usan opción_id (193)
            'antiguedad' => [223, 541],
            'nomina' => [225, 555], // Nómina mensual
            'ingresos' => [228, 552], // Ingresos anuales
            'nomina_mensual' => [225, 555],
            'ingresos_anuales' => [228, 552],
            
            // Datos financieros
            'ahorro' => [182, 699],
            'banco' => [215, 518], // ¿Con qué banco trabajas?
            'aportacion' => [181, 182],
            
            // Préstamos
            'prestamos_mensuales' => [235, 241],
            'cuota_alquiler' => [212, 520],
            
            // Propiedad
            'precio_inmueble' => [413, 206, 691],
            'valor_estimado' => [206, 375],
            'metros' => [289, 644],
            
            // Observaciones/Comentarios
            'observaciones' => [700, 218, 234, 679],
            'canal' => [701, 704],
            
            // Trabajo/estado laboral (genérico)
            'trabajo_estado' => [690],
        ];
    }

    /**
     * 🔑 MAPEO DE OPCIONES: Texto → IDs de opciones para campos de select
     * Maneja conversión de texto a opciones de Kommo
     */
    private function obtenerMapeoOpcionesGlobales(): array
    {
        return [
            'tipo_contrato' => [
                'indefinido|fijo|completo|tiempo completo' => 104,
                'parcial|part-time|medio tiempo' => 105,
                'temporal|obra|contrata' => 109,
                'autónomo|autónoma|autonomo|autonoma' => 97,
                'pensionista|pensión' => 98,
                'mercantil' => 103,
                'funcionario' => 107,
                'militar' => 357,
                'laboral fijo' => 555,
            ],
            'estado_civil' => [
                // Soltero
                'soltero|soltera|solt@' => 81,
                
                // Casado - IMPORTANTE: distinguir régimen matrimonial
                // Si especifica "gananciales" → 189 (más común por defecto)
                'casad@.*gananciales|gananciales.*casad@' => 189,
                'casad@.*separación|separación.*casad@' => 82,
                // Si solo dice "casado" sin especificar → gananciales es más común
                'casado|casada|casad@|married' => 189,
                
                // Divorciado
                'divorciado|divorciada|divorciad@' => 85,
                
                // Separado
                'separado|separada|separ@' => 84,
                
                // Viudo
                'viudo|viuda|viud@' => 86,
                
                // Pareja de hecho / Unión
                'pareja de hecho|unión de hecho|pareja|unión|unmarried couple' => 83,
            ],
            'domicilio' => [
                'propiedad|propia|owner' => 99,
                'alquiler|renta|rental' => 100,
                'familiar|family' => 101,
            ],
        ];
    }

    /**
     * Recibe webhook de Kommo, obtiene contacto de API, busca/crea cliente,
     * busca/crea expediente y actualiza hitos con datos de Kommo
     * Ruta: /API/kommo
     * Método: POST
     * 
     * Soporta 3 tipos de webhooks:
     * 1. message.add → Mensaje en chat (contact_id en message.add[0].contact_id)
     * 2. contacts.add → Contact creado (id en contacts.add[0].id)
     * 3. leads.add → Lead creado (linked_leads_id apunta al contact)
     */
    public function kommoWebhookAction(Request $request)
    {
        error_log('ENtro11111111111');
        $content = null;
        $data = [];

        try {
            // Obtener el contenido del request
            $content = $request->getContent();
            
            // ⚠️ LOG COMPLETO del webhook recibido para debugging
            error_log('KommoController: Raw webhook content (primeros 1000 chars): ' . substr($content, 0, 1000));
            error_log('KommoController: Content-Type: ' . $request->getContentType());
            
            // Validar que el contenido no esté vacío
            if (empty($content)) {
                throw new \Exception('Webhook vacío - No se recibió contenido en el request body');
            }
            
            // 🔑 DETECCIÓN AUTOMÁTICA: JSON vs Form-Urlencoded
            $contentType = $request->getContentType();
            
            // Intentar parsear como JSON primero (si Content-Type es json)
            if (strpos($contentType, 'json') !== false) {
                error_log('KommoController: Detectado Content-Type JSON - Parseando como JSON');
                $data = json_decode($content, true);
                
                if ($data === null) {
                    $jsonError = json_last_error_msg();
                    throw new \Exception('Error decodificando JSON del webhook: ' . $jsonError);
                }
            } 
            // Intentar parsear como form-urlencoded (Content-Type = form)
            elseif (strpos($contentType, 'form') !== false || strpos($contentType, 'urlencoded') !== false) {
                error_log('KommoController: Detectado Content-Type Form-Urlencoded - Parseando con parse_str()');
                parse_str($content, $data);
                error_log('KommoController: Form-urlencoded parseado. Keys: ' . json_encode(array_keys($data)));
            }
            // Si no detectamos Content-Type, intentar ambos
            else {
                error_log('KommoController: Content-Type no especificado o desconocido. Intentando JSON primero...');
                $data = json_decode($content, true);
                
                if ($data === null) {
                    error_log('KommoController: JSON parsing falló. Intentando form-urlencoded...');
                    parse_str($content, $data);
                    error_log('KommoController: Form-urlencoded parseado. Keys: ' . json_encode(array_keys($data)));
                }
            }
            
            // Validar que obtuvimos datos
            if (!is_array($data) || empty($data)) {
                throw new \Exception('No se pudieron parsear los datos del webhook. Content: ' . substr($content, 0, 500));
            }
            
            // Crear cliente HTTP
            $httpClient = new GuzzleClient();

            // Log de la recepción del webhook (MEJORADO)
            $tiposPresentes = is_array($data) ? array_keys($data) : [];
            error_log('KommoController: Webhook de Kommo recibido: ' . json_encode([
                'tipos' => $tiposPresentes,
                'total_keys' => count($tiposPresentes),
                'timestamp' => date('Y-m-d H:i:s')
            ]));

            // Detectar tipo de webhook y extraer datos
            $tipoWebhook = $this->detectarTipoWebhook($data);
            error_log('KommoController: Tipo de webhook detectado: ' . $tipoWebhook);

            $contactId = null;
            $datosMensaje = [];
            $iaUsed = false;
            $iaAvailable = false;

            // Procesar según tipo
            switch ($tipoWebhook) {
                case 'message':
                    $contactId = $this->extraerContactoIdDelMensaje($data);
                    // 🤖 NUEVO: Usar IA para extraer datos del mensaje
                    $textoMensaje = $this->extraerTextoDelMensaje($data);
                    error_log('KommoController: obteniendo texto del mensaje: ' . $textoMensaje . ' ');
                    if (!empty($textoMensaje)) {
                        error_log('KommoController: Iniciando extraccion IA del mensaje');
                        $iaUsed = true;
                        $datosIA = $this->extraerDatosConIA($textoMensaje);
                        
                        // Log detallado de respuesta IA
                        error_log('KommoController: 🤖 Respuesta IA completa: ' . json_encode($datosIA));
                        
                        $iaAvailable = !empty($datosIA['success']);
                        if ($datosIA['success'] ?? false) {
                            error_log('KommoController: ✅ IA extraccion exitosa - ' . $datosIA['campos_detectados'] . ' campos, confianza: ' . $datosIA['confianza_promedio']);
                            $datosMensaje = $datosIA;
                        } else {
                            error_log('KommoController: ❌ IA extraccion falló. Razón: ' . ($datosIA['mensaje'] ?? 'sin mensaje de error') . '. Usando regex como fallback');
                            $datosMensaje = $this->extraerDatosDelMensaje($data);
                        }
                    } else {
                        $datosMensaje = $this->extraerDatosDelMensaje($data);
                    }
                    break;
                
                case 'contact':
                    $contactId = $this->extraerContactoIdDelContact($data);
                    break;
                
                case 'contact_update':
                    // Actualización de contacto existente - ignorar de momento
                    error_log('KommoController: Webhook de actualización de contacto - ignorando por ahora');
                    return new JsonResponse([
                        'ok' => true,
                        'mensaje' => 'Webhook de actualización de contacto recibido pero ignorado',
                        'tipo' => 'contact_update',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;
                
                case 'lead':
                    // Para leads, buscamos el contact asociado
                    $leadData = $data['leads']['add'][0] ?? null;
                    if ($leadData && !empty($leadData['linked_leads_id'])) {
                        // linked_leads_id contiene los IDs de contacts asociados
                        $contactId = (int)array_key_first($leadData['linked_leads_id']);
                    }
                    if ($contactId) {
                        error_log('KommoController: Lead vinculado a contact ID: ' . $contactId);
                    }
                    break;
                
                case 'talk_ignored':
                    // Webhook de conversación/talk - ignorar completamente
                    error_log('KommoController: Webhook TALK ignorado correctamente');
                    return new JsonResponse([
                        'ok' => true,
                        'mensaje' => 'Webhook TALK (conversación) recibido pero ignorado',
                        'tipo' => 'talk',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                default:
                    // Webhook con estructura no reconocida
                    error_log('KommoController: Webhook con tipo no soportado: ' . $tipoWebhook . '. Data keys: ' . json_encode(array_keys($data)));
                    
                    // Guardar webhook para auditoría y debugging
                    try {
                        $em = $this->getDoctrine()->getManager();
                        $kommoWebhook = new KommoWebhook();
                        $kommoWebhook->setWebhookType('no_soportado');
                        $kommoWebhook->setKommoId('webhook-desconocido');
                        $kommoWebhook->setJsonRecibido($data);
                        $kommoWebhook->setEstado('no_procesado');
                        $kommoWebhook->setErrorMensaje('Estructura de webhook no reconocida. Tipo detectado: ' . $tipoWebhook);
                        $kommoWebhook->setFecha(new \DateTime());
                        $em->persist($kommoWebhook);
                        $em->flush();
                        
                        error_log('KommoController: Webhook desconocido guardado para auditoría (ID: ' . $kommoWebhook->getId() . ')');
                    } catch (\Exception $e) {
                        error_log('KommoController: Error guardando webhook desconocido: ' . $e->getMessage());
                    }
                    
                    // Retornar respuesta explicativa
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Estructura de webhook no soportada',
                        'detalles' => [
                            'tipo_detectado' => $tipoWebhook,
                            'tipos_soportados' => ['message', 'contact', 'lead'],
                            'keys_recibidas' => array_keys($data),
                            'mensaje' => 'El webhook recibido no tiene una estructura reconocida'
                        ],
                        'timestamp' => date('Y-m-d H:i:s')
                    ], 400);
            }

            if (!$contactId) {
                throw new \Exception('No se encontró ID de contacto en el webhook');
            }

            // Obtener datos del contacto desde Kommo API
            $contactoKommo = $this->obtenerContactoKommo($httpClient, $contactId);
            error_log('KommoController: Contacto obtenido de API: ' . ($contactoKommo['name'] ?? 'sin nombre'));

            // Obtener EntityManager
            $em = $this->getDoctrine()->getManager();

            // VALIDACIÓN: Extraer teléfono y email
            $telefono = $this->extraerTelefono($contactoKommo);
            $email = $this->extraerEmail($contactoKommo);

            // Si no hay teléfono ni email, intentar obtener más detalles llamando a API adicional
            if (empty($telefono) && empty($email)) {
                error_log('KommoController: Teléfono y email vacíos en respuesta inicial. Intentando obtener más detalles de API...');
                
                // Intentar obtener detalles adicionales del contacto desde API v4 con parámetros extendidos
                try {
                    $contactoDetallado = $this->obtenerContactoKommoDetallado($httpClient, $contactId);
                    if ($contactoDetallado) {
                        error_log('KommoController: Detalles adicionales obtenidos de API');
                        // Intentar extraer de la respuesta detallada
                        $telefonoDetallado = $this->extraerTelefono($contactoDetallado);
                        $emailDetallado = $this->extraerEmail($contactoDetallado);
                        
                        if (!empty($telefonoDetallado) || !empty($emailDetallado)) {
                            error_log('KommoController: Datos encontrados en respuesta detallada. Tel: ' . ($telefonoDetallado ?: 'vacío') . ', Email: ' . ($emailDetallado ?: 'vacío'));
                            // Fusionar datos
                            $contactoKommo = array_merge($contactoKommo, $contactoDetallado);
                            $telefono = $telefonoDetallado ?: $telefono;
                            $email = $emailDetallado ?: $email;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('KommoController: Error obteniendo detalles adicionales: ' . $e->getMessage());
                }
            }

            // Si sigue sin haber teléfono ni email después de intentos, NO PROCESAR
            if (empty($telefono) && empty($email)) {
                error_log('KommoController: ⚠️ Sin teléfono ni email. Webhook incompleto - Solo registrando en BD sin procesar');
                
                // Obtener EntityManager (si no lo obtuvimos antes)
                if (!isset($em)) {
                    $em = $this->getDoctrine()->getManager();
                }

                // Crear registro de webhook INCOMPLETO (auditoría)
                $kommoWebhook = new KommoWebhook();
                $kommoWebhook->setWebhookType($tipoWebhook);
                $kommoWebhook->setKommoId($contactId ?: 'sin-id');
                $kommoWebhook->setJsonRecibido($data);
                $kommoWebhook->setEstado('incompleto_sin_contacto');
                $kommoWebhook->setErrorMensaje('Webhook sin teléfono ni email - No se creó cliente ni expediente');
                $kommoWebhook->setFecha(new \DateTime());

                $em->persist($kommoWebhook);
                $em->flush();

                error_log('KommoController: Webhook incompleto registrado en BD (sin procesar)');

                // Retornar 200 OK igual (para que Kommo no reintente)
                error_log('KommoController: ✅ Creando JsonResponse para webhook incompleto (200 OK)...');
                $respuesta = new JsonResponse([
                    'ok' => true,
                    'mensaje' => 'Webhook recibido pero no procesado (falta teléfono/email)',
                    'tipo' => $tipoWebhook,
                    'estado' => 'incompleto',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $respuesta->headers->set('Content-Type', 'application/json; charset=UTF-8');
                $respuesta->setStatusCode(200);
                
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                error_log('KommoController: ✅ JsonResponse lista. Status: 200');
                
                // Enviar respuesta de forma explícita y garantizada
                error_log('KommoController: Enviando respuesta HTTP al cliente...');
                header('Content-Type: application/json; charset=UTF-8');
                header('HTTP/1.1 200 OK');
                header('Status: 200 OK');
                $data = array('ok' => true, 'mensaje' => 'Webhook recibido pero no procesado (falta teléfono/email)', 'tipo' => $tipoWebhook, 'estado' => 'incompleto', 'timestamp' => date('Y-m-d H:i:s'));
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                error_log('KommoController: ✅ RESPUESTA ENVIADA AL CLIENTE (200 OK)');
                exit(0);
            }

            // Obtener EntityManager (si no lo obtuvimos antes)
            if (!isset($em)) {
                $em = $this->getDoctrine()->getManager();
            }

            // Buscar o crear cliente
            $cliente = $this->buscarOCrearCliente($em, $contactoKommo);
            error_log('KommoController: Cliente procesado (ID: ' . $cliente->getIdUsuario() . ')');

            // Buscar o crear expediente
            $expediente = $this->buscarOCrearExpediente($em, $cliente);
            error_log('KommoController: Expediente procesado (ID: ' . $expediente->getIdExpediente() . ')');

            // 🔗 Guardar referencia a Kommo en el expediente (si el método existe)
            if (method_exists($expediente, 'setKommoContactId')) {
                $expediente->setKommoContactId($contactId);
                error_log('KommoController: Kommo Contact ID guardado en expediente: ' . $contactId);
            }
            if (method_exists($expediente, 'setKommoLastUpdate')) {
                $expediente->setKommoLastUpdate(new \DateTime());
            }
            $em->persist($expediente);
            $em->flush();

            // Actualizar hitos con datos de Kommo + datos del mensaje
            $desglose = $this->actualizarHitosKommo($em, $expediente, $contactoKommo, $datosMensaje);
            error_log('KommoController: Hitos actualizados para expediente ID: ' . $expediente->getIdExpediente() . ' - Desglose: ' . json_encode($desglose));

            // Crear registro de webhook (auditoría)
            $kommoWebhook = new KommoWebhook();
            $kommoWebhook->setWebhookType($tipoWebhook);
            $kommoWebhook->setKommoId($contactId ?: 'sin-id');
            $kommoWebhook->setJsonRecibido($data);
            $kommoWebhook->setEstado('procesado');
            $kommoWebhook->setFecha(new \DateTime());

            $em->persist($kommoWebhook);
            $em->flush();

            error_log('KommoController: Webhook registrado en BD con ID: ' . $kommoWebhook->getId());

            // Preparar datos de respuesta de forma segura
            $idClienteSeguro = isset($cliente) ? $cliente->getIdUsuario() : null;
            $idExpedienteSeguro = isset($expediente) ? $expediente->getIdExpediente() : null;
            $desgloseSeguro = isset($desglose) ? $desglose : [];
            $iaUsedSeguro = isset($iaUsed) ? $iaUsed : false;
            $iaAvailableSeguro = isset($iaAvailable) ? $iaAvailable : false;
            
            error_log('KommoController: Preparando respuesta final - Cliente: ' . $idClienteSeguro . ', Expediente: ' . $idExpedienteSeguro);

            // Respuesta exitosa
            error_log('KommoController: ✅ Creando JsonResponse para respuesta exitosa (200 OK)...');
            $respuesta = new JsonResponse([
                'ok' => true,
                'mensaje' => 'Webhook recibido y procesado correctamente',
                'tipo' => $tipoWebhook,
                'idCliente' => $idClienteSeguro,
                'idExpediente' => $idExpedienteSeguro,
                'desgloseHitos' => $desgloseSeguro,
                'ia_used' => $iaUsedSeguro,
                'ia_available' => $iaAvailableSeguro,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Establecer headers explícitamente
            $respuesta->headers->set('Content-Type', 'application/json; charset=UTF-8');
            $respuesta->setStatusCode(200);
            
            // Limpiar cualquier output buffer existente ANTES de retornar
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            error_log('KommoController: ✅ JsonResponse lista. Status: 200');
            
            // Enviar respuesta de forma explícita y garantizada
            error_log('KommoController: Enviando respuesta HTTP al cliente...');
            $responseData = array(
                'ok' => true,
                'mensaje' => 'Webhook recibido y procesado correctamente',
                'tipo' => $tipoWebhook,
                'idCliente' => $idClienteSeguro,
                'idExpediente' => $idExpedienteSeguro,
                'desgloseHitos' => $desgloseSeguro,
                'ia_used' => $iaUsedSeguro,
                'ia_available' => $iaAvailableSeguro,
                'timestamp' => date('Y-m-d H:i:s')
            );
            header('Content-Type: application/json; charset=UTF-8');
            header('HTTP/1.1 200 OK');
            header('Status: 200 OK');
            echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
            error_log('KommoController: ✅ RESPUESTA ENVIADA AL CLIENTE (200 OK)');
            exit(0);

        } catch (\Throwable $e) {
            // Log detallado del error (captura Exception Y Error/Fatal)
            error_log('KommoController: ❌ EXCEPCIÓN/ERROR CAPTURADO: ' . $e->getMessage());
            error_log('KommoController: Tipo: ' . get_class($e) . ', File: ' . $e->getFile() . ':' . $e->getLine());
            error_log('KommoController: Stack trace: ' . $e->getTraceAsString());

            // Intentar guardar el error en BD (sin que falle el return)
            try {
                error_log('KommoController: Intentando guardar error en BD...');
                $em = $this->getDoctrine()->getManager();
                $kommoWebhook = new KommoWebhook();
                $kommoWebhook->setWebhookType('error');
                $kommoWebhook->setKommoId('error-' . uniqid());
                
                // Parsear contenido de forma muy segura
                $dataParaGuardar = ['error' => $e->getMessage()];
                if (!empty($content)) {
                    $parsed = @json_decode($content, true);
                    if ($parsed === null) {
                        @parse_str($content, $parsed);
                    }
                    if (is_array($parsed) && !empty($parsed)) {
                        $dataParaGuardar = array_merge(['error' => $e->getMessage()], $parsed);
                    }
                }
                
                $kommoWebhook->setJsonRecibido($dataParaGuardar);
                $kommoWebhook->setEstado('error');
                $kommoWebhook->setErrorMensaje(substr($e->getMessage(), 0, 1000));
                $kommoWebhook->setFecha(new \DateTime());

                $em->persist($kommoWebhook);
                $em->flush();
                
                error_log('KommoController: ✅ Error guardado en BD');
            } catch (\Throwable $dbError) {
                error_log('KommoController: ⚠️ No se pudo guardar error en BD: ' . $dbError->getMessage());
            }

            // Respuesta de error garantizada (SIEMPRE retorna JSON válido)
            error_log('KommoController: Preparando respuesta de error 400');
            
            // Limpiar output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            error_log('KommoController: Enviando respuesta de error 400...');
            $errorData = array(
                'ok' => false,
                'error' => $e->getMessage(),
                'tipo_error' => get_class($e),
                'timestamp' => date('Y-m-d H:i:s')
            );
            header('Content-Type: application/json; charset=UTF-8');
            header('HTTP/1.1 400 Bad Request');
            header('Status: 400 Bad Request');
            echo json_encode($errorData, JSON_UNESCAPED_UNICODE);
            error_log('KommoController: ✅ RESPUESTA DE ERROR ENVIADA AL CLIENTE (400)');
            exit(0);
        }
        
        // FALLBACK: Si por alguna razón no hay return antes, retornar error
        error_log('KommoController: ⚠️ FALLBACK - Alcanzado final sin return (esto nunca debería pasar)');
        
        // Limpiar output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $fallbackData = array(
            'ok' => false,
            'error' => 'Error desconocido en el flujo de procesamiento',
            'timestamp' => date('Y-m-d H:i:s')
        );
        header('Content-Type: application/json; charset=UTF-8');
        header('HTTP/1.1 500 Internal Server Error');
        header('Status: 500 Internal Server Error');
        echo json_encode($fallbackData, JSON_UNESCAPED_UNICODE);
        error_log('KommoController: ✅ RESPUESTA FALLBACK ENVIADA AL CLIENTE (500)');
        exit(0);
    }

    /*public function kommoWebhookAction(Request $request)
    {
        return new JsonResponse([
            'ok' => true,
            'error' => 'Método no implementado',
        ], 200);
    }*/

    /**
     * Detecta el tipo de webhook basándose en la estructura del JSON
     */
    private function detectarTipoWebhook(array $data): string
    {
        // Mensaje (nuevo mensaje en chat)
        if (!empty($data['message']['add'])) {
            error_log('KommoController: Detectado webhook MESSAGE.ADD (Kommo chat)');
            return 'message';
        }
        
        // Contacto (nuevo contacto)
        if (!empty($data['contacts']['add'])) {
            error_log('KommoController: Detectado webhook CONTACTS.ADD (Nuevo contacto)');
            return 'contact';
        }
        
        // Actualización de contacto
        if (!empty($data['contacts']['update'])) {
            error_log('KommoController: Detectado webhook CONTACTS.UPDATE (Contacto actualizado)');
            return 'contact_update';
        }
        
        // Lead (nueva oportunidad/lead)
        if (!empty($data['leads']['add'])) {
            error_log('KommoController: Detectado webhook LEADS.ADD (Nuevo lead)');
            return 'lead';
        }
        
        // Talk (conversación - IGNORAR por ahora)
        if (!empty($data['talk']['add']) || !empty($data['talk']['update'])) {
            error_log('KommoController: Detectado webhook TALK (conversación) - Ignorando');
            return 'talk_ignored';
        }
        
        // Formatos alternativos o heredados
        if (!empty($data['message']) && is_array($data['message'])) {
            error_log('KommoController: Detectado webhook MESSAGE (formato alternativo)');
            return 'message';
        }
        if (!empty($data['contact']) && is_array($data['contact'])) {
            error_log('KommoController: Detectado webhook CONTACT (formato alternativo)');
            return 'contact';
        }
        
        // Estructura desconocida
        $keys = array_keys($data);
        error_log('KommoController: Estructura webhook no reconocida. Keys: ' . json_encode($keys) . '. Esperados: message, contacts, leads, talk');
        
        return 'unknown';
    }

    /**
     * Extrae contactId del webhook de mensaje
     */
    private function extraerContactoIdDelMensaje(array $data): ?int
    {
        if (!empty($data['message']['add'][0]['contact_id'])) {
            return (int)$data['message']['add'][0]['contact_id'];
        }
        return null;
    }

    /**
     * Extrae contactId del webhook de contact
     */
    private function extraerContactoIdDelContact(array $data): ?int
    {
        if (!empty($data['contacts']['add'][0]['id'])) {
            return (int)$data['contacts']['add'][0]['id'];
        }
        return null;
    }

    /**
     * 🤖 Extrae el texto del mensaje del webhook
     * SOLO se llama si $tipoWebhook === 'message'
     */
    private function extraerTextoDelMensaje(array $data): string
    {
        if (empty($data['message']['add'][0]['text'])) {
            return '';
        }
        return trim((string)$data['message']['add'][0]['text']);
    }

    /**
     * 🤖 Llama a InteligenciaArtificialController para extraer datos con IA
     * Envía el texto a través del endpoint /API/procesar_texto_para_expediente
     * Retorna estructura unificada: { success, valores_texto, valores_opcion, ... }
     */
    private function extraerDatosConIA(string $texto): array
    {
        // Validar texto no vacío
        if (empty(trim($texto))) {
            error_log('KommoController: ⚠️ Texto vacío para IA');
            return ['success' => false];
        }

        error_log('KommoController: 📝 IA - Texto a procesar: ' . substr($texto, 0, 100) . '...');

        // INTENTO 1: Llamada al nuevo método procesarTextoExpedienteAction (con fuzzy matching Levenshtein)
        error_log('KommoController: 1️⃣ Intentando forward() a procesarTextoExpedienteAction (nuevo)...');
        try {
            // Crear request JSON POST
            $request = new Request(
                [],  // query
                [],  // request (vacío, usaremos JSON body)
                [],  // attributes
                [],  // cookies
                [],  // files
                [    // server info
                    'REQUEST_METHOD' => 'POST',
                    'CONTENT_TYPE' => 'application/json'
                ],
                json_encode([
                    'texto' => $texto,
                    'tipo_entrada' => 'kommo',
                    'grupos' => [4, 29, 5, 6],
                ])
            );

            error_log('KommoController: Forward request method: ' . $request->getMethod());

            $response = $this->forward('AppBundle:InteligenciaArtificial:procesarTextoExpediente', [
                'request' => $request
            ]);

            $responseContent = $response->getContent();
            error_log('KommoController: 1️⃣ Forward - Respuesta cruda (primeros 300 chars): ' . substr($responseContent, 0, 300));

            $datosExtraidos = json_decode($responseContent, true);
            
            // Validar que sea JSON válido
            if ($datosExtraidos === null) {
                error_log('KommoController: 1️⃣ ⚠️ JSON inválido en respuesta forward: ' . $responseContent);
                throw new \Exception('Respuesta forward no es JSON válido');
            }

            // Validar estructura
            $success = $datosExtraidos['success'] ?? false;
            error_log('KommoController: 1️⃣ Forward - Parseado: success=' . ($success ? 'true' : 'false'));

            if (!$success) {
                error_log('KommoController: 1️⃣ ⚠️ Forward retornó success=false, pasando a cURL');
                throw new \Exception('Forward returned success=false');
            }

            // Normalizar respuesta nueva: extraer datosProcessados si existe
            if (isset($datosExtraidos['datosProcessados'])) {
                $datosProcessados = $datosExtraidos['datosProcessados'];
                $datosExtraidos = [
                    'success' => true,
                    'valores_texto' => $datosProcessados['valores_texto'] ?? [],
                    'valores_opcion' => $datosProcessados['valores_opcion'] ?? [],
                    'campos_procesados' => $datosProcessados['campos_procesados'] ?? [],
                    'resultadoIA' => $datosExtraidos['resultadoIA'] ?? null,
                ];
                error_log('KommoController: ✅ Estructura nueva detectada (procesarTextoExpediente). Campos: ' . count($datosProcessados['campos_procesados'] ?? []));
            } elseif (!isset($datosExtraidos['valores_texto']) || !isset($datosExtraidos['valores_opcion'])) {
                error_log('KommoController: 1️⃣ ⚠️ Estructura incompleta en forward, normalizando...');
                $datosExtraidos = $this->normalizarRespuestaIA($datosExtraidos);
            }

            error_log('KommoController: ✅ IA via forward() EXITOSA');
            return $datosExtraidos;

        } catch (\Exception $forwardError) {
            error_log('KommoController: 1️⃣ ❌ Forward falló: ' . $forwardError->getMessage() . ' (Line: ' . $forwardError->getLine() . ')');
        }

        // INTENTO 2: Via cURL (fallback)
        error_log('KommoController: 2️⃣ Intentando fallback cURL...');
        $curlData = $this->extraerDatosConIAviaCurl($texto);
        
        if (isset($curlData['success']) && $curlData['success']) {
            error_log('KommoController: ✅ IA via cURL EXITOSA');
            return $curlData;
        }

        error_log('KommoController: 2️⃣ ❌ cURL también falló');

        // INTENTO 3: Fallback regex
        error_log('KommoController: 3️⃣ Usando fallback regex como último recurso...');
        $regexData = $this->extraerDatosConRegex($texto);
        error_log('KommoController: 3️⃣ Regex extrajo ' . count($regexData['valores_texto'] ?? []) . ' valores de texto');
        
        return $regexData;
    }

    /**
     * 📝 Fallback final: Extrae datos usando regex directamente del texto
     * Retorna estructura unificada compatible con IA
     */
    private function extraerDatosConRegex(string $texto): array
    {
        try {
            error_log('KommoController: Iniciando extracción regex del mensaje');
            
            $datosExtraidos = [];
            $mapeoClavesACampos = $this->obtenerMapeoClavesACampos();
            $mapeoOpciones = $this->obtenerMapeoOpcionesGlobales();

            // Patrón: DNI español (números + letra o números con letras)
            if (preg_match('/(?:DNI|D\.N\.I|documento)\s*:?\s*([0-9]{8}[A-Za-z]|[0-9]{8,9})/i', $texto, $matches)) {
                $datosExtraidos['dni'] = strtoupper(trim($matches[1]));
                error_log('KommoController: Regex - DNI extraído: ' . $datosExtraidos['dni']);
            }

            // Patrón: salario, nómina, sueldo (números)
            if (preg_match('/(?:salario neto|salario|nómina|nomina|sueldo neto|sueldo)\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                error_log('KommoController: Regex - Nómina extraída: ' . $valor);
            } elseif (preg_match('/\b(?:gano|cobro|percibo)\s*:??\s*(\d{2,6}(?:[.,]\d{2})?)\b\s*(?:€|euros)?/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                error_log('KommoController: Regex - Nómina extraída (patrón gano): ' . $valor);
            }

            // Patrón: tipo de contrato (indefinido, temporal, autónomo, por obra)
            if (preg_match('/\b(indefinid[oa]|temporal|fijo|autonomo|autónomo|por obra|obra y servicio|pensionista|emplead[oa])\b/i', $texto, $m)) {
                $datosExtraidos['tipo_contrato'] = strtolower($m[1]);
                error_log('KommoController: Regex - Tipo de contrato extraído: ' . $datosExtraidos['tipo_contrato']);
            }

            // Patrón: empresa (texto)
            if (preg_match('/empresa\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                error_log('KommoController: Regex - Empresa extraída: ' . $datosExtraidos['empresa']);
            }

            // Patrón: puesto, cargo (texto)
            if (preg_match('/(?:puesto|cargo)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['puesto'] = trim($matches[1]);
                error_log('KommoController: Regex - Puesto extraído: ' . $datosExtraidos['puesto']);
            }

            // Patrón: ingresos, ingresos anuales (números)
            if (preg_match('/ingresos\s*(?:anuales|mensuales)?\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ingresos'] = $valor;
                error_log('KommoController: Regex - Ingresos extraídos: ' . $valor);
            }

            // Patrón: ciudad, provincia, localidad (texto)
            if (preg_match('/(?:ciudad|provincia|localidad|residencia)\s*:?\s*([A-Za-z0-9\s\&\.\-áéíóúñÁÉÍÓÚÑ]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['provincia'] = trim($matches[1]);
                error_log('KommoController: Regex - Provincia extraída: ' . $datosExtraidos['provincia']);
            }

            // Patrón: ahorro (números)
            if (preg_match('/ahorro\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ahorro'] = $valor;
                error_log('KommoController: Regex - Ahorro extraído: ' . $valor);
            }

            // Patrón: nacionalidad (texto)
            if (preg_match('/nacionalidad\s*:?\s*([A-Za-záéíóúñÁÉÍÓÚÑ\s\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['nacionalidad'] = trim($matches[1]);
                error_log('KommoController: Regex - Nacionalidad extraída: ' . $datosExtraidos['nacionalidad']);
            }
            // Patrón: estado civil (soltero/a, casado/a, divorciado/a, separado/a, viudo/a, pareja de hecho)
            // Captura también régimen matrimonial si se especifica (gananciales, separación de bienes)
            if (preg_match('/\b(?:soy\s+)?(?:estado\s*civil\s*[:\s])?\s*(solter[oa]|casad[oa](?:\s+en\s+(?:gananciales|separaci[oó]n\s+de\s+bienes))?|divorciad[oa]?|separad[oa]|viud[oa]|pareja\s+de\s+hecho|uni[oó]n\s+de\s+hecho)\b/i', $texto, $matches)) {
                $valor = trim($matches[1]);
                if (!empty($valor)) {
                    $datosExtraidos['estado_civil'] = $valor;
                    error_log('KommoController: Regex - Estado civil extraído: ' . $datosExtraidos['estado_civil']);
                }
            }

            // Patrón: "Trabajo en X" / "Trabajo como X" / "Trabajo para X" — capturar nombre de empresa
            if (preg_match('/\b(?:trabajo en|trabajo como|trabaja en|trabajo para|empleado en|trabajo:|trabajo\s-\s)\s*:?\s*([A-Za-z0-9\s\&\.\-\,\(\)]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                error_log('KommoController: Regex - Empresa extraída (trabajo en): ' . $datosExtraidos['empresa']);
            }

            // Patrón: banco (texto)
            if (preg_match('/(?:banco|trabajo con|entidad|cuenta en)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['banco'] = trim($matches[1]);
                error_log('KommoController: Regex - Banco extraído: ' . $datosExtraidos['banco']);
            }

            // 🔄 CONVERTIR DATOS EXTRAÍDOS A ESTRUCTURA UNIFICADA
            $valoresTexto = [];
            $valoresOpcion = [];

            foreach ($datosExtraidos as $clave => $valor) {
                // Mapear clave a IDs de campo
                $idscamp = $mapeoClavesACampos[$clave] ?? [];
                
                if (!empty($idscamp)) {
                    // Si es valor que puede ser opción (tipo_contrato)
                    if ($clave === 'tipo_contrato') {
                        foreach ($idscamp as $idCampo) {
                            $idOpcion = $this->mapearValorAOpcion($valor, $mapeoOpciones);
                            if ($idOpcion) {
                                $valoresOpcion[$idCampo] = $idOpcion;
                            }
                        }
                    } else {
                        // Valor de texto normal
                        foreach ($idscamp as $idCampo) {
                            $valoresTexto[$idCampo] = $valor;
                        }
                    }
                }
            }

            error_log('KommoController: Estructura regex convertida a: ' . count($valoresTexto) . ' valores, ' . count($valoresOpcion) . ' opciones');

            return [
                'success' => false, // Regex es fallback, no es 100% confiable
                'valores_texto' => $valoresTexto,
                'valores_opcion' => $valoresOpcion,
                'campos_detectados' => count($valoresTexto) + count($valoresOpcion),
                'confianza_promedio' => 0.50, // Menor confianza que IA
                'metodo' => 'regex_fallback'
            ];

        } catch (\Exception $e) {
            error_log('KommoController: Error en extraerDatosConRegex: ' . $e->getMessage());
            return [
                'success' => false,
                'valores_texto' => [],
                'valores_opcion' => [],
                'campos_detectados' => 0,
                'confianza_promedio' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🤖 Normaliza respuesta de IA a estructura unificada si es necesario
     */
    private function normalizarRespuestaIA(array $datosIA): array
    {
        if (isset($datosIA['valores_texto']) && isset($datosIA['valores_opcion'])) {
            return $datosIA; // Ya está en formato correcto
        }

        // Si no, asumir que es estructura antigua y retornar como está
        error_log('KommoController: IA - Respuesta no tiene estructura de valores_texto/valores_opcion');
        
        return [
            'success' => $datosIA['success'] ?? false,
            'valores_texto' => [],
            'valores_opcion' => [],
            'campos_detectados' => $datosIA['campos_detectados'] ?? 0,
            'confianza_promedio' => $datosIA['confianza_promedio'] ?? 0,
            'datos_crudos' => $datosIA // Guardar original para debugging
        ];
    }

    /**
     * 🤖 Fallback: Llamar IA vía cURL si forward falla
     * Usa el nuevo endpoint procesarTextoExpediente (con fuzzy matching)
     */
    private function extraerDatosConIAviaCurl(string $texto): array
    {
        try {
            error_log('KommoController: IA cURL - Iniciando llamada a API externa (procesarTextoExpediente)...');
            
            $client = new GuzzleClient();
            $response = $client->post(
                'https://areaprivada.hipotea.com/API/procesar_texto_expediente',
                [
                    'json' => [
                        'texto' => $texto,
                        'tipo_entrada' => 'kommo',
                        'grupos' => [4, 29, 5, 6],
                    ],
                    'timeout' => 30,
                    'connect_timeout' => 10
                ]
            );

            $statusCode = $response->getStatusCode();
            error_log('KommoController: IA cURL - Status: ' . $statusCode);

            if ($statusCode !== 200) {
                error_log('KommoController: IA cURL - Status no es 200: ' . $statusCode);
                return ['success' => false, 'error' => 'HTTP ' . $statusCode];
            }

            $datos = json_decode($response->getBody(), true);
            
            // Normalizar respuesta nueva
            if (isset($datos['datosProcessados'])) {
                $datosProcessados = $datos['datosProcessados'];
                $datos = [
                    'success' => true,
                    'valores_texto' => $datosProcessados['valores_texto'] ?? [],
                    'valores_opcion' => $datosProcessados['valores_opcion'] ?? [],
                    'campos_procesados' => $datosProcessados['campos_procesados'] ?? [],
                ];
                error_log('KommoController: IA cURL - Estructura nueva detectada. Campos: ' . count($datosProcessados['campos_procesados'] ?? []));
            }
            
            error_log('KommoController: IA cURL - Respuesta: ' . json_encode([
                'success' => $datos['success'] ?? false,
                'campos' => count($datos['valores_texto'] ?? []) + count($datos['valores_opcion'] ?? [])
            ]));

            return $datos ?? ['success' => false, 'error' => 'Respuesta vacía'];

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            error_log('KommoController: IA cURL - Error de conexión: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Conexión fallida: ' . $e->getMessage()];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            error_log('KommoController: IA cURL - Error de request: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Request fallido: ' . $e->getMessage()];
        } catch (\Exception $e) {
            error_log('KommoController: IA cURL - Error genérico: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
            return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * 🤖 FALLBACK: Extrae datos relevantes del mensaje (si existe)
     * Busca patrones como "salario neto 1600", "nómina 2000", etc.
     * Se usa cuando IA no está disponible o falla
     * 
     * AHORA DEVUELVE ESTRUCTURA UNIFICADA:
     * {
     *   "valores_texto": { "220": "Acme Corp", "225": "2000", ... },
     *   "valores_opcion": { "193": 102, ... },
     *   "success": true
     * }
     */
    private function extraerDatosDelMensaje(array $data): array
    {
        $datosExtraidos = [];
        $valoresTexto = [];
        $valoresOpcion = [];
        $mapeoClavesACampos = $this->obtenerMapeoClavesACampos();
        $mapeoOpciones = $this->obtenerMapeoOpcionesGlobales();

        // Verificar si hay mensajes en el webhook
        if (empty($data['message']['add'])) {
            return ['success' => false];
        }

        foreach ($data['message']['add'] as $mensaje) {
            $texto = $mensaje['text'] ?? '';
            
            if (empty($texto)) {
                continue;
            }

            error_log('KommoController: Parseando mensaje (fallback regex): ' . substr($texto, 0, 100));

            // Patrón: salario, nómina, sueldo (números)
            if (preg_match('/(?:salario neto|salario|nómina|nomina|sueldo neto|sueldo)\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                error_log('KommoController: Nómina extraída: ' . $valor);
            } elseif (preg_match('/\b(?:gano|cobro|percibo)\s*:??\s*(\d{2,6}(?:[.,]\d{2})?)\b\s*(?:€|euros)?/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                error_log('KommoController: Nómina extraída (patrón gano): ' . $valor);
            }

            // Patrón: tipo de contrato (indefinido, temporal, autónomo, por obra)
            if (preg_match('/\b(indefinid[oa]|temporal|fijo|autonomo|autónomo|por obra|obra y servicio|pensionista|emplead[oa])\b/i', $texto, $m)) {
                $datosExtraidos['tipo_contrato'] = strtolower($m[1]);
                error_log('KommoController: Tipo de contrato extraído: ' . $datosExtraidos['tipo_contrato']);
            }

            // Patrón: empresa (texto)
            if (preg_match('/empresa\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                error_log('KommoController: Empresa extraída: ' . $datosExtraidos['empresa']);
            }

            // Patrón: puesto, cargo (texto)
            if (preg_match('/(?:puesto|cargo)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['puesto'] = trim($matches[1]);
                error_log('KommoController: Puesto extraído: ' . $datosExtraidos['puesto']);
            }

            // Patrón: ingresos, ingresos anuales (números)
            if (preg_match('/ingresos\s*(?:anuales|mensuales)?\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ingresos'] = $valor;
                error_log('KommoController: Ingresos extraídos: ' . $valor);
            }

            // Patrón: ciudad, provincia, localidad (texto)
            if (preg_match('/(?:ciudad|provincia|localidad|residencia)\s*:?\s*([A-Za-z0-9\s\&\.\-áéíóúñÁÉÍÓÚÑ]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['provincia'] = trim($matches[1]);
                error_log('KommoController: Provincia extraída: ' . $datosExtraidos['provincia']);
            }

            // Patrón: ahorro (números)
            if (preg_match('/ahorro\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ahorro'] = $valor;
                error_log('KommoController: Ahorro extraído: ' . $valor);
            }

            // Patrón: nacionalidad (texto)
            if (preg_match('/nacionalidad\s*:?\s*([A-Za-záéíóúñÁÉÍÓÚÑ\s\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['nacionalidad'] = trim($matches[1]);
                error_log('KommoController: Nacionalidad extraída: ' . $datosExtraidos['nacionalidad']);
            }

            // Patrón: banco (texto)
            if (preg_match('/(?:banco|trabajo con|entidad|cuenta en)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['banco'] = trim($matches[1]);
                error_log('KommoController: Banco extraído: ' . $datosExtraidos['banco']);
            }
        }

        // 🔄 CONVERTIR DATOS EXTRAÍDOS A ESTRUCTURA UNIFICADA
        foreach ($datosExtraidos as $clave => $valor) {
            if (empty($valor)) {
                continue;
            }

            // Obtener IDs de campos para esta clave
            $camposIds = $mapeoClavesACampos[$clave] ?? [];
            
            if (!$camposIds) {
                error_log('KommoController: No hay mapeo de campos para clave: ' . $clave);
                continue;
            }

            // Algunos campos requieren mapeo de opciones (no valores de texto)
            if ($clave === 'tipo_contrato') {
                // Intentar mapear a opción
                $opcionId = $this->mapearValorAOpcion($clave, $valor, $mapeoOpciones);
                if ($opcionId) {
                    // El campo 193 es select, usar opción
                    $valoresOpcion[193] = $opcionId;
                    error_log('KommoController: Mapeado tipo_contrato a opción ' . $opcionId);
                } else {
                    // Fallback: almacenar como texto en campo 221
                    foreach ($camposIds as $campoId) {
                        if ($campoId !== 221) continue; // 221 es texto
                        $valoresTexto[$campoId] = $valor;
                    }
                }
            } else {
                // Es un valor de texto: mapear a todos los IDs de campos asociados
                foreach ($camposIds as $campoId) {
                    $valoresTexto[$campoId] = $valor;
                    error_log('KommoController: Mapeada clave ' . $clave . ' a campo ' . $campoId . ' => ' . substr((string)$valor, 0, 50));
                }
            }
        }

        error_log('KommoController: Estructura regex convertida a: ' . count($valoresTexto) . ' valores, ' . count($valoresOpcion) . ' opciones');

        return [
            'success' => !empty($valoresTexto) || !empty($valoresOpcion),
            'valores_texto' => $valoresTexto,
            'valores_opcion' => $valoresOpcion,
            'campos_detectados' => count($valoresTexto) + count($valoresOpcion),
            'confianza_promedio' => 0.75, // Confianza más baja para regex que para IA
            'metodo' => 'regex_fallback'
        ];
    }

    /**
     * 🔄 Mapea un valor de texto a un ID de opción según las reglas de mapeo
     */
    private function mapearValorAOpcion(string $clave, string $valor, array $mapeoOpciones): ?int
    {
        if (!isset($mapeoOpciones[$clave])) {
            return null;
        }

        $valorbajominuscula = strtolower(trim($valor));
        $mapeosOpcion = $mapeoOpciones[$clave];

        foreach ($mapeosOpcion as $patron => $opcionId) {
            // Dividir patrón por | y testear cada alternativa
            $alternativas = explode('|', $patron);
            foreach ($alternativas as $alt) {
                if (strpos($valorbajominuscula, trim($alt)) !== false) {
                    error_log('KommoController: Valor "' . $valor . '" mapeado a opción ' . $opcionId . ' (patrón: ' . $alt . ')');
                    return $opcionId;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene datos del contacto desde la API de Kommo
     */
    private function obtenerContactoKommo(ClientInterface $httpClient, int $contactId): array
    {
        $kommoSubdomain = $this->getParameter('kommo_subdomain');
        $kommoApiToken = $this->getParameter('kommo_api_token');
        $url = "https://{$kommoSubdomain}.kommo.com/api/v4/contacts/{$contactId}";

        try {
            $response = $httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $kommoApiToken,
                    'Accept' => 'application/json'
                ],
                'timeout' => 10
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('API Kommo retornó status ' . $response->getStatusCode());
            }

            $data = json_decode($response->getBody(), true);
            
            // La API v4 devuelve el contacto directamente (no en _embedded)
            // Pero mantenemos compatibilidad si viene en _embedded.contacts[0]
            if (isset($data['_embedded']['contacts'][0])) {
                return $data['_embedded']['contacts'][0];
            } elseif (isset($data['id'])) {
                // Estructura estándar de Kommo v4: el contacto es la raíz
                return $data;
            } else {
                throw new \Exception('Estructura inesperada en respuesta de API Kommo: ' . json_encode(array_keys($data)));
            }
        } catch (GuzzleException $e) {
            throw new \Exception('Error conectando a API Kommo: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene detalles adicionales del contacto desde Kommo API con parámetros extendidos
     * Intenta obtener custom_fields_values adicionales que podrían contener teléfono/email
     */
    private function obtenerContactoKommoDetallado(ClientInterface $httpClient, int $contactId): ?array
    {
        $kommoSubdomain = $this->getParameter('kommo_subdomain');
        $kommoApiToken = $this->getParameter('kommo_api_token');
        
        // Llamar con parámetros adicionales para obtener más detalles
        $url = "https://{$kommoSubdomain}.kommo.com/api/v4/contacts/{$contactId}?with=leads,customers";

        try {
            $response = $httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $kommoApiToken,
                    'Accept' => 'application/json'
                ],
                'timeout' => 10
            ]);

            if ($response->getStatusCode() !== 200) {
                error_log('KommoController: API Kommo retornó status ' . $response->getStatusCode() . ' en obtenerContactoKommoDetallado');
                return null;
            }

            $data = json_decode($response->getBody(), true);
            
            // Extraer contacto de la respuesta
            if (isset($data['_embedded']['contacts'][0])) {
                error_log('KommoController: Detalles obtenidos de _embedded.contacts[0]');
                return $data['_embedded']['contacts'][0];
            } elseif (isset($data['id'])) {
                error_log('KommoController: Detalles obtenidos desde raíz de respuesta API');
                return $data;
            }
            
            return null;
        } catch (GuzzleException $e) {
            error_log('KommoController: Error en obtenerContactoKommoDetallado: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca cliente por teléfono o lo crea nuevo
     */
    private function buscarOCrearCliente($em, array $contactoKommo): UsuarioEntidad
    {
        $telefono = $this->extraerTelefono($contactoKommo);
        $email = $this->extraerEmail($contactoKommo);
        $nombre = $contactoKommo['name'] ?? 'Cliente Kommo';

        // Buscar cliente existente por teléfono
        if ($telefono) {
            $clienteExistente = $em->getRepository(UsuarioEntidad::class)->findOneBy(
                ['telefonoMovil' => $telefono]
            );
            if ($clienteExistente) {
                error_log('KommoController: Cliente existente por teléfono: ' . $telefono);
                return $clienteExistente;
            }
        }

        // Buscar por email si teléfono no encontró nada
        if ($email) {
            $clienteExistente = $em->getRepository(UsuarioEntidad::class)->findOneBy(
                ['email' => $email]
            );
            if ($clienteExistente && $clienteExistente->getRole() === 'ROLE_CLIENTE') {
                error_log('KommoController: Cliente existente por email: ' . $email);
                return $clienteExistente;
            }
        }

        // Crear nuevo cliente
        error_log('KommoController: Creando nuevo cliente desde Kommo');
        list($nombreCliente, $apellidosCliente) = $this->separarNombreCompleto($nombre);

        $nuevoCliente = new UsuarioEntidad();
        $nuevoCliente->setUsername($nombreCliente);
        $nuevoCliente->setApellidos($apellidosCliente);
        $nuevoCliente->setEmail($email ?: 'kommo_' . uniqid() . '@example.com');
        $nuevoCliente->setTelefonoMovil($telefono ?: '');
        $nuevoCliente->setRole('ROLE_CLIENTE');
        $nuevoCliente->setEstado(true);
        $nuevoCliente->setPassword('');
        $nuevoCliente->setPlainPassword('');
        $nuevoCliente->setFechaRegistro(new \DateTime());

        $em->persist($nuevoCliente);
        $em->flush();

        error_log('KommoController: Nuevo cliente creado (ID: ' . $nuevoCliente->getIdUsuario() . ')');
        return $nuevoCliente;
    }

    /**
     * Busca expediente existente o crea uno nuevo con estructura completa
     */
    private function buscarOCrearExpediente($em, UsuarioEntidad $cliente): ExpedienteEntidad
    {
        // Buscar expediente existente
        $expedienteExistente = $em->getRepository(ExpedienteEntidad::class)->findOneBy([
            'idCliente' => $cliente,
            'estado' => 1
        ]);

        if ($expedienteExistente) {
            error_log('KommoController: Expediente existente para cliente: ' . $cliente->getIdUsuario());
            return $expedienteExistente;
        }

        // Crear nuevo expediente
        error_log('KommoController: Creando nuevo expediente para cliente: ' . $cliente->getIdUsuario());

        $primeraFase = $em->getRepository(FaseEntidad::class)->findOneBy(['orden' => 1]);
        if (!$primeraFase) {
            throw new \Exception('No hay fases configuradas en el sistema');
        }

        $expediente = new ExpedienteEntidad();
        $expediente->setIdCliente($cliente);
        $expediente->setIdFaseActual($primeraFase);
        $expediente->setEstado(1);
        $expediente->setVivienda('NUEVA VIVIENDA');
        $expediente->setFechaCreacion(new \DateTime());

        $em->persist($expediente);
        $em->flush();

        // Crear estructura completa: fases → hitos → grupos → campos
        $fases = $em->getRepository(FaseEntidad::class)->findBy([], ['orden' => 'ASC']);

        foreach ($fases as $fase) {
            $hitos = $em->getRepository(HitoEntidad::class)->findBy(['idFase' => $fase], ['orden' => 'ASC']);

            foreach ($hitos as $hito) {
                $hitoExpediente = new HitoExpedienteEntidad();
                $hitoExpediente->setIdHito($hito);
                $hitoExpediente->setIdExpediente($expediente);
                $hitoExpediente->setFechaModificacion(new \DateTime());
                $hitoExpediente->setEstado(0);

                $gruposCamposHito = $em->getRepository(GrupoCamposHitoEntidad::class)->findBy(
                    ['idHito' => $hito],
                    ['orden' => 'ASC']
                );

                foreach ($gruposCamposHito as $grupoCamposHito) {
                    $grupoHitoExpediente = new GrupoHitoExpedienteEntidad();
                    $grupoHitoExpediente->setIdHitoExpediente($hitoExpediente);
                    $grupoHitoExpediente->setIdGrupoCamposHito($grupoCamposHito);

                    $camposHito = $em->getRepository(CampoHitoEntidad::class)->findBy(
                        ['idGrupoCamposHito' => $grupoCamposHito],
                        ['orden' => 'ASC']
                    );

                    foreach ($camposHito as $campoHito) {
                        $campoHitoExpediente = new CampoHitoExpedienteEntidad();
                        $campoHitoExpediente->setIdCampoHito($campoHito);
                        $campoHitoExpediente->setIdHitoExpediente($hitoExpediente);
                        $campoHitoExpediente->setIdGrupoHitoExpediente($grupoHitoExpediente);
                        $campoHitoExpediente->setIdExpediente($expediente);
                        $campoHitoExpediente->setFechaModificacion(new \DateTime());

                        if ($campoHito->getTipo() == 4) {
                            $campoHitoExpediente->setObligatorio(1)->setSolicitarAlColaborador(1);
                        }

                        $em->persist($campoHitoExpediente);
                    }

                    $em->persist($grupoHitoExpediente);
                }

                $em->persist($hitoExpediente);
            }
        }

        $em->flush();
        error_log('KommoController: Estructura de expediente creada (ID: ' . $expediente->getIdExpediente() . ')');

        return $expediente;
    }

    /**
     * Actualiza hitos del expediente con datos de Kommo + datos del mensaje
     * Retorna un desglose de hitos y campos actualizados
     */
    private function actualizarHitosKommo($em, ExpedienteEntidad $expediente, array $contactoKommo, array $datosMensaje = []): array
    {
        $camposAActualizar = $this->construirAutorrellenoHitosKommo($contactoKommo, $datosMensaje);
        error_log('KommoController: Actualizando ' . count($camposAActualizar) . ' campos');

        $desglose = [];
        $hitosActualizados = [];

        foreach ($camposAActualizar as $idCampoHito => $configuracion) {
            $campoHitoExpediente = $em->getRepository(CampoHitoExpedienteEntidad::class)->findOneBy([
                'idExpediente' => $expediente,
                'idCampoHito' => $idCampoHito
            ]);

            if (!$campoHitoExpediente) {
                error_log('KommoController: No se encontró CampoHitoExpediente para idCampo ' . $idCampoHito . ' en expediente ' . $expediente->getIdExpediente());
                continue;
            }

            // Obtener información del hito y campo
            $hitoExpediente = $campoHitoExpediente->getIdHitoExpediente();
            $hito = $hitoExpediente->getIdHito();
            $campoHito = $campoHitoExpediente->getIdCampoHito();
            
            $idHito = $hito->getIdHito();
            $nombreHito = $hito->getNombre() ?? 'Hito #' . $idHito;
            $nombreCampo = $campoHito->getNombre() ?? 'Campo #' . $idCampoHito;

            // Registrar actualización
            if (!isset($hitosActualizados[$idHito])) {
                $hitosActualizados[$idHito] = [
                    'idHito' => $idHito,
                    'nombreHito' => $nombreHito,
                    'campos' => []
                ];
            }

            // Actualizar el campo
            if (isset($configuracion['opcion_id'])) {
                $opcionId = $configuracion['opcion_id'];
                $setterUsed = null;

                // Intentar resolver la entidad OpcionesCampo si existe el EntityManager
                $opcionesEntity = null;
                try {
                    $opcionesEntity = $em->getRepository('AppBundle:OpcionesCampo')->find($opcionId);
                } catch (\Exception $e) {
                    // No bloquear, seguiremos con fallback
                    error_log('KommoController: Error al buscar OpcionesCampo id ' . $opcionId . ' - ' . $e->getMessage());
                }

                if ($opcionesEntity && method_exists($campoHitoExpediente, 'setIdOpcionesCampo')) {
                    $campoHitoExpediente->setIdOpcionesCampo($opcionesEntity);
                    $setterUsed = 'setIdOpcionesCampo(entity)';
                } elseif (method_exists($campoHitoExpediente, 'setOpcion')) {
                    // métodos menos comunes: intentar pasar la entidad si acepta objeto
                    if ($opcionesEntity) {
                        $campoHitoExpediente->setOpcion($opcionesEntity);
                        $setterUsed = 'setOpcion(entity)';
                    } else {
                        $campoHitoExpediente->setValor((string)$opcionId);
                        $setterUsed = 'setValor(fallback)';
                    }
                } elseif (method_exists($campoHitoExpediente, 'setIdOpcion')) {
                    // intentar pasar entidad o id según firma
                    try {
                        $campoHitoExpediente->setIdOpcion($opcionesEntity ?: $opcionId);
                        $setterUsed = 'setIdOpcion';
                    } catch (\TypeError $te) {
                        $campoHitoExpediente->setValor((string)$opcionId);
                        $setterUsed = 'setValor(fallback)';
                    }
                } else {
                    // Último recurso: almacenar el id de opción en el campo de texto `valor`
                    $campoHitoExpediente->setValor((string)$opcionId);
                    $setterUsed = 'setValor(fallback)';
                }

                error_log('KommoController: Actualizando campo opcion ' . $idCampoHito . ' usando ' . $setterUsed . ' => ' . $opcionId);

                $hitosActualizados[$idHito]['campos'][] = [
                    'idCampo' => $idCampoHito,
                    'nombreCampo' => $nombreCampo,
                    'tipo' => 'opcion',
                    'valor' => $opcionId
                ];
            } elseif (isset($configuracion['valor'])) {
                $campoHitoExpediente->setValor($configuracion['valor']);
                $hitosActualizados[$idHito]['campos'][] = [
                    'idCampo' => $idCampoHito,
                    'nombreCampo' => $nombreCampo,
                    'tipo' => 'texto',
                    'valor' => $configuracion['valor']
                ];
            }

            $campoHitoExpediente->setFechaModificacion(new \DateTime());
            $em->persist($campoHitoExpediente);
        }

        // Actualizar fecha de modificación del expediente
        $expediente->setFechaModificacion(new \DateTime());
        $em->persist($expediente);

        $em->flush();

        // Convertir a array indexado
        $desglose = array_values($hitosActualizados);
        
        error_log('KommoController: Desglose de actualización: ' . json_encode($desglose));

        return $desglose;
    }

    /**
     * 🤖 Construye array de campos a rellenar desde datos de Kommo + datos extraídos (IA o regex)
     * AHORA COMPLETAMENTE DINÁMICO: Acepta cualquier estructura de valores_texto/valores_opcion
     * y los mapea directamente a los campos de expediente
     */
    private function construirAutorrellenoHitosKommo(array $lead, array $datosMensaje = []): array
    {
        // AMBAS fuentes (IA y regex) ahora devuelven la misma estructura:
        // { valores_texto: {campo_id: valor}, valores_opcion: {campo_id: opcion_id} }
        
        $valoresTexto = $datosMensaje['valores_texto'] ?? [];
        $valoresOpcion = $datosMensaje['valores_opcion'] ?? [];
        
        error_log('KommoController: Construyendo autorrelleno dinámico: ' . count($valoresTexto) . ' valores de texto, ' . count($valoresOpcion) . ' opciones');

        // Construir array de campos compatible con actualizarHitosExpediente()
        $campos = [];

        // Mapear valores de texto (campo_id => valor)
        foreach ($valoresTexto as $idCampo => $valor) {
            if (!empty($valor) && trim((string)$valor) !== '') {
                $campos[$idCampo] = ['valor' => (string)$valor];
            }
        }

        // Mapear opciones (campo_id => opcion_id)
        foreach ($valoresOpcion as $idCampo => $opcionId) {
            if (!empty($opcionId)) {
                $campos[$idCampo] = ['opcion_id' => (int)$opcionId];
            }
        }

        // Registrar resumen de campos que se van a actualizar
        error_log('KommoController: Total de campos a actualizar: ' . count($campos) . '. Desglose: ' . json_encode(array_keys($campos)));

        // Filtrar campos vacíos
        return array_filter($campos, function ($configuracion) {
            return (isset($configuracion['opcion_id']) && !empty($configuracion['opcion_id']))
                || (isset($configuracion['valor']) && trim((string)$configuracion['valor']) !== '');
        });
    }

    /**
     * Métodos auxiliares
     */
    private function separarNombreCompleto(string $nombreCompleto): array
    {
        $nombreCompleto = trim($nombreCompleto);
        if (empty($nombreCompleto)) {
            return ['', ''];
        }
        $partes = explode(' ', $nombreCompleto, 2);
        return [$partes[0] ?? '', $partes[1] ?? ''];
    }

    private function extraerTelefono(array $contacto): string
    {
        // Kommo API v4: buscar en custom_fields_values por field_code = 'PHONE'
        if (!empty($contacto['custom_fields_values']) && is_array($contacto['custom_fields_values'])) {
            foreach ($contacto['custom_fields_values'] as $campo) {
                if (!empty($campo['field_code']) && $campo['field_code'] === 'PHONE') {
                    if (!empty($campo['values'][0]['value'])) {
                        $telefono = (string)$campo['values'][0]['value'];
                        error_log('KommoController: Teléfono extraído de custom_fields_values: ' . $telefono);
                        return $telefono;
                    }
                }
            }
        }
        
        // Fallback: campos antiguos
        if (!empty($contacto['phone'])) {
            return (string)$contacto['phone'];
        }
        if (!empty($contacto['custom_fields']['telefono'])) {
            return (string)$contacto['custom_fields']['telefono'];
        }
        if (isset($contacto['_embedded']['phones'][0]['value'])) {
            return (string)$contacto['_embedded']['phones'][0]['value'];
        }
        
        error_log('KommoController: No se encontró teléfono en el contacto');
        return '';
    }

    private function extraerEmail(array $contacto): string
    {
        // Kommo API v4: buscar en custom_fields_values por field_code = 'EMAIL'
        if (!empty($contacto['custom_fields_values']) && is_array($contacto['custom_fields_values'])) {
            foreach ($contacto['custom_fields_values'] as $campo) {
                if (!empty($campo['field_code']) && $campo['field_code'] === 'EMAIL') {
                    if (!empty($campo['values'][0]['value'])) {
                        $email = (string)$campo['values'][0]['value'];
                        error_log('KommoController: Email extraído de custom_fields_values: ' . $email);
                        return $email;
                    }
                }
            }
        }
        
        // Fallback: campos antiguos
        if (!empty($contacto['email'])) {
            return (string)$contacto['email'];
        }
        if (!empty($contacto['custom_fields']['email'])) {
            return (string)$contacto['custom_fields']['email'];
        }
        if (isset($contacto['_embedded']['emails'][0]['value'])) {
            return (string)$contacto['_embedded']['emails'][0]['value'];
        }
        
        error_log('KommoController: No se encontró email en el contacto');
        return '';
    }

    /**
     * Extrae un campo customizado de Kommo buscando por múltiples alias
     * Ejemplo: extraerCampoCustom($lead, ['nómina', 'salario neto', 'nomina mensual'])

    /**
     * Dashboard admin para ver webhooks
     * Ruta: /Admin/Kommo/Webhooks
     * Método: GET
     */
    public function adminWebhooksAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $kommoRepo = $em->getRepository('AppBundle:KommoWebhook');

        // Obtener filtros del request
        $tipo = $request->get('tipo', '');
        $estado = $request->get('estado', '');
        $fechaInicioStr = $request->get('fechaInicio', '');
        $fechaFinStr = $request->get('fechaFin', '');
        $busqueda = $request->get('busqueda', '');
        $page = $request->get('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Construir query con filtros
        $qb = $em->createQueryBuilder()
            ->select('w')
            ->from('AppBundle:KommoWebhook', 'w')
            ->orderBy('w.fecha', 'DESC');

        if ($tipo) {
            $qb->andWhere('w.webhookType = :tipo')->setParameter('tipo', $tipo);
        }
        if ($estado) {
            $qb->andWhere('w.estado = :estado')->setParameter('estado', $estado);
        }
        if ($fechaInicioStr) {
            $fechaInicio = new \DateTime($fechaInicioStr . ' 00:00:00');
            $qb->andWhere('w.fecha >= :fechaInicio')->setParameter('fechaInicio', $fechaInicio);
        }
        if ($fechaFinStr) {
            $fechaFin = new \DateTime($fechaFinStr . ' 23:59:59');
            $qb->andWhere('w.fecha <= :fechaFin')->setParameter('fechaFin', $fechaFin);
        }
        if ($busqueda) {
            $qb->andWhere('w.kommoId LIKE :busqueda')->setParameter('busqueda', '%' . $busqueda . '%');
        }

        // Contar total
        $totalQuery = clone $qb;
        $total = count($totalQuery->getQuery()->getResult());
        $totalPages = ceil($total / $limit);

        // Obtener registros
        $webhooks = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Obtener estadísticas
        $estadisticas = $kommoRepo->obtenerEstadisticas();
        $tiposUnicos = $kommoRepo->obtenerTiposUnicos();

        // Preparar datos de paginación
        $pagination = [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'previousUrl' => $page > 1 ? $this->generateUrl('admin_kommo_webhooks', array_merge(
                $request->query->all(),
                ['page' => $page - 1]
            )) : null,
            'nextUrl' => $page < $totalPages ? $this->generateUrl('admin_kommo_webhooks', array_merge(
                $request->query->all(),
                ['page' => $page + 1]
            )) : null
        ];

        // Preparar datos de filtros (usar strings originales para el template)
        $filtro = [
            'tipo' => $tipo,
            'estado' => $estado,
            'fechaInicio' => $fechaInicioStr,
            'fechaFin' => $fechaFinStr,
            'busqueda' => $busqueda
        ];

        return $this->render('@App/Backoffice/Lista/Kommo.html.twig', [
            'webhooks' => $webhooks,
            'estadisticas' => $estadisticas,
            'tiposUnicos' => $tiposUnicos,
            'pagination' => $pagination,
            'filtro' => $filtro
        ]);
    }

    /**
     * Eliminar webhooks antiguos
     * Ruta: /Admin/Kommo/Limpiar
     * Método: POST
     */
    public function limpiarWebhooksAction(Request $request)
    {
        $dias = $request->get('dias', 90);
        $em = $this->getDoctrine()->getManager();
        $kommoRepo = $em->getRepository('AppBundle:KommoWebhook');

        try {
            $eliminados = $kommoRepo->eliminarPorAntigüedad($dias);

            return new JsonResponse([
                'ok' => true,
                'mensaje' => 'Limpieza completada',
                'eliminados' => $eliminados
            ]);
        } catch (\Exception $e) {
            error_log('Error al limpiar webhooks: ' . $e->getMessage());

            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'Error al eliminar registros: ' . $e->getMessage()
            ], 400);
        }
    }
}
