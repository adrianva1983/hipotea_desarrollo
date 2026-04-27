<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * IArtificalController
 * 
 * Controlador especializado en procesos de Inteligencia Artificial:
 * - Análisis de imágenes con Google Gemini Vision o OpenAI Vision
 * - Generación de respuestas con IA
 * - Análisis y extracción de datos de mensajes
 * - Gestión de configuración de IA
 * - Lógica de campos y partes para formularios dinámicos
 * - Procesamiento de expedientes con extracción automática de datos
 * 
 * Este controlador se separa de WhatsappController para mantener
 * responsabilidades bien definidas: IA aquí, WhatsApp en su controlador.
 */
class IArtificalController extends Controller
{
    /** Número máximo de iteraciones del bucle agéntico para evitar bucles infinitos */
    const MAX_AGENT_ITERATIONS = 10;

    /**
     * Registra un mensaje en el log diario (heredado desde WhatsappController)
     * Usamos el mismo método que en WhatsappController para consistencia
     */
    private function logear($mensaje)
    {
        $logDir = dirname(dirname(dirname(__DIR__))) . '/var/logs/';
        
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . 'whatsapp_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contenido = "[{$timestamp}] {$mensaje}\n";
        
        $resultado = @file_put_contents($logFile, $contenido, FILE_APPEND | LOCK_EX);
        
        if ($resultado === false) {
            error_log($mensaje);
        }
    }

