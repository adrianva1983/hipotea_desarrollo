<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
use AppBundle\Entity\VistaRotacionComerciales;

class KommoController extends Controller
{
    /**
     * Log que funciona tanto antes como DESPUÉS de fastcgi_finish_request().
     * error_log() estándar tras fastcgi_finish_request() va al log de PHP-FPM,
     * no al de Apache. Este método escribe directo al archivo de logs de Symfony.
     */
    private function bgLog(string $msg): void
    {
        $logFile = $this->get('kernel')->getLogDir() . '/kommo_bg.log';
        $linea = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
        // También a error_log para antes del fastcgi_finish_request
        error_log($msg);
    }

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
        error_log('KOMMO: Webhook recibido en /API/kommo - Iniciando procesamiento...');
        // ── PASO 1: Leer contenido ANTES de cualquier output ──
        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            $rawContent = (string) @file_get_contents('php://input');
        }
        error_log('KOMMO: Webhook recibido en /API/kommo - Paso 1');
        // ── PASO 2: Parsear datos ──
        $data = [];
        if (!empty($rawContent)) {
            $ct = $request->getContentType() ?? '';
            if (strpos($rawContent, '{') === 0 || strpos($ct, 'json') !== false) {
                $data = json_decode($rawContent, true) ?: [];
            } else {
                parse_str($rawContent, $data);
            }
        }
        error_log('KOMMO: Webhook recibido en /API/kommo - Paso 2');

        // ── PASO 3: ENVIAR 200 OK Y CERRAR CONEXIÓN ANTES DEL PROCESAMIENTO ──
        ignore_user_abort(true);
        while (@ob_get_level() > 0) {
            @ob_end_clean();
        }
        $ackBody = '{"ok":true}';
        @header('Content-Type: application/json; charset=UTF-8');
        @header('Content-Length: ' . strlen($ackBody));
        @header('Connection: close');
        echo $ackBody;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        error_log('KOMMO: Webhook recibido en /API/kommo - Paso 3');

        // ── PASO 4: Procesar en background (Kommo ya tiene el 200) ──
        if (empty($data)) {
            return new Response('', 200);
        }

        try {
            $httpClient = new GuzzleClient();

            $tipoWebhook = $this->detectarTipoWebhook($data);

            $contactId = null;
            $datosMensaje = [];

            switch ($tipoWebhook) {
                case 'message':
                    $contactId = $this->extraerContactoIdDelMensaje($data);
                    $textoMensaje = $this->extraerTextoDelMensaje($data);

                    if (!empty($textoMensaje)) {
                        $this->bgLog('🔵 KOMMO: Intentando extraer datos de IA del mensaje: ' . substr($textoMensaje, 0, 100) . '...');
                        $datosIA = $this->extraerDatosConIA($textoMensaje);
                        $this->bgLog('🟠 KOMMO: Resultado IA: ' . json_encode($datosIA));
                        if ($datosIA['success'] ?? false) {
                            $this->bgLog('✅ KOMMO: IA exitosa - ' . $datosIA['campos_detectados'] . ' campos detectados');
                            $datosMensaje = $datosIA;
                        } else {
                            $this->bgLog('⚠️ KOMMO: IA falló. Usando fallback regex...');
                            $datosMensaje = $this->extraerDatosDelMensaje($data);
                            $this->bgLog('🟠 KOMMO: Resultado Regex: ' . json_encode($datosMensaje));
                        }
                    } else {
                        $datosMensaje = $this->extraerDatosDelMensaje($data);
                    }
                    break;

                case 'contact':
                    $contactId = $this->extraerContactoIdDelContact($data);
                    break;

                case 'contact_update':
                case 'talk_ignored':
                    // $this->kommoLog('KommoController: Tipo ignorado: ' . $tipoWebhook);
                    return new Response('', 200);

                case 'lead':
                    $leadData = $data['leads']['add'][0] ?? null;
                    if ($leadData && !empty($leadData['linked_leads_id'])) {
                        $contactId = (int) array_key_first($leadData['linked_leads_id']);
                    }
                    break;

                default:
                    // $this->kommoLog('KommoController: Tipo no soportado: ' . $tipoWebhook);
                    return new Response('', 200);
            }

            if (!$contactId) {
                throw new \Exception('No contactId found');
            }

            // $this->kommoLog('KommoController: ContactID: ' . $contactId);

            // Obtener datos del contacto desde Kommo API
            $contactoKommo = $this->obtenerContactoKommo($httpClient, $contactId);
            // $this->kommoLog('KommoController: Contacto: ' . ($contactoKommo['name'] ?? 'sin nombre'));

            $em = $this->getDoctrine()->getManager();

            $telefono = $this->extraerTelefono($contactoKommo);
            $email = $this->extraerEmail($contactoKommo);

            // Si no hay teléfono ni email, intentar detalles adicionales
            if (empty($telefono) && empty($email)) {
                try {
                    $contactoDetallado = $this->obtenerContactoKommoDetallado($httpClient, $contactId);
                    if ($contactoDetallado) {
                        $telefonoDetallado = $this->extraerTelefono($contactoDetallado);
                        $emailDetallado = $this->extraerEmail($contactoDetallado);
                        if (!empty($telefonoDetallado) || !empty($emailDetallado)) {
                            $contactoKommo = array_merge($contactoKommo, $contactoDetallado);
                            $telefono = $telefonoDetallado ?: $telefono;
                            $email = $emailDetallado ?: $email;
                        }
                    }
                } catch (\Exception $e) {
                    // $this->kommoLog('KommoController: Error detalles adicionales: ' . $e->getMessage(), true);
                }
            }

            // Si sigue sin contacto, registrar incompleto
            if (empty($telefono) && empty($email)) {
                // $this->kommoLog('KommoController: Sin teléfono ni email. Registrando incompleto.');
                $kommoWebhook = new KommoWebhook();
                $kommoWebhook->setWebhookType($tipoWebhook);
                $kommoWebhook->setKommoId((string) $contactId);
                $kommoWebhook->setJsonRecibido($data);
                $kommoWebhook->setEstado('incompleto_sin_contacto');
                $kommoWebhook->setErrorMensaje('Sin teléfono ni email');
                $kommoWebhook->setFecha(new \DateTime());
                $em->persist($kommoWebhook);
                $em->flush();
                return new Response('', 200);
            }

            $cliente = $this->buscarOCrearCliente($em, $contactoKommo);
            // $this->kommoLog('KommoController: Cliente ID: ' . $cliente->getIdUsuario());

            $expediente = $this->buscarOCrearExpediente($em, $cliente);
            // $this->kommoLog('KommoController: Expediente ID: ' . $expediente->getIdExpediente());

            // Esta es la versión rotativa de uno en uno
            $repoRotacion = $this->getDoctrine()->getRepository(VistaRotacionComerciales::class);
            $comercialRotativo = $repoRotacion->createQueryBuilder('v')
                ->orderBy('v.ultimaAsignacion', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if ($comercialRotativo != null) {
                $comercial = $this->getDoctrine()->getRepository(UsuarioEntidad::class)->findOneBy([
                    'idUsuario' => $comercialRotativo->getIdUsuario()
                ]);
                if ($comercial) {
                    $expediente->setIdComercial($comercial);
                }
            }

            $em->persist($expediente);
            $em->flush();

            $this->bgLog('DEBUG KOMMO: Llamando a actualizarHitosKommo()...');
            $this->actualizarHitosKommo($em, $expediente, $contactoKommo, $datosMensaje);
            $this->bgLog('DEBUG KOMMO: ✅ actualizarHitosKommo() retornó exitosamente');

            $kommoWebhook = new KommoWebhook();
            $kommoWebhook->setWebhookType($tipoWebhook);
            $kommoWebhook->setKommoId((string) $contactId);
            $kommoWebhook->setJsonRecibido($data);
            $kommoWebhook->setEstado('procesado');
            $kommoWebhook->setFecha(new \DateTime());
            $em->persist($kommoWebhook);
            $this->bgLog('DEBUG KOMMO: Guardando KommoWebhook con estado procesado...');
            $em->flush();
            $this->bgLog('DEBUG KOMMO: ✅ KommoWebhook guardado exitosamente');
            $this->bgLog('DEBUG KOMMO: ✅ Procesamiento completado');

        } catch (\Throwable $e) {
            $this->bgLog('❌ KOMMO ERROR CAPTURADO: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            $this->bgLog('❌ KOMMO TRACE: ' . $e->getTraceAsString());

            try {
                $em = $this->getDoctrine()->getManager();
                $kommoWebhook = new KommoWebhook();
                $kommoWebhook->setWebhookType('error');
                $kommoWebhook->setKommoId('error-' . uniqid());
                $kommoWebhook->setJsonRecibido(array_merge(['_error' => $e->getMessage()], $data ?: []));
                $kommoWebhook->setEstado('error');
                $kommoWebhook->setErrorMensaje(substr($e->getMessage(), 0, 1000));
                $kommoWebhook->setFecha(new \DateTime());
                $em->persist($kommoWebhook);
                $em->flush();
            } catch (\Throwable $dbError) {
                $this->bgLog('❌ KOMMO No se pudo guardar error en BD: ' . $dbError->getMessage());
            }
        }

        return new Response('', 200);
    }

    /**
     * Detecta el tipo de webhook basándose en la estructura del JSON
     */
    private function detectarTipoWebhook(array $data): string
    {
        // Mensaje (nuevo mensaje en chat)
        if (!empty($data['message']['add'])) {
            // Detectado webhook MESSAGE.ADD (silenciado)
            // $this->kommoLog('KommoController: Detectado webhook MESSAGE.ADD (Kommo chat)');
            return 'message';
        }

        // Contacto (nuevo contacto)
        if (!empty($data['contacts']['add'])) {
            // Detectado webhook CONTACTS.ADD (silenciado)
            // $this->kommoLog('KommoController: Detectado webhook CONTACTS.ADD (Nuevo contacto)');
            return 'contact';
        }

        // Actualización de contacto
        if (!empty($data['contacts']['update'])) {
            // Detectado webhook CONTACTS.UPDATE (silenciado)
            // $this->kommoLog('KommoController: Detectado webhook CONTACTS.UPDATE (Contacto actualizado)');
            return 'contact_update';
        }

        // Lead (nueva oportunidad/lead)
        if (!empty($data['leads']['add'])) {
            // Detectado webhook LEADS.ADD (silenciado)
            // $this->kommoLog('KommoController: Detectado webhook LEADS.ADD (Nuevo lead)');
            return 'lead';
        }

        // Talk (conversación - IGNORAR por ahora)
        if (!empty($data['talk']['add']) || !empty($data['talk']['update'])) {
            // Detectado webhook TALK (silenciado)
            // $this->kommoLog('KommoController: Detectado webhook TALK (conversación) - Ignorando');
            return 'talk_ignored';
        }

        // Formatos alternativos o heredados
        if (!empty($data['message']) && is_array($data['message'])) {
            // Detectado webhook MESSAGE (formato alternativo) - silenciado
            // $this->kommoLog('KommoController: Detectado webhook MESSAGE (formato alternativo)');
            return 'message';
        }
        if (!empty($data['contact']) && is_array($data['contact'])) {
            // Detectado webhook CONTACT (formato alternativo) - silenciado
            // $this->kommoLog('KommoController: Detectado webhook CONTACT (formato alternativo)');
            return 'contact';
        }

        // Estructura desconocida
        $keys = array_keys($data);
        // $this->kommoLog('KommoController: Estructura webhook no reconocida. Keys: ' . json_encode($keys) . '. Esperados: message, contacts, leads, talk');

        return 'unknown';
    }

    /**
     * Extrae contactId del webhook de mensaje
     */
    private function extraerContactoIdDelMensaje(array $data): ?int
    {
        if (!empty($data['message']['add'][0]['contact_id'])) {
            return (int) $data['message']['add'][0]['contact_id'];
        }
        return null;
    }

    /**
     * Extrae contactId del webhook de contact
     */
    private function extraerContactoIdDelContact(array $data): ?int
    {
        if (!empty($data['contacts']['add'][0]['id'])) {
            return (int) $data['contacts']['add'][0]['id'];
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
        return trim((string) $data['message']['add'][0]['text']);
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
            // Texto vacío para IA (silenciado)
            return ['success' => false];
        }

        // IA - texto a procesar (silenciado)

        // INTENTO 1: Llamada al nuevo método procesarTextoExpedienteAction (con fuzzy matching Levenshtein)
        // $this->kommoLog('KommoController: Intentando forward() a procesarTextoExpedienteAction (nuevo)...');
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

            // Forward request method (silenciado)
            // $this->kommoLog('KommoController: Forward request method: ' . $request->getMethod());

            $response = $this->forward('AppBundle:InteligenciaArtificial:procesarTextoExpediente', [
                'request' => $request
            ]);

            $responseContent = $response->getContent();
            // Forward - Respuesta cruda (silenciada)
            // $this->kommoLog('KommoController: Forward - Respuesta cruda (primeros 300 chars): ' . substr($responseContent, 0, 300));

            $datosExtraidos = json_decode($responseContent, true);

            // Validar que sea JSON válido
            if ($datosExtraidos === null) {
                // JSON inválido en respuesta forward (silenciado)
                // $this->kommoLog('KommoController: JSON inválido en respuesta forward: ' . $responseContent, true);
                throw new \Exception('Respuesta forward no es JSON válido');
            }

            // Validar estructura
            $success = $datosExtraidos['success'] ?? false;
            // Forward - Parseado (silenciado)
            // $this->kommoLog('KommoController: Forward - Parseado: success=' . ($success ? 'true' : 'false'));

            if (!$success) {
                // Forward retornó success=false, pasando a cURL (silenciado)
                // $this->kommoLog('KommoController: Forward retornó success=false, pasando a cURL');
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
                // Estructura nueva detectada (silenciada)
                // $this->kommoLog('KommoController: Estructura nueva detectada (procesarTextoExpediente). Campos: ' . count($datosProcessados['campos_procesados'] ?? []));
            } elseif (!isset($datosExtraidos['valores_texto']) || !isset($datosExtraidos['valores_opcion'])) {
                // Estructura incompleta en forward, normalizando... (silenciado)
                // $this->kommoLog('KommoController: Estructura incompleta en forward, normalizando...');
                $datosExtraidos = $this->normalizarRespuestaIA($datosExtraidos);
            }

            // IA via forward() exitosa (silenciado)
            // $this->kommoLog('KommoController: IA via forward() EXITOSA');
            return $datosExtraidos;

        } catch (\Exception $forwardError) {
            // $this->kommoLog('KommoController: Forward falló: ' . $forwardError->getMessage() . ' (Line: ' . $forwardError->getLine() . ')', true);
        }

        // INTENTO 2: Via cURL (fallback)
        // Intentando fallback cURL (silenciado)
        // $this->kommoLog('KommoController: Intentando fallback cURL...');
        $curlData = $this->extraerDatosConIAviaCurl($texto);

        if (isset($curlData['success']) && $curlData['success']) {
            // IA via cURL exitosa (silenciado)
            return $curlData;
        }

        // cURL también falló (silenciado)

        // INTENTO 3: Fallback regex
        // Usando fallback regex como último recurso (silenciado)
        $regexData = $this->extraerDatosConRegex($texto);
        // $this->kommoLog('KommoController: Regex extrajo ' . count($regexData['valores_texto'] ?? []) . ' valores de texto');

        return $regexData;
    }

    /**
     * 📝 Fallback final: Extrae datos usando regex directamente del texto
     * Retorna estructura unificada compatible con IA
     */
    private function extraerDatosConRegex(string $texto): array
    {
        try {
            // Iniciando extracción regex del mensaje (silenciado)

            $datosExtraidos = [];
            $mapeoClavesACampos = $this->obtenerMapeoClavesACampos();
            $mapeoOpciones = $this->obtenerMapeoOpcionesGlobales();

            // Patrón: DNI español (números + letra o números con letras)
            if (preg_match('/(?:DNI|D\.N\.I|documento)\s*:?\s*([0-9]{8}[A-Za-z]|[0-9]{8,9})/i', $texto, $matches)) {
                $datosExtraidos['dni'] = strtoupper(trim($matches[1]));
                // Regex - DNI extraído: $datosExtraidos['dni'] (silenciado)
            }

            // Patrón: salario, nómina, sueldo (números)
            if (preg_match('/(?:salario neto|salario|nómina|nomina|sueldo neto|sueldo)\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                // Regex - Nómina extraída: $valor (silenciado)
            } elseif (preg_match('/\b(?:gano|cobro|percibo)\s*:??\s*(\d{2,6}(?:[.,]\d{2})?)\b\s*(?:€|euros)?/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                // Regex - Nómina extraída (patrón gano): $valor (silenciado)
            }

            // Patrón: tipo de contrato (indefinido, temporal, autónomo, por obra)
            if (preg_match('/\b(indefinid[oa]|temporal|fijo|autonomo|autónomo|por obra|obra y servicio|pensionista|emplead[oa])\b/i', $texto, $m)) {
                $datosExtraidos['tipo_contrato'] = strtolower($m[1]);
                // Regex - Tipo de contrato extraído: $datosExtraidos['tipo_contrato'] (silenciado)
            }

            // Patrón: empresa (texto)
            if (preg_match('/empresa\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                // Regex - Empresa extraída: $datosExtraidos['empresa'] (silenciado)
            }

            // Patrón: puesto, cargo (texto)
            if (preg_match('/(?:puesto|cargo)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['puesto'] = trim($matches[1]);
                // Regex - Puesto extraído: $datosExtraidos['puesto'] (silenciado)
            }

            // Patrón: ingresos, ingresos anuales (números)
            if (preg_match('/ingresos\s*(?:anuales|mensuales)?\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ingresos'] = $valor;
                // Regex - Ingresos extraídos: $valor (silenciado)
            }

            // Patrón: ciudad, provincia, localidad (texto)
            if (preg_match('/(?:ciudad|provincia|localidad|residencia)\s*:?\s*([A-Za-z0-9\s\&\.\-áéíóúñÁÉÍÓÚÑ]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['provincia'] = trim($matches[1]);
                // Regex - Provincia extraída: $datosExtraidos['provincia'] (silenciado)
            }

            // Patrón: ahorro (números)
            if (preg_match('/ahorro\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ahorro'] = $valor;
                // Regex - Ahorro extraído: $valor (silenciado)
            }

            // Patrón: nacionalidad (texto)
            if (preg_match('/nacionalidad\s*:?\s*([A-Za-záéíóúñÁÉÍÓÚÑ\s\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['nacionalidad'] = trim($matches[1]);
                // Regex - Nacionalidad extraída: $datosExtraidos['nacionalidad'] (silenciado)
            }
            // Patrón: estado civil (soltero/a, casado/a, divorciado/a, separado/a, viudo/a, pareja de hecho)
            // Captura también régimen matrimonial si se especifica (gananciales, separación de bienes)
            if (preg_match('/\b(?:soy\s+)?(?:estado\s*civil\s*[:\s])?\s*(solter[oa]|casad[oa](?:\s+en\s+(?:gananciales|separaci[oó]n\s+de\s+bienes))?|divorciad[oa]?|separad[oa]|viud[oa]|pareja\s+de\s+hecho|uni[oó]n\s+de\s+hecho)\b/i', $texto, $matches)) {
                $valor = trim($matches[1]);
                if (!empty($valor)) {
                    $datosExtraidos['estado_civil'] = $valor;
                    // Regex - Estado civil extraído: $datosExtraidos['estado_civil'] (silenciado)
                }
            }

            // Patrón: "Trabajo en X" / "Trabajo como X" / "Trabajo para X" — capturar nombre de empresa
            if (preg_match('/\b(?:trabajo en|trabajo como|trabaja en|trabajo para|empleado en|trabajo:|trabajo\s-\s)\s*:?\s*([A-Za-z0-9\s\&\.\-\,\(\)]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                // Regex - Empresa extraída (trabajo en): $datosExtraidos['empresa'] (silenciado)
            }

            // Patrón: banco (texto)
            if (preg_match('/(?:banco|trabajo con|entidad|cuenta en)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['banco'] = trim($matches[1]);
                // Regex - Banco extraído: $datosExtraidos['banco'] (silenciado)
            }

            // Patrón: número de hijos (captura "2 hijos", "2 hijos a mi cargo", "tengo 2 hijos", etc.)
            if (preg_match('/(\d{1,2})\s+hij(?:o|os|a|as)(?:\s+a\s+mi\s+cargo)?\b/i', $texto, $matches)) {
                $datosExtraidos['hijos'] = (int) $matches[1];
                // Regex - Número de hijos extraído: $datosExtraidos['hijos'] (silenciado)
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

            // Estructura regex convertida (silenciado)

            return [
                'success' => false, // Regex es fallback, no es 100% confiable
                'valores_texto' => $valoresTexto,
                'valores_opcion' => $valoresOpcion,
                'campos_detectados' => count($valoresTexto) + count($valoresOpcion),
                'confianza_promedio' => 0.50, // Menor confianza que IA
                'metodo' => 'regex_fallback'
            ];

        } catch (\Exception $e) {
            // $this->kommoLog('KommoController: Error en extraerDatosConRegex: ' . $e->getMessage(), true);
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
        // IA estructura no estándar (silenciado)

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
     * Registro centralizado y filtrado para KommoController.
     * Solo escribe en el error_log las entradas marcadas como importantes,
     * o cuando se solicita forzar el log (parámetro $force).
     */
    private function kommoLog(string $msg, bool $force = false): void
    {
        // Palabras clave que consideramos significativas
        $keywords = ['WEBHOOK recibido', '✅ Procesado', 'ERROR:', 'Cliente ID:', 'Expediente ID:'];
        foreach ($keywords as $k) {
            if (strpos($msg, $k) !== false) {
                error_log($msg);
                return;
            }
        }

        if ($force) {
            error_log($msg);
        }
    }

    /**
     * 🤖 Fallback: Llamar IA vía cURL si forward falla
     * Usa el nuevo endpoint procesarTextoExpediente (con fuzzy matching)
     */
    private function extraerDatosConIAviaCurl(string $texto): array
    {
        try {
            // IA cURL - iniciando llamada a API externa (silenciado)

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
            // IA cURL - Status: ' . $statusCode (silenciado)

            if ($statusCode !== 200) {
                // IA cURL - Status no es 200: ' . $statusCode (silenciado)
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
                // IA cURL - Estructura nueva detectada (silenciado)
            }

            // IA cURL - Respuesta (silenciado)

            return $datos ?? ['success' => false, 'error' => 'Respuesta vacía'];

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // $this->kommoLog('KommoController: IA cURL - Error de conexión: ' . $e->getMessage(), true);
            return ['success' => false, 'error' => 'Conexión fallida: ' . $e->getMessage()];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // $this->kommoLog('KommoController: IA cURL - Error de request: ' . $e->getMessage(), true);
            return ['success' => false, 'error' => 'Request fallido: ' . $e->getMessage()];
        } catch (\Exception $e) {
            // $this->kommoLog('KommoController: IA cURL - Error genérico: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')', true);
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

            // Parseando mensaje (fallback regex) (silenciado)

            // Patrón: salario, nómina, sueldo (números)
            if (preg_match('/(?:salario neto|salario|nómina|nomina|sueldo neto|sueldo)\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                // Nómina extraída: $valor (silenciado)
            } elseif (preg_match('/\b(?:gano|cobro|percibo)\s*:??\s*(\d{2,6}(?:[.,]\d{2})?)\b\s*(?:€|euros)?/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['nomina'] = $valor;
                // Nómina extraída (patrón gano): $valor (silenciado)
            }

            // Patrón: tipo de contrato (indefinido, temporal, autónomo, por obra)
            if (preg_match('/\b(indefinid[oa]|temporal|fijo|autonomo|autónomo|por obra|obra y servicio|pensionista|emplead[oa])\b/i', $texto, $m)) {
                $datosExtraidos['tipo_contrato'] = strtolower($m[1]);
                // Tipo de contrato extraído: $datosExtraidos['tipo_contrato'] (silenciado)
            }

            // Patrón: empresa (texto)
            if (preg_match('/empresa\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['empresa'] = trim($matches[1]);
                // Empresa extraída: $datosExtraidos['empresa'] (silenciado)
            }

            // Patrón: puesto, cargo (texto)
            if (preg_match('/(?:puesto|cargo)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['puesto'] = trim($matches[1]);
                // Puesto extraído: $datosExtraidos['puesto'] (silenciado)
            }

            // Patrón: ingresos, ingresos anuales (números)
            if (preg_match('/ingresos\s*(?:anuales|mensuales)?\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ingresos'] = $valor;
                // Ingresos extraídos: $valor (silenciado)
            }

            // Patrón: ciudad, provincia, localidad (texto)
            if (preg_match('/(?:ciudad|provincia|localidad|residencia)\s*:?\s*([A-Za-z0-9\s\&\.\-áéíóúñÁÉÍÓÚÑ]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['provincia'] = trim($matches[1]);
                // Provincia extraída: $datosExtraidos['provincia'] (silenciado)
            }

            // Patrón: ahorro (números)
            if (preg_match('/ahorro\s*:?\s*(\d+(?:[.,]\d{2})?)/i', $texto, $matches)) {
                $valor = str_replace(',', '.', $matches[1]);
                $datosExtraidos['ahorro'] = $valor;
                // Ahorro extraído: $valor (silenciado)
            }

            // Patrón: nacionalidad (texto)
            if (preg_match('/nacionalidad\s*:?\s*([A-Za-záéíóúñÁÉÍÓÚÑ\s\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['nacionalidad'] = trim($matches[1]);
                // Nacionalidad extraída: $datosExtraidos['nacionalidad'] (silenciado)
            }

            // Patrón: banco (texto)
            if (preg_match('/(?:banco|trabajo con|entidad|cuenta en)\s*:?\s*([A-Za-z0-9\s\&\.\-]+?)(?:\.|,|$|\n|—)/i', $texto, $matches)) {
                $datosExtraidos['banco'] = trim($matches[1]);
                // Banco extraído: $datosExtraidos['banco'] (silenciado)
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
                // $this->kommoLog('KommoController: No hay mapeo de campos para clave: ' . $clave);
                continue;
            }

            // Algunos campos requieren mapeo de opciones (no valores de texto)
            if ($clave === 'tipo_contrato') {
                // Intentar mapear a opción
                $opcionId = $this->mapearValorAOpcion($clave, $valor, $mapeoOpciones);
                if ($opcionId) {
                    // El campo 193 es select, usar opción
                    $valoresOpcion[193] = $opcionId;
                    // $this->kommoLog('KommoController: Mapeado tipo_contrato a opción ' . $opcionId);
                } else {
                    // Fallback: almacenar como texto en campo 221
                    foreach ($camposIds as $campoId) {
                        if ($campoId !== 221)
                            continue; // 221 es texto
                        $valoresTexto[$campoId] = $valor;
                    }
                }
            } else {
                // Es un valor de texto: mapear a todos los IDs de campos asociados
                foreach ($camposIds as $campoId) {
                    $valoresTexto[$campoId] = $valor;
                    // $this->kommoLog('KommoController: Mapeada clave ' . $clave . ' a campo ' . $campoId . ' => ' . substr((string)$valor, 0, 50));
                }
            }
        }

        // Estructura regex convertida (silenciado)

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
                    // $this->kommoLog('KommoController: Valor "' . $valor . '" mapeado a opción ' . $opcionId . ' (patrón: ' . $alt . ')');
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
                // $this->kommoLog('KommoController: API Kommo retornó status ' . $response->getStatusCode() . ' en obtenerContactoKommoDetallado', true);
                return null;
            }

            $data = json_decode($response->getBody(), true);

            // Extraer contacto de la respuesta
            if (isset($data['_embedded']['contacts'][0])) {
                // Detalles obtenidos de _embedded.contacts[0] (silenciado)
                return $data['_embedded']['contacts'][0];
            } elseif (isset($data['id'])) {
                // Detalles obtenidos desde raíz de respuesta API (silenciado)
                return $data;
            }

            return null;
        } catch (GuzzleException $e) {
            // $this->kommoLog('KommoController: Error en obtenerContactoKommoDetallado: ' . $e->getMessage(), true);
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
                // $this->kommoLog('KommoController: Cliente existente por teléfono: ' . $telefono);
                return $clienteExistente;
            }
        }

        // Buscar por email si teléfono no encontró nada
        if ($email) {
            $clienteExistente = $em->getRepository(UsuarioEntidad::class)->findOneBy(
                ['email' => $email]
            );
            if ($clienteExistente && $clienteExistente->getRole() === 'ROLE_CLIENTE') {
                // $this->kommoLog('KommoController: Cliente existente por email: ' . $email);
                return $clienteExistente;
            }
        }

        // Crear nuevo cliente
        // $this->kommoLog('KommoController: Creando nuevo cliente desde Kommo');
        list($nombreCliente, $apellidosCliente) = $this->separarNombreCompleto($nombre);

        $nuevoCliente = new UsuarioEntidad();
        $nuevoCliente->setUsername($nombreCliente);
        $nuevoCliente->setApellidos($apellidosCliente);
        // Solo guardar email si Kommo lo proporciona
        if ($email) {
            $nuevoCliente->setEmail($email);
        } else {
            $nuevoCliente->setEmail('');
        }
        $nuevoCliente->setTelefonoMovil($telefono ?: '');
        $nuevoCliente->setRole('ROLE_CLIENTE');
        $nuevoCliente->setEstado(true);
        $nuevoCliente->setPassword('');
        $nuevoCliente->setPlainPassword('');
        $nuevoCliente->setFechaRegistro(new \DateTime());

        $em->persist($nuevoCliente);
        $em->flush();

        // $this->kommoLog('KommoController: Nuevo cliente creado (ID: ' . $nuevoCliente->getIdUsuario() . ')');
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
            // $this->kommoLog('KommoController: Expediente existente para cliente: ' . $cliente->getIdUsuario());
            return $expedienteExistente;
        }

        // Crear nuevo expediente
        // $this->kommoLog('KommoController: Creando nuevo expediente para cliente: ' . $cliente->getIdUsuario());

        $primeraFase = $em->getRepository(FaseEntidad::class)->findOneBy(['orden' => 1]);
        if (!$primeraFase) {
            throw new \Exception('No hay fases configuradas en el sistema');
        }

        $expediente = new ExpedienteEntidad();
        $expediente->setIdCliente($cliente);
        $expediente->setIdFaseActual($primeraFase);
        $expediente->setEstado(1);
        $expediente->setVivienda('NUEVO LEAD KOMMO');
        $expediente->setFechaCreacion(new \DateTime());

        // Asignar referencia única para el nuevo expediente
        $this->asignarReferenciaAExpediente($expediente);

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
        // $this->kommoLog('KommoController: Estructura de expediente creada (ID: ' . $expediente->getIdExpediente() . ')');

        return $expediente;
    }

    /**
     * Actualiza hitos del expediente con datos de Kommo + datos del mensaje
     * Retorna un desglose de hitos y campos actualizados
     */
    private function actualizarHitosKommo($em, ExpedienteEntidad $expediente, array $contactoKommo, array $datosMensaje = []): array
    {
        $this->bgLog('DEBUG KOMMO: actualizarHitosKommo() - Contacto: ' . json_encode($contactoKommo));
        $this->bgLog('DEBUG KOMMO: actualizarHitosKommo() - DatosMensaje: ' . json_encode($datosMensaje));

        $camposAActualizar = $this->construirAutorrellenoHitosKommo($contactoKommo, $datosMensaje);
        // $this->kommoLog('KommoController: Actualizando ' . count($camposAActualizar) . ' campos');

        $desglose = [];
        $hitosActualizados = [];

        $this->bgLog('Paso 1.1');

        foreach ($camposAActualizar as $idCampoHito => $configuracion) {
            $campoHitoExpediente = $em->getRepository(CampoHitoExpedienteEntidad::class)->findOneBy([
                'idExpediente' => $expediente,
                'idCampoHito' => $idCampoHito
            ]);

            if (!$campoHitoExpediente) {
                // $this->kommoLog('KommoController: No se encontró CampoHitoExpediente para idCampo ' . $idCampoHito . ' en expediente ' . $expediente->getIdExpediente());
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
                $this->bgLog('DEBUG KOMMO: Actualizando campo ' . $idCampoHito . ' con opcion_id: ' . $opcionId);
                $setterUsed = null;

                // Usar getReference() de Doctrine: crea un proxy sin consultar la BD,
                // siempre funciona para asignar FKs mientras el id sea válido.
                $opcionesRef = $em->getReference('AppBundle:OpcionesCampo', $opcionId);
                $campoHitoExpediente->setIdOpcionesCampo($opcionesRef);
                $campoHitoExpediente->setValor(null);
                $this->bgLog('DEBUG KOMMO: Campo ' . $idCampoHito . ' - opción guardada con referencia id=' . $opcionId);

                $hitosActualizados[$idHito]['campos'][] = [
                    'idCampo' => $idCampoHito,
                    'nombreCampo' => $nombreCampo,
                    'tipo' => 'opcion',
                    'valor' => $opcionId
                ];
            } elseif (isset($configuracion['valor'])) {
                $this->bgLog('DEBUG KOMMO: Actualizando campo ' . $idCampoHito . ' con valor: ' . $configuracion['valor']);
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

        $this->bgLog('Paso 1.2');

        // Actualizar fecha de modificación del expediente
        $expediente->setFechaModificacion(new \DateTime());
        $em->persist($expediente);

        try {
            $this->bgLog('DEBUG KOMMO: Intentando flush() de cambios...');
            $em->flush();
            $this->bgLog('DEBUG KOMMO: ✅ Flush exitoso');
        } catch (\Exception $e) {
            $this->bgLog('DEBUG KOMMO: ❌ ERROR EN FLUSH: ' . $e->getMessage());
            $this->bgLog('DEBUG KOMMO: Exception trace: ' . $e->getTraceAsString());
            throw $e;
        }

        // Convertir a array indexado
        $desglose = array_values($hitosActualizados);

        $this->bgLog('DEBUG KOMMO: Desglose retornando: ' . json_encode($desglose));

        $this->bgLog('DEBUG KOMMO: ✅ actualizarHitosKommo() completado exitosamente');
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

        $this->bgLog('🟢 KOMMO construirAutorrellenoHitosKommo() - datosMensaje recibido: ' . json_encode($datosMensaje));

        $valoresTexto = $datosMensaje['valores_texto'] ?? [];
        $valoresOpcion = $datosMensaje['valores_opcion'] ?? [];

        $this->bgLog('DEBUG KOMMO: valoresTexto: ' . json_encode($valoresTexto));
        $this->bgLog('DEBUG KOMMO: valoresOpcion: ' . json_encode($valoresOpcion));

        // Construir array de campos compatible con actualizarHitosExpediente()
        $campos = [];

        // 📋 CAMPOS AUTOMÁTICOS DESDE KOMMO (origen RRSS + fecha + contacto)
        // Campo 673: Origen → opción 663 (RRSS)
        $campos[673] = ['opcion_id' => 663];
        $this->bgLog('Paso 10');

        // Campo 693: Nombre y Campo 694: Apellido desde el nombre del contacto
        $nombreCompleto = $lead['name'] ?? '';
        if (!empty($nombreCompleto)) {
            list($nombre, $apellido) = $this->separarNombreCompleto($nombreCompleto);

            if (!empty($nombre)) {
                $campos[693] = ['valor' => $nombre];
                $this->kommoLog('KommoController: Nombre (campo 693) establecido a ' . $nombre);
            }

            if (!empty($apellido)) {
                $campos[694] = ['valor' => $apellido];
                $this->kommoLog('KommoController: Apellido (campo 694) establecido a ' . $apellido);
            }
        }
        error_log('Paso 11');
        // Campo 688: Fecha del lead → fecha de creación del contacto en Kommo
        if (!empty($lead['date_create']) || !empty($lead['created_at'])) {
            $fechaCreacion = $lead['date_create'] ?? $lead['created_at'];
            // Si es timestamp, convertir a fecha
            if (is_numeric($fechaCreacion)) {
                $fechaFormato = date('Y-m-d', $fechaCreacion);
            } else {
                // Si es string, intentar parsearlo
                $fechaObj = \DateTime::createFromFormat('Y-m-d H:i:s', $fechaCreacion) ?:
                    \DateTime::createFromFormat('U', $fechaCreacion) ?:
                    new \DateTime($fechaCreacion);
                $fechaFormato = $fechaObj->format('Y-m-d');
            }
            $campos[688] = ['valor' => $fechaFormato];
            $this->kommoLog('KommoController: Fecha del lead (campo 688) establecida a ' . $fechaFormato);
        }
        error_log('Paso 12');
        // Campo 695: Teléfono desde Kommo
        $telefono = $this->extraerTelefono($lead);
        if (!empty($telefono)) {
            $campos[695] = ['valor' => $telefono];
            // También rellenar el campo 408 (telefono en vistas/formularios)
            $campos[408] = ['valor' => $telefono];
            $this->kommoLog('KommoController: Teléfono (campos 695 y 408) establecido a ' . $telefono);
        }

        // Campo 696: Email desde Kommo
        $email = $this->extraerEmail($lead);
        if (!empty($email)) {
            $campos[696] = ['valor' => $email];
            // También rellenar el campo 407 (email en vistas/formularios)
            $campos[407] = ['valor' => $email];
            $this->kommoLog('KommoController: Email (campos 696 y 407) establecido a ' . $email);
        }

        // Mapear valores de texto (campo_id => valor) - pueden sobrescribir los automáticos
        foreach ($valoresTexto as $idCampo => $valor) {
            if (!empty($valor) && trim((string) $valor) !== '') {
                $campos[$idCampo] = ['valor' => (string) $valor];
            }
        }

        // Mapear opciones (campo_id => opcion_id) - pueden sobrescribir los automáticos
        foreach ($valoresOpcion as $idCampo => $opcionId) {
            if (!empty($opcionId)) {
                $campos[$idCampo] = ['opcion_id' => (int) $opcionId];
            }
        }

        // Registrar resumen de campos que se van a actualizar
        error_log('DEBUG KOMMO: CAMPOS FINALES A GUARDAR: ' . json_encode($campos));
        $this->kommoLog('KommoController: Total de campos a actualizar: ' . count($campos) . '. Desglose: ' . json_encode(array_keys($campos)));

        // Filtrar campos vacíos
        $camposFinales = array_filter($campos, function ($configuracion) {
            return (isset($configuracion['opcion_id']) && !empty($configuracion['opcion_id']))
                || (isset($configuracion['valor']) && trim((string) $configuracion['valor']) !== '');
        });

        error_log('DEBUG KOMMO: camposFinales despues de filtrar: ' . json_encode($camposFinales));
        return $camposFinales;
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
        $telefono = '';

        // Kommo API v4: buscar en custom_fields_values por field_code = 'PHONE'
        if (!empty($contacto['custom_fields_values']) && is_array($contacto['custom_fields_values'])) {
            foreach ($contacto['custom_fields_values'] as $campo) {
                if (!empty($campo['field_code']) && $campo['field_code'] === 'PHONE') {
                    if (!empty($campo['values'][0]['value'])) {
                        $telefono = (string) $campo['values'][0]['value'];
                        $this->kommoLog('KommoController: Teléfono extraído de custom_fields_values: ' . $telefono);
                        break;
                    }
                }
            }
        }

        // Fallback: campos antiguos
        if (!$telefono && !empty($contacto['phone'])) {
            $telefono = (string) $contacto['phone'];
        }
        if (!$telefono && !empty($contacto['custom_fields']['telefono'])) {
            $telefono = (string) $contacto['custom_fields']['telefono'];
        }
        if (!$telefono && isset($contacto['_embedded']['phones'][0]['value'])) {
            $telefono = (string) $contacto['_embedded']['phones'][0]['value'];
        }

        if (!$telefono) {
            $this->kommoLog('KommoController: No se encontró teléfono en el contacto');
            return '';
        }

        // Normalizar teléfono: quitar +34 si existe
        $telefono = trim($telefono);
        if (strpos($telefono, '+34') === 0) {
            $telefono = substr($telefono, 3); // Quitar los 3 primeros caracteres (+34)
        }

        $this->kommoLog('KommoController: Teléfono normalizado: ' . $telefono);
        return $telefono;
    }

    private function extraerEmail(array $contacto): string
    {
        // Kommo API v4: buscar en custom_fields_values por field_code = 'EMAIL'
        if (!empty($contacto['custom_fields_values']) && is_array($contacto['custom_fields_values'])) {
            foreach ($contacto['custom_fields_values'] as $campo) {
                if (!empty($campo['field_code']) && $campo['field_code'] === 'EMAIL') {
                    if (!empty($campo['values'][0]['value'])) {
                        $email = (string) $campo['values'][0]['value'];
                        $this->kommoLog('KommoController: Email extraído de custom_fields_values: ' . $email);
                        return $email;
                    }
                }
            }
        }

        // Fallback: campos antiguos
        if (!empty($contacto['email'])) {
            return (string) $contacto['email'];
        }
        if (!empty($contacto['custom_fields']['email'])) {
            return (string) $contacto['custom_fields']['email'];
        }
        if (isset($contacto['_embedded']['emails'][0]['value'])) {
            return (string) $contacto['_embedded']['emails'][0]['value'];
        }

        $this->kommoLog('KommoController: No se encontró email en el contacto');
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
            $this->kommoLog('Error al limpiar webhooks: ' . $e->getMessage(), true);

            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'Error al eliminar registros: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Genera una referencia única para un expediente
     * Formato: NNNN/YY (ej: 0001/26, 0002/26, etc.)
     * 
     * @param int $anio El año de 2 dígitos para el que generar la referencia
     * @return string Referencia en formato NNNN/YY
     * @throws \Exception Si hay error al generar la referencia
     */
    private function generarReferencia(int $anio): string
    {
        try {
            $doctrine = $this->getDoctrine();
            $conn = $doctrine->getConnection();

            // Query SQL para obtener el máximo número de referencia del año especificado
            $sql = "
				SELECT MAX(CAST(SUBSTRING_INDEX(referencia, '/', 1) AS UNSIGNED)) as max_numero
				FROM expediente
				WHERE referencia LIKE :patron
			";

            $stmt = $conn->prepare($sql);
            $patron = '%/' . str_pad($anio, 2, '0', STR_PAD_LEFT);
            $stmt->bindValue('patron', $patron);
            $stmt->execute();
            $result = $stmt->fetch();

            $maxNumero = isset($result['max_numero']) && !is_null($result['max_numero'])
                ? (int) $result['max_numero']
                : 0;

            $siguienteNumero = $maxNumero + 1;

            // Formatear: NNNN/YY (4 dígitos para número, 2 para año)
            $referencia = sprintf('%05d/%02d', $siguienteNumero, $anio);

            return $referencia;
        } catch (\Exception $e) {
            throw new \Exception('Error al generar referencia de expediente: ' . $e->getMessage());
        }
    }

    /**
     * Asigna una referencia a un expediente si no la tiene
     * 
     * @param ExpedienteEntidad $expediente
     * @return string La referencia asignada
     * @throws \Exception
     */
    private function asignarReferenciaAExpediente(ExpedienteEntidad $expediente): string
    {
        // Si ya tiene referencia, no generar otra
        if (!empty($expediente->getReferencia())) {
            return $expediente->getReferencia();
        }

        // Obtener el año desde la fecha de creación del expediente
        $anio = (int) $expediente->getFechaCreacion()->format('y');

        // Generar y asignar la referencia
        $referencia = $this->generarReferencia($anio);
        $expediente->setReferencia($referencia);

        return $referencia;
    }
}
