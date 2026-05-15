<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class KommoController extends Controller
{
    /**
     * @Route("/backoffice/leads-kommo", name="leads_kommo")
     */
    public function leadsKommoAction()
    {
        $repo = $this->getDoctrine()->getRepository('AppBundle:KommoWebhook');
        $webhooks = $repo->findBy([], ['fecha' => 'DESC']);

        $grupos = [];
        foreach ($webhooks as $webhook) {
            $json = $webhook->getJsonRecibido();
            if (is_string($json)) {
                $jsonDecodificado = json_decode($json, true);
                $json = is_array($jsonDecodificado) ? $jsonDecodificado : [];
            } elseif ($json instanceof \stdClass) {
                $json = json_decode(json_encode($json), true);
            } elseif (!is_array($json)) {
                $json = [];
            }

            $kommoId = resolverKommoIdPrincipal($json, $webhook);
            $groupKey = $kommoId !== '' ? 'kommo_' . $kommoId : 'registro_' . $webhook->getId();

            $lead = [
                'id' => $webhook->getId(),
                'group_key' => $groupKey,
                'kommo_id' => $kommoId,
                'fecha' => $webhook->getFecha(),
                'nombre' => '',
                'nombre_contacto' => '',
                'telefono' => '',
                'telefono_contacto' => '',
                'email' => '',
                'email_contacto' => '',
                'provincia' => '',
                'canal' => '',
                'estado' => $webhook->getEstado(),
            ];

            $leadData = obtenerPrimerEventoKommo($json, 'leads');
            if (!empty($leadData)) {
                $lead['nombre'] = limpiarTextoVisibleKommo($leadData['name'] ?? '');
                $lead['provincia'] = extraerCampoPersonalizado($leadData, 'Provincia');
                $lead['canal'] = limpiarTextoVisibleKommo($leadData['tags'][0]['name'] ?? '');
            }

            $contact = obtenerPrimerEventoKommo($json, 'contacts');
            if (!empty($contact)) {
                $lead['nombre_contacto'] = limpiarTextoVisibleKommo($contact['name'] ?? '');
                $lead['telefono_contacto'] = extraerCampoPersonalizado($contact, 'Teléfono', 'PHONE');
                $lead['email_contacto'] = extraerCampoPersonalizado($contact, 'Correo', 'EMAIL');

                if ($lead['telefono'] === '' && $lead['telefono_contacto'] !== '') {
                    $lead['telefono'] = $lead['telefono_contacto'];
                }
                if ($lead['email'] === '' && $lead['email_contacto'] !== '') {
                    $lead['email'] = $lead['email_contacto'];
                }
                if (esNombreGenericoKommo($lead['nombre']) && $lead['nombre_contacto'] !== '') {
                    $lead['nombre'] = $lead['nombre_contacto'];
                }
            }

            $webhookType = method_exists($webhook, 'getWebhookType') ? $webhook->getWebhookType() : '';
            $eventoKommo = obtenerPrimerEventoKommo($json, $webhookType);
            $detalle = [
                'id' => $webhook->getId(),
                'webhook_type' => $webhookType,
                'fecha' => $webhook->getFecha() ? $webhook->getFecha()->format('d/m/Y H:i') : '',
                'nombre' => $lead['nombre'],
                'telefono' => $lead['telefono'],
                'email' => $lead['email'],
                'autor_kommo' => obtenerAutorKommo($eventoKommo),
                'origen_kommo' => obtenerOrigenKommo($eventoKommo),
                'mensaje_kommo' => obtenerTextoMensajeKommo($eventoKommo, $json),
                'detalle_kommo' => obtenerDetalleKommo($json, $lead, $webhookType),
            ];

            if (!isset($grupos[$groupKey])) {
                $lead['total_entradas'] = 0;
                $lead['detalles'] = [];
                $grupos[$groupKey] = $lead;
            }

            foreach (['nombre', 'telefono', 'email', 'provincia', 'canal'] as $campo) {
                if ($grupos[$groupKey][$campo] === '' && $lead[$campo] !== '') {
                    $grupos[$groupKey][$campo] = $lead[$campo];
                }
            }

            $estadoWebhook = (string) $webhook->getEstado();
            if (prioridadEstadoKommo($estadoWebhook) > prioridadEstadoKommo($grupos[$groupKey]['estado'] ?? '')) {
                $grupos[$groupKey]['estado'] = $estadoWebhook;
            }

            $grupos[$groupKey]['total_entradas']++;
            $grupos[$groupKey]['detalles'][] = $detalle;
        }

        $leads = array_values($grupos);
        foreach ($leads as &$leadResumen) {
            $clienteExistente = buscarClienteKommo($this->getDoctrine(), $leadResumen);
            $leadResumen['cliente_existente_id'] = null;
            $leadResumen['cliente_existente_nombre'] = '';
            $leadResumen['num_expedientes'] = 0;
            $leadResumen['primer_expediente_id'] = null;
            $leadResumen['expedientes_info'] = [];

            if ($clienteExistente) {
                $leadResumen['cliente_existente_id'] = $clienteExistente->getIdUsuario();
                $leadResumen['cliente_existente_nombre'] = trim($clienteExistente->getUsername() . ' ' . $clienteExistente->getApellidos());

                $expedientesCliente = $this->getDoctrine()->getRepository('AppBundle:Expediente')->findBy([
                    'idCliente' => $clienteExistente,
                    'estado' => 1
                ], ['fechaCreacion' => 'DESC']);

                $leadResumen['num_expedientes'] = count($expedientesCliente);
                foreach ($expedientesCliente as $expedienteCliente) {
                    $leadResumen['expedientes_info'][] = [
                        'id' => $expedienteCliente->getIdExpediente(),
                        'fecha' => $expedienteCliente->getFechaCreacion() ? $expedienteCliente->getFechaCreacion()->format('d/m/Y') : '',
                        'vivienda' => method_exists($expedienteCliente, 'getVivienda') ? (string) $expedienteCliente->getVivienda() : '',
                        'url' => $this->generateUrl('modificar_expediente', ['id' => $expedienteCliente->getIdExpediente()]),
                    ];
                }

                if (!empty($expedientesCliente)) {
                    $leadResumen['primer_expediente_id'] = $expedientesCliente[0]->getIdExpediente();
                }
            }
        }
        unset($leadResumen);

        return $this->render('@App/Backoffice/Lista/Leads.html.twig', array(
			'titulo' => 'Lista de Leads de Kommo',
			'leads' => $leads
		));
    }

    public function procesarLeadKommoAction(Request $request, $groupKey)
    {
        $lead = obtenerLeadKommoPorClave($this->getDoctrine(), $groupKey);
        if (!$lead) {
            $this->addFlash('warning', 'No se ha encontrado el lead de Kommo a procesar.');
            return $this->redirectToRoute('leads_kommo2');
        }

        $managerEntidad = $this->getDoctrine()->getManager();
        $cliente = buscarClienteKommo($this->getDoctrine(), $lead);
        $clienteCreado = false;

        if (!$cliente) {
            $cliente = crearClienteDesdeLeadKommo($lead);
            $managerEntidad->persist($cliente);
            $clienteCreado = true;
        }

        $expedientesCliente = $this->getDoctrine()->getRepository('AppBundle:Expediente')->findBy([
            'idCliente' => $cliente,
            'estado' => 1
        ], ['fechaCreacion' => 'DESC']);

        if (!empty($expedientesCliente) && !$request->query->get('forceNew')) {
            $this->addFlash('info', 'El cliente ya existe y ya tiene expediente. Se ha abierto el expediente más reciente.');
            return $this->redirectToRoute('modificar_expediente', ['id' => $expedientesCliente[0]->getIdExpediente()]);
        }

        $expediente = crearExpedienteDesdeLeadKommo($this->getDoctrine(), $managerEntidad, $cliente, $lead, $this->getUser());
        $managerEntidad->persist($expediente);

        $webhooksProcesados = obtenerWebhooksKommoPorClave($this->getDoctrine(), $groupKey);
        foreach ($webhooksProcesados as $webhookProcesado) {
            if (method_exists($webhookProcesado, 'setEstado')) {
                $webhookProcesado->setEstado('procesado');
                $managerEntidad->persist($webhookProcesado);
            }
        }

        $managerEntidad->flush();

        if ($clienteCreado) {
            $this->addFlash('success', 'Se ha creado el cliente y el expediente desde el lead de Kommo.');
        } else {
            $this->addFlash('success', 'Se ha creado un nuevo expediente para el cliente existente.');
        }

        return $this->redirectToRoute('modificar_expediente', ['id' => $expediente->getIdExpediente()]);
    }

    public function responderMensajeExpedienteAction(Request $request, $id)
    {
        $payload = json_decode($request->getContent(), true);
        if (json_last_error() !== 0 || !is_array($payload)) {
            $payload = $request->request->all();
        }

        $texto = sanitizarTextoRespuestaManualKommo($payload['mensaje'] ?? '');
        if ($texto === '') {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'Debes escribir un mensaje antes de enviarlo.'
            ], 400);
        }

        $doctrine = $this->getDoctrine();
        $expediente = $doctrine->getRepository('AppBundle:Expediente')->find($id);
        if (!$expediente) {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'No se ha encontrado el expediente.'
            ], 404);
        }

        $kommoLeadId = '';
        try {
            $repoMensajes = $doctrine->getRepository('AppBundle:ExpedienteChatMensaje');
            $mensajes = $repoMensajes->findBy([
                'idExpediente' => $expediente,
                'proveedor' => 'kommo',
            ], ['fechaMensaje' => 'DESC']);

            foreach ($mensajes as $mensajeExistente) {
                if (method_exists($mensajeExistente, 'getKommoLeadId')) {
                    $kommoLeadId = trim((string) $mensajeExistente->getKommoLeadId());
                    if ($kommoLeadId !== '') {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if ($kommoLeadId === '' && method_exists($expediente, 'getTexto')) {
            if (preg_match('/Kommo ID:\s*([0-9]+)/i', (string) $expediente->getTexto(), $coincidencias)) {
                $kommoLeadId = trim((string) ($coincidencias[1] ?? ''));
            }
        }

        if ($kommoLeadId === '') {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'Este expediente no tiene una conversación de Kommo vinculada todavía.'
            ], 400);
        }

        $subdomain = trim((string) $this->getParameter('kommo_subdomain'));
        $fieldId = (int) $this->getParameter('kommo_reply_field_id');
        $botId = (int) $this->getParameter('kommo_bot_id');
        $token = trim((string) obtenerTokenApiKommo());

        if ($subdomain === '' || $fieldId <= 0 || $botId <= 0 || $token === '') {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => 'Falta configuración de Kommo para poder enviar la respuesta manual.'
            ], 500);
        }

        $logger = null;
        try {
            $logger = $this->get('logger');
        } catch (\Throwable $e) {
        }

        $resultadoEnvio = enviarRespuestaManualLeadKommo($subdomain, $token, $kommoLeadId, $texto, $fieldId, $botId, $logger);
        if (empty($resultadoEnvio['ok'])) {
            return new JsonResponse([
                'ok' => false,
                'mensaje' => $resultadoEnvio['mensaje'] ?? 'No se pudo enviar la respuesta a Kommo.'
            ], 500);
        }

        $fecha = new \DateTime();
        $autorNombre = 'Hipotea';
        if ($this->getUser() && method_exists($this->getUser(), 'getUsername')) {
            $autorNombre = trim((string) $this->getUser()->getUsername()) ?: 'Hipotea';
        }

        try {
            if (class_exists('\AppBundle\Entity\ExpedienteChatMensaje')) {
                $managerEntidad = $doctrine->getManager();
                $mensajeChat = new \AppBundle\Entity\ExpedienteChatMensaje();

                if (method_exists($mensajeChat, 'setProveedor')) {
                    $mensajeChat->setProveedor('kommo');
                }
                if (method_exists($mensajeChat, 'setExternalMessageId')) {
                    $mensajeChat->setExternalMessageId('manual:' . $kommoLeadId . ':' . substr(md5($texto . microtime(true)), 0, 20));
                }
                if (method_exists($mensajeChat, 'setKommoLeadId')) {
                    $mensajeChat->setKommoLeadId((string) $kommoLeadId);
                }
                if (method_exists($mensajeChat, 'setDireccion')) {
                    $mensajeChat->setDireccion('saliente');
                }
                if (method_exists($mensajeChat, 'setAutorNombre')) {
                    $mensajeChat->setAutorNombre($autorNombre);
                }
                if (method_exists($mensajeChat, 'setAutorTipo')) {
                    $mensajeChat->setAutorTipo('interno');
                }
                if (method_exists($mensajeChat, 'setMensaje')) {
                    $mensajeChat->setMensaje(limitarTextoKommo($texto, 5000));
                }
                if (method_exists($mensajeChat, 'setPayloadJson')) {
                    $payloadJson = json_encode([
                        'manual_reply' => true,
                        'lead_id' => $kommoLeadId,
                        'texto' => $texto,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $mensajeChat->setPayloadJson($payloadJson !== false ? $payloadJson : '');
                }
                if (method_exists($mensajeChat, 'setEstado')) {
                    $mensajeChat->setEstado('enviado');
                }
                if (method_exists($mensajeChat, 'setLeido')) {
                    $mensajeChat->setLeido(true);
                }
                if (method_exists($mensajeChat, 'setFechaMensaje')) {
                    $mensajeChat->setFechaMensaje($fecha);
                }
                if (method_exists($mensajeChat, 'setFechaActualizacion')) {
                    $mensajeChat->setFechaActualizacion($fecha);
                }
                if (method_exists($mensajeChat, 'setIdExpediente')) {
                    $mensajeChat->setIdExpediente($expediente);
                }

                $managerEntidad->persist($mensajeChat);
                $managerEntidad->flush();
            }
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->warning('Kommo manual reply: no se pudo guardar la traza local del mensaje enviado', [
                    'expediente_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse([
            'ok' => true,
            'mensaje' => 'Respuesta enviada correctamente a Kommo.',
            'texto' => $texto,
            'fecha' => $fecha->format('d/m/Y H:i'),
            'autor' => $autorNombre,
            'kommo_id' => $kommoLeadId,
        ]);
    }

    /**
     * Sugiere una respuesta automática para una conversación de Kommo usando IA
     * 
     * @Route("/AJAX/ExpedienteChatSugerirRespuesta/{id}", name="expediente_chat_sugerir_kommo", methods={"POST"})
     */
    public function sugerirRespuestaAction(Request $request, $id)
    {
        try {
            error_log('🔍 [PRUEBA 1] Iniciando sugerirRespuestaAction con ID: ' . $id);
            
            // Obtener expediente
            $doctrine = $this->getDoctrine();
            $expediente = $doctrine->getRepository('AppBundle:Expediente')->find($id);
            
            error_log('🔍 [PRUEBA 2] Expediente encontrado: ' . ($expediente ? 'SÍ' : 'NO'));
            
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No se encontró el expediente.'
                ], 404);
            }
            
            // Obtener conversación de Kommo
            error_log('🔍 [PRUEBA 3] Buscando conversación de Kommo...');
            
            $repoMensajes = $doctrine->getRepository('AppBundle:ExpedienteChatMensaje');
            $mensajes = $repoMensajes->findBy([
                'idExpediente' => $expediente,
                'proveedor' => 'kommo',
            ], ['fechaMensaje' => 'DESC'], 10);
            
            error_log('🔍 [PRUEBA 4] Mensajes encontrados: ' . count($mensajes));
            
            $conversacion = [];
            foreach (array_reverse($mensajes) as $msg) {
                $autor = method_exists($msg, 'getAutorNombre') ? $msg->getAutorNombre() : 'Desconocido';
                $texto = method_exists($msg, 'getMensaje') ? $msg->getMensaje() : '';
                $conversacion[] = $autor . ': ' . $texto;
            }
            
            if (empty($conversacion)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No hay conversación de Kommo para este expediente.'
                ], 400);
            }
            
            error_log('🔍 [PRUEBA 5] Obteniendo configuración IA...');
            
            // Obtener configuración IA
            $configIA = $this->obtenerConfiguracionIA();
            
            error_log('🔍 [PRUEBA 6] Configuración IA obtenida. Provider: ' . ($configIA['provider'] ?? 'DESCONOCIDO'));
            
            if (empty($configIA['provider'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No está configurado ningún proveedor IA.'
                ], 500);
            }
            
            error_log('🔍 [PRUEBA 7] Construyendo prompt...');
            
            // Construir prompt
            $prompt = $this->construirPromptSugerenciaRespuesta($conversacion, $expediente);
            
            error_log('🔍 [PRUEBA 8] Enviando a ' . $configIA['provider'] . '...');
            
            // Enviar a proveedor IA según configuración
            $resultado = [];
            switch ($configIA['provider']) {
                case 'gemini':
                    $resultado = $this->enviarPromptAGemini($prompt, $configIA);
                    break;
                case 'openai':
                    $resultado = $this->enviarPromptAOpenAI($prompt, $configIA);
                    break;
                case 'ollama':
                    $resultado = $this->enviarPromptAOllama($prompt, $configIA);
                    break;
                default:
                    throw new \Exception('Proveedor IA desconocido: ' . $configIA['provider']);
            }
            
            error_log('✅ [PRUEBA 9] IA respondió exitosamente');
            
            // Limitar sugerencia a 250 caracteres máximo
            $sugerenciaTexto = $resultado['texto'] ?? '';
            if (mb_strlen($sugerenciaTexto, 'UTF-8') > 250) {
                $sugerenciaTexto = mb_substr($sugerenciaTexto, 0, 247, 'UTF-8') . '...';
            }
            
            return new JsonResponse([
                'success' => true,
                'sugerencia' => $sugerenciaTexto,
                'proveedor' => $configIA['provider'],
                'tokens' => [
                    'entrada' => $resultado['prompt_tokens'] ?? 0,
                    'salida' => $resultado['completion_tokens'] ?? 0,
                    'total' => $resultado['tokens'] ?? 0
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log('❌ Error en sugerirRespuestaAction: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al obtener sugerencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía un prompt de texto puro a Google Gemini
     */
    private function enviarPromptAGemini($prompt, $configIA)
    {
        error_log('📤 [GEMINI] Iniciando envío a Gemini...');
        
        // Limpiar el prompt: eliminar caracteres problemáticos
        $prompt = trim((string) $prompt);
        if (empty($prompt)) {
            throw new \Exception('Prompt vacío para Gemini');
        }
        
        // Truncar si es demasiado largo (máx 8000 caracteres para seguridad)
        if (mb_strlen($prompt, 'UTF-8') > 8000) {
            error_log('⚠️ [GEMINI] Prompt truncado de ' . mb_strlen($prompt, 'UTF-8') . ' a 8000 caracteres');
            $prompt = mb_substr($prompt, 0, 8000, 'UTF-8');
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configIA['model']}:generateContent?key={$configIA['api_key']}";
        error_log('🔗 [GEMINI] URL: ' . substr($url, 0, 80) . '...');
        
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => $prompt
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => (float) ($configIA['temperature'] ?? 0.7),
                "maxOutputTokens" => (int) ($configIA['max_tokens'] ?? 500)
            ]
        ];
        
        // Validar JSON
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            error_log('❌ [GEMINI] Error al codificar JSON: ' . json_last_error_msg());
            throw new \Exception('Error al codificar payload JSON para Gemini');
        }
        
        error_log('📋 [GEMINI] Payload size: ' . strlen($jsonPayload) . ' bytes');
        error_log('📝 [GEMINI] Prompt snippet: ' . substr($prompt, 0, 100) . '...');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        error_log('📡 [GEMINI] Enviando request a Gemini...');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log('🔙 [GEMINI] HTTP Code: ' . $httpCode . ' | cURL Error: ' . ($curlError ?: 'NONE'));
        
        if ($httpCode !== 200) {
            error_log('❌ [GEMINI] Error en Gemini API. Response: ' . substr($response, 0, 300));
            throw new \Exception('Error en Gemini API (' . $httpCode . '): ' . substr($response, 0, 200));
        }
        
        $data = json_decode($response, true);
        error_log('📊 [GEMINI] JSON Response keys: ' . ($data ? implode(', ', array_keys($data)) : 'NULL'));
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('❌ [GEMINI] Respuesta inválida. Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            throw new \Exception('Respuesta inválida de Gemini API');
        }
        
        $textoRespuesta = $data['candidates'][0]['content']['parts'][0]['text'];
        
        $tokensEntrada = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $tokensSalida = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
        $tokensTotales = $data['usageMetadata']['totalTokenCount'] ?? ($tokensEntrada + $tokensSalida);
        
        error_log('✅ [GEMINI] Éxito. Tokens - Entrada: ' . $tokensEntrada . ' | Salida: ' . $tokensSalida . ' | Total: ' . $tokensTotales);
        error_log('📝 [GEMINI] Texto: ' . substr($textoRespuesta, 0, 150) . '...');
        
        return [
            'texto' => $textoRespuesta,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }

    /**
     * Envía un prompt de texto puro a OpenAI
     */
    private function enviarPromptAOpenAI($prompt, $configIA)
    {
        $url = "https://api.openai.com/v1/chat/completions";
        
        $payload = [
            "model" => $configIA['model'],
            "messages" => [
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "max_tokens" => $configIA['max_tokens'],
            "temperature" => $configIA['temperature']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $configIA['api_key']
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Error en OpenAI API (' . $httpCode . '): ' . substr($response, 0, 200));
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Respuesta inválida de OpenAI API');
        }
        
        $textoRespuesta = $data['choices'][0]['message']['content'];
        
        $tokensEntrada = $data['usage']['prompt_tokens'] ?? 0;
        $tokensSalida = $data['usage']['completion_tokens'] ?? 0;
        $tokensTotales = $data['usage']['total_tokens'] ?? ($tokensEntrada + $tokensSalida);
        
        return [
            'texto' => $textoRespuesta,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }

    /**
     * Envía un prompt de texto puro a Ollama (modelo local)
     */
    private function enviarPromptAOllama($prompt, $configIA)
    {
        $url = "https://crabbedly-unpersonalized-angelique.ngrok-free.dev/api/generate";
        
        $payload = [
            "model" => $configIA['model'],
            "prompt" => $prompt,
            "stream" => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Error en Ollama API (' . $httpCode . '): ' . substr($response, 0, 200));
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['response']) || empty($data['response'])) {
            throw new \Exception('Respuesta vacía de Ollama API');
        }
        
        $textoRespuesta = $data['response'];
        
        $tokensEntrada = $data['prompt_eval_count'] ?? 0;
        $tokensSalida = $data['eval_count'] ?? 0;
        $tokensTotales = ($tokensEntrada + $tokensSalida);
        
        return [
            'texto' => $textoRespuesta,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }

    /**
     * Construye un prompt contextualizado para sugerir respuestas a mensajes de Kommo
     * Lee dinámicamente desde ficheros .md con detección automática de cambios
     */
    private function construirPromptSugerenciaRespuesta($conversacion, $expediente)
    {
        $nombreCliente = method_exists($expediente, 'getNombreCliente') 
            ? $expediente->getNombreCliente() 
            : 'Cliente';
        
        // Instanciar BotController (cada llamada detecta cambios en ficheros .md)
        $botController = new \AppBundle\Controller\BotController();
        
        // Construir prompt dinámicamente desde ficheros .md
        $prompt = $botController->construirPromptDinamico($conversacion, $nombreCliente);
        
        return $prompt;
    }

    public function borrarLeadsKommoAction(Request $request)
    {
        $seleccionados = $request->request->get('cliente', []);

        if (!is_array($seleccionados) || empty($seleccionados)) {
            $this->addFlash('warning', 'No se ha seleccionado ningún lead.');
            return $this->redirectToRoute('leads_kommo2');
        }

        $em = $this->getDoctrine()->getManager();
        $eliminados = 0;

        foreach ($seleccionados as $groupKey) {
            $webhooks = obtenerWebhooksKommoPorClave($this->getDoctrine(), $groupKey);
            foreach ($webhooks as $webhook) {
                $em->remove($webhook);
                $eliminados++;
            }
        }

        $em->flush();
        $this->addFlash('success', sprintf('Se han borrado %d registros de Kommo.', $eliminados));

        return $this->redirectToRoute('leads_kommo2');
    }

    /**
     * Obtiene configuración de IA desde tabla ia_config o variables de entorno
     */
    private function obtenerConfiguracionIA()
    {
        try {
            $em = $this->getDoctrine()->getManager();
            
            // 1. Intentar obtener de base de datos primero
            error_log('📊 [CONFIG] Paso 1: Buscando configuración IA en BD...');
            
            try {
                // Estrategia flexible: obtener todo de ia_config y buscar lo que necesitamos
                $sql = "SELECT * FROM ia_config LIMIT 5";
                $connection = $em->getConnection();
                $stmt = $connection->prepare($sql);
                $stmt->execute();
                $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                error_log('📊 [CONFIG] Resultados de BD: ' . count($resultados) . ' registros encontrados');
                
                if (!empty($resultados)) {
                    error_log('📊 [CONFIG] Estructura de BD: ' . json_encode(array_keys($resultados[0])));
                    
                    foreach ($resultados as $configDB) {
                        // Buscar API key en diferentes nombres de columna posibles
                        $apiKey = '';
                        foreach (['api_key', 'apiKey', 'clave_api', 'token', 'key'] as $columna) {
                            if (!empty($configDB[$columna])) {
                                $apiKey = trim($configDB[$columna]);
                                break;
                            }
                        }
                        
                        if ($apiKey !== '') {
                            // Buscar proveedor en diferentes nombres
                            $provider = 'gemini';
                            foreach (['proveedor', 'provider', 'tipo', 'name'] as $columna) {
                                if (!empty($configDB[$columna])) {
                                    $provider = strtolower(trim($configDB[$columna]));
                                    break;
                                }
                            }
                            
                            // Buscar modelo
                            $model = 'gemini-1.5-flash';
                            foreach (['model', 'modelo', 'nombre_modelo', 'model_name'] as $columna) {
                                if (!empty($configDB[$columna])) {
                                    $model = trim($configDB[$columna]);
                                    break;
                                }
                            }
                            
                            error_log('✅ [CONFIG] Usando config de BD: provider=' . $provider . ', api_key=' . substr($apiKey, 0, 10) . '...');
                            
                            return [
                                'provider' => $provider,
                                'api_key' => $apiKey,
                                'model' => $model,
                                'temperature' => 0.7,
                                'max_tokens' => 8192
                            ];
                        }
                    }
                }
                
            } catch (\Exception $eDb) {
                error_log('⚠️ [CONFIG] Error consultando BD: ' . $eDb->getMessage());
            }
            
            // 2. Fallback a variables de entorno
            error_log('📊 [CONFIG] Paso 2: Buscando en variables de entorno...');
            
            // Intentar GEMINI primero
            $geminiKey = trim(getenv('GEMINI_API_KEY') ?: '');
            if ($geminiKey !== '') {
                error_log('✅ [CONFIG] Encontrada GEMINI_API_KEY en env');
                return [
                    'provider' => 'gemini',
                    'api_key' => $geminiKey,
                    'model' => trim(getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash'),
                    'temperature' => 0.7,
                    'max_tokens' => 8192
                ];
            }
            
            // Intentar OpenAI
            $openaiKey = trim(getenv('OPENAI_API_KEY') ?: '');
            if ($openaiKey !== '') {
                error_log('✅ [CONFIG] Encontrada OPENAI_API_KEY en env');
                return [
                    'provider' => 'openai',
                    'api_key' => $openaiKey,
                    'model' => trim(getenv('OPENAI_MODEL') ?: 'gpt-4'),
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ];
            }
            
            // Intentar Ollama
            $ollamaKey = trim(getenv('OLLAMA_API_KEY') ?: '');
            if ($ollamaKey !== '') {
                error_log('✅ [CONFIG] Encontrada OLLAMA_API_KEY en env');
                return [
                    'provider' => 'ollama',
                    'api_key' => $ollamaKey,
                    'model' => trim(getenv('OLLAMA_MODEL') ?: 'llama2'),
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ];
            }
            
            // 3. Si no hay nada, retornar error
            error_log('❌ [CONFIG] NO se encontró configuración IA en BD ni en variables de entorno');
            return [
                'provider' => '',
                'api_key' => '',
                'model' => '',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];
            
        } catch (\Exception $e) {
            error_log('❌ [CONFIG] Excepción en obtenerConfiguracionIA: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            return [
                'provider' => '',
                'api_key' => '',
                'model' => '',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];
        }
    }
}

function jsonUnicodeResponseKommo($data, $status = 200)
{
    $response = new JsonResponse($data, $status);
    $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

    return $response;
}

function sanitizarTextoRespuestaManualKommo($texto)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return '';
    }

    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $texto = preg_replace('/[ \t]+\n/u', "\n", $texto);
    $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);

    return limitarTextoKommo($texto, 4000);
}

function peticionApiMutacionKommo($subdomain, $token, $path, $method, array $payload = [], $logger = null)
{
    $subdomain = trim((string) $subdomain);
    $token = trim((string) $token);
    $path = trim((string) $path);
    $method = strtoupper(trim((string) $method));
    $inicio = microtime(true);

    if ($subdomain === '' || $token === '' || $path === '' || !in_array($method, ['POST', 'PATCH'], true)) {
        return ['ok' => false, 'mensaje' => 'Parámetros incompletos para enviar la petición a Kommo.'];
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'mensaje' => 'No se pudo serializar el mensaje para Kommo.'];
    }

    $urls = [
        'https://' . $subdomain . '.kommo.com' . $path,
        'https://' . $subdomain . '.amocrm.com' . $path,
    ];

    $resultado = false;
    $httpCode = 0;
    $curlError = '';
    $urlUsada = $urls[0];

    foreach ($urls as $url) {
        $urlUsada = $url;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json, application/problem+json',
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resultado = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError === '' && $httpCode > 0) {
            break;
        }
    }

    $duracionMs = (int) round((microtime(true) - $inicio) * 1000);
    error_log('Kommo API: ' . $method . ' ' . $path . ' -> HTTP ' . $httpCode . ' en ' . $duracionMs . 'ms', 0);

    if ($curlError !== '') {
        if ($logger) {
            $logger->warning('Kommo API: error de red en petición de envío manual', [
                'url' => $urlUsada,
                'error' => $curlError,
            ]);
        }
        return ['ok' => false, 'mensaje' => 'Error de conexión con Kommo: ' . $curlError];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        if ($logger) {
            $logger->warning('Kommo API: respuesta no válida al enviar mensaje manual', [
                'url' => $urlUsada,
                'http_code' => $httpCode,
                'body' => is_string($resultado) ? mb_substr($resultado, 0, 800) : '',
            ]);
        }
        return ['ok' => false, 'mensaje' => 'Kommo devolvió HTTP ' . $httpCode . ' al enviar la respuesta.'];
    }

    $data = [];
    if (is_string($resultado) && trim($resultado) !== '') {
        $decodificado = json_decode($resultado, true);
        if (is_array($decodificado)) {
            $data = $decodificado;
        }
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'data' => $data,
    ];
}

