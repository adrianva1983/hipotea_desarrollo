<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class InteligenciaArtificialController extends Controller
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }



    /**
     * Envía texto a Google Gemini
     */

    /**
     * Envía texto a Google Gemini
     */
    private function enviarAGeminiTexto($texto, $prompt, $configIA)
    {
        try {
            error_log('InteligenciaArtificialController: 🚀 enviarAGeminiTexto() - Iniciando...');
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configIA['model']}:generateContent?key={$configIA['api_key']}";
            error_log('InteligenciaArtificialController: URL Gemini: ' . $url);

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
                    "temperature" => $configIA['temperature'],
                    "maxOutputTokens" => $configIA['max_tokens']
                ]
            ];

            error_log('InteligenciaArtificialController: Payload size: ' . strlen(json_encode($payload)) . ' bytes');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            
            error_log('InteligenciaArtificialController: ⏳ Enviando request a Gemini...');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log('InteligenciaArtificialController: HTTP Code: ' . $httpCode);
            if (!empty($curlError)) {
                error_log('InteligenciaArtificialController: cURL Error: ' . $curlError);
            }

            if ($response === false) {
                error_log('InteligenciaArtificialController: ❌ cURL exec() retornó FALSE. Error: ' . $curlError);
                throw new \Exception('cURL error: ' . $curlError);
            }

            error_log('InteligenciaArtificialController: Response (primeros 500 chars): ' . substr($response, 0, 500));

            if ($httpCode !== 200) {
                error_log('InteligenciaArtificialController: ❌ HTTP Error ' . $httpCode . '. Response: ' . $response);
                throw new \Exception('Error HTTP Gemini (' . $httpCode . '): ' . substr($response, 0, 200));
            }

            error_log('InteligenciaArtificialController: 📦 Parseando respuesta JSON...');
            $data = json_decode($response, true);

            if ($data === null) {
                error_log('InteligenciaArtificialController: ❌ JSON inválido en respuesta');
                throw new \Exception('Respuesta inválida de Gemini (JSON parsing failed)');
            }

            // Verificar errores en respuesta
            if (isset($data['error'])) {
                error_log('InteligenciaArtificialController: ❌ Gemini retornó error: ' . json_encode($data['error']));
                throw new \Exception('Gemini error: ' . ($data['error']['message'] ?? 'Unknown'));
            }

            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                error_log('InteligenciaArtificialController: ❌ Estructura inválida en respuesta Gemini: ' . json_encode($data));
                throw new \Exception('Respuesta inválida de Gemini (estructura inesperada)');
            }

            $textoRespuesta = $data['candidates'][0]['content']['parts'][0]['text'];
            error_log('InteligenciaArtificialController: Respuesta de IA (primeros 300 chars): ' . substr($textoRespuesta, 0, 300));
            
            // Limpiar markdown JSON si es necesario
            $textoRespuesta = preg_replace('/```json\s*|\s*```/', '', $textoRespuesta);
            
            error_log('InteligenciaArtificialController: 🔄 Parseando JSON de respuesta IA...');
            $datosExtraidos = json_decode($textoRespuesta, true);

            if (!is_array($datosExtraidos)) {
                error_log('InteligenciaArtificialController: ❌ Respuesta IA parseada no es array: ' . gettype($datosExtraidos) . '. Valor: ' . substr($textoRespuesta, 0, 200));
                throw new \Exception('Respuesta de IA no es JSON válido. Recibido: ' . substr($textoRespuesta, 0, 100));
            }

            error_log('InteligenciaArtificialController: ✅ enviarAGeminiTexto() exitoso. Datos: ' . count($datosExtraidos) . ' keys');

            return [
                'datos' => $datosExtraidos,
                'confianza' => 0.90,
                'proveedor' => 'GEMINI'
            ];
            
        } catch (\Exception $e) {
            error_log('InteligenciaArtificialController: ❌ Error en enviarAGeminiTexto(): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envía texto a OpenAI
     */
    private function enviarAOpenAITexto($texto, $prompt, $configIA)
    {
        $url = "https://api.openai.com/v1/chat/completions";

        $payload = [
            "model" => $configIA['model'] ?? "gpt-3.5-turbo",
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => $configIA['temperature'],
            "max_tokens" => $configIA['max_tokens']
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
            throw new \Exception('Error HTTP OpenAI (' . $httpCode . '): ' . substr($response, 0, 200));
        }

        $data = json_decode($response, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Respuesta inválida de OpenAI');
        }

        $textoRespuesta = $data['choices'][0]['message']['content'];
        
        // Limpiar markdown JSON
        $textoRespuesta = preg_replace('/```json\s*|\s*```/', '', $textoRespuesta);
        
        $datosExtraidos = json_decode($textoRespuesta, true);

        if (!is_array($datosExtraidos)) {
            throw new \Exception('Respuesta de OpenAI no es JSON válido');
        }

        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.90,
            'proveedor' => 'OPENAI'
        ];
    }

    /**
     * Envía texto a Ollama
     */
    private function enviarAOllamaTexto($texto, $prompt, $configIA)
    {
        $url = "https://crabbedly-unpersonalized-angelique.ngrok-free.dev/api/generate";

        $payload = [
            "model" => $configIA['model'] ?? "mistral",
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Error HTTP Ollama (' . $httpCode . '): ' . substr($response, 0, 200));
        }

        $data = json_decode($response, true);

        if (!isset($data['response'])) {
            throw new \Exception('Respuesta vacía de Ollama');
        }

        $textoRespuesta = $data['response'];
        
        // Limpiar markdown JSON
        $textoRespuesta = preg_replace('/```json\s*|\s*```/', '', $textoRespuesta);
        
        $datosExtraidos = json_decode($textoRespuesta, true);

        if (!is_array($datosExtraidos)) {
            throw new \Exception('Respuesta de Ollama no es JSON válido');
        }

        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.85,
            'proveedor' => 'OLLAMA'
        ];
    }

    /**
     * Obtiene configuración IA desde BD o variables de entorno
     */
    private function obtenerConfiguracionIA()
    {
        try {
            error_log('InteligenciaArtificialController: obtenerConfiguracionIA() - Buscando en BD...');
            
            $em = $this->getDoctrine()->getManager();

            // Intentar obtener de BD
            $sql = "SELECT * FROM ia_config WHERE activo = 1 AND es_proveedor_por_defecto = 1 LIMIT 1";
            error_log('InteligenciaArtificialController: SQL: ' . $sql);
            
            $connection = $em->getConnection();
            $statement = $connection->prepare($sql);
            $statement->execute();
            $configDB = $statement->fetch();

            if ($configDB && !empty($configDB['api_key'])) {
                error_log('InteligenciaArtificialController: ✅ Config IA encontrada en BD. Provider: ' . $configDB['provider']);
                $this->logger->info('✅ Config IA desde BD: ' . $configDB['provider']);
                return [
                    'provider' => $configDB['provider'] ?? 'GEMINI',
                    'api_key' => $configDB['api_key'],
                    'model' => $configDB['model'] ?? 'gemini-1.5-flash',
                    'temperature' => $configDB['temperatura'] ?? 0.7,
                    'max_tokens' => $configDB['max_tokens'] ?? 8192
                ];
            }

            error_log('InteligenciaArtificialController: ⚠️ No encontrada en BD, intentando variables de entorno...');

            // Fallback a variables de entorno
            $geminiKey = getenv('GEMINI_API_KEY');
            $openaiKey = getenv('OPENAI_API_KEY');
            $ollamaUrl = getenv('OLLAMA_API_URL');
            
            error_log('InteligenciaArtificialController: Variables de entorno - GEMINI: ' . (empty($geminiKey) ? 'NO' : 'SÍ') . 
                     ', OPENAI: ' . (empty($openaiKey) ? 'NO' : 'SÍ') . ', OLLAMA: ' . (empty($ollamaUrl) ? 'NO' : 'SÍ'));

            $apiKey = $geminiKey ?: $openaiKey ?: $ollamaUrl;

            if (!$apiKey) {
                error_log('InteligenciaArtificialController: ❌ NO HAY CONFIGURACIÓN IA EN BD NI EN VARIABLES DE ENTORNO');
                $this->logger->warning('⚠️ No se encontró configuración IA');
                return ['api_key' => '', 'provider' => 'GEMINI'];
            }

            error_log('InteligenciaArtificialController: ✅ Config IA desde variables de entorno. API Key presente.');
            return [
                'provider' => $geminiKey ? 'GEMINI' : ($openaiKey ? 'OPENAI' : 'OLLAMA'),
                'api_key' => $apiKey,
                'model' => 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];

        } catch (\Exception $e) {
            error_log('InteligenciaArtificialController: ❌ Error en obtenerConfiguracionIA: ' . $e->getMessage());
            $this->logger->error('Error en obtenerConfiguracionIA: ' . $e->getMessage());
            return ['api_key' => '', 'provider' => 'GEMINI'];
        }
    }


    /**
     * Procesa respuesta de IA en formato {"campos": [{id, nombre, valor}]}
     * y la convierte en {"valores_texto": {...}, "valores_opcion": {...}}
     */
    private function procesarRespuestaIA($datosIA, $grupoIds = [4])
    {
        $valoresTexto = [];
        $valoresOpcion = [];
        $camposProcesados = [];

        // Obtener campos desde BD para conocer sus tipos
        $camposBD = $this->obtenerCamposDeGruposConOpciones($grupoIds);

        // Si viene en formato {"campos": [...]}
        if (isset($datosIA['campos']) && is_array($datosIA['campos'])) {
            $campos = $datosIA['campos'];
            
            foreach ($campos as $campo) {
                if (!isset($campo['id']) || !isset($campo['valor'])) {
                    continue;
                }

                $idCampo = (int)$campo['id'];
                $valor = $campo['valor'];
                $nombre = $campo['nombre'] ?? '';

                // Si no tenemos info del campo en BD, asumimos texto
                if (!isset($camposBD[$idCampo])) {
                    $valoresTexto[$idCampo] = (string)$valor;
                    $camposProcesados[] = [
                        'id' => $idCampo,
                        'nombre' => $nombre,
                        'valor' => $valor,
                        'tipo' => 'texto'
                    ];
                    continue;
                }

                $tipoCampo = $camposBD[$idCampo]['tipo'];
                $opciones = $camposBD[$idCampo]['opciones'] ?? [];

                // Campo de opción (dropdown, radio)
                if (in_array($tipoCampo, [2, 3]) && !empty($opciones)) {
                    $valorNorm = strtolower(trim((string)$valor));
                    $opcionEncontrada = null;

                    // Registrar opciones disponibles
                    $opcionesDisponibles = array_column($opciones, 'valor');
                    error_log('InteligenciaArtificialController: procesarRespuestaIA - Campo ' . $idCampo . ' (' . $nombre . ') buscando: "' . $valor . '" entre: ' . json_encode($opcionesDisponibles));

                    // Búsqueda exacta
                    foreach ($opciones as $opcion) {
                        if (strtolower(trim($opcion['valor'])) === $valorNorm) {
                            $opcionEncontrada = $opcion['id'];
                            error_log('InteligenciaArtificialController: ✅ Campo ' . $idCampo . ' - Coincidencia EXACTA: "' . $opcion['valor'] . '" → ID ' . $opcionEncontrada);
                            break;
                        }
                    }

                    // Búsqueda parcial (substring)
                    if ($opcionEncontrada === null) {
                        foreach ($opciones as $opcion) {
                            $opcionNorm = strtolower(trim($opcion['valor']));
                            if (strpos($opcionNorm, $valorNorm) !== false || strpos($valorNorm, $opcionNorm) !== false) {
                                $opcionEncontrada = $opcion['id'];
                                error_log('InteligenciaArtificialController: ✅ Campo ' . $idCampo . ' - Coincidencia PARCIAL: "' . $opcion['valor'] . '" → ID ' . $opcionEncontrada);
                                break;
                            }
                        }
                    }

                    // Búsqueda fuzzy (Levenshtein) - para palabras similares (ej: fijo/fija)
                    if ($opcionEncontrada === null) {
                        $mejorOpcion = null;
                        $mejorSimilitud = 0;
                        $umbralSimilitud = 0.75; // 75% de similitud (captura variaciones de género: fijo/fija)

                        foreach ($opciones as $opcion) {
                            $opcionNorm = strtolower(trim($opcion['valor']));
                            
                            // Calcular distancia Levenshtein
                            $distancia = levenshtein($valorNorm, $opcionNorm);
                            $maxLen = max(strlen($valorNorm), strlen($opcionNorm));
                            $similitud = 1 - ($distancia / $maxLen);

                            if ($similitud > $mejorSimilitud && $similitud >= $umbralSimilitud) {
                                $mejorSimilitud = $similitud;
                                $mejorOpcion = $opcion;
                            }
                        }

                        if ($mejorOpcion !== null) {
                            $opcionEncontrada = $mejorOpcion['id'];
                            error_log('InteligenciaArtificialController: ✅ Campo ' . $idCampo . ' - Coincidencia FUZZY (' . round($mejorSimilitud * 100) . '%): "' . $mejorOpcion['valor'] . '" → ID ' . $opcionEncontrada);
                        } else {
                            error_log('InteligenciaArtificialController: ℹ️ Campo ' . $idCampo . ' - Fuzzy search no encontró coincidencia >= 75% para "' . $valor . '". Mejor similitud: ' . round($mejorSimilitud * 100) . '%');
                        }
                    }

                    if ($opcionEncontrada !== null) {
                        $valoresOpcion[$idCampo] = $opcionEncontrada;
                        $camposProcesados[] = [
                            'id' => $idCampo,
                            'nombre' => $nombre,
                            'valor' => $valor,
                            'tipo' => 'opcion',
                            'opcionId' => $opcionEncontrada
                        ];
                    } else {
                        error_log('InteligenciaArtificialController: ⚠️ Campo ' . $idCampo . ' - SIN OPCIÓN para valor "' . $valor . '". Opciones disponibles: ' . json_encode($opcionesDisponibles));
                        $camposProcesados[] = [
                            'id' => $idCampo,
                            'nombre' => $nombre,
                            'valor' => $valor,
                            'tipo' => 'opcion_no_encontrada',
                            'opcionesDisponibles' => $opcionesDisponibles
                        ];
                    }
                } else {
                    // Campo de texto
                    $valoresTexto[$idCampo] = (string)$valor;
                    $camposProcesados[] = [
                        'id' => $idCampo,
                        'nombre' => $nombre,
                        'valor' => $valor,
                        'tipo' => 'texto'
                    ];
                }
            }
        }

        error_log('InteligenciaArtificialController: procesarRespuestaIA - texto: ' . count($valoresTexto) . ', opcion: ' . count($valoresOpcion) . ', total: ' . count($camposProcesados));
        
        // Resumir estado de campos
        $resumen = [
            'texto' => count($valoresTexto),
            'opcion' => count($valoresOpcion),
            'opcion_no_encontrada' => count(array_filter($camposProcesados, fn($c) => $c['tipo'] === 'opcion_no_encontrada')),
            'total' => count($camposProcesados)
        ];
        error_log('InteligenciaArtificialController: procesarRespuestaIA RESUMEN: ' . json_encode($resumen));

        return [
            'valores_texto' => $valoresTexto,
            'valores_opcion' => $valoresOpcion,
            'campos_procesados' => $camposProcesados
        ];
    }

    /**
     * Obtiene campos con sus opciones desde BD para procesamiento dinámico
     */
    private function obtenerCamposDeGruposConOpciones(array $grupoIds)
    {
        if (empty($grupoIds)) {
            return [];
        }

        $conn = $this->getDoctrine()->getConnection();
        $resultado = [];

        // Placeholders para IN (?)
        $placeholders = implode(',', array_fill(0, count($grupoIds), '?'));

        // Obtener campos
        $sql = 'SELECT ch.id_campo_hito, ch.nombre, ch.tipo 
                FROM campo_hito ch 
                WHERE ch.id_grupo_campos_hito IN (' . $placeholders . ') 
                ORDER BY ch.orden';

        $stmt = $conn->executeQuery($sql, array_values($grupoIds));
        $filas = $stmt->fetchAll();

        foreach ($filas as $fila) {
            $idCampo = (int)$fila['id_campo_hito'];
            $tipo = (int)$fila['tipo'];
            $opciones = [];

            // Si es un campo de opción, obtener sus valores
            if (in_array($tipo, [2, 3])) {
                $sqlOpciones = 'SELECT oc.id_opciones_campo, oc.valor 
                               FROM opciones_campo oc 
                               WHERE oc.id_campo_hito = ? 
                               ORDER BY oc.orden';
                
                $stmtOpciones = $conn->prepare($sqlOpciones);
                $stmtOpciones->execute([$idCampo]);
                $filasOpciones = $stmtOpciones->fetchAll();

                foreach ($filasOpciones as $opcion) {
                    $opciones[] = [
                        'id' => (int)$opcion['id_opciones_campo'],
                        'valor' => $opcion['valor']
                    ];
                }
            }

            $resultado[$idCampo] = [
                'nombre' => $fila['nombre'],
                'tipo' => $tipo,
                'opciones' => $opciones
            ];
        }

        error_log('InteligenciaArtificialController: obtenerCamposDeGruposConOpciones - total campos: ' . count($resultado));
        return $resultado;
    }

    private function obtenerCamposDeGruposMod(array $grupoIds)
    {
        if (empty($grupoIds)) {
            return [];
        }

        $conn = $this->getDoctrine()->getConnection();
        $resultado = [];

        // Placeholders para IN (?)
        $placeholders = implode(',', array_fill(0, count($grupoIds), '?'));

        // Ejecutar la consulta SQL nativa
        $sql = 'SELECT ch.id_campo_hito, ch.nombre, ch.tipo 
                FROM campo_hito ch 
                WHERE ch.id_grupo_campos_hito IN (' . $placeholders . ') 
                ORDER BY ch.orden';

        $stmt = $conn->executeQuery($sql, array_values($grupoIds));
        $filas = $stmt->fetchAll();

        foreach ($filas as $fila) {
            $resultado[] = [
                'id_campo_hito' => (int)$fila['id_campo_hito'],
                'nombre'        => $fila['nombre'],
                'tipo'          => (int)$fila['tipo'],
            ];
        }

        error_log('InteligenciaArtificialController: obtenerCamposDeGruposMod - total campos: ' . count($resultado));
        return $resultado;
    }
    /**
     * Construye un prompt dinámico para extracción de datos basado en los campos disponibles
    */
    private function construirPromptExtractor(array $camposBD, $texto = '')
    {
        // Construir la lista de campos dinámicamente
        $seccionCampos = '';
        foreach ($camposBD as $campo) {
            $nombre = $campo['nombre'];
            $id = $campo['id_campo_hito'];
            $tipo = $campo['tipo'];
            
            // Indicar si es un campo de selección (tipo 2=dropdown, tipo 3=radio)
            $tipoIndicador = in_array($tipo, [2, 3]) ? ' [SELECCIÓN]' : '';
            $seccionCampos .= "- ID {$id}: {$nombre}{$tipoIndicador}\n";
        }

        $prompt = <<<EOT
        Actúa como un extractor de datos para un CRM. Tu misión es mapear la información del texto a los IDs de mi formulario.

        ### REGLA DE ORO PARA DATOS NO DEFINIDOS:
        Si el usuario menciona información que NO tiene un ID asignado abajo (por ejemplo: Nombre de la empresa, CIF, Horario de contacto, Redes sociales, Profesión, Notas adicionales), DEBES incluirla obligatoriamente en el campo ID: 191 (Comentarios).

        ### ESTRUCTURA DE EXTRACCIÓN:
        {$seccionCampos}

        ### INSTRUCCIONES CRÍTICAS:

        1. NORMALIZACIÓN:
           - Nombres en Title Case (Juan, María, González)
           - Emails en minúsculas (juan@example.com)
           - Teléfonos SOLO números sin espacios ni caracteres especiales (612345678)
           - Cantidades monetarias como números enteros sin € ni puntos (ej: 250000 no 250.000€)
           - Fechas en formato dd/mm/yyyy

        2. CAMPOS DE SELECCIÓN [SELECCIÓN] - OPCIONES EXACTAS A DEVOLVER:

           ESTADO CIVIL (ID 198):
           Opciones exactas disponibles:
           - "Solter@" (si dice soltero/a)
           - "Casad@ en gananciales" (si dice casado/a SIN especificar régimen → gananciales es DEFAULT)
           - "Casad@ en separación de bienes" (si especifica "separación de bienes")
           - "Pareja de hecho" (si dice pareja de hecho/unión de hecho)
           - "Separad@" (si dice separado/a)
           - "Divorciad@" (si dice divorciado/a)
           - "Viud@" (si dice viudo/a)
           
           EJEMPLOS CONCRETOS:
           - Texto: "soy casado" → {"id": "198", "valor": "Casad@ en gananciales"} ← GANANCIALES es default
           - Texto: "estoy divorciado" → {"id": "198", "valor": "Divorciad@"}
           - Texto: "soltero" → {"id": "198", "valor": "Solter@"}

           OTROS CAMPOS DE SELECCIÓN:
           - Tipo de Contrato: Devuelve EXACTAMENTE una de las opciones disponibles
           - Domicilio: Devuelve EXACTAMENTE una de las opciones disponibles
           
           IMPORTANTE: 
           - Si menciona estado civil: DEBES incluirlo, incluso sin formato "estado civil:"
           - Ejemplo: "soy casado y estoy interesado..." → INCLUYE estado civil como opción exacta
           - Devuelve SIEMPRE el valor de OPCIÓN EXACTA (no versión genérica)

        3. EXTRACCIÓN DE DATOS:
           - Extrae SOLO información que el usuario mencione explícitamente en el texto
           - Si un campo no tiene información, NO lo incluyas en el JSON
           - Si datos relevantes no encajan con los campos anteriores, inclúyelos en ID: 191 (Comentarios)

        4. FORMATO DE SALIDA - CRÍTICO:
           - Devuelve ÚNICAMENTE el objeto JSON plano
           - PROHIBIDO incluir bloques de código Markdown (```json ... ```)
           - PROHIBIDO incluir explicaciones o texto adicional fuera del JSON
           - Estructura exacta (INCLUYE el nombre del campo):

        {
        "campos": [
            {"id": "693", "nombre": "Nombre", "valor": "Juan"},
            {"id": "695", "nombre": "Teléfono", "valor": "612345678"},
            {"id": "696", "nombre": "Email", "valor": "juan@example.com"},
            {"id": "191", "nombre": "Comentarios", "valor": "Mencionó que trabaja en 'TecnoSL' como ingeniero. Prefiere contacto por la tarde."}
        ]
        }

        TEXTO A ANALIZAR:
        $texto
        EOT;

        error_log('InteligenciaArtificialController: construirPromptExtractor - prompt generado con ' . count($camposBD) . ' campos, texto: ' . strlen($texto) . ' chars');
        
        return $prompt;
    }

    public function procesarTextoExpedienteAction(Request $request)
    {
        try {
            // Obtener datos del request
            $isJson = strpos($request->headers->get('Content-Type', ''), 'application/json') !== false;

            if ($isJson) {
                $body = json_decode($request->getContent(), true) ?? [];
                $texto = $body['texto'] ?? null;
            } else {
                $texto = $request->request->get('texto');
            }

            // Validar que el texto no esté vacío
            if (!$texto || empty(trim($texto))) {
                error_log('InteligenciaArtificialController: ❌ procesarTextoExpedienteAction - Texto vacío o nulo');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'El texto no puede estar vacío.'
                ], 400);
            }

            error_log('InteligenciaArtificialController: procesarTextoExpedienteAction - Texto OK, longitud: ' . strlen($texto));

            // Extraer grupos del request (dinámico) - fallback a [4] si no viene
            if ($isJson) {
                $grupoIds = $body['grupos'] ?? [4];
            } else {
                $gruposParam = $request->request->get('grupos');
                $grupoIds = is_array($gruposParam) ? $gruposParam : ($gruposParam ? json_decode($gruposParam, true) : [4]);
            }
            
            // Validar que grupos sea un array válido
            if (!is_array($grupoIds) || empty($grupoIds)) {
                $grupoIds = [4];
            }
            
            error_log('InteligenciaArtificialController: procesarTextoExpedienteAction - Grupos a procesar: ' . json_encode($grupoIds));

            $configIA = $this->obtenerConfiguracionIA();

            if (empty($configIA['api_key'])) {
                error_log('InteligenciaArtificialController: ❌ procesarTextoExpedienteAction - NO HAY API_KEY CONFIGURADA');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'No hay configuración de IA activa.'
                ], 500);
            }
            $camposBD = $this->obtenerCamposDeGruposMod($grupoIds);
            $prompt = $this->construirPromptExtractor($camposBD, $texto);

            $resultadoIA = null;
                
            if ($configIA['provider'] === 'GEMINI') 
            {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAGeminiTexto()...');
                $resultadoIA = $this->enviarAGeminiTexto($texto, $prompt, $configIA);
            } 
            elseif ($configIA['provider'] === 'OPENAI') 
            {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAOpenAITexto()...');
                $resultadoIA = $this->enviarAOpenAITexto($texto, $prompt, $configIA);
            } 
            elseif ($configIA['provider'] === 'OLLAMA') 
            {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAOllamaTexto()...');
                $resultadoIA = $this->enviarAOllamaTexto($texto, $prompt, $configIA);
            } 
            else 
            {
                error_log('InteligenciaArtificialController: ❌ Provider desconocido: ' . $configIA['provider']);
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'Proveedor IA desconocido: ' . $configIA['provider']
                ], 500);
            }

            if (!$resultadoIA) {
                error_log('InteligenciaArtificialController: ❌ El proveedor devolvió NULL');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'No se obtuvo respuesta del proveedor IA'
                ], 500);
            }

            // Procesar la respuesta: extraer "campos" del array
            $datosProcessados = $this->procesarRespuestaIA($resultadoIA['datos'], $grupoIds);

            error_log('InteligenciaArtificialController: ✅ procesarTextoExpedienteAction exitoso');
            return new JsonResponse([
                'success' => true,
                'mensaje' => 'Textos procesados y campos mapeados con éxito',
                'texto'=> substr($texto, 0, 100) . '...', 
                'configIA'=> ['provider' => $configIA['provider']],
                'resultadoIA'=> $resultadoIA,
                'datosProcessados' => $datosProcessados
            ], 200);

        } catch (\Exception $e) {
            error_log('InteligenciaArtificialController: ❌ Error en procesarTextoExpedienteAction: ' . $e->getMessage());
            $this->logger->error('Error en procesarTextoExpedienteAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'mensaje' => 'Error al procesar: ' . $e->getMessage()
            ], 500);
        }
    }
}