    /**
     * Analiza una imagen usando Google Gemini Vision o OpenAI Vision
     * Genera una descripción o análisis contextual de la imagen
     *
     * @param string $imageData Base64 de la imagen
     * @param string $imageType MIME type (image/jpeg, image/png, etc.)
     * @param string|null $context Contexto o descripción del usuario (opcional)
     * @param string|null $systemPrompt Prompt personalizado para el análisis
     * @return string|null Descripción de la imagen o null en caso de error
     */
    public function analizarImagenConIA(string $imageData, string $imageType, ?string $context = null, ?string $systemPrompt = null): ?string
    {
        error_log('=== INICIO analizarImagenConIA ===');
        error_log('Parámetros recibidos:');
        error_log('  imageData: ' . (strlen($imageData) . ' chars'));
        error_log('  imageType: ' . $imageType);
        error_log('  context: ' . ($context ?? 'null'));
        error_log('  systemPrompt: ' . (strlen($systemPrompt ?? '') . ' chars'));
        
        try {
            error_log('Obteniendo configuración de IA...');
            $config = $this->obtenerConfiguracionIA();
            error_log('Config obtenida: ' . ($config ? 'SÍ' : 'NULL'));
            
            if ($config) {
                error_log('Contenido config:');
                error_log('  provider: ' . ($config['provider'] ?? 'not set'));
                error_log('  api_key: ' . (isset($config['api_key']) && $config['api_key'] ? '***' : 'NO ESTABLECIDA'));
                error_log('  model: ' . ($config['model'] ?? 'not set'));
            }
            
            if (!$config) {
                throw new \Exception('No hay configuración de IA disponible (obtenerConfiguracionIA retornó NULL)');
            }

            $provider = strtoupper($config['provider'] ?? 'GEMINI');
            error_log('Provider seleccionado: ' . $provider);

            $finalSystemPrompt = $systemPrompt 
                ?: 'Analiza esta imagen y proporciona una descripción clara y concisa. Sé amable y profesional en tu respuesta.';

            if ($provider === 'OPENAI') {
                error_log('Llamando a analizarImagenOpenAI...');
                $resultado = $this->analizarImagenOpenAI($imageData, $imageType, $context, $finalSystemPrompt, $config);
                error_log('analizarImagenOpenAI retornó: ' . ($resultado ? 'RESPUESTA' : 'NULL'));
                return $resultado;
            } else {
                error_log('Llamando a analizarImagenGemini...');
                $resultado = $this->analizarImagenGemini($imageData, $imageType, $context, $finalSystemPrompt, $config);
                error_log('analizarImagenGemini retornó: ' . ($resultado ? 'RESPUESTA' : 'NULL'));
                return $resultado;
            }

        } catch (\Exception $e) {
            error_log('EXCEPCIÓN en analizarImagenConIA: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en analizarImagenConIA', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            error_log('=== FIN analizarImagenConIA (ERROR) ===');
            return null;
        }
    }

    /**
     * Analiza una imagen usando Google Gemini Vision API
     */
    public function analizarImagenGemini(string $imageData, string $imageType, ?string $context, string $systemPrompt, array $config): ?string
    {
        error_log('=== INICIO analizarImagenGemini ===');
        try {
            $apiKey = $config['api_key'] ?? null;
            $model = $config['model'] ?? 'gemini-pro-vision';

            error_log('Gemini - apiKey: ' . ($apiKey ? 'SÍ' : 'NO'));
            error_log('Gemini - model: ' . $model);

            if (!$apiKey) {
                throw new \Exception('GEMINI_API_KEY no configurada');
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            error_log('Gemini - URL: ' . str_replace($apiKey, '***', $url));

            $userPrompt = $context 
                ? "Imagen recibida. Contexto: $context\n\n$systemPrompt"
                : $systemPrompt;

            error_log('Gemini - userPrompt: ' . substr($userPrompt, 0, 50) . '...');

            $payload = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $userPrompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $imageType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024
                ]
            ]);

            error_log('Gemini - Payload size: ' . strlen($payload) . ' bytes');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            error_log('Gemini - Enviando request a API...');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log('Gemini - HTTP Code: ' . $httpCode);
            error_log('Gemini - Response size: ' . strlen($response) . ' bytes');
            
            if ($curlError) {
                error_log('Gemini - cURL Error: ' . $curlError);
                throw new \Exception('cURL Error: ' . $curlError);
            }

            if ($httpCode !== 200) {
                error_log('Gemini - Error response (primeros 300 chars): ' . substr($response, 0, 300));
                throw new \Exception("Gemini Vision API retornó HTTP $httpCode: " . substr($response, 0, 100));
            }

            error_log('Gemini - Decodificando JSON...');
            $responseData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Gemini - JSON Error: ' . json_last_error_msg());
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            error_log('Gemini - Response keys: ' . json_encode(array_keys($responseData ?? [])));
            
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $respuesta = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                error_log('Gemini - ✓ Respuesta obtenida: ' . strlen($respuesta) . ' chars');
                error_log('=== FIN analizarImagenGemini (ÉXITO) ===');
                return $respuesta;
            } else {
                error_log('Gemini - ERROR: Estructura no encontrada');
                error_log('Gemini - Full response: ' . json_encode($responseData));
                throw new \Exception('No se encontró texto en la respuesta');
            }

        } catch (\Exception $e) {
            error_log('Gemini - EXCEPCIÓN: ' . $e->getMessage());
            error_log('Gemini - Stack: ' . $e->getTraceAsString());
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en analizarImagenGemini', [
                    'error' => $e->getMessage()
                ]);
            }
            error_log('=== FIN analizarImagenGemini (ERROR) ===');
            return null;
        }
    }

    /**
     * Analiza una imagen usando OpenAI Vision API
     */
    public function analizarImagenOpenAI(string $imageData, string $imageType, ?string $context, string $systemPrompt, array $config): ?string
    {
        try {
            $apiKey = $config['api_key'] ?? null;
            $model = $config['model'] ?? 'gpt-4-vision-preview';

            if (!$apiKey) {
                throw new \Exception('OPENAI_API_KEY no configurada');
            }

            $url = 'https://api.openai.com/v1/chat/completions';

            $mediaType = str_replace('image/', '', $imageType);

            $userPrompt = $context 
                ? "Imagen recibida. Contexto: $context\n\n$systemPrompt"
                : $systemPrompt;

            $payload = json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $userPrompt
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/{$mediaType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 1024,
                'temperature' => 0.7
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("OpenAI Vision API retornó HTTP $httpCode");
            }

            $responseData = json_decode($response, true);
            if (isset($responseData['choices'][0]['message']['content'])) {
                return trim($responseData['choices'][0]['message']['content']);
            }

            return null;

        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en analizarImagenOpenAI', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Genera una respuesta de mensaje usando IA
     * Procesa imágenes o texto según el tipo de mensaje
     */
    public function generarMensajeConIA(string $messageText, string $tipoMensaje = 'text', ?string $systemPrompt = null, ?string $imageData = null, ?string $imageType = null, ?string $userRole = null, ?string $clienteName = null, ?string $technicianName = null, ?int $idExpediente = null): ?string
    {
        error_log('=== INICIO generarMensajeConIA ===');
        error_log('tipoMensaje: ' . $tipoMensaje);
        error_log('userRole: ' . ($userRole ?? 'desconocido'));
        error_log('clienteName: ' . ($clienteName ?? 'desconocido'));
        error_log('technicianName: ' . ($technicianName ?? 'desconocido'));
        error_log('imageData present: ' . ($imageData ? 'YES (' . strlen($imageData) . ' chars)' : 'NO'));
        error_log('imageType: ' . ($imageType ?? 'null'));
        error_log('messageText: ' . substr($messageText ?? '', 0, 100));
        
        $text = strtolower(trim($messageText));
        
        $nombreCliente = $clienteName ? explode(' ', trim($clienteName))[0] : 'amigo';
        $nombreTecnico = $technicianName ? explode(' ', trim($technicianName))[0] : 'Asistente';
        $esComercial = $userRole && stripos($userRole, 'comercial') !== false;
        $esTecnico = $userRole && stripos($userRole, 'tecnico') !== false;

        if ($text === '/hola' || $text === 'hola') {
            if ($esComercial || $esTecnico) {
                return "¡Hola $nombreCliente! 👋 Soy $nombreTecnico de Hipotea. ¿Qué necesitas hoy?";
            } else {
                return "¡Hola $nombreCliente! 👋 Soy $nombreTecnico, asistente de Hipotea. ¿Buscas información sobre tu hipoteca o necesitas una simulación? Cuéntame un poco tu situación.";
            }
        }

        if ($text === '/ayuda' || $text === 'ayuda') {
            return "💡 *¿Cómo puedo ayudarte?*\n\n💰 Simular hipoteca\n📄 Documentación necesaria\n☎️ Agendar llamada\n❓ Preguntas generales\n\nCuéntame qué necesitas.";
        }

        if ($text === '/info') {
            return '🏠 *Hipotea - Tu hipoteca simplificada*\n\nAquí resolvemos dudas sobre tu hipoteca y te ayudamos con simulaciones. ¿Qué necesitas hoy?';
        }

        error_log('Checking tipoMensaje: ' . $tipoMensaje);
        
        if ($tipoMensaje === 'image') {
            error_log('IMAGEN DETECTADA');
            error_log('imageData: ' . ($imageData ? 'presente' : 'VACIO'));
            error_log('imageType: ' . ($imageType ?? 'VACIO'));
            
            if ($imageData && $imageType) {
                try {
                    error_log('Llamando analizarImagenConIA...');
                    $analisisImagen = $this->analizarImagenConIA($imageData, $imageType, $messageText, $systemPrompt);
                    error_log('analizarImagenConIA retornó: ' . ($analisisImagen ? 'RESPUESTA (' . strlen($analisisImagen) . ' chars)' : 'NULL'));
                    
                    if ($analisisImagen) {
                        error_log('=== FIN generarMensajeConIA - RETORNANDO ANÁLISIS ===');
                        return $analisisImagen;
                    }
                } catch (\Exception $e) {
                    error_log('Exception en analizarImagenConIA: ' . $e->getMessage());
                    if ($this->container->has('logger')) {
                        $this->container->get('logger')->warning('Error analizando imagen con IA', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                error_log('imageData o imageType vacíos - retornando fallback');
            }
            
            error_log('=== FIN generarMensajeConIA - RETORNANDO FALLBACK IMAGEN ===');
            return '📸 ¡Bonita imagen! Gracias por compartirla.';
        }

        if ($tipoMensaje === 'videoMessage') {
            error_log('=== FIN generarMensajeConIA - VIDEO ===');
            return '🎥 ¡Video recibido! Gracias por compartirlo.';
        }

        if ($tipoMensaje === 'documentMessage') {
            error_log('=== FIN generarMensajeConIA - DOCUMENTO ===');
            return '📄 Documento recibido correctamente.';
        }

        error_log('TEXTO DETECTADO - tipoMensaje: ' . $tipoMensaje);
        
        if ($tipoMensaje === 'conversation' || $tipoMensaje === 'text' || $tipoMensaje === 'extendedTextMessage') {
            if (stripos($text, 'gracias') !== false) {
                return "¡De nada $nombreCliente! 😊 Soy $nombreTecnico. ¿Hay algo más que quieras consultar?";
            }

            if (stripos($text, 'adiós') !== false || stripos($text, 'adios') !== false || stripos($text, 'chao') !== false) {
                return "¡Perfecto $nombreCliente! Quedo aquí. Un saludo de $nombreTecnico 👋";
            }

            if (stripos($text, 'cómo estás') !== false || stripos($text, 'como estas') !== false) {
                return "¡Todo bien $nombreCliente! 👍 Soy $nombreTecnico. ¿En qué puedo ayudarte?";
            }

            try {
                $promptFinal = $systemPrompt;
                if (!$promptFinal) {
                    if ($esComercial) {
                        $promptFinal = "Eres un asistente de Hipotea que ayuda a comerciales de hipotecas a gestionar clientes por WhatsApp. Responde en 1-3 frases, siempre en español, con tono cercano y profesional. Haz preguntas concretas para entender el caso del cliente y guía hacia simulación, documentación o llamada. Evita tecnicismos.";
                    } elseif ($esTecnico) {
                        $promptFinal = "Eres un asistente técnico de Hipotea que ayuda a técnicos de soporte con expedientes y consultas. Responde en 1-3 frases, siempre en español, con tono profesional. Sé directo y eficiente.";
                    } else {
                        $promptFinal = "Actúas como comercial de Hipotea por WhatsApp hablando con un cliente. Responde SIEMPRE en español, con mensajes cortos (1–3 frases), tono cercano y profesional. Haz preguntas concretas para entender el caso y guía al cliente hacia el siguiente paso (simulación, documentación, llamada). Evita tecnicismos y no inventes información.";
                    }
                }
                $respuesta = $this->llamarAPIIA($messageText, $promptFinal, $idExpediente);
                if ($respuesta) {
                    return $respuesta;
                }
            } catch (\Exception $e) {
                if ($this->container->has('logger')) {
                    $this->container->get('logger')->warning('Error llamando a IA', [
                        'error' => $e->getMessage(),
                        'mensaje' => substr($messageText, 0, 100)
                    ]);
                }
            }

            error_log('=== FIN generarMensajeConIA - FALLBACK TEXTO ===');
            return "Entendido. Dame un momento para ayudarte mejor. 🔍";
        }

        error_log('ADVERTENCIA: tipoMensaje no reconocido: ' . $tipoMensaje);
        error_log('=== FIN generarMensajeConIA - FALLBACK GENÉRICO ===');
        return '✅ Mensaje recibido correctamente. Por favor, espera una respuesta.';
    }

    /**
     * Obtiene la configuración de IA desde la tabla ia_config
     * Implementa lógica de fallback a variables de entorno
     *
     * @return array|null Array con configuración de IA o null si no existe
     */
    public function obtenerConfiguracionIA(): ?array
    {
        error_log('=== INICIO obtenerConfiguracionIA ===');
        
        try {
            $conn = $this->getDoctrine()->getConnection();
            
            error_log('Buscando configuración activa en tabla ia_config...');
            $sql = 'SELECT * FROM ia_config WHERE activo = TRUE ORDER BY created_at DESC LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();
            
            if ($config) {
                error_log('Config activa encontrada: provider=' . ($config['provider'] ?? 'null'));
                error_log('=== FIN obtenerConfiguracionIA (CONFIG ACTIVA) ===');
                return $config;
            }
            
            error_log('No hay configuración activa, buscando por defecto...');

            $sql = 'SELECT * FROM ia_config WHERE es_proveedor_por_defecto = TRUE LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch();
            
            if ($config) {
                error_log('Config por defecto encontrada: provider=' . ($config['provider'] ?? 'null'));
                error_log('=== FIN obtenerConfiguracionIA (CONFIG POR DEFECTO) ===');
                return $config;
            }
            
            error_log('No hay configuración en tabla, intentando variables de entorno...');

            $provider = strtoupper(trim($_ENV['IA_PROVIDER'] ?? getenv('IA_PROVIDER') ?? 'GEMINI'));
            error_log('IA_PROVIDER desde ENV: ' . $provider);
            
            if ($provider === 'OPENAI') {
                error_log('Configurando OpenAI...');
                $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
                error_log('OPENAI_API_KEY presente: ' . ($apiKey ? 'SÍ' : 'NO'));
                
                if (!$apiKey) {
                    error_log('ERROR: OPENAI_API_KEY no configurada en .env');
                    error_log('=== FIN obtenerConfiguracionIA (SIN API KEY OPENAI) ===');
                    return null;
                }
                
                $config = [
                    'provider' => 'OPENAI',
                    'api_key' => $apiKey,
                    'api_url' => 'https://api.openai.com/v1',
                    'model' => $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?? 'gpt-3.5-turbo',
                    'system_prompt' => null,
                    'temperatura' => 0.7,
                    'max_tokens' => 1024
                ];
                error_log('Config OpenAI preparada correctamente');
            } else {
                error_log('Configurando Gemini...');
                $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
                error_log('GEMINI_API_KEY presente: ' . ($apiKey ? 'SÍ' : 'NO'));
                
                if (!$apiKey) {
                    error_log('ERROR: GEMINI_API_KEY no configurada en .env');
                    error_log('=== FIN obtenerConfiguracionIA (SIN API KEY GEMINI) ===');
                    return null;
                }
                
                $config = [
                    'provider' => 'GEMINI',
                    'api_key' => $apiKey,
                    'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'model' => $_ENV['GEMINI_MODEL'] ?? getenv('GEMINI_MODEL') ?? 'gemini-pro',
                    'system_prompt' => null,
                    'temperatura' => 0.7,
                    'max_tokens' => 1024,
                    'top_p' => 0.95,
                    'top_k' => 40
                ];
                error_log('Config Gemini preparada correctamente');
            }

            error_log('=== FIN obtenerConfiguracionIA (CONFIG ENV) ===');
            return $config ?: null;

        } catch (\Exception $e) {
            error_log('EXCEPCIÓN en obtenerConfiguracionIA: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            if ($this->container->has('logger')) {
                $this->container->get('logger')->warning('Error obteniendo configuración IA de tabla', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            error_log('=== FIN obtenerConfiguracionIA (EXCEPCIÓN) ===');
            return null;
        }
    }

    /**
     * Obtiene el histórico de conversación para un expediente
     * Devuelve los últimos N mensajes con formato legible para el contexto
     *
     * @param int $idExpediente ID del expediente
     * @param int $limit Número máximo de mensajes anteriores a recuperar
     * @return string Contexto de conversación formateado, o string vacío si no hay histórico
     */
    public function obtenerContextoConversacion(int $idExpediente, int $limit = 5): string
    {
        try {
            $conn = $this->getDoctrine()->getConnection();
            
            error_log('Obteniendo contexto de conversación para expediente ' . $idExpediente);
            
            $sql = 'SELECT role, role_label, text, timestamp 
                    FROM chat_history 
                    WHERE id_expediente = :idExpediente 
                    ORDER BY timestamp DESC 
                    LIMIT :limit';
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('idExpediente', $idExpediente);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $mensajes = $stmt->fetchAll();
            
            if (empty($mensajes)) {
                error_log('No hay histórico de conversación');
                return '';
            }
            
            error_log('Encontrados ' . count($mensajes) . ' mensajes anteriores');
            
            $mensajes = array_reverse($mensajes);
            
            $contexto = "\n\n--- HISTÓRICO DE CONVERSACIÓN ANTERIOR ---\n";
            foreach ($mensajes as $msg) {
                $role = $msg['role'] === 'assistant' ? 'Asistente' : 'Cliente';
                $label = $msg['role_label'] ? ' (' . $msg['role_label'] . ')' : '';
                $text = isset($msg['text']) ? $msg['text'] : '';
                
                // Si el texto es JSON, extraer el contenido
                if (json_decode($text, true)) {
                    $json = json_decode($text, true);
                    $text = $json['content'] ?? $json['text'] ?? $text;
                }
                
                $contexto .= "{$role}{$label}: " . substr($text, 0, 100) . "\n";
            }
            $contexto .= "--- FIN HISTÓRICO ---\n";
            
            return $contexto;
            
        } catch (\Exception $e) {
            error_log('Error obteniendo contexto: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Llama a la API de IA general (coordina Gemini u OpenAI)
     */
    public function llamarAPIIA(string $mensaje, ?string $systemPrompt = null, ?int $idExpediente = null): ?string
    {
        try {
            $config = $this->obtenerConfiguracionIA();
            if (!$config) {
                throw new \Exception('No hay configuración de IA disponible');
            }

            $mensajeEnriquecido = $mensaje;
            if ($idExpediente) {
                error_log('llamarAPIIA: Obteniendo contexto histórico para expediente ' . $idExpediente);
                $contexto = $this->obtenerContextoConversacion($idExpediente);
                if ($contexto) {
                    error_log('llamarAPIIA: Contexto obtenido, inyectando en mensaje');
                    $mensajeEnriquecido = $contexto . "\n\nNuevo mensaje del cliente: " . $mensaje;
                    error_log('llamarAPIIA: Mensaje enriquecido, tamaño total: ' . strlen($mensajeEnriquecido) . ' chars');
                }
            }

            $provider = strtoupper($config['provider'] ?? 'GEMINI');

            switch ($provider) {
                case 'OPENAI':
                    return $this->llamarOpenAIAPI($mensajeEnriquecido, $systemPrompt, $config);
                case 'GEMINI':
                default:
                    return $this->llamarGeminiAPI($mensajeEnriquecido, $systemPrompt, $config);
            }
        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en llamarAPIIA', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Llama a la API de Google Gemini para generar respuestas con IA
     */
    public function llamarGeminiAPI(string $mensaje, ?string $systemPrompt = null, ?array $config = null): ?string
    {
        error_log('=== INICIO llamarGeminiAPI (TEXTO) ===');
        error_log('Mensaje input: ' . substr($mensaje, 0, 50) . '...');
        
        try {
            if (!$config) {
                error_log('Obteniendo configuración...');
                $config = $this->obtenerConfiguracionIA();
                if (!$config) {
                    throw new \Exception('No se pudo obtener configuración de Gemini');
                }
            }

            $apiKey = $config['api_key'] ?? null;
            $apiUrl = $config['api_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models';
            $model = $config['model'] ?? 'gemini-pro';
            $temperatura = $config['temperatura'] ?? 0.7;
            $maxTokens = $config['max_tokens'] ?? 1024;
            $topP = $config['top_p'] ?? 0.95;
            $topK = $config['top_k'] ?? 40;

            error_log('GeminiAPI - apiKey: ' . ($apiKey ? 'SÍ' : 'NO'));
            error_log('GeminiAPI - model: ' . $model);
            error_log('GeminiAPI - temperatura: ' . $temperatura);

            if (!$apiKey) {
                throw new \Exception('GEMINI_API_KEY no está configurada');
            }

            $finalSystemPrompt = $systemPrompt 
                ?: ($config['system_prompt'] ?? 'Eres un asistente útil y amigable de WhatsApp. Responde de forma concisa, siempre en español. Sé amable y profesional.');

            $fullPrompt = "Sistema: $finalSystemPrompt\n\nMensaje del usuario: $mensaje";
            error_log('GeminiAPI - fullPrompt length: ' . strlen($fullPrompt));

            $url = "{$apiUrl}/{$model}:generateContent?key={$apiKey}";
            error_log('GeminiAPI - URL: ' . str_replace($apiKey, '***', $url));

            $payload = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $fullPrompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => (float)$temperatura,
                    'topK' => (int)$topK,
                    'topP' => (float)$topP,
                    'maxOutputTokens' => (int)$maxTokens
                ]
            ]);

            error_log('GeminiAPI - Payload size: ' . strlen($payload) . ' bytes');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            error_log('GeminiAPI - Enviando request a API...');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log('GeminiAPI - HTTP Code: ' . $httpCode);
            error_log('GeminiAPI - Response size: ' . strlen($response) . ' bytes');

            if ($curlError) {
                error_log('GeminiAPI - cURL Error: ' . $curlError);
                throw new \Exception('cURL Error: ' . $curlError);
            }

            if ($httpCode !== 200) {
                error_log('GeminiAPI - Error response (primeros 300 chars): ' . substr($response, 0, 300));
                throw new \Exception("Gemini API retornó HTTP $httpCode: " . substr($response, 0, 100));
            }

            error_log('GeminiAPI - Decodificando JSON...');
            $responseData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('GeminiAPI - JSON Error: ' . json_last_error_msg());
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            error_log('GeminiAPI - Response keys: ' . json_encode(array_keys($responseData ?? [])));

            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $respuesta = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                error_log('GeminiAPI - ✓ Respuesta obtenida: ' . strlen($respuesta) . ' chars');
                
                if ($this->container->has('logger')) {
                    $this->container->get('logger')->info('Respuesta generada por Gemini', [
                        'modelo' => $model,
                        'mensaje_chars' => strlen($mensaje),
                        'respuesta_chars' => strlen($respuesta)
                    ]);
                }

                error_log('=== FIN llamarGeminiAPI (ÉXITO) ===');
                return $respuesta;
            } else {
                error_log('GeminiAPI - ERROR: Estructura no encontrada');
                error_log('GeminiAPI - Full response: ' . json_encode($responseData));
                throw new \Exception('Estructura de respuesta de Gemini inesperada');
            }

        } catch (\Exception $e) {
            error_log('GeminiAPI - EXCEPCIÓN: ' . $e->getMessage());
            error_log('GeminiAPI - Stack: ' . $e->getTraceAsString());
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en llamarGeminiAPI', [
                    'error' => $e->getMessage()
                ]);
            }
            error_log('=== FIN llamarGeminiAPI (ERROR) ===');
            return null;
        }
    }

    /**
     * Llama a la API de OpenAI para generar respuestas con IA
     */
    public function llamarOpenAIAPI(string $mensaje, ?string $systemPrompt = null, ?array $config = null): ?string
    {
        try {
            if (!$config) {
                $config = $this->obtenerConfiguracionIA();
                if (!$config || strtoupper($config['provider']) !== 'OPENAI') {
                    throw new \Exception('No se pudo obtener configuración de OpenAI');
                }
            }

            $apiKey = $config['api_key'] ?? null;
            $apiUrl = $config['api_url'] ?? 'https://api.openai.com/v1';
            $model = $config['model'] ?? 'gpt-3.5-turbo';
            $temperatura = $config['temperatura'] ?? 0.7;
            $maxTokens = $config['max_tokens'] ?? 1024;

            if (!$apiKey) {
                throw new \Exception('OPENAI_API_KEY no está configurada');
            }

            $finalSystemPrompt = $systemPrompt 
                ?: ($config['system_prompt'] ?? 'Eres un asistente útil y amigable de WhatsApp. Responde de forma concisa, siempre en español. Sé amable y profesional.');

            $url = "{$apiUrl}/chat/completions";

            $payload = json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $finalSystemPrompt],
                    ['role' => 'user', 'content' => $mensaje]
                ],
                'temperature' => (float)$temperatura,
                'max_tokens' => (int)$maxTokens
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Content-Length: ' . strlen($payload)
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('cURL Error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception("OpenAI API retornó HTTP $httpCode");
            }

            $responseData = json_decode($response, true);

            if (isset($responseData['choices'][0]['message']['content'])) {
                $respuesta = $responseData['choices'][0]['message']['content'];
                
                if ($this->container->has('logger')) {
                    $this->container->get('logger')->info('Respuesta generada por OpenAI', [
                        'modelo' => $model,
                        'mensaje_chars' => strlen($mensaje),
                        'respuesta_chars' => strlen($respuesta)
                    ]);
                }

                return trim($respuesta);
            } else {
                throw new \Exception('Estructura de respuesta de OpenAI inesperada');
            }

        } catch (\Exception $e) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error('Error en llamarOpenAIAPI', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Analiza un mensaje entrante con IA para extraer datos del expediente
     * IMPORTANTE: Ahora aplica condiciones ANTES de enviar a IA
     */
    public function analizarMensajeParaDatos($mensaje, $idExpediente, ?array $metadatosCampos = null, ?array $datosFase1 = null)
    {
        $this->logear('DEBUG: ¡¡¡ DENTRO DE analizarMensajeParaDatos() !!!');
        $this->logear('DEBUG: mensaje="' . substr($mensaje ?? '', 0, 50) . '", idExpediente=' . $idExpediente . ', metadatos=' . (is_array($metadatosCampos) ? count($metadatosCampos) : 'null') . ', datosFase1=' . ($datosFase1 ? 'SÍ' : 'NO'));
        
        if (!$mensaje) {
            $this->logear('DEBUG: mensaje está vacío, retornando null');
            return null;
        }

        $mensaje = trim($mensaje);
        $this->logear('=== INICIO analizarMensajeParaDatos con IA ===');
        $this->logear('Mensaje: ' . substr($mensaje, 0, 100) . '...');

        // Si no tenemos datosFase1, obtenerlos
        if (!$datosFase1) {
            $this->logear('DEBUG: datosFase1 no proporcionado, obteniendo desde BD...');
            $datosFase1 = $this->obtenerDatosFase1($idExpediente, $this->getDoctrine()->getConnection());
        }

        if ($metadatosCampos === null) {
            // Obtener campos requeridos SIN condiciones
            $camposRequeridos = $this->obtenerCamposRequeridos();
            
            // Crear array temporal para aplicar condiciones
            $camposFaltantesTmp = [];
            foreach ($camposRequeridos as $idCampo) {
                $camposFaltantesTmp[] = [
                    'nombre' => "Campo {$idCampo}",
                    'valor' => '',
                    'id_campo_hito' => $idCampo
                ];
            }
            
            // Aplicar condiciones ANTES de cargar metadatos
            $this->logear('DEBUG: Aplicando condiciones ANTES de cargar metadatos para IA...');
            $this->aplicarCondicionesCondicionales($camposFaltantesTmp, $datosFase1);
            
            // Extraer solo los IDs de campos después de aplicar condiciones
            $camposConCondiciones = array_map(function($c) { return $c['id_campo_hito']; }, $camposFaltantesTmp);
            $this->logear('DEBUG: Campos después de aplicar condiciones: ' . implode(', ', $camposConCondiciones));
            
            // Ahora cargar metadatos SOLO de los campos que quedan después de condiciones
            $metadatosCampos = $this->obtenerMetadatosCampos($camposConCondiciones);
        }

        $systemPrompt = $this->construirPromptDinamico($metadatosCampos);
        $this->logear('DEBUG: Sistema prompt generado dinámicamente. Tipos de campos: ' . implode(', ', array_keys($metadatosCampos)));
        $this->logear('DEBUG: systemPrompt enviado a IA (primeros 300 chars): ' . substr($systemPrompt, 0, 300));

        try {
            $this->logear('DEBUG: Llamando a llamarAPIIA()...');
            $respuestaIA = $this->llamarAPIIA($mensaje, $systemPrompt, $idExpediente);
            
            $this->logear('DEBUG: respuestaIA=' . ($respuestaIA ? 'SÍ (' . strlen($respuestaIA) . ' chars)' : 'NULL'));
            $this->logear('DEBUG: respuestaIA COMPLETA: ' . substr($respuestaIA ?? '', 0, 500));
            
            if (!$respuestaIA) {
                $this->logear('✗ IA no retornó respuesta, intentando fallback con regex');
                return $this->analizarMensajeParaDatosConRegex($mensaje);
            }

            $respuestaIA = preg_replace('/^```json\s*/', '', $respuestaIA);
            $respuestaIA = preg_replace('/\s*```$/', '', $respuestaIA);
            $respuestaIA = preg_replace('/^```\s*/', '', $respuestaIA);
            $this->logear('DEBUG: respuestaIA DESPUÉS DE LIMPIAR: ' . substr($respuestaIA ?? '', 0, 500));

            error_log('IA respuesta: ' . substr($respuestaIA, 0, 200));

            $datosExtraidos = json_decode($respuestaIA, true);
            
            if (!$datosExtraidos || !is_array($datosExtraidos)) {
                error_log('✗ No se pudo parsear respuesta de IA como JSON, usando fallback regex');
                return $this->analizarMensajeParaDatosConRegex($mensaje);
            }

            $mapoCampos = $metadatosCampos;

            $resultado = [
                'mensaje_original' => substr($mensaje, 0, 200),
                'longitud_mensaje' => strlen($mensaje),
                'campos_encontrados' => [],
                'puede_procesar' => false,
                'metodo' => 'IA'
            ];

            if (isset($datosExtraidos['campos_encontrados']) && is_array($datosExtraidos['campos_encontrados'])) {
                foreach ($datosExtraidos['campos_encontrados'] as $campo) {
                    $tipo = strtolower($campo['tipo'] ?? '');
                    $valor = trim($campo['valor'] ?? '');
                    $nombre = $campo['nombre'] ?? 'desconocido';
                    
                    $this->logear("DEBUG IA: tipo='{$tipo}' | valor='{$valor}' | nombre='{$nombre}'");
                    $this->logear('DEBUG IA: mapoCampos keys: ' . implode(', ', array_keys($mapoCampos)));
                    
                    if (!empty($valor)) {
                        $campoEncontrado = null;
                        
                        foreach ($mapoCampos as $mapoInfo) {
                            if (isset($mapoInfo['tipo']) && strtolower($mapoInfo['tipo']) === $tipo) {
                                $campoEncontrado = $mapoInfo;
                                break;
                            }
                        }
                        
                        if (!$campoEncontrado) {
                            $nombreLower = strtolower($nombre);
                            foreach ($mapoCampos as $mapoInfo) {
                                $mapoNombreLower = strtolower($mapoInfo['nombre'] ?? '');
                                if ($mapoNombreLower === $nombreLower || 
                                    (strpos($mapoNombreLower, $nombreLower) !== false || 
                                     strpos($nombreLower, $mapoNombreLower) !== false)) {
                                    $campoEncontrado = $mapoInfo;
                                    $this->logear("✓ Campo encontrado por NOMBRE: {$nombre} → {$mapoInfo['nombre']}");
                                    break;
                                }
                            }
                        }
                        
                        if ($campoEncontrado) {
                            $resultado['campos_encontrados'][] = [
                                'tipo' => $tipo,
                                'nombre_campo' => $campoEncontrado['nombre'],
                                'campo_id' => $campoEncontrado['id'],
                                'valor' => $valor
                            ];
                            $this->logear("✓ Dato extraído por IA [{$tipo}]: {$campoEncontrado['nombre']} = {$valor}");
                        } else {
                            $this->logear("✗ Campo '{$nombre}' (tipo: {$tipo}) no encontrado en mapoCampos. Valores válidos: " . implode(', ', array_keys($mapoCampos)));
                        }
                    } else {
                        $this->logear("✗ Valor vacío para tipo '{$tipo}'");
                    }
                }
            }

            $resultado['puede_procesar'] = !empty($resultado['campos_encontrados']);
            
            $this->logear('=== FIN analizarMensajeParaDatos (IA) ===');
            $this->logear('IA encontró: ' . count($resultado['campos_encontrados']) . ' campos');
            return $resultado;

        } catch (\Exception $e) {
            $this->logear('✗ Excepción en IA: ' . $e->getMessage() . ', usando fallback regex');
            return $this->analizarMensajeParaDatosConRegex($mensaje);
        }
    }

    /**
     * Fallback: Analiza mensaje con regex cuando IA no está disponible
     */
    public function analizarMensajeParaDatosConRegex($mensaje)
    {
        error_log('=== FALLBACK: analizarMensajeParaDatosConRegex ===');
        
        $datosEncontrados = [];
        $mensaje = trim($mensaje);

        $patrones = [
            'nacionalidad' => [
                'regex' => '/^(?:nacionalidad|país|origen)\s*[:\s]?\s*(.+)$/im',
                'fallback_countries' => ['españa', 'china', 'rusia', 'india', 'brasil', 'mexicana?', 'francesa?', 'italiana?', 'alemana?', 'portuguesa?', 'holandesa?', 'belga', 'suiza', 'sueca?', 'noruega?', 'dinamarca', 'austria', 'grecia', 'turquía', 'marruecos', 'argelia', 'francia', 'reino unido', 'estados?\\s*unidos?', 'usa', 'canadá', 'argentina', 'colombia', 'venezuela', 'perú', 'chile', 'ecuador', 'paraguay', 'uruguay', 'bolivia', 'costa\\s*rica', 'panamá', 'guatemala', 'honduras', 'el\\s*salvador', 'nicaragua', 'república\\s*dominicana', 'cuba', 'haiti', 'jamaica', 'filipinas', 'tailandia', 'vietnam', 'malasia', 'singapur', 'indonesia', 'camboya', 'bangladesh', 'pakistán', 'afganistán', 'irak', 'irán', 'israel', 'líbano', 'siria', 'jordania', 'arabia\\s*saudita', 'emiratos\\s*árabes', 'qatar', 'bahréin', 'omán', 'yemen', 'kuwait', 'méxico', 'japón', 'corea\\s*del\\s*sur', 'corea\\s*del\\s*norte', 'taiwán', 'mongolia', 'kazajstán', 'uzbekistán', 'turkmenistán', 'kirguistán', 'tayikistán', 'zimbabue', 'botsuana', 'namibia', 'sudáfrica', 'kenia', 'uganda', 'tanzania', 'mozambique', 'angola', 'camerún', 'costa\\s*de\\s*marfil', 'ghana', 'senegal', 'mali', 'níger', 'chad', 'sudán', 'etiopía', 'somalia', 'mauricio', 'seychelles'],
                'campo_id' => 195,
                'nombre' => 'Nacionalidad'
            ],
            'fecha_nacimiento' => [
                'regex' => '/(?:nacimiento|fecha\s+nac|nac\.|dob|fec\s+nac)\s*[:\s]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
                'campo_id' => 196,
                'nombre' => 'Fecha de nacimiento'
            ],
            'hijos' => [
                'regex' => '/(?:hijos|¿cuántos\s+hijos|tengo)\s*[:\s]?\s*(\w+)/i',
                'fallback_numbers' => ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                'campo_id' => 199,
                'nombre' => '¿Cuántos hijos tienes?'
            ],
            'telefono' => [
                'regex' => '/(?:tél|teléfono|móvil|celular|tlf)\s*[:\s]?\s*(\d{9,12})/i',
                'campo_id' => 408,
                'nombre' => 'Teléfono'
            ],
            'email' => [
                'regex' => '/(?:email|correo|e-mail|mail)\s*[:\s]?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
                'campo_id' => 409,
                'nombre' => 'Email'
            ],
            'dni' => [
                'regex' => '/(?:dni|dni\.|nif|nie|pasaporte|doc|documento)\s*[:\s]?\s*([a-zA-Z0-9]{8,12})/i',
                'campo_id' => 194,
                'nombre' => 'DNI'
            ],
            'importe' => [
                'regex' => '/(\d+(?:[.,]\d{2})?)\s*(?:euros?|€|eur)?/i',
                'campo_id' => 212,
                'nombre' => 'Importe'
            ]
        ];

        foreach ($patrones as $tipo => $config) {
            $valor = null;
            
            if (preg_match($config['regex'], $mensaje, $matches)) {
                $valor = trim($matches[1]);
            } 
            elseif ($tipo === 'nacionalidad' && isset($config['fallback_countries'])) {
                $mensajeLower = strtolower($mensaje);
                foreach ($config['fallback_countries'] as $pais) {
                    if (preg_match('/\b' . preg_quote($pais) . '\b/i', $mensajeLower)) {
                        if (preg_match('/\b(' . preg_quote($pais) . ')\b/i', $mensaje, $m)) {
                            $valor = trim($m[1]);
                        }
                        break;
                    }
                }
            }
            elseif ($tipo === 'fecha_nacimiento') {
                $mensajeLimpio = preg_replace('/\s+/', '', $mensaje);
                
                if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/', $mensajeLimpio, $matches)) {
                    $valor = trim($matches[1]);
                }
                elseif (preg_match('/^(\d{1,2})[\/\-\.](\d{2,4})(\d{4})$/', $mensajeLimpio, $matches)) {
                    $dia = $matches[1];
                    $mesParte = $matches[2];
                    $anio = $matches[3];
                    
                    $mes = strlen($mesParte) > 2 ? substr($mesParte, -2) : $mesParte;
                    $valor = $dia . '/' . $mes . '/' . $anio;
                    $this->logear("Fecha reorganizada: {$mensajeLimpio} -> {$valor}");
                }
                elseif (preg_match('/^(\d{8})$/', $mensajeLimpio, $matches)) {
                    $digitos = $matches[1];
                    $valor = substr($digitos, 0, 2) . '/' . substr($digitos, 2, 2) . '/' . substr($digitos, 4, 4);
                    $this->logear("Fecha de 8 dígitos: {$digitos} -> {$valor}");
                }
            }
            elseif ($tipo === 'hijos' && isset($config['fallback_numbers'])) {
                $mensajeLower = strtolower($mensaje);
                foreach ($config['fallback_numbers'] as $numero) {
                    if (preg_match('/\b' . preg_quote($numero) . '\b/i', $mensajeLower)) {
                        if (preg_match('/\b(' . preg_quote($numero) . ')\b/i', $mensaje, $m)) {
                            $valor = trim($m[1]);
                        }
                        break;
                    }
                }
            }
            elseif ($tipo === 'importe') {
                if (preg_match('/(\d+(?:[.,]\d{2})?)(?:\s*(?:euros?|€|eur))?/i', $mensaje, $matches)) {
                    $numeroBruto = trim($matches[1]);
                    $valor = preg_replace('/[.,](?=\d{0,2}$)/', '.', $numeroBruto);
                    $valor = preg_replace('/[.,](?!\d{0,2}$)/', '', $valor);
                    $valor = str_replace('.', '', $valor);
                    $this->logear("Importe detectado: '{$numeroBruto}' -> '{$valor}'");
                }
            }
            
            if (!empty($valor)) {
                $datosEncontrados[] = [
                    'tipo' => $tipo,
                    'nombre_campo' => $config['nombre'],
                    'campo_id' => $config['campo_id'],
                    'valor' => $valor
                ];
                $this->logear("→ Dato detectado [regex-{$tipo}]: {$config['nombre']} = {$valor}");
            }
        }

        return [
            'mensaje_original' => substr($mensaje, 0, 200),
            'longitud_mensaje' => strlen($mensaje),
            'campos_encontrados' => $datosEncontrados,
            'puede_procesar' => !empty($datosEncontrados),
            'metodo' => 'REGEX'
        ];
    }

    /**
     * Métodos de gestión de campos, partes y configuración del formulario
     */
    public function obtenerCamposRequeridosPorParte($numeroParte = 1): array
    {
        $partes = [
            // Parte 1: Comenzamos (estructura inicial)
            1 => [456, 190],
            
            // Parte 2: Titular 1 - Datos Personales y Vivienda
            // Se excluye campo 218 (Comentarios - Sólo GN)
            2 => [192, 196, 194, 458, 211, 212, 213, 459, 215, 199, 195, 198, 200, 201, 202, 561],
            
            // Parte 3: Titular 1 - Datos Económicos y Otros préstamos
            // Se excluye campo 234 (Comentarios - Sólo GN)
            3 => [193, 464, 465, 466, 467, 468, 469, 470, 219, 220, 221, 563, 460, 222, 223, 224, 225, 226, 227, 228, 461, 229, 230, 232, 233, 401, 462, 377, 471, 472, 473, 474, 239, 244],
        ];
        
        return $partes[$numeroParte] ?? [];
    }

    public function obtenerCamposRequeridos(): array
    {
        $todosCampos = [];
        $numeroParte = 1;
        
        while (true) {
            $camposParte = $this->obtenerCamposRequeridosPorParte($numeroParte);
            if (empty($camposParte)) {
                break;
            }
            $todosCampos = array_merge($todosCampos, $camposParte);
            $numeroParte++;
        }
        
        return $todosCampos;
    }

    public function obtenerOpcionesCampos(): array
    {
        return [
            // Grupo Comenzamos
            456 => ['uno' => '355', 'dos' => '356'],
            190 => ['no' => '80', 'uno' => '79', 'dos' => '557'],
            
            // Grupo Datos Personales y Vivienda
            211 => ['propiedad' => '99', 'propia' => '99', 'propietario' => '99', 'propietaria' => '99', 'casa propia' => '99', 'mi casa' => '99', 'es mía' => '99', 'me pertenece' => '99', 'tengo' => '99', 'alquiler' => '100', 'alquilada' => '100', 'alquilado' => '100', 'en alquiler' => '100', 'vivo en un alquiler' => '100', 'vivo alquilado' => '100', 'alquilamos' => '100', 'piso de alquiler' => '100', 'casa de alquiler' => '100', 'otro' => '101', 'otra' => '101', 'situación' => '101', 'situacion' => '101', 'otra situación' => '101', 'prestarme' => '101', 'prestado' => '101', 'de familia' => '101', 'de un familiar' => '101', 'usuarios' => '101', 'otra cosa' => '101'],
            198 => ['soltero' => '81', 'solteru' => '81', 'separación de bienes' => '82', 'separacion de bienes' => '82', 'gananciales' => '189', 'pareja de hecho' => '83', 'separado' => '84', 'separada' => '84', 'divorciado' => '85', 'divorciada' => '85', 'viudo' => '86', 'viuda' => '86'],
            200 => ['recibo' => '87', 'pago' => '88', 'no' => '89'],
            561 => ['si' => '551', 'sí' => '551', 'no' => '552'],
            
            // Grupo Datos Económicos
            193 => ['autónomo' => '97', 'autonomo' => '97', 'autónom@' => '97', 'autonoma' => '97', 'autónoma' => '97', 'pensionista' => '98', 'pensionado' => '98', 'pensionada' => '98', 'jubilado' => '98', 'jubilada' => '98', 'empleado' => '102', 'empleada' => '102', 'emplead@' => '102', 'empleador' => '102', 'trabajador' => '102', 'trabajadora' => '102', 'mercantil' => '103', 'comerciante' => '103', 'empresario' => '103', 'empresaria' => '103'],
            465 => ['autónomo' => '362', 'autonomo' => '362', 'sociedad' => '363'],
            467 => ['si' => '364', 'sí' => '364', 'no' => '365'],
            221 => ['indefinido a tiempo completo' => '104', 'indefinido a tiempo parcial' => '105', 'indefinido discontinuo' => '106', 'funcionario' => '107', 'interinidad' => '108', 'temporal a tiempo completo' => '109', 'temporal a tiempo parcial' => '110', 'militar' => '357', 'personal laboral fijo' => '555'],
            460 => ['si' => '358', 'sí' => '358', 'no' => '359'],
            226 => ['0' => '111', 'cero' => '111', '1' => '112', 'uno' => '112', '2' => '113', 'dos' => '113', '3' => '114', 'tres' => '114', '4' => '115', 'cuatro' => '115'],
            461 => ['si' => '360', 'sí' => '360', 'no' => '361'],
            230 => ['si' => '116', 'sí' => '116', 'no' => '117'],
            233 => ['alquiler' => '118', 'pensiones' => '119', 'ayudas sociales' => '119', 'otros' => '120'],
            
            // Grupo Otros préstamos
            474 => ['si' => '368', 'sí' => '368', 'no' => '369'],
            239 => ['no' => '121', 'si' => '122', 'sí' => '122'],
            244 => ['si' => '123', 'sí' => '123', 'no' => '124'],
            
            // Campos sin opciones
            377 => ['si' => '190', 'sí' => '190', 'no' => '191'],
            472 => ['si' => '366', 'sí' => '366', 'no' => '367'],
            212 => [],
            213 => [],
            220 => []
        ];
    }

    public function obtenerOpcionesFormateadas(int $idCampo): ?string
    {
        $opcionesMapeo = $this->obtenerOpcionesCampos();
        
        if (!isset($opcionesMapeo[$idCampo])) {
            return null;
        }
        
        $opciones = $opcionesMapeo[$idCampo];
        $opcionesUnicas = array_unique(array_values($opciones));
        
        $mapeoInverso = [
            '81' => 'Solter@', '82' => 'Sep. de bienes', '189' => 'Gananciales', '83' => 'Pareja de hecho', '84' => 'Separad@', '85' => 'Divorciad@', '86' => 'Viud@',
            '99' => 'Propiedad', '100' => 'Alquiler', '101' => 'Otra',
            '551' => 'Sí', '552' => 'No',
            '355' => 'Uno', '356' => 'Dos',
            '80' => 'No', '79' => 'Uno', '557' => 'Dos',
            '97' => 'Autónomo', '98' => 'Pensionista', '102' => 'Empleado', '103' => 'Empresario',
            '111' => '0', '112' => '1', '113' => '2', '114' => '3', '115' => '4',
            '360' => 'Sí', '361' => 'No',
        ];
        
        $opcionesLegibles = [];
        foreach ($opcionesUnicas as $id) {
            if (isset($mapeoInverso[$id])) {
                $opcionesLegibles[] = $mapeoInverso[$id];
            }
        }
        
        if (empty($opcionesLegibles)) {
            return null;
        }
        
        return " (" . implode(" | ", $opcionesLegibles) . ")";
    }

    public function obtenerCondicionesCondicionales(): array
    {
        $cond = [
            // GRUPO DATOS PERSONALES Y VIVIENDA
            // 212: Cuota Alquiler - Solo si 211 (Domicilio) = '100' (Alquiler)
            212 => [
                'dependeDe' => 211,
                'valor' => '100',
                'tipoComparacion' => 'opcion'
            ],
            // 213: Años Alquiler - Solo si 211 (Domicilio) = '100' (Alquiler)
            213 => [
                'dependeDe' => 211,
                'valor' => '100',
                'tipoComparacion' => 'opcion'
            ],
            // 459: Cuéntanos otra situación - Solo si 211 (Domicilio) = '101' (Otra)
            459 => [
                'dependeDe' => 211,
                'valor' => '101',
                'tipoComparacion' => 'opcion'
            ],
            // 200: Pensión - Solo si 198 (Estado Civil) = '84' (Separado) o '85' (Divorciado)
            200 => [
                'dependeDe' => 198,
                'valores' => ['84', '85'],
                'tipoComparacion' => 'opcion'
            ],
            // 201: Pensión Alimenticia - Solo si 200 (Pensión) = '87' (Recibo) o '88' (Pago)
            201 => [
                'dependeDe' => 200,
                'valores' => ['87', '88'],
                'tipoComparacion' => 'opcion'
            ],
            // 202: Pensión Compensatoria - Solo si 200 (Pensión) = '87' (Recibo) o '88' (Pago)
            202 => [
                'dependeDe' => 200,
                'valores' => ['87', '88'],
                'tipoComparacion' => 'opcion'
            ],
            
            // GRUPO DATOS ECONÓMICOS
            // 219: Importe pensión - Solo si 193 (Tipo empleo) = '98' (Pensionista)
            219 => [
                'dependeDe' => 193,
                'valor' => '98',
                'tipoComparacion' => 'opcion'
            ],
            // 220: Nombre empresa - Solo si 193 (Tipo empleo) = '102' (Empleado) o '103' (Mercantil)
            220 => [
                'dependeDe' => 193,
                'valores' => ['102', '103'],
                'tipoComparacion' => 'opcion'
            ],
            // 464: Sector al que te dedicas - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            464 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 465: Autónomo o sociedad - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            465 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 466: Años con negocio - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            466 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 467: ¿Tienes nómina? - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            467 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 468: Cantidad mensual - Solo si 467 (Nómina) = '364' (Sí)
            468 => [
                'dependeDe' => 467,
                'valor' => '364',
                'tipoComparacion' => 'opcion'
            ],
            // 469: Beneficio declarado - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            469 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 470: Beneficio trimestral - Solo si 193 (Tipo empleo) = '97' (Autónomo)
            470 => [
                'dependeDe' => 193,
                'valor' => '97',
                'tipoComparacion' => 'opcion'
            ],
            // 221: Tipo de contrato - Solo si 193 (Tipo empleo) = '102' (Empleado) o '103' (Mercantil)
            221 => [
                'dependeDe' => 193,
                'valores' => ['102', '103'],
                'tipoComparacion' => 'opcion'
            ],
            // 563: Meses trabajas - Solo si 221 (Contrato) = '106' (Indefinido discontinuo)
            563 => [
                'dependeDe' => 221,
                'valor' => '106',
                'tipoComparacion' => 'opcion'
            ],
            // 460: Contrato larga duración - Solo si 221 (Contrato) = '357' (Militar)
            460 => [
                'dependeDe' => 221,
                'valor' => '357',
                'tipoComparacion' => 'opcion'
            ],
            // 222: Puesto de trabajo - Solo si 193 (Tipo empleo) = '102' (Empleado) o '103' (Mercantil)
            222 => [
                'dependeDe' => 193,
                'valores' => ['102', '103'],
                'tipoComparacion' => 'opcion'
            ],
            // 223: Antigüedad - Solo si 193 (Tipo empleo) = '102' (Empleado) o '103' (Mercantil)
            223 => [
                'dependeDe' => 193,
                'valores' => ['102', '103'],
                'tipoComparacion' => 'opcion'
            ],
            // 224: Fecha finalización contrato - Solo si 221 (Contrato) = '109' (Temporal tc) o '110' (Temporal tp)
            224 => [
                'dependeDe' => 221,
                'valores' => ['109', '110'],
                'tipoComparacion' => 'opcion'
            ],
            // 225: Nómina mensual - Solo si 193 (Tipo empleo) = '102' (Empleado) o '103' (Mercantil)
            225 => [
                'dependeDe' => 193,
                'valores' => ['102', '103'],
                'tipoComparacion' => 'opcion'
            ],
            // 229: Meses sin trabajar - Solo si 461 (Paro) = '360' (Sí)
            229 => [
                'dependeDe' => 461,
                'valor' => '360',
                'tipoComparacion' => 'opcion'
            ],
            // 232: Importe otro ingreso - Solo si 230 (Otro ingreso) = '116' (Sí)
            232 => [
                'dependeDe' => 230,
                'valor' => '116',
                'tipoComparacion' => 'opcion'
            ],
            // 233: Tipo de ingreso - Solo si 230 (Otro ingreso) = '116' (Sí)
            233 => [
                'dependeDe' => 230,
                'valor' => '116',
                'tipoComparacion' => 'opcion'
            ],
            // 401: Concepto ingresos - Solo si 233 (Tipo ingreso) = '120' (Otros)
            401 => [
                'dependeDe' => 233,
                'valor' => '120',
                'tipoComparacion' => 'opcion'
            ],
            
            // GRUPO OTROS - AVALOS
            // 471: Valor vivienda avalaría - Solo si 377 (Avalas otra propiedad) = '190' (Sí)
            471 => [
                'dependeDe' => 377,
                'valor' => '190',
                'tipoComparacion' => 'opcion'
            ],
            // 472: Libre de cargas - Solo si 377 (Avalas otra propiedad) = '190' (Sí)
            472 => [
                'dependeDe' => 377,
                'valor' => '190',
                'tipoComparacion' => 'opcion'
            ],
            // 473: Hipoteca restante - Solo si 472 (Libre de cargas) = '367' (No)
            473 => [
                'dependeDe' => 472,
                'valor' => '367',
                'tipoComparacion' => 'opcion'
            ]
        ];

        $this->logear("obtenerCondicionesCondicionales en " . __FILE__ . ":" . __LINE__);
        $this->logear("keys=" . implode(',', array_keys($cond)) . " count=" . count($cond));
        $this->logear("dump=" . var_export($cond, true));

        return $cond;
    }

    public function aplicarCondicionesCondicionales(&$camposFaltantesParte, array $datosFase1): void
    {
        $condiciones = $this->obtenerCondicionesCondicionales();
        
        $this->logear("=== INICIO aplicarCondicionesCondicionales - " . count($condiciones) . " condiciones a verificar ===");
        
        foreach ($condiciones as $idCampoCondicional => $condicion) {
            $idCampoPadre = $condicion['dependeDe'];
            $valoresEsperados = isset($condicion['valores']) ? (array)$condicion['valores'] : [$condicion['valor']];
            $tipoComparacion = $condicion['tipoComparacion'] ?? 'opcion';
            
            $this->logear("→ Procesando condición para campo {$idCampoCondicional}:");
            $this->logear("  - Depende de: {$idCampoPadre}");
            $this->logear("  - Valores esperados: " . implode(', ', $valoresEsperados));
            
            $campoPadre = $this->buscarCampoEnFase1($datosFase1, $idCampoPadre);
            
            if (!$campoPadre) {
                $this->logear("  ✗ Campo padre {$idCampoPadre} NO ENCONTRADO");
                continue;
            }
            
            $this->logear("DEBUG campoPadre completo: " . var_export($campoPadre, true));
            
            if ($tipoComparacion === 'opcion') {
                $valorActual = '';
                $idOpcionesObj = $campoPadre['id_opciones_campo'] ?? null;
                
                // Si es un objeto Doctrine (relación), extraer el ID
                if (is_object($idOpcionesObj)) {
                    // Intenta acceder como propiedad pública o getter
                    if (property_exists($idOpcionesObj, 'idOpcionesCampo')) {
                        $valorActual = (string)$idOpcionesObj->idOpcionesCampo;
                        $this->logear("  - id_opciones_campo es un objeto, extraído idOpcionesCampo: '{$valorActual}'");
                    } elseif (method_exists($idOpcionesObj, 'getIdOpcionesCampo')) {
                        $valorActual = (string)$idOpcionesObj->getIdOpcionesCampo();
                        $this->logear("  - id_opciones_campo es un objeto, extraído vía getter: '{$valorActual}'");
                    } else {
                        $valorActual = trim((string)$idOpcionesObj);
                        $this->logear("  - id_opciones_campo es un objeto desconocido, conversión a string: '{$valorActual}'");
                    }
                } else {
                    // Es un valor primitivo
                    $valorActual = trim((string)($idOpcionesObj ?? ''));
                    
                    if (empty($valorActual) && !empty($campoPadre['valor'])) {
                        if (preg_match('/opcion_(\d+)/', $campoPadre['valor'], $matches)) {
                            $valorActual = $matches[1];
                            $this->logear("  - id_opciones_campo vacío, extraído de valor: '{$valorActual}'");
                        }
                    }
                }
                
                $this->logear("  - Valor actual (opcion): '{$valorActual}' (tipo: " . gettype($valorActual) . ")");
            } else {
                $valorActual = trim($campoPadre['valor'] ?? '');
                $this->logear("  - Valor actual (valor): '{$valorActual}' (tipo: " . gettype($valorActual) . ")");
            }
            
            $cumpleCondicion = false;
            foreach ($valoresEsperados as $valorEsperado) {
                if ($valorActual === (string)$valorEsperado) {
                    $cumpleCondicion = true;
                    break;
                }
            }
            
            $this->logear("  - ¿Cumple condición?: " . ($cumpleCondicion ? 'SÍ ✓' : 'NO ✗'));
            
            if ($cumpleCondicion) {
                $campoCondicional = $this->buscarCampoEnFase1($datosFase1, $idCampoCondicional);
                $valorCondicional = $campoCondicional ? trim($campoCondicional['valor'] ?? '') : '';
                
                $this->logear("  - Campo condicional existe: " . ($campoCondicional ? 'SÍ' : 'NO'));
                $this->logear("  - Valor condicional: '" . $valorCondicional . "' (vacío: " . (empty($valorCondicional) ? 'SÍ' : 'NO') . ")");
                
                if (empty($valorCondicional)) {
                    if (!$campoCondicional) {
                        $nombreCampo = $this->obtenerNombreCampoDesdeBD($idCampoCondicional);
                        $camposFaltantesParte[] = [
                            'nombre' => $nombreCampo ?: "Campo {$idCampoCondicional}",
                            'valor' => '',
                            'id_campo_hito' => $idCampoCondicional
                        ];
                        $this->logear("  ✓ Campo {$idCampoCondicional} AGREGADO (no existía)");
                    } else {
                        $camposFaltantesParte[] = $campoCondicional;
                        $this->logear("  ✓ Campo {$idCampoCondicional} AGREGADO (vacío/null)");
                    }
                } else {
                    $this->logear("  ✓ Campo {$idCampoCondicional} ya tiene valor");
                }
            } else {
                $countAntes = count($camposFaltantesParte);
                $camposFaltantesParte = array_filter($camposFaltantesParte, function($c) use ($idCampoCondicional) {
                    return ($c['id_campo_hito'] ?? null) !== $idCampoCondicional;
                });
                $countDespues = count($camposFaltantesParte);
                if ($countAntes !== $countDespues) {
                    $this->logear("  ✓ Campo {$idCampoCondicional} REMOVIDO (condición no cumplida)");
                } else {
                    $this->logear("  ✓ Campo {$idCampoCondicional} no estaba en la lista");
                }
            }
        }
        
        $this->logear("=== FIN aplicarCondicionesCondicionales - Total campos: " . count($camposFaltantesParte) . " ===");
    }

    public function obtenerMetadatosCampos(array $idsCampos): array
    {
        try {
            $conn = $this->getDoctrine()->getConnection();
            $mapoCampos = [];
            
            foreach ($idsCampos as $idCampo) {
                $sql = 'SELECT id_campo_hito, nombre, tipo FROM campo_hito WHERE id_campo_hito = :id LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('id', (int)$idCampo);
                $stmt->execute();
                $campo = $stmt->fetch();
                
                if ($campo) {
                    $nombreLower = strtolower($campo['nombre']);
                    $tipoExtraccion = 'generico';
                    
                    if (strpos($nombreLower, 'nacionalidad') !== false) {
                        $tipoExtraccion = 'nacionalidad';
                    } elseif (strpos($nombreLower, 'fecha') !== false && strpos($nombreLower, 'nacimiento') !== false) {
                        $tipoExtraccion = 'fecha_nacimiento';
                    } elseif (strpos($nombreLower, 'hijos') !== false) {
                        $tipoExtraccion = 'hijos';
                    } elseif (strpos($nombreLower, 'titulares') !== false) {
                        $tipoExtraccion = 'titulares';
                    } elseif (strpos($nombreLower, 'teléfono') !== false || strpos($nombreLower, 'telefono') !== false) {
                        $tipoExtraccion = 'telefono';
                    } elseif (strpos($nombreLower, 'email') !== false || strpos($nombreLower, 'correo') !== false) {
                        $tipoExtraccion = 'email';
                    } elseif (strpos($nombreLower, 'dni') !== false || strpos($nombreLower, 'nif') !== false || strpos($nombreLower, 'nie') !== false || strpos($nombreLower, 'tarjeta') !== false) {
                        $tipoExtraccion = 'dni';
                    } elseif (strpos($nombreLower, 'cuota') !== false || strpos($nombreLower, 'alquiler') !== false || strpos($nombreLower, 'importe') !== false) {
                        $tipoExtraccion = 'importe';
                    }
                    
                    $clave = $tipoExtraccion . '_' . $idCampo;
                    $mapoCampos[$clave] = [
                        'id' => (int)$idCampo,
                        'nombre' => $campo['nombre'],
                        'tipo_dato' => (int)$campo['tipo'],
                        'tipo' => $tipoExtraccion
                    ];
                    
                    $this->logear("✓ Campo dinámico cargado: [{$tipoExtraccion}] {$campo['nombre']} (ID: {$idCampo})");
                } else {
                    $this->logear("⚠ Campo ID {$idCampo} no encontrado en BD");
                }
            }
            
            return $mapoCampos;
            
        } catch (\Exception $e) {
            $this->logear('✗ Error obteniendo metadatos de campos: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerProximaParteYCamposFaltantes(int $idExpediente, array $datosFase1): array
    {
        $numeroParte = 1;
        $numeroParteAnterior = 0;
        
        while (true) {
            $camposParte = $this->obtenerCamposRequeridosPorParte($numeroParte);
            
            if (empty($camposParte)) {
                $this->logear("Todas las partes estan completas");
                return [
                    'numero_parte' => 0,
                    'numero_parte_anterior' => $numeroParteAnterior,
                    'campos_faltantes' => [],
                    'campos_faltantes_segmentados' => [],
                    'primer_segmento' => [],
                    'mensaje_completo' => '✓ ¡Expediente completado! Gracias por toda la información.'
                ];
            }
            
            $camposFaltantesParte = [];
            
            foreach ($camposParte as $idCampo) {
                $campo = $this->buscarCampoEnFase1($datosFase1, $idCampo);
                
                if (!$campo) {
                    $this->logear("Campo $idCampo no encontrado en datosFase1, considerado como faltante");
                    $nombreCampo = $this->obtenerNombreCampoDesdeBD($idCampo);
                    $camposFaltantesParte[] = [
                        'nombre' => $nombreCampo ?? "Campo $idCampo",
                        'valor' => '',
                        'id_campo_hito' => $idCampo,
                        'opciones' => $this->obtenerOpcionesFormateadas($idCampo)
                    ];
                    continue;
                }
                
                $valor = trim($campo['valor'] ?? '');
                
                if (empty($valor)) {
                    $nombreCampo = $this->obtenerNombreCampoDesdeBD($idCampo);
                    $camposFaltantesParte[] = [
                        'nombre' => $nombreCampo ?? ($campo['nombre'] ?? "Campo $idCampo"),
                        'valor' => '',
                        'id_campo_hito' => $idCampo,
                        'opciones' => $this->obtenerOpcionesFormateadas($idCampo)
                    ];
                    $this->logear("Campo $idCampo (vacío) considerado como faltante");
                }
            }
            
            $this->aplicarCondicionesCondicionales($camposFaltantesParte, $datosFase1);
            
            if (!empty($camposFaltantesParte)) {
                $this->logear("Parte $numeroParte incompleta: " . count($camposFaltantesParte) . " campos faltantes");
                
                // Segmentar los campos en grupos pequeños (2 campos por grupo)
                $camposSegmentados = $this->segmentarCampos($camposFaltantesParte, 2);
                $primerSegmento = reset($camposSegmentados) ?: [];
                
                // Obtener nombre del cliente directamente desde BD
                $nombreCliente = $this->obtenerNombreClienteDesdeDAtos($idExpediente);
                
                // Generar mensaje completo
                $mensajeCompleto = '';
                
                // Si es la primera parte y primera vez, agregar mensaje inicial
                if ($numeroParte === 1 && $numeroParteAnterior === 0) {
                    $mensajeCompleto = $this->generarMensajeInicial($nombreCliente);
                } else {
                    // Si es un segmento posterior
                    $mensajeCompleto = $this->generarMensajeSegmentoSiguiente($numeroParte, count($camposSegmentados));
                }
                
                // Agregar el segmento actual
                $mensajeSegmentado = $this->generarMensajeSegmentado($primerSegmento);
                $mensajeCompleto .= $mensajeSegmentado;
                
                return [
                    'numero_parte' => $numeroParte,
                    'numero_parte_anterior' => $numeroParteAnterior,
                    'campos_faltantes' => $camposFaltantesParte,
                    'campos_faltantes_segmentados' => $camposSegmentados,
                    'primer_segmento' => $primerSegmento,
                    'nombre_cliente' => $nombreCliente,
                    'mensaje_completo' => $mensajeCompleto
                ];
            }
            
            $this->logear("Parte $numeroParte completada, verificando siguiente...");
            $numeroParteAnterior = $numeroParte;
            $numeroParte++;
        }
    }

    /**
     * Obtiene el nombre del cliente directamente desde la BD
     * @param int $idExpediente ID del expediente
     * @return string Nombre del cliente o "Cliente" por defecto
     */
    public function obtenerNombreClienteDesdeDAtos(?int $idExpediente = null): string
    {
        $this->logear("DEBUG obtenerNombreClienteDesdeDAtos: idExpediente=" . ($idExpediente ?? 'NULL'));
        
        if (!$idExpediente) {
            $this->logear("DEBUG: idExpediente es nulo, retornando 'Cliente'");
            return 'Cliente';
        }
        
        try {
            $this->logear("DEBUG: Intentando obtener Doctrine");
            $doctrine = $this->getDoctrine();
            $this->logear("DEBUG: Doctrine obtenido, intentando obtener Manager");
            
            $em = $doctrine->getManager();
            $this->logear("DEBUG: EntityManager obtenido correctamente");
            
            // Obtener el expediente
            $this->logear("DEBUG: Buscando expediente {$idExpediente}");
            $expediente = $em->getRepository('AppBundle:Expediente')->findOneBy([
                'idExpediente' => $idExpediente
            ]);
            
            if (!$expediente) {
                $this->logear("DEBUG: Expediente {$idExpediente} no encontrado");
                return 'Cliente';
            }
            
            $this->logear("DEBUG: Expediente encontrado, obteniendo ID cliente");
            
            // Obtener el cliente relacionado
            $idCliente = $expediente->getIdCliente();
            if (!$idCliente) {
                $this->logear("DEBUG: Expediente sin cliente asignado");
                return 'Cliente';
            }
            
            $this->logear("DEBUG: ID cliente = {$idCliente}, buscando usuario");
            
            $cliente = $em->getRepository('AppBundle:Usuario')->findOneBy([
                'idUsuario' => $idCliente
            ]);
            
            if (!$cliente) {
                $this->logear("DEBUG: Cliente {$idCliente} no encontrado");
                return 'Cliente';
            }
            
            $this->logear("DEBUG: Cliente encontrado, obteniendo nombre");
            
            $nombre = $cliente->getNombre() ?? '';
            $apellidos = $cliente->getApellidos() ?? '';
            $this->logear("DEBUG: Nombre='{$nombre}', Apellidos='{$apellidos}'");
            
            $nombreCompleto = trim($nombre . ' ' . $apellidos);
            $nombreCompleto = trim($nombreCompleto);
            
            if (!empty($nombreCompleto)) {
                $this->logear("✓ Nombre obtenido de BD: '{$nombreCompleto}'");
                return $nombreCompleto;
            }
            
            $this->logear("DEBUG: Nombre vacío");
            return 'Cliente';
            
        } catch (\Exception $e) {
            $error_msg = $e->getMessage();
            $error_trace = $e->getTraceAsString();
            $this->logear('✗ Error obteniendo nombre: ' . $error_msg);
            $this->logear('✗ Trace: ' . substr($error_trace, 0, 500));
            return 'Cliente';
        } catch (\Throwable $t) {
            $this->logear('✗ Error fatal obteniendo nombre: ' . $t->getMessage());
            return 'Cliente';
        }
    }

    /**
     * Obtiene el teléfono del técnico o comercial del expediente
     * Si no existe técnico ni comercial, retorna null para que se use el teléfono del sistema
     * @param int $idExpediente ID del expediente
     * @return string|null Teléfono del técnico/comercial o null
     */
    public function obtenerTelefonoTecnicoDelExpediente(?int $idExpediente = null): ?string
    {
        if (!$idExpediente) {
            return null;
        }
        
        try {
            $em = $this->getDoctrine()->getManager();
            
            // Obtener el expediente con id_tecnico e id_comercial
            $expediente = $em->getRepository('AppBundle:Expediente')->findOneBy([
                'idExpediente' => $idExpediente
            ]);
            
            if (!$expediente) {
                $this->logear("DEBUG: Expediente {$idExpediente} no encontrado para obtener técnico");
                return null;
            }
            
            // Intentar obtener técnico primero
            $idTecnico = $expediente->getIdTecnico();
            if ($idTecnico) {
                $tecnico = $em->getRepository('AppBundle:Usuario')->find($idTecnico);
                if ($tecnico && $tecnico->getTelefonoMovil()) {
                    $this->logear("✓ Teléfono del técnico obtenido: {$tecnico->getTelefonoMovil()}");
                    return $tecnico->getTelefonoMovil();
                }
            }
            
            // Si no hay técnico, intentar comercial
            $idComercial = $expediente->getIdComercial();
            if ($idComercial) {
                $comercial = $em->getRepository('AppBundle:Usuario')->find($idComercial);
                if ($comercial && $comercial->getTelefonoMovil()) {
                    $this->logear("✓ Teléfono del comercial obtenido: {$comercial->getTelefonoMovil()}");
                    return $comercial->getTelefonoMovil();
                }
            }
            
            // Ninguno tiene teléfono asignado
            $this->logear("DEBUG: Expediente {$idExpediente} sin técnico/comercial con teléfono, usando sistema");
            return null;
            
        } catch (\Exception $e) {
            $this->logear('✗ Error obteniendo teléfono del técnico: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Segmenta un array de campos en grupos pequeños para no abrumar al usuario
     * @param array $campos Array de campos a segmentar
     * @param int $tamanioGrupo Cantidad de campos por grupo (default 2)
     * @return array Array de arrays, cada uno conteniendo N campos
     */
    public function segmentarCampos(array $campos, int $tamanioGrupo = 2): array
    {
        $segmentados = [];
        $grupoActual = [];
        
        foreach ($campos as $campo) {
            $grupoActual[] = $campo;
            
            if (count($grupoActual) >= $tamanioGrupo) {
                $segmentados[] = $grupoActual;
                $grupoActual = [];
            }
        }
        
        // Agregar el último grupo si tiene elementos
        if (!empty($grupoActual)) {
            $segmentados[] = $grupoActual;
        }
        
        return $segmentados;
    }

    /**
     * Genera un mensaje inicial personalizado para comenzar a pedir datos
     * @param string $nombreCliente Nombre del cliente
     * @return string Mensaje inicial
     */
    public function generarMensajeInicial(string $nombreCliente): string
    {
        $nombre = explode(' ', trim($nombreCliente))[0];
        
        $this->logear("DEBUG: generarMensajeInicial - nombreCliente='{$nombreCliente}' | nombre extraído='{$nombre}'");
        
        $mensaje = "Hola, {$nombre}. 👋\n\n";
        $mensaje .= "Somos del equipo de Hipotea. Nos ponemos en contacto contigo para continuar con tu trámite de hipoteca.\n\n";
        $mensaje .= "Para poder avanzar, necesitamos que completes la siguiente información:\n\n";
        
        return $mensaje;
    }

    /**
     * Genera un mensaje segmentado para pedir pocos campos a la vez
     * Evita que el usuario reciba demasiadas preguntas de una sola vez
     * @param array $campos Array de campos a pedir (máx 2-3)
     * @return string Mensaje formateado
     */
    public function generarMensajeSegmentado(array $campos): string
    {
        if (empty($campos)) {
            return '¡Perfecto! Gracias por toda la información 😊';
        }
        
        $mensaje = '';
        
        foreach ($campos as $campo) {
            $nombre = $campo['nombre'] ?? 'Campo desconocido';
            $opciones = $campo['opciones'] ?? '';
            $mensaje .= "* " . $nombre . $opciones . "\n";
        }
        
        $mensaje .= "\nCuando puedas, nos lo haces saber. ¡Muchas gracias! 😊";
        
        return $mensaje;
    }

    /**
     * Genera el mensaje siguiente cuando quedan más segmentos
     * Usa IA para generar variaciones humanizadas
     * @param int $numSegmento Número de segmento (1-based)
     * @param int $totalSegmentos Total de segmentos
     * @return string Prefijo del mensaje
     */
    public function generarMensajeSegmentoSiguiente(int $numSegmento, int $totalSegmentos): string
    {
        // Opciones de saludos variados (para humanizar)
        $saludos = [
            "¡Perfecto! Gracias por esos datos. ✓",
            "¡Excelente! Gracias por tu información. ✓",
            "¡Genial! Muchas gracias por esos datos. ✓",
            "¡Muy bien! Gracias por completar esa información. ✓",
            "¡Perfecto! Apreciamos tu información. ✓",
        ];
        
        // Opciones de continuación (para humanizar)
        $continuaciones = [
            "Ahora necesitamos un poco más de información:",
            "Continuemos con los siguientes datos:",
            "Por favor, ayúdanos a completar estos datos:",
            "Nos gustaría que nos proporcionaras lo siguiente:",
            "Para poder avanzar, necesitamos que completes esto:",
            "El siguiente paso es proporcionarnos esto:",
        ];
        
        // Seleccionar aleatoriamente para variar
        $saludo = $saludos[array_rand($saludos)];
        $continuacion = $continuaciones[array_rand($continuaciones)];
        
        $mensaje = $saludo . "\n\n";
        $mensaje .= $continuacion . "\n";
        
        return $mensaje;
    }

    public function obtenerNombreCampoDesdeBD(int $idCampo): ?string
    {
        try {
            $conn = $this->getDoctrine()->getConnection();
            $sql = 'SELECT nombre FROM campo_hito WHERE id_campo_hito = :id LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('id', $idCampo);
            $stmt->execute();
            $resultado = $stmt->fetch();
            return $resultado ? $resultado['nombre'] : null;
        } catch (\Exception $e) {
            $this->logear('Error obteniendo nombre de campo ' . $idCampo . ': ' . $e->getMessage());
            return null;
        }
    }

    public function construirPromptDinamico(array $metadatos): string
    {
        if (empty($metadatos)) {
            return 'Eres un extractor de datos especializado en identificar información de expedientes. Tu tarea es analizar el mensaje del cliente y extraer datos relevantes. Responde SIEMPRE con JSON válido.';
        }
        
        $listaCampos = [];
        foreach ($metadatos as $tipo => $info) {
            $listaCampos[] = "- " . $info['nombre'] . " ({$tipo})";
        }
        
        $textoLista = implode("\n            ", $listaCampos);
        
        $instruccionesOpciones = '';
        $opcionesCampos = $this->obtenerOpcionesCampos();
        foreach ($opcionesCampos as $campoId => $opciones) {
            $opcionesTexto = implode(", ", array_keys($opciones));
            $instruccionesOpciones .= "\n- Para \"¿Cuántos titulares sois?\": Solo responde con: " . $opcionesTexto;
        }
        
        $prompt = 'INSTRUCCIÓN CRÍTICA: Debes SIEMPRE responder con un JSON válido, nada más, nada menos. No incluyas ningún otro texto fuera del JSON.

            Eres un extractor de datos especializado en identificar información de expedientes.
            Tu tarea es analizar el mensaje del cliente y extraer SOLO los datos que encuentres en uno de estos campos:
                        ' . $textoLista . '

            FORMATO DE RESPUESTA (OBLIGATORIO - DEBES RESPONDER SIEMPRE CON ESTE JSON):
            {"campos_encontrados": [{"tipo": "tipo_campo", "nombre": "Nombre del campo", "valor": "valor encontrado"}], "hay_datos": true/false}

            SI NO HAY DATOS, RESPONDE EXACTAMENTE CON:
            {"campos_encontrados": [], "hay_datos": false}

            INSTRUCCIONES DE EXTRACCIÓN:
            - Solo extrae si la información está clara en el mensaje
            - No inventes datos
            - Para fechas, normaliza al formato DD/MM/YYYY
            - Para titulares/números, normaliza: "uno", "dos", "tres", etc.
            - Para DNI: Reconoce cualquier string de 8-12 caracteres alfanuméricos como posible DNI/NIE/Pasaporte (ej: Y4516744D, 12345678A, ABC123456)
            - Para Domicilio actual: Reconoce respuestas sobre vivienda como "propiedad", "alquiler", "alquilado", "vivo en alquiler", "casa propia", "es mía", etc.
            - Para IMPORTES (cuota alquiler, paga extra, ingresos): Reconoce números con o sin unidades de moneda (ej: "550", "550 euros", "550€", "550 EUR") y extrae solo el número (ej: valor extraído = "550")' . $instruccionesOpciones . '

            VALORES SIN ETIQUETA (IMPORTANTE):
            El usuario puede responder con SOLO EL VALOR, sin etiqueta ni explicación. En estos casos:
            - Si el mensaje es un valor que coincide con el tipo de uno de los campos requeridos, extráelo.

            Remembert: Tu respuesta DEBE SER SIEMPRE UN JSON VÁLIDO, nada más. No escribas explicaciones, solo el JSON.';
        
        return $prompt;
    }

    public function obtenerDatosFase1($idExpediente, $conn) 
    {
        $datosFase1 = [];

        try {
            $em = $this->getDoctrine()->getManager();

            $fase = $em->getRepository('AppBundle:Fase')->findOneBy([
                'orden' => 1
            ]);

            if (!$fase) {
                return ['error' => 'Fase 1 no encontrada'];
            }

            $hitos = $em->getRepository('AppBundle:Hito')->findBy(
                ['idFase' => $fase->getIdFase()],
                ['orden' => 'ASC']
            );

            $datosFase1['fase'] = [
                'id_fase' => $fase->getIdFase(),
                'hitos' => []
            ];

            foreach ($hitos as $hito) {
                $idHito = $hito->getIdHito();

                $hitosExp = $em->getRepository('AppBundle:HitoExpediente')->findBy([
                    'idHito' => $idHito,
                    'idExpediente' => $idExpediente
                ]);

                $datosPorHito = [
                    'nombre' => $hito->getNombre(),
                    'id_hito' => $idHito,
                    'grupos' => []
                ];

                foreach ($hitosExp as $hitoExp) {
                    $idHitoExp = $hitoExp->getIdHitoExpediente();

                    $grupos = $em->getRepository('AppBundle:GrupoHitoExpediente')->findBy([
                        'idHitoExpediente' => $idHitoExp
                    ]);

                    foreach ($grupos as $grupo) {
                        $datosPorGrupo = [
                            'id_grupo' => $grupo->getIdGrupoHitoExpediente(),
                            'campos' => []
                        ];

                        $campos = $em->getRepository('AppBundle:CampoHitoExpediente')->findBy([
                            'idGrupoHitoExpediente' => $grupo->getIdGrupoHitoExpediente()
                        ]);

                        foreach ($campos as $campo) {
                            $campoHito = $campo->getIdCampoHito();
                            $nombreCampo = $campoHito ? $campoHito->getNombre() : 'Campo sin nombre';
                            $tipoCampo = $campoHito ? $campoHito->getTipo() : null;

                            $datosPorCampo = [
                                'id_campo' => $campo->getIdCampoHitoExpediente(),
                                'id_campo_hito' => $campoHito ? $campoHito->getIdCampoHito() : null,
                                'nombre' => $nombreCampo,
                                'tipo' => $tipoCampo,
                                'valor' => $campo->getValor(),
                                'id_opciones_campo' => $campo->getIdOpcionesCampo(),
                                'archivos' => []
                            ];

                            $archivos = $em->getRepository('AppBundle:FicheroCampo')->findBy([
                                'idCampoHitoExpediente' => $campo->getIdCampoHitoExpediente(),
                                'idExpediente' => $idExpediente
                            ]);

                            foreach ($archivos as $archivo) {
                                $datosPorCampo['archivos'][] = [
                                    'id' => $archivo->getIdFicheroCampo(),
                                    'nombre_fichero' => $archivo->getNombreFichero(),
                                    'nombre_original' => $archivo->getNombreOriginal()
                                ];
                            }

                            $datosPorGrupo['campos'][] = $datosPorCampo;
                        }

                        $datosPorHito['grupos'][] = $datosPorGrupo;
                    }
                }

                $datosFase1['fase']['hitos'][] = $datosPorHito;
            }

            return $datosFase1;

        } catch (\Exception $e) {
            error_log('Error obteniendo datos de Fase 1: ' . $e->getMessage());
            return ['error' => 'Error obteniendo datos de la fase: ' . $e->getMessage()];
        }
    }

    public function buscarCampoEnFase1($datosFase1, $idCampo)
    {
        if (empty($datosFase1['fase']['hitos'])) {
            return null;
        }

        foreach ($datosFase1['fase']['hitos'] as $hito) {
            if (empty($hito['grupos'])) {
                continue;
            }

            foreach ($hito['grupos'] as $grupo) {
                if (empty($grupo['campos'])) {
                    continue;
                }

                foreach ($grupo['campos'] as $campo) {
                    if ($campo['id_campo_hito'] == $idCampo) {
                        $campo['hito'] = $hito['nombre'];
                        return $campo;
                    }
                }
            }
        }

        return null;
    }

    public function tieneConversacionReciente(int $idExpediente, int $minutosAtras = 10): bool
    {
        try {
            $conn = $this->getDoctrine()->getConnection();
            $fechaLimite = date('Y-m-d H:i:s', time() - ($minutosAtras * 60));
            
            $sql = 'SELECT COUNT(*) as total FROM chat_history 
                    WHERE id_expediente = :idExp AND timestamp > :fechaLimite';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('idExp', $idExpediente);
            $stmt->bindValue('fechaLimite', $fechaLimite);
            $stmt->execute();
            $resultado = $stmt->fetch();
            
            return (int)($resultado['total'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function construirMensajeUnificado($nombreCliente, $camposFaltantes, $tieneHistorico = false, $esNuevaParte = false)
    {
        $nombres = explode(' ', trim($nombreCliente));
        $primerNombre = $nombres[0] ?? 'Cliente';

        $totalCampos = count($camposFaltantes);
        
        if ($totalCampos === 0) {
            return "¡Ya tienes todo completado! Muchas gracias por tu información.";
        }

        $listaCampos = [];
        foreach ($camposFaltantes as $campo) {
            $texto = "• " . $campo['nombre'];
            
            if (isset($campo['id_campo_hito'])) {
                $opciones = $this->obtenerOpcionesFormateadas($campo['id_campo_hito']);
                if ($opciones) {
                    $texto .= $opciones;
                }
            }
            
            $listaCampos[] = $texto;
        }
        $textoLista = implode("\n", $listaCampos);

        if (!$tieneHistorico) {
            $mensaje = "¡Hola $primerNombre! 👋\n\n";
            $mensaje .= "Te escribo desde Hipotea para dar seguimiento a tu trámite de hipoteca que iniciaste con nosotros.\n\n";
            $mensaje .= "📋 Para poder avanzar, necesitamos que completes esta información:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "Cuando puedas, nos lo haces saber. ¡Muchas gracias! 😊";
        } elseif ($esNuevaParte) {
            $mensaje = "¡Perfecto! Gracias por compartir esos datos. ✓\n\n";
            $mensaje .= "📋 Ahora necesitamos que completes esta información:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "Cuando tengas un momento. ¡Muchas gracias! 😊";
        } else {
            $mensaje = "Gracias por tu respuesta. ✓\n\n";
            $mensaje .= "📋 Necesitamos que completes lo siguiente:\n\n";
            $mensaje .= $textoLista . "\n\n";
            $mensaje .= "¡Muchas gracias por tu ayuda! 😊";
        }

        $this->logear("✓ Mensaje construido: " . strlen($mensaje) . " caracteres | tieneHistorico=$tieneHistorico | esNuevaParte=$esNuevaParte");
        return $mensaje;
    }

    // =========================================================================
    // AGENTE ESPECIALISTA EN CREACIÓN DE EXPEDIENTES Y CLIENTES
    // =========================================================================

    /**
     * Endpoint HTTP del agente de creación de expedientes.
     *
     * POST /API/IA/AgenteExpediente
     * Body JSON: { "mensaje": "...", "id_usuario_solicitante": 5 }
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return JsonResponse
     */
    public function agenteExpedienteAction(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        try {
            $datos = json_decode($request->getContent(), true);
            $mensaje = trim($datos['mensaje'] ?? '');
            $idUsuarioSolicitante = isset($datos['id_usuario_solicitante']) ? (int)$datos['id_usuario_solicitante'] : null;

            if (empty($mensaje)) {
                return new JsonResponse(['error' => 'El campo "mensaje" es obligatorio.'], 400);
            }

            $resultado = $this->ejecutarAgenteExpediente($mensaje, $idUsuarioSolicitante);

            return new JsonResponse($resultado);

        } catch (\Exception $e) {
            $this->logear('AgenteExpediente ERROR: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Error interno del agente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Bucle agéntico principal.
     * Envía el mensaje al LLM junto con las definiciones de herramientas y ejecuta
     * las llamadas a funciones hasta que el modelo produce una respuesta final.
     *
     * @param string   $mensaje               Petición en lenguaje natural
     * @param int|null $idUsuarioSolicitante   ID del usuario que lanza la petición (opcional)
     * @return array   Resultado final con expediente_id, cliente_id y mensaje de respuesta
     */
    public function ejecutarAgenteExpediente(string $mensaje, ?int $idUsuarioSolicitante = null): array
    {
        $this->logear('=== INICIO ejecutarAgenteExpediente ===');
        $this->logear('Mensaje: ' . substr($mensaje, 0, 200));

        $config = $this->obtenerConfiguracionIA();
        if (!$config) {
            return ['error' => 'No hay configuración de IA disponible.', 'exito' => false];
        }

        $herramientas = $this->definirHerramientasAgente();
        $provider = strtoupper($config['provider'] ?? 'GEMINI');

        $systemPrompt = 'Eres un agente especialista en la creación de expedientes hipotecarios. '
            . 'Tu objetivo es crear un expediente completo para un cliente. '
            . 'Si el cliente no existe, créalo primero usando la herramienta crear_cliente. '
            . 'Si el cliente ya existe, búscalo con buscar_cliente y usa su id. '
            . 'Una vez tengas el id del cliente, crea el expediente con crear_expediente. '
            . 'Cuando hayas completado todas las acciones, responde con un resumen en español '
            . 'indicando el id del cliente y el id del expediente creado.';

        // Historial de mensajes del agente
        $mensajes = [
            ['role' => 'user', 'content' => $mensaje]
        ];

        $estadoFinal = [
            'exito'        => false,
            'cliente_id'   => null,
            'expediente_id' => null,
            'respuesta'    => '',
            'acciones'     => []
        ];

        // Máximo de iteraciones para evitar bucles infinitos
        for ($i = 0; $i < self::MAX_AGENT_ITERATIONS; $i++) {
            $this->logear("Agente iteración {$i}");

            if ($provider === 'OPENAI') {
                $respuestaLLM = $this->llamarOpenAIConHerramientas($mensajes, $herramientas, $config, $systemPrompt);
            } else {
                $respuestaLLM = $this->llamarGeminiConHerramientas($mensajes, $herramientas, $config, $systemPrompt);
            }

            if (isset($respuestaLLM['error'])) {
                $estadoFinal['respuesta'] = 'Error al comunicarse con la IA: ' . $respuestaLLM['error'];
                break;
            }

            // ¿El LLM quiere llamar a una herramienta?
            if (!empty($respuestaLLM['tool_calls'])) {
                foreach ($respuestaLLM['tool_calls'] as $toolCall) {
                    $nombreHerramienta = $toolCall['nombre'];
                    $parametros        = $toolCall['parametros'];

                    $this->logear("Herramienta llamada: {$nombreHerramienta} con " . json_encode($parametros));

                    $resultadoHerramienta = $this->ejecutarHerramientaAgente($nombreHerramienta, $parametros);

                    $this->logear("Resultado herramienta: " . json_encode($resultadoHerramienta));

                    // Actualizar estado según resultado
                    if ($nombreHerramienta === 'crear_cliente' && !empty($resultadoHerramienta['id_cliente'])) {
                        $estadoFinal['cliente_id'] = $resultadoHerramienta['id_cliente'];
                    }
                    if ($nombreHerramienta === 'buscar_cliente' && !empty($resultadoHerramienta['id_cliente'])) {
                        $estadoFinal['cliente_id'] = $resultadoHerramienta['id_cliente'];
                    }
                    if ($nombreHerramienta === 'crear_expediente' && !empty($resultadoHerramienta['id_expediente'])) {
                        $estadoFinal['expediente_id'] = $resultadoHerramienta['id_expediente'];
                        $estadoFinal['exito'] = true;
                    }

                    $estadoFinal['acciones'][] = [
                        'herramienta' => $nombreHerramienta,
                        'parametros'  => $parametros,
                        'resultado'   => $resultadoHerramienta
                    ];

                    // Agregar al historial: la llamada del asistente y el resultado de la herramienta
                    $mensajes[] = [
                        'role'       => 'assistant',
                        'tool_calls' => [$toolCall]
                    ];
                    $mensajes[] = [
                        'role'        => 'tool',
                        'tool_call_id' => $toolCall['id'] ?? 'call_' . $i,
                        'nombre'      => $nombreHerramienta,
                        'content'     => json_encode($resultadoHerramienta, JSON_UNESCAPED_UNICODE)
                    ];
                }
                // Continuar el bucle para obtener la respuesta final del LLM
                continue;
            }

            // El LLM ha producido una respuesta textual final
            $estadoFinal['respuesta'] = $respuestaLLM['texto'] ?? '';
            $this->logear('Agente respuesta final: ' . substr($estadoFinal['respuesta'], 0, 200));
            break;
        }

        $this->logear('=== FIN ejecutarAgenteExpediente === exito=' . ($estadoFinal['exito'] ? 'SI' : 'NO'));
        return $estadoFinal;
    }

    /**
     * Devuelve la definición de las herramientas disponibles para el agente.
     */
    private function definirHerramientasAgente(): array
    {
        return [
            [
                'nombre'      => 'buscar_cliente',
                'descripcion' => 'Busca un cliente existente por email, nombre completo o teléfono. Devuelve id_cliente si se encuentra.',
                'parametros'  => [
                    'email'    => ['tipo' => 'string', 'descripcion' => 'Email del cliente', 'requerido' => false],
                    'nombre'   => ['tipo' => 'string', 'descripcion' => 'Nombre y/o apellidos del cliente', 'requerido' => false],
                    'telefono' => ['tipo' => 'string', 'descripcion' => 'Teléfono móvil del cliente', 'requerido' => false],
                ]
            ],
            [
                'nombre'      => 'crear_cliente',
                'descripcion' => 'Crea un nuevo cliente (usuario con rol ROLE_CLIENTE). Devuelve id_cliente del usuario creado.',
                'parametros'  => [
                    'nombre'    => ['tipo' => 'string', 'descripcion' => 'Nombre del cliente', 'requerido' => true],
                    'apellidos' => ['tipo' => 'string', 'descripcion' => 'Apellidos del cliente', 'requerido' => true],
                    'email'     => ['tipo' => 'string', 'descripcion' => 'Email del cliente (se usará como nombre de usuario)', 'requerido' => true],
                    'telefono'  => ['tipo' => 'string', 'descripcion' => 'Teléfono móvil (opcional)', 'requerido' => false],
                    'nif'       => ['tipo' => 'string', 'descripcion' => 'DNI/NIE/NIF del cliente (opcional)', 'requerido' => false],
                ]
            ],
            [
                'nombre'      => 'crear_expediente',
                'descripcion' => 'Crea un expediente con todos sus hitos y campos para un cliente existente. Devuelve id_expediente.',
                'parametros'  => [
                    'id_cliente' => ['tipo' => 'integer', 'descripcion' => 'ID del cliente (usuario) al que se asocia el expediente', 'requerido' => true],
                    'vivienda'   => ['tipo' => 'string', 'descripcion' => 'Descripción de la vivienda (opcional, por defecto "NUEVA VIVIENDA")', 'requerido' => false],
                ]
            ]
        ];
    }

    /**
     * Despacha la llamada a la herramienta correspondiente.
     */
    private function ejecutarHerramientaAgente(string $nombre, array $parametros): array
    {
        switch ($nombre) {
            case 'buscar_cliente':
                return $this->herramientaBuscarCliente($parametros);
            case 'crear_cliente':
                return $this->herramientaCrearCliente($parametros);
            case 'crear_expediente':
                return $this->herramientaCrearExpediente($parametros);
            default:
                return ['error' => "Herramienta desconocida: {$nombre}"];
        }
    }

    /**
     * Herramienta: buscar_cliente
     * Busca un cliente existente por email, nombre o teléfono.
     */
    private function herramientaBuscarCliente(array $params): array
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $repo = $em->getRepository('AppBundle:Usuario');

            // Buscar por email (criterio más preciso)
            if (!empty($params['email'])) {
                $usuario = $repo->findOneBy(['email' => trim($params['email'])]);
                if ($usuario) {
                    return [
                        'encontrado' => true,
                        'id_cliente' => $usuario->getIdUsuario(),
                        'nombre'     => $usuario->getUsername() . ' ' . $usuario->getApellidos(),
                        'email'      => $usuario->getEmail()
                    ];
                }
            }

            // Buscar por teléfono
            if (!empty($params['telefono'])) {
                $usuario = $repo->findOneBy(['telefonoMovil' => trim($params['telefono'])]);
                if ($usuario) {
                    return [
                        'encontrado' => true,
                        'id_cliente' => $usuario->getIdUsuario(),
                        'nombre'     => $usuario->getUsername() . ' ' . $usuario->getApellidos(),
                        'email'      => $usuario->getEmail()
                    ];
                }
            }

            // Búsqueda parcial por nombre usando QueryBuilder
            if (!empty($params['nombre'])) {
                $nombreBusqueda = trim($params['nombre']);
                $qb = $repo->createQueryBuilder('u');
                $qb->where($qb->expr()->orX(
                    $qb->expr()->like('u.nombre', ':nombre'),
                    $qb->expr()->like('u.apellidos', ':nombre')
                ))
                ->setParameter('nombre', '%' . $nombreBusqueda . '%')
                ->setMaxResults(1);

                $usuario = $qb->getQuery()->getOneOrNullResult();
                if ($usuario) {
                    return [
                        'encontrado' => true,
                        'id_cliente' => $usuario->getIdUsuario(),
                        'nombre'     => $usuario->getUsername() . ' ' . $usuario->getApellidos(),
                        'email'      => $usuario->getEmail()
                    ];
                }
            }

            return ['encontrado' => false, 'mensaje' => 'No se encontró ningún cliente con los criterios proporcionados.'];

        } catch (\Exception $e) {
            $this->logear('herramientaBuscarCliente ERROR: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Herramienta: crear_cliente
     * Crea un nuevo usuario con rol ROLE_CLIENTE.
     */
    private function herramientaCrearCliente(array $params): array
    {
        try {
            $nombre    = trim($params['nombre'] ?? '');
            $apellidos = trim($params['apellidos'] ?? '');
            $email     = trim($params['email'] ?? '');

            if (empty($nombre) || empty($apellidos) || empty($email)) {
                return ['error' => 'Se requieren nombre, apellidos y email para crear un cliente.'];
            }

            $em   = $this->getDoctrine()->getManager();
            $repo = $em->getRepository('AppBundle:Usuario');

            // Evitar duplicados por email
            $existente = $repo->findOneBy(['email' => $email]);
            if ($existente) {
                return [
                    'encontrado'  => true,
                    'id_cliente'  => $existente->getIdUsuario(),
                    'mensaje'     => 'Ya existe un cliente con ese email. Se devuelve el existente.',
                    'nombre'      => $existente->getUsername() . ' ' . $existente->getApellidos(),
                    'email'       => $existente->getEmail()
                ];
            }

            $usuario = new \AppBundle\Entity\Usuario();
            $usuario->setUsername($nombre)
                    ->setApellidos($apellidos)
                    ->setEmail($email)
                    ->setRole('ROLE_CLIENTE')
                    ->setEstado(true)
                    ->setPoliticaPrivacidad(false)
                    ->setContratoFipre(false)
                    ->setFechaRegistro(new \DateTime())
                    ->setFechaConexion(new \DateTime());

            if (!empty($params['telefono'])) {
                $usuario->setTelefonoMovil(trim($params['telefono']));
            }
            if (!empty($params['nif'])) {
                $usuario->setNif(trim($params['nif']));
            }

            // Contraseña temporal aleatoria
            $passwordTemporal = bin2hex(random_bytes(8));
            $encoder = $this->container->get('security.password_encoder');
            $usuario->setPassword($encoder->encodePassword($usuario, $passwordTemporal));

            $em->persist($usuario);
            $em->flush();

            $this->logear("herramientaCrearCliente: cliente creado id=" . $usuario->getIdUsuario());

            return [
                'creado'     => true,
                'id_cliente' => $usuario->getIdUsuario(),
                'nombre'     => $nombre . ' ' . $apellidos,
                'email'      => $email
            ];

        } catch (\Exception $e) {
            $this->logear('herramientaCrearCliente ERROR: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Herramienta: crear_expediente
     * Crea un expediente completo (con HitoExpediente, GrupoHitoExpediente y CampoHitoExpediente)
     * para un cliente dado, replicando la lógica de activarCuentaAction.
     */
    private function herramientaCrearExpediente(array $params): array
    {
        try {
            $idCliente = isset($params['id_cliente']) ? (int)$params['id_cliente'] : 0;
            $vivienda  = trim($params['vivienda'] ?? 'NUEVA VIVIENDA') ?: 'NUEVA VIVIENDA';

            if ($idCliente <= 0) {
                return ['error' => 'Se requiere id_cliente para crear un expediente.'];
            }

            $em = $this->getDoctrine()->getManager();

            $cliente = $em->getRepository('AppBundle:Usuario')->find($idCliente);
            if (!$cliente) {
                return ['error' => "No se encontró el cliente con id={$idCliente}."];
            }

            // Primera fase disponible
            $fase = $em->getRepository('AppBundle:Fase')->findOneBy(['tipo' => 0]);
            if (!$fase) {
                $fase = $em->getRepository('AppBundle:Fase')->findOneBy([], ['orden' => 'ASC']);
            }

            $expediente = new \AppBundle\Entity\Expediente();
            $expediente->setEstado(1)
                       ->setIdCliente($cliente)
                       ->setVivienda($vivienda)
                       ->setFechaCreacion(new \DateTime());

            if ($fase) {
                $expediente->setIdFaseActual($fase);
            }

            // Crear hitos y sus campos para todas las fases
            $fases = $em->getRepository('AppBundle:Fase')->findBy([], ['orden' => 'ASC']);

            foreach ($fases as $faseItem) {
                $hitos = $em->getRepository('AppBundle:Hito')->findBy(
                    ['idFase' => $faseItem],
                    ['orden'  => 'ASC']
                );

                foreach ($hitos as $hito) {
                    $hitoExpediente = new \AppBundle\Entity\HitoExpediente();
                    $hitoExpediente->setIdHito($hito)
                                   ->setIdExpediente($expediente)
                                   ->setFechaModificacion(new \DateTime())
                                   ->setEstado(0);

                    $gruposCamposHito = $em->getRepository('AppBundle:GrupoCamposHito')->findBy(
                        ['idHito' => $hito],
                        ['orden'  => 'ASC']
                    );

                    foreach ($gruposCamposHito as $grupoCamposHito) {
                        $grupoHitoExpediente = new \AppBundle\Entity\GrupoHitoExpediente();
                        $grupoHitoExpediente->setIdHitoExpediente($hitoExpediente)
                                            ->setIdGrupoCamposHito($grupoCamposHito);

                        $camposHito = $em->getRepository('AppBundle:CampoHito')->findBy(
                            ['idGrupoCamposHito' => $grupoCamposHito],
                            ['orden'             => 'ASC']
                        );

                        foreach ($camposHito as $campoHito) {
                            $campoHitoExpediente = new \AppBundle\Entity\CampoHitoExpediente();
                            $campoHitoExpediente->setIdCampoHito($campoHito)
                                               ->setIdHitoExpediente($hitoExpediente)
                                               ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                                               ->setIdExpediente($expediente)
                                               ->setFechaModificacion(new \DateTime());

                            if ($campoHito->getTipo() == 4) {
                                $campoHitoExpediente->setObligatorio(1)
                                                    ->setSolicitarAlColaborador(1);
                            }

                            $em->persist($campoHitoExpediente);
                        }

                        $em->persist($grupoHitoExpediente);
                    }

                    $em->persist($hitoExpediente);
                }
            }

            $em->persist($expediente);
            $em->flush();

            $idExpediente = $expediente->getIdExpediente();
            $this->logear("herramientaCrearExpediente: expediente creado id={$idExpediente} para cliente id={$idCliente}");

            return [
                'creado'        => true,
                'id_expediente' => $idExpediente,
                'id_cliente'    => $idCliente,
                'vivienda'      => $vivienda
            ];

        } catch (\Exception $e) {
            $this->logear('herramientaCrearExpediente ERROR: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Llama a OpenAI con soporte de function calling (tools).
     * Devuelve array con 'texto' (respuesta final) o 'tool_calls' (llamadas a herramientas).
     */
    private function llamarOpenAIConHerramientas(array $mensajes, array $herramientas, array $config, string $systemPrompt): array
    {
        try {
            $apiKey  = $config['api_key'] ?? null;
            $apiUrl  = rtrim($config['api_url'] ?? 'https://api.openai.com/v1', '/');
            $model   = $config['model'] ?? 'gpt-3.5-turbo';

            if (!$apiKey) {
                return ['error' => 'OPENAI_API_KEY no configurada'];
            }

            // Convertir herramientas al formato OpenAI
            $toolsOpenAI = [];
            foreach ($herramientas as $h) {
                $properties = [];
                $required   = [];
                foreach ($h['parametros'] as $paramNombre => $paramInfo) {
                    $properties[$paramNombre] = [
                        'type'        => $paramInfo['tipo'] === 'integer' ? 'integer' : 'string',
                        'description' => $paramInfo['descripcion']
                    ];
                    if ($paramInfo['requerido']) {
                        $required[] = $paramNombre;
                    }
                }
                $toolsOpenAI[] = [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $h['nombre'],
                        'description' => $h['descripcion'],
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => $properties,
                            'required'   => $required
                        ]
                    ]
                ];
            }

            // Construir mensajes para OpenAI
            $mensajesOpenAI = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($mensajes as $msg) {
                if ($msg['role'] === 'tool') {
                    $mensajesOpenAI[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $msg['tool_call_id'] ?? 'call_0',
                        'content'      => $msg['content']
                    ];
                } elseif ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                    $tcOpenAI = [];
                    foreach ($msg['tool_calls'] as $tc) {
                        $tcOpenAI[] = [
                            'id'       => $tc['id'] ?? 'call_0',
                            'type'     => 'function',
                            'function' => [
                                'name'      => $tc['nombre'],
                                'arguments' => json_encode($tc['parametros'], JSON_UNESCAPED_UNICODE)
                            ]
                        ];
                    }
                    $mensajesOpenAI[] = ['role' => 'assistant', 'tool_calls' => $tcOpenAI];
                } else {
                    $mensajesOpenAI[] = ['role' => $msg['role'], 'content' => $msg['content'] ?? ''];
                }
            }

            $payload = json_encode([
                'model'       => $model,
                'messages'    => $mensajesOpenAI,
                'tools'       => $toolsOpenAI,
                'tool_choice' => 'auto',
                'temperature' => (float)($config['temperatura'] ?? 0.3),
                'max_tokens'  => (int)($config['max_tokens'] ?? 1024)
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init("{$apiUrl}/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['error' => 'cURL: ' . $curlError];
            }
            if ($httpCode !== 200) {
                return ['error' => "OpenAI devolvió HTTP {$httpCode}: " . substr($response, 0, 200)];
            }

            $data    = json_decode($response, true);
            $message = $data['choices'][0]['message'] ?? null;
            if (!$message) {
                return ['error' => 'Respuesta inesperada de OpenAI'];
            }

            // ¿Hay tool_calls?
            if (!empty($message['tool_calls'])) {
                $toolCalls = [];
                foreach ($message['tool_calls'] as $tc) {
                    $toolCalls[] = [
                        'id'         => $tc['id'],
                        'nombre'     => $tc['function']['name'],
                        'parametros' => json_decode($tc['function']['arguments'], true) ?? []
                    ];
                }
                return ['tool_calls' => $toolCalls];
            }

            return ['texto' => trim($message['content'] ?? '')];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Llama a Google Gemini con soporte de function calling.
     * Devuelve array con 'texto' (respuesta final) o 'tool_calls' (llamadas a herramientas).
     */
    private function llamarGeminiConHerramientas(array $mensajes, array $herramientas, array $config, string $systemPrompt): array
    {
        try {
            $apiKey = $config['api_key'] ?? null;
            $apiUrl = $config['api_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models';
            $model  = $config['model'] ?? 'gemini-pro';

            if (!$apiKey) {
                return ['error' => 'GEMINI_API_KEY no configurada'];
            }

            // Convertir herramientas al formato Gemini
            $functionDeclarations = [];
            foreach ($herramientas as $h) {
                $properties = [];
                $required   = [];
                foreach ($h['parametros'] as $paramNombre => $paramInfo) {
                    $properties[$paramNombre] = [
                        'type'        => strtoupper($paramInfo['tipo'] === 'integer' ? 'INTEGER' : 'STRING'),
                        'description' => $paramInfo['descripcion']
                    ];
                    if ($paramInfo['requerido']) {
                        $required[] = $paramNombre;
                    }
                }
                $functionDeclarations[] = [
                    'name'        => $h['nombre'],
                    'description' => $h['descripcion'],
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => $properties,
                        'required'   => $required
                    ]
                ];
            }

            // Construir contents para Gemini
            $contents = [];
            foreach ($mensajes as $msg) {
                if ($msg['role'] === 'tool') {
                    $contents[] = [
                        'role'  => 'function',
                        'parts' => [[
                            'functionResponse' => [
                                'name'     => $msg['nombre'] ?? 'tool',
                                'response' => ['result' => $msg['content']]
                            ]
                        ]]
                    ];
                } elseif ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                    $parts = [];
                    foreach ($msg['tool_calls'] as $tc) {
                        $parts[] = [
                            'functionCall' => [
                                'name' => $tc['nombre'],
                                'args' => $tc['parametros']
                            ]
                        ];
                    }
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                } else {
                    $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                    $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content'] ?? '']]];
                }
            }

            // Insertar system prompt como primer mensaje de usuario si no hay historial previo
            if (!empty($systemPrompt)) {
                array_unshift($contents, [
                    'role'  => 'user',
                    'parts' => [['text' => 'Instrucciones del sistema: ' . $systemPrompt]]
                ]);
                array_splice($contents, 1, 0, [[
                    'role'  => 'model',
                    'parts' => [['text' => 'Entendido. Estoy listo para ayudarte.']]
                ]]);
            }

            $payload = json_encode([
                'contents' => $contents,
                'tools'    => [['functionDeclarations' => $functionDeclarations]],
                'generationConfig' => [
                    'temperature'     => (float)($config['temperatura'] ?? 0.3),
                    'maxOutputTokens' => (int)($config['max_tokens'] ?? 1024)
                ]
            ], JSON_UNESCAPED_UNICODE);

            $url = "{$apiUrl}/{$model}:generateContent?key={$apiKey}";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['error' => 'cURL: ' . $curlError];
            }
            if ($httpCode !== 200) {
                return ['error' => "Gemini devolvió HTTP {$httpCode}: " . substr($response, 0, 200)];
            }

            $data  = json_decode($response, true);
            $parts = $data['candidates'][0]['content']['parts'] ?? [];

            if (empty($parts)) {
                return ['error' => 'Gemini no devolvió parts en la respuesta'];
            }

            // ¿Hay function calls?
            $toolCalls = [];
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $toolCalls[] = [
                        'id'         => 'call_gemini_' . uniqid(),
                        'nombre'     => $part['functionCall']['name'],
                        'parametros' => $part['functionCall']['args'] ?? []
                    ];
                }
            }
            if (!empty($toolCalls)) {
                return ['tool_calls' => $toolCalls];
            }

            // Respuesta de texto
            $texto = '';
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $texto .= $part['text'];
                }
            }

            return ['texto' => trim($texto)];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Guarda los datos extraídos de un mensaje en el expediente
     */
    public function guardarDatosEnExpediente(int $idExpediente, array $datosExtraidos, string $telefonoOrigen, string $nombreCliente = '', string $nifCliente = '')
    {
        $this->logear('=== INICIO guardarDatosEnExpediente ===');
        $this->logear('ID Expediente: ' . $idExpediente);
        $this->logear('Nombre Cliente: ' . $nombreCliente);
        $this->logear('Datos a guardar: ' . json_encode($datosExtraidos['campos_encontrados']));
        
        $conn = $this->getDoctrine()->getConnection();
        $camposGuardados = 0;
        $camposError = 0;

        if (is_array($datosExtraidos['campos_encontrados'])) {
            $campo192Existe = false;
            $campo194Existe = false;
            foreach ($datosExtraidos['campos_encontrados'] as $campo) {
                if (isset($campo['campo_id']) && $campo['campo_id'] == 192) {
                    $campo192Existe = true;
                }
                if (isset($campo['campo_id']) && $campo['campo_id'] == 194) {
                    $campo194Existe = true;
                }
            }
            
            if (!$campo192Existe && !empty($nombreCliente)) {
                $datosExtraidos['campos_encontrados'][] = [
                    'tipo' => 'nombre_apellidos',
                    'nombre_campo' => 'Nombre y Apellidos',
                    'campo_id' => 192,
                    'valor' => $nombreCliente
                ];
                $this->logear('✓ Campo 192 agregado automáticamente: ' . $nombreCliente);
            }
            
            if (!$campo194Existe && !empty($nifCliente)) {
                $datosExtraidos['campos_encontrados'][] = [
                    'tipo' => 'dni',
                    'nombre_campo' => 'DNI, NIE, Tarjeta Residencia',
                    'campo_id' => 194,
                    'valor' => $nifCliente
                ];
                $this->logear('✓ Campo 194 agregado automáticamente: ' . $nifCliente);
            }
        }

        if (empty($datosExtraidos['campos_encontrados'])) {
            $this->logear('✗ No hay campos para guardar');
            return ['exito' => false, 'guardados' => 0];
        }

        try {
            $timestamp = date('Y-m-d H:i:s');
            
            $opcionesMapeo = $this->obtenerOpcionesCampos();
            
            foreach ($datosExtraidos['campos_encontrados'] as $campo) {
                try {
                    $idCampoHito = $campo['campo_id'];
                    $valor = trim($campo['valor']);
                    $nombreCampo = $campo['nombre_campo'];
                    $idOpcional = null;

                    $this->logear("→ Guardando: {$nombreCampo} (ID: {$idCampoHito}) = '{$valor}'");

                    if (empty($valor)) {
                        $this->logear('✗ Valor vacío, saltando');
                        continue;
                    }
                    
                    if (isset($opcionesMapeo[$idCampoHito])) {
                        $valorNormalizado = strtolower(trim($valor));
                        $valorMapeado = null;
                        
                        foreach ($opcionesMapeo[$idCampoHito] as $opcionUsuario => $opcionBD) {
                            if (strpos($valorNormalizado, strtolower($opcionUsuario)) !== false) {
                                $valorMapeado = $opcionBD;
                                $idOpcional = $valorMapeado;
                                $this->logear("  → Mapeado: '{$valor}' → opción ID '{$valorMapeado}'");
                                break;
                            }
                        }
                    }

                    $sql = 'SELECT id_campo_hito_expediente, valor, id_opciones_campo FROM campo_hito_expediente 
                            WHERE id_expediente = :idExp AND id_campo_hito = :idCampo LIMIT 1';
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue('idExp', $idExpediente);
                    $stmt->bindValue('idCampo', $idCampoHito);
                    $stmt->execute();
                    $resultado = $stmt->fetch();

                    if ($resultado) {
                        $valorActual = trim($resultado['valor'] ?? '');
                        $tieneOpcional = !empty($resultado['id_opciones_campo']);
                        $esValorCorrupto = preg_match('/^campo_hito_\d+_opcion_\d+$/', $valorActual);
                        
                        if ($tieneOpcional && !$esValorCorrupto) {
                            $this->logear('⚠ CAMPO YA TIENE OPCIÓN ASIGNADA: ' . $nombreCampo . ' = opción ID: ' . $resultado['id_opciones_campo'] . ' (no se actualiza)');
                            continue;
                        }
                        
                        if (!empty($valorActual) && !$esValorCorrupto) {
                            $this->logear('⚠ CAMPO YA TIENE VALOR: ' . $nombreCampo . ' = "' . $valorActual . '" (no se actualiza)');
                            continue;
                        }
                        
                        if ($esValorCorrupto) {
                            $this->logear('⚠ VALOR CORRUPTO DETECTADO: ' . $valorActual . ' - SOBRESCRIBIENDO CON: ' . $valor);
                        }
                        
                        $sqlUpdate = 'UPDATE campo_hito_expediente 
                                      SET valor = :valor, id_opciones_campo = :idOpcional, fecha_modificacion = :timestamp
                                      WHERE id_expediente = :idExp AND id_campo_hito = :idCampo';
                        $stmt = $conn->prepare($sqlUpdate);
                        $stmt->bindValue('valor', $valor);
                        $stmt->bindValue('idOpcional', $idOpcional);
                        $stmt->bindValue('timestamp', $timestamp);
                        $stmt->bindValue('idExp', $idExpediente);
                        $stmt->bindValue('idCampo', $idCampoHito);
                        $stmt->execute();
                        $this->logear('✓ ACTUALIZADO: ' . $nombreCampo);
                    } else {
                        $sqlInsert = 'INSERT INTO campo_hito_expediente 
                                      (id_expediente, id_campo_hito, valor, id_opciones_campo, fecha_modificacion, obligatorio)
                                      VALUES (:idExp, :idCampo, :valor, :idOpcional, :timestamp, 0)';
                        $stmt = $conn->prepare($sqlInsert);
                        $stmt->bindValue('idExp', $idExpediente);
                        $stmt->bindValue('idCampo', $idCampoHito);
                        $stmt->bindValue('valor', $valor);
                        $stmt->bindValue('idOpcional', $idOpcional);
                        $stmt->bindValue('timestamp', $timestamp);
                        $stmt->execute();
                        $this->logear('✓ INSERTADO: ' . $nombreCampo);
                    }

                    $camposGuardados++;

                } catch (\Exception $e) {
                    $this->logear('✗ Error: ' . $e->getMessage());
                    $camposError++;
                }
            }

            $this->logear("=== FIN guardarDatosEnExpediente: {$camposGuardados} guardados, {$camposError} errores ===");
            
            return [
                'exito' => $camposGuardados > 0,
                'guardados' => $camposGuardados,
                'errores' => $camposError
            ];

        } catch (\Exception $e) {
            $this->logear('✗ EXCEPCIÓN FATAL: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return [
                'exito' => false,
                'guardados' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