function enviarRespuestaManualLeadKommo($subdomain, $token, $leadId, $texto, $fieldId, $botId, $logger = null)
{
    $leadId = trim((string) $leadId);
    $texto = sanitizarTextoRespuestaManualKommo($texto);
    $fieldId = (int) $fieldId;
    $botId = (int) $botId;

    if ($leadId === '' || $texto === '' || $fieldId <= 0 || $botId <= 0) {
        return ['ok' => false, 'mensaje' => 'Faltan datos para responder manualmente por Kommo.'];
    }

    $actualizacionLead = peticionApiMutacionKommo(
        $subdomain,
        $token,
        '/api/v4/leads/' . rawurlencode($leadId),
        'PATCH',
        [
            'custom_fields_values' => [
                [
                    'field_id' => $fieldId,
                    'values' => [
                        ['value' => $texto]
                    ]
                ]
            ]
        ],
        $logger
    );

    if (empty($actualizacionLead['ok'])) {
        return $actualizacionLead;
    }

    $ejecucionBot = peticionApiMutacionKommo(
        $subdomain,
        $token,
        '/api/v4/bots/' . rawurlencode((string) $botId) . '/run',
        'POST',
        [
            'entity_type' => 'leads',
            'entity_id' => (int) $leadId,
        ],
        $logger
    );

    if (empty($ejecucionBot['ok'])) {
        return $ejecucionBot;
    }

    return [
        'ok' => true,
        'mensaje' => 'Respuesta enviada correctamente a Kommo.',
        'lead_id' => $leadId,
        'texto' => $texto,
    ];
}

function compactarPayloadKommoParaGuardarKommo($payload)
{
    if (!is_array($payload)) {
        return $payload;
    }

    $compacto = array();

    if (!empty($payload['account']) && is_array($payload['account'])) {
        $compacto['account'] = recortarPayloadKommoRecursivoKommo($payload['account'], 0);
    }

    foreach (array('message', 'talk', 'leads', 'contacts', 'unsorted') as $tipo) {
        if (empty($payload[$tipo]) || !is_array($payload[$tipo])) {
            continue;
        }

        $compacto[$tipo] = array();
        foreach (array('add', 'update', 'note') as $accion) {
            if (!empty($payload[$tipo][$accion][0])) {
                $compacto[$tipo][$accion] = array(
                    recortarPayloadKommoRecursivoKommo($payload[$tipo][$accion][0], 0)
                );
            }
        }
    }

    if (empty($compacto)) {
        $compacto = recortarPayloadKommoRecursivoKommo($payload, 0);
    }

    return $compacto;
}

function recortarPayloadKommoRecursivoKommo($valor, $nivel = 0)
{
    if ($nivel > 5) {
        return '[recortado]';
    }

    if (is_array($valor)) {
        $resultado = array();
        $contador = 0;
        foreach ($valor as $clave => $item) {
            $contador++;
            if ($contador > 30) {
                $resultado['_truncated'] = true;
                break;
            }
            $resultado[$clave] = recortarPayloadKommoRecursivoKommo($item, $nivel + 1);
        }
        return $resultado;
    }

    if (is_string($valor)) {
        if (function_exists('mb_strlen') && mb_strlen($valor) > 500) {
            return mb_substr($valor, 0, 500) . '…';
        }
        if (strlen($valor) > 500) {
            return substr($valor, 0, 500) . '...';
        }
    }

    return $valor;
}

// Función auxiliar para extraer campos personalizados
function extraerCampoPersonalizado($data, $nombre, $code = null) {
    if (!isset($data['custom_fields'])) return '';
    foreach ($data['custom_fields'] as $field) {
        if ($code && isset($field['code']) && $field['code'] === $code) {
            return limpiarTextoVisibleKommo($field['values'][0]['value'] ?? '');
        }
        if (isset($field['name']) && $field['name'] === $nombre) {
            return limpiarTextoVisibleKommo($field['values'][0]['value'] ?? '');
        }
    }
    return '';
}

function resolverKommoIdPrincipal($json, $webhook = null)
{
    $eventoLead = obtenerPrimerEventoKommo($json, 'leads');
    if (!empty($eventoLead['id'])) {
        return (string) $eventoLead['id'];
    }

    $eventoContact = obtenerPrimerEventoKommo($json, 'contacts');
    if (!empty($eventoContact['linked_leads_id']) && is_array($eventoContact['linked_leads_id'])) {
        $linkedIds = array_keys($eventoContact['linked_leads_id']);
        if (!empty($linkedIds[0])) {
            return (string) $linkedIds[0];
        }
    }

    $eventoMessage = obtenerPrimerEventoKommo($json, 'message');
    if (!empty($eventoMessage['entity_type']) && $eventoMessage['entity_type'] === 'lead') {
        if (!empty($eventoMessage['entity_id'])) {
            return (string) $eventoMessage['entity_id'];
        }
        if (!empty($eventoMessage['element_id'])) {
            return (string) $eventoMessage['element_id'];
        }
    }

    $eventoTalk = obtenerPrimerEventoKommo($json, 'talk');
    if (!empty($eventoTalk['entity_type']) && $eventoTalk['entity_type'] === 'lead' && !empty($eventoTalk['entity_id'])) {
        return (string) $eventoTalk['entity_id'];
    }

    if ($webhook && method_exists($webhook, 'getKommoId')) {
        return (string) $webhook->getKommoId();
    }

    return '';
}

function obtenerWebhooksKommoPorClave($doctrine, $groupKey)
{
    $repo = $doctrine->getRepository('AppBundle:KommoWebhook');
    $todos = $repo->findBy([], ['fecha' => 'DESC']);
    $coincidentes = [];

    foreach ($todos as $webhook) {
        $json = $webhook->getJsonRecibido();
        if (is_string($json)) {
            $jsonDecodificado = json_decode($json, true);
            $json = is_array($jsonDecodificado) ? $jsonDecodificado : [];
        } elseif ($json instanceof \stdClass) {
            $json = json_decode(json_encode($json), true);
        } elseif (!is_array($json)) {
            $json = [];
        }

        $kommoId = resolverKommoIdPrincipal($json, $webhook);
        $claveActual = $kommoId !== '' ? 'kommo_' . $kommoId : 'registro_' . $webhook->getId();

        if ($claveActual === $groupKey) {
            $coincidentes[] = $webhook;
        }
    }

    return $coincidentes;
}

function esNombreGenericoKommo($nombre)
{
    $nombre = trim((string) $nombre);
    if ($nombre === '') {
        return true;
    }

    $nombreLower = mb_strtolower($nombre);
    return strpos($nombreLower, 'facebook') !== false
        || strpos($nombreLower, 'instagram') !== false
        || strpos($nombreLower, 'lead') !== false;
}

function prioridadEstadoKommo($estado)
{
    $estado = strtolower(trim((string) $estado));

    if ($estado === 'error') {
        return 3;
    }
    if ($estado === 'procesado') {
        return 2;
    }

    return 1;
}

function normalizarTelefonoKommo($telefono)
{
    $telefono = preg_replace('/\D+/', '', (string) $telefono);
    if (strlen($telefono) > 9) {
        $telefono = substr($telefono, -9);
    }
    return $telefono;
}

function limpiarTextoVisibleKommo($texto)
{
    if (is_array($texto) || $texto instanceof \stdClass) {
        return '';
    }

    $texto = trim((string) $texto);
    if ($texto === '') {
        return '';
    }

    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', $texto);

    return trim((string) $texto);
}

function esEmailRealKommo($email)
{
    $email = trim((string) $email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $emailLower = function_exists('mb_strtolower')
        ? mb_strtolower($email, 'UTF-8')
        : strtolower($email);

    if (substr($emailLower, -14) === '@local.invalid') {
        return false;
    }

    return true;
}

function normalizarEmailKommo($email)
{
    $email = trim((string) $email);
    if (!esEmailRealKommo($email)) {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($email, 'UTF-8')
        : strtolower($email);
}

function crearClaveBloqueoKommo($kommoId = '', array $lead = [])
{
    $partes = [];

    if (trim((string) $kommoId) !== '') {
        $partes[] = 'kommo_' . trim((string) $kommoId);
    }

    $telefono = normalizarTelefonoKommo($lead['telefono'] ?? ($lead['telefono_contacto'] ?? ''));
    if ($telefono !== '') {
        $partes[] = 'tel_' . $telefono;
    }

    $email = normalizarEmailKommo($lead['email'] ?? ($lead['email_contacto'] ?? ''));
    if ($email !== '') {
        $partes[] = 'mail_' . md5($email);
    }

    if (empty($partes)) {
        $partes[] = 'kommo_general';
    }

    return implode('__', $partes);
}

function abrirBloqueoProcesadoKommo($clave)
{
    $clave = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $clave);
    $ruta = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kommo_' . $clave . '.lock';
    $handle = @fopen($ruta, 'c');

    if (!$handle) {
        return null;
    }

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        return null;
    }

    return $handle;
}

function liberarBloqueoProcesadoKommo($handle)
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function normalizarTextoKommo($texto)
{
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'
    ]);
    $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto);
    return trim((string) $texto);
}

function limitarTextoKommo($texto, $max = 250)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return '';
    }

    if (mb_strlen($texto, 'UTF-8') <= $max) {
        return $texto;
    }

    return rtrim(mb_substr($texto, 0, max(0, $max - 3), 'UTF-8')) . '...';
}

function fusionarCamposCustomKommo(array &$destino, array $data)
{
    if (empty($data['custom_fields']) || !is_array($data['custom_fields'])) {
        return;
    }

    foreach ($data['custom_fields'] as $field) {
        $nombre = trim((string) ($field['name'] ?? ($field['code'] ?? '')));
        if ($nombre === '') {
            continue;
        }

        $valores = [];
        if (!empty($field['values']) && is_array($field['values'])) {
            foreach ($field['values'] as $valor) {
                if (isset($valor['value']) && trim((string) $valor['value']) !== '') {
                    $valores[] = trim((string) $valor['value']);
                }
            }
        }

        $valorTexto = implode(', ', array_unique($valores));
        if ($valorTexto === '') {
            continue;
        }

        $destino[normalizarTextoKommo($nombre)] = $valorTexto;
        if (!empty($field['code'])) {
            $destino[normalizarTextoKommo($field['code'])] = $valorTexto;
        }
    }
}

function obtenerCampoCustomKommo(array $lead, array $aliases)
{
    if (empty($lead['custom_fields']) || !is_array($lead['custom_fields'])) {
        return '';
    }

    foreach ($aliases as $alias) {
        $clave = normalizarTextoKommo($alias);
        if (!empty($lead['custom_fields'][$clave])) {
            return trim((string) $lead['custom_fields'][$clave]);
        }
    }

    return '';
}

function inferirDatosLeadKommo(array $lead)
{
    $textoOriginal = trim((string) ($lead['observaciones'] ?? ''));
    $texto = normalizarTextoKommo($textoOriginal);
    $datos = [];

    if ($texto !== '') {
        if (strpos($texto, 'agrup') !== false || strpos($texto, 'deuda') !== false) {
            $datos['interes'] = 'Agrupación de deudas';
        } elseif (
            strpos($texto, 'ampliacion de hipoteca') !== false
            || strpos($texto, 'mejorar mi hipoteca') !== false
            || strpos($texto, 'hipoteca actual') !== false
            || strpos($texto, 'ampliar') !== false
        ) {
            $datos['interes'] = 'Cambio o mejora de hipoteca';
        } elseif (strpos($texto, 'compra') !== false || strpos($texto, 'compraventa') !== false || strpos($texto, 'vivienda') !== false) {
            $datos['interes'] = 'Compraventa';
        }

        if (
            strpos($texto, 'ya tengo') !== false
            || strpos($texto, 'ya la tengo') !== false
            || strpos($texto, 'me quedan') !== false
        ) {
            $datos['tiene_inmueble'] = 'Sí';
        }
    }

    if ($textoOriginal !== '') {
        if (preg_match('/ampli(?:ar|acion).*?a\s*([\d\.\,]+)\s*€/iu', $textoOriginal, $coincidencia)) {
            $datos['importe_hipoteca'] = trim($coincidencia[1]) . ' €';
        } elseif (preg_match_all('/([\d\.\,]+)\s*€/u', $textoOriginal, $coincidencias) && !empty($coincidencias[1])) {
            $ultimoImporte = end($coincidencias[1]);
            $datos['importe_hipoteca'] = trim((string) $ultimoImporte) . ' €';
        }
    }

    return $datos;
}

function esMensajeControlKommo($texto)
{
    $texto = normalizarTextoKommo($texto);
    $mensajesControl = [
        'comenzar',
        'continuar',
        'consulta',
        'otros temas',
        'escribir consulta'
    ];

    return in_array($texto, $mensajesControl, true);
}

function construirObservacionesLeadKommo(array $lead)
{
    $partes = [];

    if (!empty($lead['canal'])) {
        $partes[] = 'Campaña: ' . trim((string) $lead['canal']);
    }
    if (!empty($lead['kommo_id'])) {
        $partes[] = 'Lead Kommo: ' . trim((string) $lead['kommo_id']);
    }

    $partes = array_unique(array_filter($partes));
    return implode(' | ', $partes);
}

function resolverOpcionesOrigenKommo(array $lead)
{
    $texto = normalizarTextoKommo(($lead['canal'] ?? '') . ' ' . ($lead['origen_kommo'] ?? '') . ' ' . ($lead['observaciones'] ?? ''));
    $campos = [];

    $campos[673] = ['opcion_id' => 691];

    if (strpos($texto, 'whatsapp') !== false || strpos($texto, 'waba') !== false) {
        return $campos;
    }

    if (
        strpos($texto, 'facebook') !== false
        || preg_match('/\bfb\b/u', $texto)
        || strpos($texto, 'instagram') !== false
        || strpos($texto, 'tiktok') !== false
        || strpos($texto, 'linkedin') !== false
        || strpos($texto, 'youtube') !== false
        || strpos($texto, 'rrss') !== false
    ) {

        if (strpos($texto, 'facebook') !== false || preg_match('/\bfb\b/u', $texto)) {
            $campos[676] = ['opcion_id' => 674];
        } elseif (strpos($texto, 'instagram') !== false) {
            $campos[676] = ['opcion_id' => 675];
        } elseif (strpos($texto, 'tiktok') !== false) {
            $campos[676] = ['opcion_id' => 673];
        } elseif (strpos($texto, 'linkedin') !== false) {
            $campos[676] = ['opcion_id' => 676];
        } elseif (strpos($texto, 'youtube') !== false) {
            $campos[676] = ['opcion_id' => 677];
        }

        return $campos;
    }

    if (strpos($texto, 'google') !== false) {
        return $campos;
    }

    if (strpos($texto, 'web') !== false || strpos($texto, 'formulario') !== false || strpos($texto, 'landing') !== false) {
        return $campos;
    }

    return $campos;
}

function resolverOpcionesHipotecaKommo(array $lead, $interes = '')
{
    $texto = normalizarTextoKommo($interes . ' ' . ($lead['canal'] ?? '') . ' ' . ($lead['observaciones'] ?? ''));
    $campos = [];

    if (strpos($texto, 'agrup') !== false || strpos($texto, 'deuda') !== false) {
        $campos[179] = ['opcion_id' => 73];
    } elseif (
        strpos($texto, 'ampliacion') !== false
        || strpos($texto, 'cambio de hipoteca') !== false
        || strpos($texto, 'mejora de hipoteca') !== false
        || strpos($texto, 'hipoteca actual') !== false
    ) {
        $campos[179] = ['opcion_id' => 72];
    } elseif (strpos($texto, 'compra') !== false || strpos($texto, 'compraventa') !== false) {
        $campos[179] = ['opcion_id' => 71];
    }

    if (strpos($texto, 'mixto') !== false) {
        $campos[411] = ['opcion_id' => 202];
        $campos[189] = ['opcion_id' => 78];
    } elseif (strpos($texto, 'fijo') !== false) {
        $campos[411] = ['opcion_id' => 203];
        $campos[189] = ['opcion_id' => 76];
    } elseif (strpos($texto, 'variable') !== false) {
        $campos[411] = ['opcion_id' => 201];
        $campos[189] = ['opcion_id' => 77];
    }

    return $campos;
}

function resolverTipoEmpleoKommo($textoTrabajo)
{
    $texto = normalizarTextoKommo($textoTrabajo);
    if ($texto === '') {
        return null;
    }

    if (strpos($texto, 'autonom') !== false) {
        return 97;
    }
    if (strpos($texto, 'pension') !== false || strpos($texto, 'jubil') !== false) {
        return 98;
    }
    if (strpos($texto, 'mercantil') !== false || strpos($texto, 'empresa') !== false || strpos($texto, 'sociedad') !== false) {
        return 103;
    }

    return 102;
}

function construirAutorrellenoHitosKommo(array $lead)
{
    $nombreCompleto = trim((string) (!empty($lead['nombre_contacto']) ? $lead['nombre_contacto'] : ($lead['nombre'] ?? '')));
    list($nombre, $apellidos) = separarNombreCompletoKommo($nombreCompleto);

    $fechaLead = '';
    if (!empty($lead['fecha']) && $lead['fecha'] instanceof \DateTimeInterface) {
        $fechaLead = $lead['fecha']->format('d/m/Y');
    }

    $observaciones = construirObservacionesLeadKommo($lead);
    $inferidos = inferirDatosLeadKommo($lead);

    $trabajo = obtenerCampoCustomKommo($lead, ['trabajo o estado laboral', 'estado laboral', 'trabajo', 'ocupacion', 'profesion']);
    $interes = obtenerCampoCustomKommo($lead, ['interesado compraventa o cambio de hipoteca', 'compraventa o cambio de hipoteca', 'tipo de operacion', 'operacion']);
    $tieneInmueble = obtenerCampoCustomKommo($lead, ['tienes ya el inmueble', 'ya tienes el inmueble']);
    $valorInmueble = obtenerCampoCustomKommo($lead, ['valor del inmueble', 'importe compraventa', 'precio inmueble', 'precio de compra']);
    $ciudadInmueble = obtenerCampoCustomKommo($lead, ['en que ciudad esta el inmueble', 'ciudad inmueble', 'municipio inmueble']);
    $ahorro = obtenerCampoCustomKommo($lead, ['cuanto ahorro aportas', 'ahorro actual', 'ahorro', 'aportacion']);
    $importeHipoteca = obtenerCampoCustomKommo($lead, ['importe hipoteca', 'importe de hipoteca', 'importe solicitado']);
    $empresa = obtenerCampoCustomKommo($lead, ['nombre empresa', 'empresa']);
    $puesto = obtenerCampoCustomKommo($lead, ['puesto de trabajo', 'puesto']);
    $nomina = obtenerCampoCustomKommo($lead, ['nomina mensual neto', 'nomina mensual', 'nomina']);
    $ingresosAnuales = obtenerCampoCustomKommo($lead, ['ingresos netos anuales aproximados', 'ingresos netos anuales', 'ingresos anuales']);
    $ciudadResidencia = obtenerCampoCustomKommo($lead, ['en que ciudad resides actualmente', 'ciudad residencia', 'municipio']);

    if ($interes === '' && !empty($inferidos['interes'])) {
        $interes = $inferidos['interes'];
    }
    if ($tieneInmueble === '' && !empty($inferidos['tiene_inmueble'])) {
        $tieneInmueble = $inferidos['tiene_inmueble'];
    }
    if ($importeHipoteca === '' && !empty($inferidos['importe_hipoteca'])) {
        $importeHipoteca = $inferidos['importe_hipoteca'];
    }

    $campos = [
        688 => ['valor' => $fechaLead],
        693 => ['valor' => $nombre],
        694 => ['valor' => $apellidos],
        695 => ['valor' => $lead['telefono'] ?? ''],
        696 => ['valor' => $lead['email'] ?? ''],
        689 => ['valor' => $lead['provincia'] ?? ''],
        690 => ['valor' => $trabajo],
        702 => ['valor' => $interes],
        692 => ['valor' => $tieneInmueble],
        691 => ['valor' => $valorInmueble],
        697 => ['valor' => $ciudadInmueble],
        699 => ['valor' => $ahorro],
        700 => ['valor' => $observaciones],
        701 => ['valor' => 'Kommo'],
        405 => ['valor' => $importeHipoteca],
        191 => ['valor' => $observaciones],
        192 => ['valor' => $nombreCompleto],
        218 => ['valor' => $observaciones],
        234 => ['valor' => $observaciones],
        407 => ['valor' => $lead['email'] ?? ''],
        408 => ['valor' => $lead['telefono'] ?? ''],
        679 => ['valor' => $observaciones],
        704 => ['valor' => $lead['canal'] ?? ''],
        458 => ['valor' => $ciudadResidencia !== '' ? $ciudadResidencia : ($lead['provincia'] ?? '')],
        220 => ['valor' => $empresa],
        222 => ['valor' => $puesto],
        225 => ['valor' => $nomina],
        228 => ['valor' => $ingresosAnuales],
    ];

    error_log('Campos antes de resolver opciones: '.print_r($campos['225'], true));

    $tipoEmpleo = resolverTipoEmpleoKommo($trabajo);
    if ($tipoEmpleo !== null) {
        $campos[193] = ['opcion_id' => $tipoEmpleo];
    }

    foreach (resolverOpcionesOrigenKommo($lead) as $campoId => $configuracion) {
        $campos[$campoId] = $configuracion;
    }
    foreach (resolverOpcionesHipotecaKommo($lead, $interes) as $campoId => $configuracion) {
        $campos[$campoId] = $configuracion;
    }
    error_log('Campos después de resolver opciones: ');
    return array_filter($campos, function ($configuracion) {
        return (!empty($configuracion['opcion_id']))
            || (array_key_exists('valor', $configuracion) && trim((string) $configuracion['valor']) !== '');
    });
}

function aplicarAutorrellenoCampoHitoKommo($doctrine, $campoHitoExpediente, array $configuracion)
{
    if (array_key_exists('valor', $configuracion) && method_exists($campoHitoExpediente, 'setValor')) {
        $campoHitoExpediente->setValor(limitarTextoKommo($configuracion['valor'], 250));
    }

    if (!empty($configuracion['opcion_id']) && method_exists($campoHitoExpediente, 'setIdOpcionesCampo')) {
        $opcion = $doctrine->getRepository('AppBundle:OpcionesCampo')->find($configuracion['opcion_id']);
        if ($opcion) {
            $campoHitoExpediente->setIdOpcionesCampo($opcion);
        }
    }
}

function buscarClienteKommo($doctrine, array $lead)
{
    $repositorioUsuarios = $doctrine->getRepository('AppBundle:Usuario');
    $clientes = $repositorioUsuarios->findBy(['role' => 'ROLE_CLIENTE'], ['idUsuario' => 'ASC']);

    $telefonoBuscado = normalizarTelefonoKommo($lead['telefono'] ?? ($lead['telefono_contacto'] ?? ''));
    $emailBuscado = normalizarEmailKommo($lead['email'] ?? ($lead['email_contacto'] ?? ''));
    $nombreBuscado = normalizarTextoKommo(($lead['nombre_contacto'] ?? '') !== '' ? $lead['nombre_contacto'] : ($lead['nombre'] ?? ''));

    if ($telefonoBuscado !== '') {
        foreach ($clientes as $cliente) {
            $telefonosCliente = [];
            if (method_exists($cliente, 'getTelefonoMovil')) {
                $telefonosCliente[] = $cliente->getTelefonoMovil();
            }
            if (method_exists($cliente, 'getTelefono')) {
                $telefonosCliente[] = $cliente->getTelefono();
            }

            foreach ($telefonosCliente as $telefonoCliente) {
                if (normalizarTelefonoKommo($telefonoCliente) === $telefonoBuscado) {
                    return $cliente;
                }
            }
        }
    }

    if ($emailBuscado !== '') {
        foreach ($clientes as $cliente) {
            if (normalizarEmailKommo($cliente->getEmail()) === $emailBuscado) {
                return $cliente;
            }
        }
    }

    if ($nombreBuscado !== '') {
        foreach ($clientes as $cliente) {
            $nombreCliente = normalizarTextoKommo((string) ($cliente->getUsername() . ' ' . $cliente->getApellidos()));
            if ($nombreCliente !== '' && $nombreCliente === $nombreBuscado) {
                return $cliente;
            }
        }
    }

    return null;
}

function obtenerLeadKommoPorClave($doctrine, $groupKey)
{
    $webhooks = obtenerWebhooksKommoPorClave($doctrine, $groupKey);
    $kommoId = '';

    if (empty($webhooks)) {
        return null;
    }

    $primerJson = $webhooks[0]->getJsonRecibido();
    if (is_string($primerJson)) {
        $jsonDecodificado = json_decode($primerJson, true);
        $primerJson = is_array($jsonDecodificado) ? $jsonDecodificado : [];
    } elseif ($primerJson instanceof \stdClass) {
        $primerJson = json_decode(json_encode($primerJson), true);
    } elseif (!is_array($primerJson)) {
        $primerJson = [];
    }
    $kommoId = resolverKommoIdPrincipal($primerJson, $webhooks[0]);

    $lead = [
        'id' => $webhooks[0]->getId(),
        'group_key' => $groupKey,
        'kommo_id' => $kommoId,
        'fecha' => $webhooks[0]->getFecha(),
        'nombre' => '',
        'nombre_contacto' => '',
        'telefono' => '',
        'telefono_contacto' => '',
        'email' => '',
        'email_contacto' => '',
        'provincia' => '',
        'canal' => '',
        'estado' => $webhooks[0]->getEstado(),
        'custom_fields' => [],
        'mensajes' => [],
        'observaciones' => '',
        'origen_kommo' => '',
    ];

    foreach ($webhooks as $webhook) {
        $json = $webhook->getJsonRecibido();
        if (is_string($json)) {
            $jsonDecodificado = json_decode($json, true);
            $json = is_array($jsonDecodificado) ? $jsonDecodificado : [];
        } elseif ($json instanceof \stdClass) {
            $json = json_decode(json_encode($json), true);
        } elseif (!is_array($json)) {
            $json = [];
        }

        $leadData = obtenerPrimerEventoKommo($json, 'leads');
        if (!empty($leadData)) {
            fusionarCamposCustomKommo($lead['custom_fields'], $leadData);

            if ($lead['nombre'] === '' && !empty($leadData['name'])) {
                $lead['nombre'] = limpiarTextoVisibleKommo($leadData['name']);
            }
            if ($lead['provincia'] === '') {
                $lead['provincia'] = extraerCampoPersonalizado($leadData, 'Provincia');
            }
            if ($lead['canal'] === '' && isset($leadData['tags'][0]['name'])) {
                $lead['canal'] = $leadData['tags'][0]['name'];
            }
        }

        $contact = obtenerPrimerEventoKommo($json, 'contacts');
        if (!empty($contact)) {
            fusionarCamposCustomKommo($lead['custom_fields'], $contact);

            $telefono = extraerCampoPersonalizado($contact, 'Teléfono', 'PHONE');
            $email = extraerCampoPersonalizado($contact, 'Correo', 'EMAIL');
            $lead['nombre_contacto'] = $lead['nombre_contacto'] ?: limpiarTextoVisibleKommo($contact['name'] ?? '');
            $lead['telefono_contacto'] = $lead['telefono_contacto'] ?: $telefono;
            $lead['email_contacto'] = $lead['email_contacto'] ?: $email;

            if ($lead['telefono'] === '' && $telefono !== '') {
                $lead['telefono'] = $telefono;
            }
            if ($lead['email'] === '' && $email !== '') {
                $lead['email'] = $email;
            }
            if (esNombreGenericoKommo($lead['nombre']) && !empty($contact['name'])) {
                $lead['nombre'] = $contact['name'];
            }
        }

        $webhookType = method_exists($webhook, 'getWebhookType') ? $webhook->getWebhookType() : '';
        $eventoKommo = obtenerPrimerEventoKommo($json, $webhookType);
        $textoMensaje = obtenerTextoMensajeKommo($eventoKommo, $json);

        $autorNombre = trim((string) ($eventoKommo['author']['name'] ?? ''));
        if ($autorNombre !== '') {
            if ($lead['nombre_contacto'] === '') {
                $lead['nombre_contacto'] = $autorNombre;
            }
            if ($lead['nombre'] === '' || esNombreGenericoKommo($lead['nombre'])) {
                $lead['nombre'] = $autorNombre;
            }
        }

        if ($textoMensaje !== '' && !esMensajeControlKommo($textoMensaje)) {
            $lead['mensajes'][] = $textoMensaje;
        }
        if ($lead['origen_kommo'] === '' && !empty($eventoKommo['origin'])) {
            $lead['origen_kommo'] = trim((string) $eventoKommo['origin']);
        }
    }

    $lead['mensajes'] = array_values(array_unique(array_filter($lead['mensajes'])));
    $lead['observaciones'] = construirObservacionesLeadKommo($lead);

    return $lead;
}

function separarNombreCompletoKommo($nombreCompleto)
{
    $nombreCompleto = limpiarTextoVisibleKommo($nombreCompleto);
    if ($nombreCompleto === '') {
        return ['Cliente', 'Kommo'];
    }

    $partes = preg_split('/\s+/', $nombreCompleto, 2);
    return [$partes[0], $partes[1] ?? ''];
}

function crearClienteDesdeLeadKommo(array $lead)
{
    $nombreBase = !empty($lead['nombre_contacto']) ? $lead['nombre_contacto'] : ($lead['nombre'] ?? '');
    list($nombre, $apellidos) = separarNombreCompletoKommo($nombreBase);

    $email = trim((string) (!empty($lead['email_contacto']) ? $lead['email_contacto'] : ($lead['email'] ?? '')));
    if (!esEmailRealKommo($email)) {
        $email = '';
    }

    $telefono = !empty($lead['telefono_contacto']) ? $lead['telefono_contacto'] : ($lead['telefono'] ?? '');

    return (new \AppBundle\Entity\Usuario())
        ->setUsername($nombre)
        ->setApellidos($apellidos)
        ->setEmail($email)
        ->setTelefonoMovil($telefono)
        ->setProvincia($lead['provincia'] ?? '')
        ->setEmpresa('Kommo')
        ->setRole('ROLE_CLIENTE')
        ->setPassword(password_hash(uniqid('kommo_', true), PASSWORD_BCRYPT))
        ->setEstado(true)
        ->setPoliticaPrivacidad(false);
}

function leadKommoTieneDatosMinimosParaCliente(array $lead)
{
    $nombre = trim((string) (!empty($lead['nombre_contacto']) ? $lead['nombre_contacto'] : ($lead['nombre'] ?? '')));
    $telefono = preg_replace('/\D+/', '', (string) (!empty($lead['telefono_contacto']) ? $lead['telefono_contacto'] : ($lead['telefono'] ?? '')));
    $email = trim((string) (!empty($lead['email_contacto']) ? $lead['email_contacto'] : ($lead['email'] ?? '')));

    $nombreValido = $nombre !== '' && !esNombreGenericoKommo($nombre);
    $telefonoValido = is_string($telefono) && strlen($telefono) >= 6;
    $emailValido = esEmailRealKommo($email);

    return $nombreValido && ($telefonoValido || $emailValido);
}

function leadKommoNecesitaDatosContacto(array $lead)
{
    $telefono = trim((string) ($lead['telefono_contacto'] ?? ($lead['telefono'] ?? '')));
    $email = trim((string) ($lead['email_contacto'] ?? ($lead['email'] ?? '')));
    $nombre = trim((string) ($lead['nombre_contacto'] ?? ''));

    return $telefono === '' || $email === '' || $nombre === '';
}

function resolverWebhookTypeKommo(array $payload)
{
    foreach (['message', 'talk', 'leads', 'contacts', 'unsorted'] as $tipo) {
        if (isset($payload[$tipo])) {
            return $tipo;
        }
    }

    if (!empty($payload['webhook_type'])) {
        return (string) $payload['webhook_type'];
    }

    if (count($payload) === 1) {
        return (string) array_key_first($payload);
    }

    return null;
}

function debeAutoProcesarWebhookKommo($webhookType, array $payload = [])
{
    $webhookType = strtolower(trim((string) $webhookType));

    if (in_array($webhookType, ['leads', 'contacts', 'unsorted'], true)) {
        return true;
    }

    if ($webhookType === 'talk') {
        return false;
    }

    if ($webhookType === 'message') {
        $evento = obtenerPrimerEventoKommo($payload, 'message');
        $texto = obtenerTextoMensajeKommo($evento, $payload);

        if ($texto === '' || esMensajeControlKommo($texto)) {
            return false;
        }

        return true;
    }

    return !empty($payload['leads']) || !empty($payload['contacts']) || !empty($payload['unsorted']);
}

function obtenerMotivoProcesadoWebhookKommo($webhookType, array $payload = [])
{
    $webhookType = strtolower(trim((string) $webhookType));

    if (in_array($webhookType, ['leads', 'contacts', 'unsorted'], true)) {
        return 'procesar_evento_' . $webhookType;
    }

    if ($webhookType === 'talk') {
        return 'ignorado_talk_estado';
    }

    if ($webhookType === 'message') {
        $evento = obtenerPrimerEventoKommo($payload, 'message');
        $texto = obtenerTextoMensajeKommo($evento, $payload);

        if ($texto === '') {
            return 'ignorado_mensaje_vacio';
        }

        if (esMensajeControlKommo($texto)) {
            return 'ignorado_mensaje_control:' . limitarTextoKommo($texto, 80);
        }

        return 'procesar_mensaje_util:' . limitarTextoKommo($texto, 80);
    }

    return 'procesar_por_payload_compuesto';
}

function resolverIdentificadorEventoExternoKommo(array $payload)
{
    if (!empty($payload['message']['add'][0]['id'])) {
        return 'message:' . (string) $payload['message']['add'][0]['id'];
    }
    if (!empty($payload['talk']['add'][0]['id'])) {
        return 'talk:' . (string) $payload['talk']['add'][0]['id'];
    }
    if (!empty($payload['talk']['add'][0]['talk_id'])) {
        return 'talk:' . (string) $payload['talk']['add'][0]['talk_id'];
    }
    if (!empty($payload['leads']['add'][0]['id'])) {
        return 'lead:' . (string) $payload['leads']['add'][0]['id'];
    }
    if (!empty($payload['contacts']['add'][0]['id'])) {
        return 'contact:' . (string) $payload['contacts']['add'][0]['id'];
    }
    if (!empty($payload['unsorted']['add'][0]['uid'])) {
        return 'unsorted:' . (string) $payload['unsorted']['add'][0]['uid'];
    }

    return '';
}

function crearFechaMensajeKommo($timestamp = null)
{
    $fecha = new \DateTime();
    if ($timestamp !== null && $timestamp !== '' && is_numeric($timestamp)) {
        $fecha->setTimestamp((int) $timestamp);
    }

    return $fecha;
}

function guardarMensajeExpedienteKommo($doctrine, $managerEntidad, $expediente = null, array $lead = [], array $payload = [], $logger = null, $kommoWebhook = null)
{
    $webhookType = strtolower(trim((string) resolverWebhookTypeKommo($payload)));
    if ($webhookType !== 'message') {
        return null;
    }

    $evento = obtenerPrimerEventoKommo($payload, 'message');
    $textoMensaje = obtenerTextoMensajeKommo($evento, $payload);
    if ($textoMensaje === '' || esMensajeControlKommo($textoMensaje)) {
        return null;
    }

    if (!class_exists('\AppBundle\Entity\ExpedienteChatMensaje')) {
        return null;
    }

    try {
        $repo = $doctrine->getRepository('AppBundle:ExpedienteChatMensaje');
    } catch (\Throwable $e) {
        if ($logger) {
            $logger->warning('Kommo chat: entidad o mapeo no disponible todavía', ['error' => $e->getMessage()]);
        }
        return null;
    }

    $externalMessageId = resolverIdentificadorEventoExternoKommo($payload);
    if ($externalMessageId === '') {
        $externalMessageId = 'message:' . md5(json_encode(compactarPayloadKommoParaGuardarKommo($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $mensajeChat = null;

    try {
        if ($externalMessageId !== '') {
            $mensajeChat = $repo->findOneBy([
                'proveedor' => 'kommo',
                'externalMessageId' => $externalMessageId,
            ]);
        }
    } catch (\Throwable $e) {
        if ($logger) {
            $logger->warning('Kommo chat: la tabla aún no está disponible para consultar', ['error' => $e->getMessage()]);
        }
        return null;
    }

    if (!$mensajeChat) {
        $mensajeChat = new \AppBundle\Entity\ExpedienteChatMensaje();
    }

    $telefono = trim((string) ($lead['telefono_contacto'] ?? ($lead['telefono'] ?? '')));
    $fechaMensaje = crearFechaMensajeKommo($evento['created_at'] ?? null);
    $payloadJson = json_encode(compactarPayloadKommoParaGuardarKommo($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $mensajeChat
        ->setProveedor('kommo')
        ->setExternalMessageId($externalMessageId)
        ->setKommoLeadId((string) ($lead['kommo_id'] ?? ''))
        ->setKommoContactId((string) ($evento['contact_id'] ?? ''))
        ->setTalkId((string) ($evento['talk_id'] ?? ''))
        ->setChatId((string) ($evento['chat_id'] ?? ''))
        ->setDireccion('entrante')
        ->setAutorNombre(obtenerAutorKommo($evento))
        ->setAutorTipo(limpiarTextoVisibleKommo($evento['author']['type'] ?? 'externo'))
        ->setTelefono($telefono)
        ->setMensaje(limitarTextoKommo($textoMensaje, 5000))
        ->setPayloadJson($payloadJson !== false ? $payloadJson : '')
        ->setEstado($expediente ? 'vinculado' : 'pendiente')
        ->setLeido(false)
        ->setFechaMensaje($fechaMensaje)
        ->setFechaActualizacion(new \DateTime());

    if ($expediente) {
        $mensajeChat->setIdExpediente($expediente);
    }

    if ($kommoWebhook && method_exists($mensajeChat, 'setIdKommoWebhook')) {
        $mensajeChat->setIdKommoWebhook($kommoWebhook);
    }

    $managerEntidad->persist($mensajeChat);

    return $mensajeChat;
}

function vincularMensajesPendientesExpedienteKommo($doctrine, $managerEntidad, $expediente, array $lead = [])
{
    if (!$expediente || !class_exists('\AppBundle\Entity\ExpedienteChatMensaje')) {
        return 0;
    }

    try {
        $repo = $doctrine->getRepository('AppBundle:ExpedienteChatMensaje');
    } catch (\Throwable $e) {
        return 0;
    }

    $pendientes = [];
    $kommoId = trim((string) ($lead['kommo_id'] ?? ''));
    $telefono = trim((string) ($lead['telefono_contacto'] ?? ($lead['telefono'] ?? '')));

    if ($kommoId !== '') {
        $pendientes = $repo->findBy([
            'idExpediente' => null,
            'proveedor' => 'kommo',
            'kommoLeadId' => $kommoId,
        ], ['fechaMensaje' => 'ASC']);
    } elseif ($telefono !== '') {
        $pendientes = $repo->findBy([
            'idExpediente' => null,
            'proveedor' => 'kommo',
            'telefono' => $telefono,
        ], ['fechaMensaje' => 'ASC']);
    }

    $contador = 0;
    foreach ($pendientes as $mensajePendiente) {
        $mensajePendiente->setIdExpediente($expediente);
        $mensajePendiente->setEstado('vinculado');
        $mensajePendiente->setFechaActualizacion(new \DateTime());
        $managerEntidad->persist($mensajePendiente);
        $contador++;
    }

    return $contador;
}

function esWebhookKommoDuplicado($doctrine, $webhookType, $kommoId, array $payload)
{
    $identificadorExterno = resolverIdentificadorEventoExternoKommo($payload);
    $payloadCompactoActual = json_encode(compactarPayloadKommoParaGuardarKommo($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $repo = $doctrine->getRepository('AppBundle:KommoWebhook');
    $criterios = [];
    if ($webhookType !== null && $webhookType !== '') {
        $criterios['webhookType'] = $webhookType;
    }
    if ($kommoId !== null && $kommoId !== '') {
        $criterios['kommoId'] = $kommoId;
    }

    $recientes = $repo->findBy($criterios, ['fecha' => 'DESC'], 50);
    foreach ($recientes as $webhook) {
        $json = $webhook->getJsonRecibido();
        if (is_string($json)) {
            $jsonDecodificado = json_decode($json, true);
            $json = is_array($jsonDecodificado) ? $jsonDecodificado : [];
        } elseif ($json instanceof \stdClass) {
            $json = json_decode(json_encode($json), true);
        } elseif (!is_array($json)) {
            $json = [];
        }

        if ($identificadorExterno !== '' && resolverIdentificadorEventoExternoKommo($json) === $identificadorExterno) {
            return true;
        }

        $payloadCompactoGuardado = json_encode(compactarPayloadKommoParaGuardarKommo($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadCompactoActual !== false && $payloadCompactoGuardado === $payloadCompactoActual) {
            return true;
        }
    }

    return false;
}

function resolverKommoLeadIdDesdePayloadKommo(array $payload)
{
    if (!empty($payload['kommo_lead_id'])) {
        return (string) $payload['kommo_lead_id'];
    }

    $kommoId = resolverKommoIdPrincipal($payload);
    if ($kommoId !== '') {
        return (string) $kommoId;
    }

    foreach (['message', 'talk', 'leads', 'contacts', 'unsorted'] as $tipo) {
        if (!isset($payload[$tipo]) || !is_array($payload[$tipo])) {
            continue;
        }

        foreach (['add', 'update'] as $accion) {
            if (empty($payload[$tipo][$accion][0]) || !is_array($payload[$tipo][$accion][0])) {
                continue;
            }

            $evento = $payload[$tipo][$accion][0];
            if (!empty($evento['linked_leads_id']) && is_array($evento['linked_leads_id'])) {
                $linkedIds = array_keys($evento['linked_leads_id']);
                if (!empty($linkedIds[0])) {
                    return (string) $linkedIds[0];
                }
            }

            foreach (['entity_id', 'element_id', 'id', 'lead_id', 'talk_id'] as $idKey) {
                if (!empty($evento[$idKey])) {
                    return (string) $evento[$idKey];
                }
            }
        }
    }

    return null;
}

function actualizarExpedienteDesdeLeadKommoAutomatico($doctrine, $managerEntidad, $expediente, array $lead)
{
    $resumen = [];
    if (!empty($lead['kommo_id'])) {
        $resumen[] = 'Kommo ID: ' . $lead['kommo_id'];
    }
    if (!empty($lead['canal'])) {
        $resumen[] = 'Canal: ' . $lead['canal'];
    }
    if (!empty($lead['provincia'])) {
        $resumen[] = 'Provincia: ' . $lead['provincia'];
    }
    if (!empty($lead['telefono'])) {
        $resumen[] = 'Tel: ' . $lead['telefono'];
    }
    if (!empty($lead['email'])) {
        $resumen[] = 'Email: ' . $lead['email'];
    }
    if (!empty($lead['observaciones'])) {
        $resumen[] = 'Observaciones: ' . limitarTextoKommo($lead['observaciones'], 250);
    }

    if (method_exists($expediente, 'setTexto')) {
        $expediente->setTexto(limitarTextoKommo(implode(' | ', $resumen), 250));
    }
    if (method_exists($expediente, 'setVivienda') && !empty($lead['nombre'])) {
        $expediente->setVivienda(limitarTextoKommo('Lead Kommo - ' . $lead['nombre'], 120));
    }
    if (method_exists($expediente, 'setFechaModificacion')) {
        $expediente->setFechaModificacion(new \DateTime());
    }
    $managerEntidad->persist($expediente);

    $autorrellenoCampos = construirAutorrellenoHitosKommo($lead);
    if (empty($autorrellenoCampos)) {
        return;
    }

    $idsActualizarSiempre = [191, 218, 234, 679, 700, 701, 704];
    $camposExpediente = $doctrine->getRepository('AppBundle:CampoHitoExpediente')->findBy([
        'idExpediente' => $expediente
    ]);

    foreach ($camposExpediente as $campoHitoExpediente) {
        if (!method_exists($campoHitoExpediente, 'getIdCampoHito') || !$campoHitoExpediente->getIdCampoHito()) {
            continue;
        }

        $idCampoHito = $campoHitoExpediente->getIdCampoHito()->getIdCampoHito();
        if (!isset($autorrellenoCampos[$idCampoHito])) {
            continue;
        }

        $valorActual = method_exists($campoHitoExpediente, 'getValor') ? trim((string) $campoHitoExpediente->getValor()) : '';
        if ($valorActual === '' || in_array($idCampoHito, $idsActualizarSiempre, true)) {
            aplicarAutorrellenoCampoHitoKommo($doctrine, $campoHitoExpediente, $autorrellenoCampos[$idCampoHito]);
            $managerEntidad->persist($campoHitoExpediente);
        }
    }
}

function obtenerSubdominioKommoDesdePayload(array $payload)
{
    return !empty($payload['account']['subdomain']) ? trim((string) $payload['account']['subdomain']) : '';
}

function obtenerVariableEntornoKommo(array $variables)
{
    foreach ($variables as $variable) {
        $valor = getenv($variable);
        if (is_string($valor) && trim($valor) !== '') {
            return trim($valor);
        }

        if (isset($_SERVER[$variable]) && trim((string) $_SERVER[$variable]) !== '') {
            return trim((string) $_SERVER[$variable]);
        }

        if (isset($_ENV[$variable]) && trim((string) $_ENV[$variable]) !== '') {
            return trim((string) $_ENV[$variable]);
        }
    }

    return '';
}

function obtenerTokenApiKommo()
{
    // Entorno de pruebas: pega aquí un access token válido de Kommo.
    $placeholderToken = 'PEGA_AQUI_TU_ACCESS_TOKEN_DE_KOMMO';
    $tokenPruebas = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjFkZjg2NzAxZjQ4MjM1NTI1MWZjY2YwNDE1MmRjNjlmZjliZTU1NDkxNDRiODgxYTVmMGZmODUzNDY4Njk2Mjk1MWI0NzRiYWZkZWJiNjhhIn0.eyJhdWQiOiJiODdkYTRjYi1lMGM1LTQ5YmUtYTUyYy1iM2FkMTUzM2RmNDQiLCJqdGkiOiIxZGY4NjcwMWY0ODIzNTUyNTFmY2NmMDQxNTJkYzY5ZmY5YmU1NTQ5MTQ0Yjg4MWE1ZjBmZjg1MzQ2ODY5NjI5NTFiNDc0YmFmZGViYjY4YSIsImlhdCI6MTc3NjMyMzIyNCwibmJmIjoxNzc2MzIzMjI0LCJleHAiOjE3OTg2NzUyMDAsInN1YiI6IjEwNzU3MTAzIiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMzQ3ODI3LCJiYXNlX2RvbWFpbiI6ImtvbW1vLmNvbSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJwdXNoX25vdGlmaWNhdGlvbnMiLCJmaWxlcyIsImNybSIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiMjhmYWFmYWYtNzU4NC00NGUyLWEyNmYtYWY4MjhlY2VkZTQ0IiwiYXBpX2RvbWFpbiI6ImFwaS1nLmtvbW1vLmNvbSJ9.ao1XvQMuOUnITIQrRQWd9gARLUusjei0m2f7ubZqvWJMqhpin35EqeeFpAaOZ_OZNjb_fphrqfdffHXcg_aitTGAkSdhfqZ6Mq0z541EqEdUOdG6iaEHJJUQkElgpuh9OhanhVlCKLcpvkoRDj3gVoVMiFManjm5-yVHMKwtwfwlgr0LnheQz1MY-JqCnNXrP5ELSCvrfo0sUGouZ3iB-kiRawEvLhw9ycsocAtzPnkOiMtFIeTgpJpfYKo7Zq546RSB-IlUtq_Kjs1SP3iRpdXdN0b5wICwnoMia1Slf5FLq9OjvwmJ06MlppNM50UhLJg9vZhSNd7xXQrkaGb54w';

    if ($tokenPruebas !== '' && $tokenPruebas !== $placeholderToken) {
        return trim((string) $tokenPruebas);
    }

    return obtenerVariableEntornoKommo(['KOMMO_ACCESS_TOKEN', 'AMOCRM_ACCESS_TOKEN', 'KOMMO_LONG_LIVED_TOKEN']);
}

function obtenerConfigOauthKommo()
{
    return [
        'client_id' => obtenerVariableEntornoKommo(['KOMMO_CLIENT_ID', 'AMOCRM_CLIENT_ID']),
        'client_secret' => obtenerVariableEntornoKommo(['KOMMO_CLIENT_SECRET', 'AMOCRM_CLIENT_SECRET']),
        'refresh_token' => obtenerVariableEntornoKommo(['KOMMO_REFRESH_TOKEN', 'AMOCRM_REFRESH_TOKEN']),
        'redirect_uri' => obtenerVariableEntornoKommo(['KOMMO_REDIRECT_URI', 'AMOCRM_REDIRECT_URI']),
    ];
}

function obtenerDiagnosticoOauthKommo()
{
    $config = obtenerConfigOauthKommo();
    $faltantes = [];

    foreach ($config as $clave => $valor) {
        if (trim((string) $valor) === '') {
            $faltantes[] = $clave;
        }
    }

    return [
        'config' => $config,
        'faltantes' => $faltantes,
        'token_api_disponible' => obtenerTokenApiKommo() !== '',
    ];
}

function renovarTokenApiKommo($subdomain, $logger = null)
{
    $subdomain = trim((string) $subdomain);
    $oauth = obtenerDiagnosticoOauthKommo();
    $config = $oauth['config'];

    if ($subdomain === '' || $config['client_id'] === '' || $config['client_secret'] === '' || $config['refresh_token'] === '' || $config['redirect_uri'] === '') {
        error_log('Kommo OAuth: faltan credenciales para renovar el access token. subdomain=' . $subdomain . ' faltantes=' . implode(',', $oauth['faltantes']), 0);
        if ($logger) {
            $logger->warning('Kommo OAuth: configuración incompleta para renovar token', [
                'subdomain' => $subdomain,
                'faltantes' => $oauth['faltantes'],
            ]);
        }
        return '';
    }

    $payload = json_encode([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'refresh_token',
        'refresh_token' => $config['refresh_token'],
        'redirect_uri' => $config['redirect_uri'],
    ]);

    $urls = [
        'https://' . $subdomain . '.kommo.com/oauth2/access_token',
        'https://' . $subdomain . '.amocrm.com/oauth2/access_token',
    ];

    foreach ($urls as $url) {
        error_log('Kommo OAuth: intentando renovar token en ' . $url, 0);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resultado = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError !== '') {
            error_log('Kommo OAuth: curl error ' . $curlError, 0);
            continue;
        }

        $data = json_decode((string) $resultado, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($data['access_token'])) {
            putenv('KOMMO_ACCESS_TOKEN=' . $data['access_token']);
            $_ENV['KOMMO_ACCESS_TOKEN'] = $data['access_token'];
            $_SERVER['KOMMO_ACCESS_TOKEN'] = $data['access_token'];

            if (!empty($data['refresh_token'])) {
                putenv('KOMMO_REFRESH_TOKEN=' . $data['refresh_token']);
                $_ENV['KOMMO_REFRESH_TOKEN'] = $data['refresh_token'];
                $_SERVER['KOMMO_REFRESH_TOKEN'] = $data['refresh_token'];
            }

            error_log('Kommo OAuth: access token renovado correctamente', 0);
            return trim((string) $data['access_token']);
        }

        error_log('Kommo OAuth: fallo renovando token. HTTP ' . $httpCode . ' body ' . mb_substr((string) $resultado, 0, 800), 0);
        if ($logger) {
            $logger->warning('Kommo OAuth: no se pudo renovar el token', [
                'url' => $url,
                'http_code' => $httpCode,
            ]);
        }
    }

    return '';
}

function extraerCampoPersonalizadoV4Kommo(array $data, $nombre, $code = null)
{
    if (empty($data['custom_fields_values']) || !is_array($data['custom_fields_values'])) {
        return '';
    }

    foreach ($data['custom_fields_values'] as $field) {
        if ($code && isset($field['field_code']) && $field['field_code'] === $code) {
            $valores = [];
            foreach (($field['values'] ?? []) as $valor) {
                if (isset($valor['value']) && trim((string) $valor['value']) !== '') {
                    $valores[] = trim((string) $valor['value']);
                }
            }
            return implode(', ', array_unique($valores));
        }

        if (isset($field['field_name']) && $field['field_name'] === $nombre) {
            $valores = [];
            foreach (($field['values'] ?? []) as $valor) {
                if (isset($valor['value']) && trim((string) $valor['value']) !== '') {
                    $valores[] = trim((string) $valor['value']);
                }
            }
            return implode(', ', array_unique($valores));
        }
    }

    return '';
}

function aplicarDatosContactoKommoEnLead(array &$lead, array $contactApi)
{
    if (empty($lead['nombre_contacto']) && !empty($contactApi['name'])) {
        $lead['nombre_contacto'] = limpiarTextoVisibleKommo($contactApi['name']);
    }

    $telefono = extraerCampoPersonalizadoV4Kommo($contactApi, 'Teléfono', 'PHONE');
    $email = extraerCampoPersonalizadoV4Kommo($contactApi, 'Correo', 'EMAIL');

    if ($email === '') {
        $email = extraerCampoPersonalizadoV4Kommo($contactApi, 'Email', 'EMAIL');
    }

    if (empty($lead['telefono_contacto']) && $telefono !== '') {
        $lead['telefono_contacto'] = $telefono;
    }
    if (empty($lead['telefono']) && $telefono !== '') {
        $lead['telefono'] = $telefono;
    }
    if (empty($lead['email_contacto']) && $email !== '') {
        $lead['email_contacto'] = $email;
    }
    if (empty($lead['email']) && $email !== '') {
        $lead['email'] = $email;
    }
    if (empty($lead['nombre']) && !empty($lead['nombre_contacto'])) {
        $lead['nombre'] = $lead['nombre_contacto'];
    }
}

function peticionApiKommo($subdomain, $token, $path, $logger = null, $reintentarAuth = true)
{
    $subdomain = trim((string) $subdomain);
    $token = trim((string) $token);
    $inicio = microtime(true);

    if ($subdomain === '' || $path === '') {
        error_log('Kommo API: parámetros incompletos para petición. subdomain=' . $subdomain . ' path=' . $path, 0);
        return [];
    }

    if ($token === '') {
        $token = renovarTokenApiKommo($subdomain, $logger);
        if ($token === '') {
            error_log('Kommo API: no hay access token válido para consultar ' . $path . ' (subdomain=' . $subdomain . ')', 0);
            return ['_auth_error' => true];
        }
    }

    $urls = [
        'https://' . $subdomain . '.kommo.com' . $path,
        'https://' . $subdomain . '.amocrm.com' . $path,
    ];

    $resultado = false;
    $httpCode = 0;
    $curlError = '';
    $urlUsada = $urls[0];

    foreach ($urls as $url) {
        $urlUsada = $url;
        error_log('Kommo API: iniciando petición a ' . $url . ' token_present=' . ($token !== '' ? 'si' : 'no'), 0);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resultado = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError === '' && $httpCode > 0) {
            break;
        }
    }

    $duracionMs = (int) round((microtime(true) - $inicio) * 1000);
    error_log('Kommo API: respuesta HTTP ' . $httpCode . ' en ' . $duracionMs . 'ms para ' . $urlUsada, 0);
    if (is_string($resultado) && $resultado !== '') {
        error_log('Kommo API: body ' . mb_substr($resultado, 0, 1200), 0);
    }
    if ($curlError !== '') {
        error_log('Kommo API: curl error ' . $curlError, 0);
    }

    if ($resultado === false || $curlError !== '') {
        if ($logger) {
            $logger->warning('Kommo API: error al consultar datos del lead/contacto', [
                'url' => $urlUsada,
                'error' => $curlError,
            ]);
        }
        return [];
    }

    $data = json_decode($resultado, true);
    if (($httpCode === 401 || $httpCode === 403) && $reintentarAuth) {
        error_log('Kommo API: respuesta de autenticación no válida; intentando renovar token para ' . $path, 0);
        $tokenRenovado = renovarTokenApiKommo($subdomain, $logger);
        if ($tokenRenovado !== '' && $tokenRenovado !== $token) {
            return peticionApiKommo($subdomain, $tokenRenovado, $path, $logger, false);
        }
    }

    if ($httpCode === 401 || $httpCode === 403) {
        if ($logger) {
            $logger->warning('Kommo API: token inválido o sin permisos; se omite el enriquecimiento en tiempo real', [
                'url' => $urlUsada,
                'http_code' => $httpCode,
                'duracion_ms' => $duracionMs,
                'token_present' => $token !== '',
            ]);
        }
        return ['_auth_error' => true];
    }

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
        if ($logger) {
            $logger->warning('Kommo API: respuesta no válida al enriquecer lead', [
                'url' => $urlUsada,
                'http_code' => $httpCode,
                'body' => mb_substr((string) $resultado, 0, 500),
            ]);
        }
        return [];
    }

    return $data;
}

function enriquecerLeadKommoDesdeApi($lead, array $payload = [], $logger = null)
{
    $autorNombre = limpiarTextoVisibleKommo($payload['message']['add'][0]['author']['name'] ?? '');
    if ($autorNombre !== '') {
        if (empty($lead['nombre_contacto'])) {
            $lead['nombre_contacto'] = $autorNombre;
        }
        if (empty($lead['nombre']) || esNombreGenericoKommo($lead['nombre'])) {
            $lead['nombre'] = $autorNombre;
        }
    }

    if (empty($lead['origen_kommo']) && !empty($payload['message']['add'][0]['origin'])) {
        $lead['origen_kommo'] = trim((string) $payload['message']['add'][0]['origin']);
    }

    if (leadKommoTieneDatosMinimosParaCliente($lead)) {
        error_log('Kommo API: enriquecimiento omitido; el lead ya tiene datos mínimos suficientes. kommo_id=' . ($lead['kommo_id'] ?? ''), 0);
        return $lead;
    }

    $subdomain = obtenerSubdominioKommoDesdePayload($payload);
    $token = obtenerTokenApiKommo();
    if ($subdomain === '') {
        error_log('Kommo API: enriquecimiento omitido para no bloquear el webhook; subdominio no disponible. kommo_id=' . ($lead['kommo_id'] ?? ''), 0);
        return $lead;
    }

    $leadId = !empty($lead['kommo_id']) ? $lead['kommo_id'] : resolverKommoLeadIdDesdePayloadKommo($payload);
    $contactId = '';
    error_log('Kommo API: enriqueciendo lead. kommo_id=' . ($lead['kommo_id'] ?? '') . ' leadId=' . $leadId . ' token_present=' . ($token !== '' ? 'si' : 'no') . ' subdomain=' . $subdomain, 0);

    if (!empty($payload['message']['add'][0]['contact_id'])) {
        $contactId = (string) $payload['message']['add'][0]['contact_id'];
    } elseif (!empty($payload['contacts']['add'][0]['id'])) {
        $contactId = (string) $payload['contacts']['add'][0]['id'];
    }

    if ($contactId !== '' && leadKommoNecesitaDatosContacto($lead)) {
        error_log('Kommo API: enriqueciendo contacto prioritario. contactId=' . $contactId, 0);
        $contactApi = peticionApiKommo($subdomain, $token, '/api/v4/contacts/' . rawurlencode($contactId), $logger, false);
        if (!empty($contactApi['_auth_error'])) {
            return $lead;
        }
        if (!empty($contactApi)) {
            aplicarDatosContactoKommoEnLead($lead, $contactApi);
        }
    }

    if ($leadId !== '' && ($contactId === '' || empty($lead['canal']) || empty($lead['provincia']) || empty($lead['nombre']))) {
        $leadApi = peticionApiKommo($subdomain, $token, '/api/v4/leads/' . rawurlencode($leadId) . '?with=contacts', $logger, false);
        if (!empty($leadApi['_auth_error'])) {
            return $lead;
        }
        if (!empty($leadApi)) {
            if (empty($lead['nombre']) && !empty($leadApi['name'])) {
                $lead['nombre'] = limpiarTextoVisibleKommo($leadApi['name']);
            }

            if (empty($lead['canal']) && !empty($leadApi['_embedded']['tags']) && is_array($leadApi['_embedded']['tags'])) {
                $tags = [];
                foreach ($leadApi['_embedded']['tags'] as $tag) {
                    if (!empty($tag['name'])) {
                        $tags[] = trim((string) $tag['name']);
                    }
                }
                $lead['canal'] = implode(', ', array_unique($tags));
            }

            $provinciaLead = extraerCampoPersonalizadoV4Kommo($leadApi, 'Provincia');
            if (empty($lead['provincia']) && $provinciaLead !== '') {
                $lead['provincia'] = $provinciaLead;
            }

            if ($contactId === '' && !empty($leadApi['_embedded']['contacts'][0]['id'])) {
                $contactId = (string) $leadApi['_embedded']['contacts'][0]['id'];
            }
        }
    }

    if ($contactId !== '' && leadKommoNecesitaDatosContacto($lead)) {
        error_log('Kommo API: enriqueciendo contacto final. contactId=' . $contactId, 0);
        $contactApi = peticionApiKommo($subdomain, $token, '/api/v4/contacts/' . rawurlencode($contactId), $logger, false);
        if (!empty($contactApi['_auth_error'])) {
            return $lead;
        }
        if (!empty($contactApi)) {
            aplicarDatosContactoKommoEnLead($lead, $contactApi);
        }
    }

    if (empty($lead['nombre']) && !empty($lead['nombre_contacto'])) {
        $lead['nombre'] = $lead['nombre_contacto'];
    }

    return $lead;
}

function autoProcesarLeadKommo($doctrine, $managerEntidad, $kommoId, $logger = null, array $payload = [])
{
    if (empty($kommoId)) {
        return ['accion' => 'solo_guardado'];
    }

    $groupKey = 'kommo_' . $kommoId;
    $bloqueo = abrirBloqueoProcesadoKommo(crearClaveBloqueoKommo($kommoId, ['kommo_id' => $kommoId]));

    try {
        $lead = obtenerLeadKommoPorClave($doctrine, $groupKey);
        if (!$lead) {
            return ['accion' => 'solo_guardado', 'motivo' => 'No se pudo agrupar el lead'];
        }

        $lead = enriquecerLeadKommoDesdeApi($lead, $payload, $logger);
        if (!$lead) {
            return ['accion' => 'solo_guardado', 'motivo' => 'No se pudo agrupar el lead'];
        }

        if (!leadKommoTieneDatosMinimosParaCliente($lead)) {
            if ($logger) {
                $logger->info('Kommo webhook pendiente por datos incompletos', [
                    'kommo_id' => $kommoId,
                    'nombre' => $lead['nombre'] ?? null,
                    'nombre_contacto' => $lead['nombre_contacto'] ?? null,
                    'telefono' => $lead['telefono'] ?? null,
                    'email' => $lead['email'] ?? null,
                ]);
            }

            guardarMensajeExpedienteKommo($doctrine, $managerEntidad, null, $lead, $payload, $logger);

            return ['accion' => 'pendiente', 'motivo' => 'Lead recibido pero aún sin datos suficientes para crear cliente/expediente'];
        }

        $cliente = buscarClienteKommo($doctrine, $lead);
        $clienteCreado = false;
        if (!$cliente) {
            $cliente = crearClienteDesdeLeadKommo($lead);
            $managerEntidad->persist($cliente);
            $managerEntidad->flush();
            $clienteCreado = true;
        }

        if (!$cliente) {
            guardarMensajeExpedienteKommo($doctrine, $managerEntidad, null, $lead, $payload, $logger);

            return ['accion' => 'pendiente', 'motivo' => 'Lead recibido pero aún sin datos suficientes para crear cliente/expediente'];
        }

        $expediente = $doctrine->getRepository('AppBundle:Expediente')->findOneBy([
            'idCliente' => $cliente,
            'estado' => 1
        ], ['fechaCreacion' => 'DESC']);

        $accion = 'expediente_actualizado';
        $crearNuevoPorAntiguedad = false;
        if ($expediente && method_exists($expediente, 'getFechaCreacion')) {
            $fechaCreacionExpediente = $expediente->getFechaCreacion();
            if ($fechaCreacionExpediente instanceof \DateTimeInterface) {
                $limiteAntiguedad = (new \DateTime())->modify('-1 month');
                $crearNuevoPorAntiguedad = $fechaCreacionExpediente < $limiteAntiguedad;
            }
        }

        if (!$expediente || $crearNuevoPorAntiguedad) {
            $expediente = crearExpedienteDesdeLeadKommo($doctrine, $managerEntidad, $cliente, $lead, null);
            $managerEntidad->persist($expediente);

            if ($crearNuevoPorAntiguedad) {
                $accion = 'expediente_nuevo_por_antiguedad';
            } else {
                $accion = $clienteCreado ? 'cliente_y_expediente_creados' : 'expediente_creado';
            }
        } else {
            actualizarExpedienteDesdeLeadKommoAutomatico($doctrine, $managerEntidad, $expediente, $lead);
        }

        vincularMensajesPendientesExpedienteKommo($doctrine, $managerEntidad, $expediente, $lead);
        guardarMensajeExpedienteKommo($doctrine, $managerEntidad, $expediente, $lead, $payload, $logger);

        $webhooksRelacionados = obtenerWebhooksKommoPorClave($doctrine, $groupKey);
        foreach ($webhooksRelacionados as $webhookRelacionado) {
            if (method_exists($webhookRelacionado, 'setEstado')) {
                $webhookRelacionado->setEstado('procesado');
            }
            if (method_exists($webhookRelacionado, 'setErrorMensaje')) {
                $webhookRelacionado->setErrorMensaje(null);
            }
            $managerEntidad->persist($webhookRelacionado);
        }

        $managerEntidad->flush();

        if ($logger) {
            $logger->info('Kommo webhook auto-procesado', [
                'kommo_id' => $kommoId,
                'accion' => $accion,
                'cliente_id' => method_exists($cliente, 'getIdUsuario') ? $cliente->getIdUsuario() : null,
                'expediente_id' => method_exists($expediente, 'getIdExpediente') ? $expediente->getIdExpediente() : null,
            ]);
        }

        return [
            'accion' => $accion,
            'cliente_id' => method_exists($cliente, 'getIdUsuario') ? $cliente->getIdUsuario() : null,
            'expediente_id' => method_exists($expediente, 'getIdExpediente') ? $expediente->getIdExpediente() : null,
        ];
    } finally {
        liberarBloqueoProcesadoKommo($bloqueo);
    }
}

function crearExpedienteDesdeLeadKommo($doctrine, $managerEntidad, $cliente, array $lead, $usuarioActual)
{
    $fase = $doctrine->getRepository('AppBundle:Fase')->findOneBy(['tipo' => 0]);
    if (!$fase) {
        $fase = $doctrine->getRepository('AppBundle:Fase')->findOneBy(['orden' => 1]);
    }

    $resumen = [];
    if (!empty($lead['kommo_id'])) {
        $resumen[] = 'Kommo ID: ' . $lead['kommo_id'];
    }
    if (!empty($lead['canal'])) {
        $resumen[] = 'Canal: ' . $lead['canal'];
    }
    if (!empty($lead['provincia'])) {
        $resumen[] = 'Provincia: ' . $lead['provincia'];
    }
    if (!empty($lead['telefono'])) {
        $resumen[] = 'Tel: ' . $lead['telefono'];
    }
    if (!empty($lead['email'])) {
        $resumen[] = 'Email: ' . $lead['email'];
    }

    if (!empty($lead['observaciones'])) {
        $resumen[] = 'Observaciones: ' . mb_substr((string) $lead['observaciones'], 0, 250);
    }

    $textoResumen = limitarTextoKommo(implode(' | ', $resumen), 250);
    $tituloVivienda = !empty($lead['nombre']) ? 'Lead Kommo - ' . $lead['nombre'] : 'Lead Kommo';

    $expediente = (new \AppBundle\Entity\Expediente())
        ->setEstado(1)
        ->setIdCliente($cliente)
        ->setIdFaseActual($fase)
        ->setVivienda(limitarTextoKommo($tituloVivienda, 120))
        ->setTexto($textoResumen)
        ->setFechaCreacion(new \DateTime())
        ->setFechaModificacion(new \DateTime());

    if ($usuarioActual) {
        $rolActual = $usuarioActual->getRoles()[0] ?? '';
        if (in_array($rolActual, ['ROLE_COLABORADOR', 'ROLE_JEFE_OFICINA', 'ROLE_JEFE_INMOBILIARIA', 'ROLE_RESPONSABLE_ZONA'])) {
            $expediente->setIdColaborador($usuarioActual);
            if ($usuarioActual->getIdInmobiliaria() && $usuarioActual->getIdInmobiliaria()->getIdComercial()) {
                $expediente->setIdComercial($usuarioActual->getIdInmobiliaria()->getIdComercial());
            }
        } elseif ($rolActual === 'ROLE_TECNICO') {
            $expediente->setIdTecnico($usuarioActual);
        } else {
            $expediente->setIdComercial($usuarioActual);
        }
    }

    inicializarEstructuraExpedienteKommo($doctrine, $managerEntidad, $expediente, $lead);

    return $expediente;
}

function inicializarEstructuraExpedienteKommo($doctrine, $managerEntidad, $expediente, array $lead = [])
{
    $fases = $doctrine->getRepository('AppBundle:Fase')->findBy([], ['orden' => 'ASC']);
    $autorrellenoCampos = construirAutorrellenoHitosKommo($lead);

    foreach ($fases as $fase) {
        $hitos = $doctrine->getRepository('AppBundle:Hito')->findBy([
            'idFase' => $fase
        ], ['orden' => 'ASC']);

        foreach ($hitos as $hito) {
            $hitoExpediente = (new \AppBundle\Entity\HitoExpediente())
                ->setIdHito($hito)
                ->setIdExpediente($expediente)
                ->setFechaModificacion(new \DateTime())
                ->setEstado(0);

            $gruposCamposHito = $doctrine->getRepository('AppBundle:GrupoCamposHito')->findBy([
                'idHito' => $hito
            ], ['orden' => 'ASC']);

            foreach ($gruposCamposHito as $grupoCamposHito) {
                $grupoHitoExpediente = (new \AppBundle\Entity\GrupoHitoExpediente())
                    ->setIdHitoExpediente($hitoExpediente)
                    ->setIdGrupoCamposHito($grupoCamposHito);

                $camposHito = $doctrine->getRepository('AppBundle:CampoHito')->findBy([
                    'idGrupoCamposHito' => $grupoCamposHito
                ], ['orden' => 'ASC']);

                foreach ($camposHito as $campoHito) {
                    $campoHitoExpediente = (new \AppBundle\Entity\CampoHitoExpediente())
                        ->setIdCampoHito($campoHito)
                        ->setIdHitoExpediente($hitoExpediente)
                        ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                        ->setIdExpediente($expediente)
                        ->setFechaModificacion(new \DateTime());

                    if ($campoHito->getTipo() == 4) {
                        $campoHitoExpediente->setObligatorio(1)
                            ->setSolicitarAlColaborador(1);
                    }

                    $idCampoHito = method_exists($campoHito, 'getIdCampoHito') ? $campoHito->getIdCampoHito() : null;
                    if ($idCampoHito !== null && isset($autorrellenoCampos[$idCampoHito])) {
                        aplicarAutorrellenoCampoHitoKommo($doctrine, $campoHitoExpediente, $autorrellenoCampos[$idCampoHito]);
                    }

                    $managerEntidad->persist($campoHitoExpediente);
                }

                $managerEntidad->persist($grupoHitoExpediente);
            }

            $managerEntidad->persist($hitoExpediente);
        }
    }
}

function obtenerValorEscalarRecursivo($data, array $keys)
{
    if ($data instanceof \stdClass) {
        $data = json_decode(json_encode($data), true);
    }

    if (!is_array($data)) {
        return is_scalar($data) ? trim((string) $data) : '';
    }

    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $valor = $data[$key];
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                return trim((string) $valor);
            }
            if (is_array($valor) || $valor instanceof \stdClass) {
                $encontrado = obtenerValorEscalarRecursivo($valor, $keys);
                if ($encontrado !== '') {
                    return $encontrado;
                }
            }
        }
    }

    foreach ($data as $valor) {
        if (is_array($valor) || $valor instanceof \stdClass) {
            $encontrado = obtenerValorEscalarRecursivo($valor, $keys);
            if ($encontrado !== '') {
                return $encontrado;
            }
        }
    }

    return '';
}

function obtenerPrimerEventoKommo($json, $webhookType = '')
{
    $tipo = strtolower(trim((string) $webhookType));

    if ($tipo !== '') {
        foreach (['add', 'update'] as $accion) {
            if (isset($json[$tipo][$accion][0]) && is_array($json[$tipo][$accion][0])) {
                return $json[$tipo][$accion][0];
            }
        }

        return [];
    }

    foreach (['leads', 'contacts', 'message', 'talk'] as $clave) {
        foreach (['add', 'update'] as $accion) {
            if (isset($json[$clave][$accion][0]) && is_array($json[$clave][$accion][0])) {
                return $json[$clave][$accion][0];
            }
        }
    }

    return [];
}

function obtenerAutorKommo(array $evento)
{
    return limpiarTextoVisibleKommo($evento['author']['name'] ?? '');
}

function obtenerOrigenKommo(array $evento)
{
    return limpiarTextoVisibleKommo($evento['origin'] ?? '');
}

function obtenerTextoMensajeKommo(array $evento, $json = [])
{
    if (!empty($evento['text']) && trim((string) $evento['text']) !== '') {
        return limpiarTextoVisibleKommo($evento['text']);
    }

    $texto = obtenerValorEscalarRecursivo($json, ['text', 'message', 'body', 'content', 'comment', 'note']);
    return limpiarTextoVisibleKommo($texto);
}

function obtenerDetalleKommo($json, array $lead, $webhookType = '')
{
    $detalle = [];
    $tipo = strtolower((string) $webhookType);
    $evento = obtenerPrimerEventoKommo($json, $webhookType);

    if ($tipo === 'message') {
        $textoMensaje = obtenerTextoMensajeKommo($evento, $json);
        if ($textoMensaje !== '') {
            $detalle[] = 'Mensaje: ' . $textoMensaje;
        }
        if (!empty($evento['type'])) {
            $detalle[] = 'Dirección: ' . $evento['type'];
        }
        if (!empty($evento['author']['name'])) {
            $detalle[] = 'Autor: ' . $evento['author']['name'];
        }
    } elseif ($tipo === 'talk') {
        if (!empty($evento['talk_id'])) {
            $detalle[] = 'Talk ID: ' . $evento['talk_id'];
        }
        if (!empty($evento['chat_id'])) {
            $detalle[] = 'Chat ID: ' . $evento['chat_id'];
        }
        if (isset($evento['is_read'])) {
            $detalle[] = 'Leído: ' . ($evento['is_read'] == '1' ? 'Sí' : 'No');
        }
        if (isset($evento['is_in_work'])) {
            $detalle[] = 'En gestión: ' . ($evento['is_in_work'] == '1' ? 'Sí' : 'No');
        }
    } elseif ($tipo === 'leads') {
        if (!empty($evento['name'])) {
            $detalle[] = 'Lead: ' . $evento['name'];
        }

        $provincia = extraerCampoPersonalizado($evento, 'Provincia');
        if ($provincia !== '') {
            $detalle[] = 'Provincia: ' . $provincia;
        }

        if (!empty($evento['tags']) && is_array($evento['tags'])) {
            $tags = [];
            foreach ($evento['tags'] as $tag) {
                if (!empty($tag['name'])) {
                    $tags[] = $tag['name'];
                }
            }
            if (!empty($tags)) {
                $detalle[] = 'Tags: ' . implode(', ', $tags);
            }
        }
    } elseif ($tipo === 'contacts') {
        if (!empty($evento['name'])) {
            $detalle[] = 'Contacto: ' . $evento['name'];
        }

        $telefono = extraerCampoPersonalizado($evento, 'Teléfono', 'PHONE');
        $correo = extraerCampoPersonalizado($evento, 'Correo', 'EMAIL');

        if ($telefono !== '') {
            $detalle[] = 'Tel: ' . $telefono;
        }
        if ($correo !== '') {
            $detalle[] = 'Email: ' . $correo;
        }
    }

    if (!empty($evento['origin'])) {
        $detalle[] = 'Origen: ' . $evento['origin'];
    }

    if (empty($detalle)) {
        $textoMensaje = obtenerValorEscalarRecursivo($json, ['text', 'message', 'body', 'content', 'comment', 'note']);
        if ($textoMensaje !== '') {
            $detalle[] = $textoMensaje;
        }
    }

    if (empty($detalle) && !empty($lead['nombre'])) {
        $detalle[] = 'Nombre: ' . $lead['nombre'];
    }

    $detalle = array_unique(array_filter($detalle));

    return !empty($detalle) ? implode(' | ', $detalle) : 'Sin detalle relevante disponible';
}
