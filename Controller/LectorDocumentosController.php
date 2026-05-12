<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LectorDocumentosController extends Controller
{

    public function pruebaAction(Request $request, $id)
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Expediente no encontrado',
            'id' => $id
        ], 404);
    }

    /**
     * Analiza documentos cargados en fase 4 de un expediente
     * 
     * @Route("/API/analizar_documentos_fase4/{id}", name="api_analizar_documentos_fase4", methods={"POST"})
     */
    public function analizarDocumentosFase4Action(Request $request, $id)
    {
        
        try {
            // Validar que el expediente existe y pertenece al usuario
            $em = $this->getDoctrine()->getManager();
            $expediente = $em->getRepository('AppBundle:Expediente')->find($id);
            
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener documentos de fase 4
            $documentos = $this->obtenerDocumentosFase4($id);
            
            if (empty($documentos)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No hay documentos en fase 4',
                    'documentos_procesados' => 0,
                    'analisis' => []
                ], 200);
            }
            
            // Procesar cada documento con IA
            $analisisResultados = $this->procesarDocumentosConIA($id, $documentos);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Análisis completado',
                'documentos_procesados' => count($analisisResultados),
                'analisis' => $analisisResultados
            ], 200);
            
        } catch (\Exception $e) {
            $this->get('logger')->error('Error en analizarDocumentosFase4: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al analizar documentos: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtiene documentos cargados en campos tipo 4 (documentos) de la fase actual
     */
    private function obtenerDocumentosFase4($idExpediente)
    {
        $em = $this->getDoctrine()->getManager();
        
        $sql = "
            SELECT 
                fc.id_fichero_campo,
                fc.nombre_fichero,
                ch.nombre AS nombre_campo,
                che.valor AS nombre_archivo_original
            FROM fichero_campo fc
            INNER JOIN campo_hito ch ON fc.id_campo_hito = ch.id_campo_hito
            INNER JOIN campo_hito_expediente che ON fc.id_campo_hito_expediente = che.id_campo_hito_expediente
            WHERE 
                fc.id_expediente = :idExpediente
                AND ch.tipo = 4
            ORDER BY fc.id_fichero_campo DESC
        ";
        
        $connection = $em->getConnection();
        $statement = $connection->prepare($sql);
        $statement->execute(['idExpediente' => $idExpediente]);
        
        return $statement->fetchAll();
    }
    
    /**
     * Procesa documentos con IA y guarda resultados
     */
    private function procesarDocumentosConIA($idExpediente, $documentos)
    {
        $analisisResultados = [];
        $datosConsolidados = [];  // Array para recopilar todos los JSONs extraídos
        $em = $this->getDoctrine()->getManager();
        $filesDir = $this->getParameter('files_directory');
        
        // Obtener configuración IA
        $configIA = $this->obtenerConfiguracionIA();
        
        foreach ($documentos as $doc) {
            try {
                $rutaCompleta = $filesDir . '/' . $doc['nombre_fichero'];
                
                // Validar que el archivo existe
                if (!file_exists($rutaCompleta)) {
                    throw new \Exception('Archivo no encontrado: ' . $rutaCompleta);
                }
                
                // Determinar tipo MIME por extensión
                $tipoMime = $this->obtenerTipoMimePorExtension($doc['nombre_fichero']);
                
                // Validar tipo de archivo soportado
                if (!$this->esFormatoSoportado($tipoMime)) {
                    throw new \Exception('Tipo de archivo no soportado: ' . $tipoMime);
                }
                
                // Verificar si ya fue analizado
                $analisisExistente = $em->getRepository('AppBundle:DocumentoAnalisis')
                    ->findOneBy(['idFicheroCampo' => $doc['id_fichero_campo']]);
                
                if ($analisisExistente) {
                    $analisisResultados[] = [
                        'id_fichero_campo' => $doc['id_fichero_campo'],
                        'nombre_campo' => $doc['nombre_campo'],
                        'estado' => 'ya_procesado',
                        'id_analisis' => $analisisExistente->getIdAnalisis()
                    ];
                    continue;
                }
                
                // Predeterminar tipo MIME y datos del documento
                $resultadoIA = null;
                $docWithMime = array_merge($doc, ['tipo_mime' => $tipoMime]);
                
                // PASO 1: Verificar si existe contenido JSON en FicheroCampo
                /*error_log("🔍 Verificando contenido_json para archivo: " . $doc['nombre_fichero']);
                
                $ficheroCampo = $em->getRepository('AppBundle:FicheroCampo')
                    ->findOneBy(['idFicheroCampo' => $doc['id_fichero_campo']]);
                
                $contenidoJsonStr = null;
                if ($ficheroCampo) {
                    $contenidoJsonStr = $ficheroCampo->getContenidoJson();
                }
                
                // PASO 2: Si existe JSON válido, usarlo directamente
                if ($contenidoJsonStr && !empty($contenidoJsonStr)) {
                    $datosExtraidos = json_decode($contenidoJsonStr, true);
                    
                    if (is_array($datosExtraidos)) {
                        error_log("✅ Usando contenido_json existente para archivo: " . $doc['nombre_fichero']);
                        
                        $resultadoIA = [
                            'datos' => $datosExtraidos,
                            'confianza' => 0.95,
                            'tokens' => 0,
                            'origen' => 'ia'
                        ];
                    } else {
                        error_log("⚠️ JSON inválido en contenido_json para: " . $doc['nombre_fichero']);
                    }
                }*/
                
                // PASO 3: Si no hay JSON válido, proceder con IA
                if ($resultadoIA === null) {
                    error_log("📡 Analizando documento con IA: " . $doc['nombre_fichero']);
                    
                    if ($configIA['provider'] === 'GEMINI') {
                        $resultadoIA = $this->enviarAGemini($rutaCompleta, $docWithMime, $configIA);
                    } else if ($configIA['provider'] === 'OPENAI') {
                        $resultadoIA = $this->enviarAOpenAI($rutaCompleta, $docWithMime, $configIA);
                    } else if ($configIA['provider'] === 'OLLAMA') {
                        $resultadoIA = $this->enviarAOllama($rutaCompleta, $docWithMime, $configIA);
                    }
                    
                    if ($resultadoIA) {
                        error_log("✅ Análisis completado con IA: " . $configIA['provider']);
                    }
                }

                
                // PASO 4: Guardar análisis en BD SOLO si fue procesado con IA (no si es JSON cacheado)
                $idAnalisis = null;
                $origenAnalisis = $resultadoIA['origen'] ?? 'ia';
                
                if ($origenAnalisis === 'ia') {
                    // Guardar resultado de IA en documento_analisis
                    $idAnalisis = $this->guardarAnalisisEnBD($idExpediente, $docWithMime, $resultadoIA, $configIA);
                    error_log("📊 Análisis guardado en BD con ID: " . $idAnalisis);
                }
                
                $datosExtraidos = $resultadoIA['datos'] ?? null;
                
                // ✅ AGREGAR DATOS EXTRAÍDOS AL CONSOLIDADO
                if ($datosExtraidos) {
                    $datosConsolidados[] = [
                        'nombre_documento' => $doc['nombre_archivo_original'] ?? $doc['nombre_campo'],
                        'nombre_campo' => $doc['nombre_campo'],
                        'datos' => $datosExtraidos,
                        'confianza' => $resultadoIA['confianza'] ?? 0,
                        'proveedor' => $configIA['provider'] ?? 'desconocido'
                    ];
                }
                
                $analisisResultados[] = [
                    'id_fichero_campo' => $doc['id_fichero_campo'],
                    'nombre_campo' => $doc['nombre_campo'],
                    'nombre_archivo' => $doc['nombre_archivo_original'],
                    'estado' => 'procesado',
                    'id_analisis' => $idAnalisis,
                    'datos_extraidos' => $datosExtraidos,
                    'confianza' => $resultadoIA['confianza'] ?? 0,
                    'origen' => $origenAnalisis,
                    'proveedor_ia' => ($origenAnalisis === 'ia') ? $configIA['provider'] : null
                ];
                
            } catch (\Exception $e) {
                $this->get('logger')->error('Error procesando documento: ' . $e->getMessage());
                
                // Guardar error en BD
                $this->guardarErrorAnalisisEnBD($idExpediente, $doc, $e->getMessage(), $configIA);
                
                $analisisResultados[] = [
                    'id_fichero_campo' => $doc['id_fichero_campo'],
                    'nombre_campo' => $doc['nombre_campo'],
                    'estado' => 'error',
                    'mensaje_error' => $e->getMessage()
                ];
            }
        }
        
        // NOTA: No guardar/actualizar el consolidado durante el procesamiento automático
        // de documentos. El consolidado debe generarse explícitamente desde el modal
        // o mediante la acción dedicada. Si se desea reactivar esta funcionalidad,
        // descomentar la llamada a guardarAnalisisConsolidadoEnBD() más abajo.
        if (!empty($datosConsolidados)) {
            error_log("⚠️ Omitiendo actualización del consolidado durante el procesamiento de documentos para expediente " . $idExpediente . ". Genera el consolidado desde el modal si es necesario.");
            // Para reactivar, descomentar:
            // try {
            //     $this->guardarAnalisisConsolidadoEnBD($idExpediente, $datosConsolidados, $configIA);
            //     error_log("✅ Registro consolidado guardado para expediente " . $idExpediente . " con " . count($datosConsolidados) . " documentos");
            // } catch (\Exception $e) {
            //     $this->get('logger')->error('Error al guardar análisis consolidado: ' . $e->getMessage());
            // }
        }
        
        return $analisisResultados;
    }

    
    private function enviarAOllama($rutaArchivo, $documento, $configIA)
    {
        $prompt = $this->construirPromptAnalisis($documento['nombre_campo']);
        $base64Content = base64_encode(file_get_contents($rutaArchivo));
        
        $url = "https://crabbedly-unpersonalized-angelique.ngrok-free.dev/api/generate";
        
        $payload = [
            "model" => "llava",
            "prompt" => $prompt,
            "images" => [$base64Content],
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
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception('Error CURL OLLAMA: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('Error HTTP Ollama (' . $httpCode . '): ' . $response);
        }
        
        if (empty($response)) {
            throw new \Exception('Respuesta vacía de OLLAMA');
        }
        
        $data = json_decode($response, true);
        
        if (!is_array($data)) {
            throw new \Exception('Respuesta inválida de OLLAMA: ' . substr($response, 0, 200));
        }
        
        // OLLAMA devuelve en clave 'response'
        if (!isset($data['response']) || empty($data['response'])) {
            $this->get('logger')->warning('OLLAMA response vacía. Raw: ' . json_encode($data));
            return ['datos' => ['error' => 'Sin respuesta de OLLAMA'], 'confianza' => 0, 'tokens' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0];
        }
        
        $textoRespuesta = $data['response'];
        
        // Intentar parsear como JSON
        $datosExtraidos = json_decode($textoRespuesta, true);
        if (!is_array($datosExtraidos)) {
            // Si no es JSON, guardar como texto plano
            $datosExtraidos = ['texto_extraido' => $textoRespuesta];
        }
        
        // Ollama devuelve eval_count (tokens generados) y eval_duration
        $tokensSalida = $data['eval_count'] ?? 0;
        $tokensEntrada = $data['prompt_eval_count'] ?? 0;
        $tokensTotales = ($tokensEntrada + $tokensSalida);
        
        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.75,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }
    
    /**
     * Obtiene configuración de IA desde tabla ia_config o variables de entorno
     */
    private function obtenerConfiguracionIA()
    {
        try {
            $em = $this->getDoctrine()->getManager();
            
            // Intentar obtener de base de datos
            $sql = "SELECT * FROM ia_config WHERE activo = 1 AND es_proveedor_por_defecto = 1 LIMIT 1";
            $connection = $em->getConnection();
            $statement = $connection->prepare($sql);
            $result = $statement->execute();
            $configDB = $statement->fetch();
            
            error_log('📊 Buscando configuración IA en BD: ' . ($configDB ? 'ENCONTRADA' : 'NO ENCONTRADA'));
            
            if ($configDB && !empty($configDB['api_key'])) {
                error_log('✅ Usando configuración de BD: ' . $configDB['provider']);
                return [
                    'provider' => $configDB['provider'] ?? 'GEMINI',
                    'api_key' => $configDB['api_key'],
                    'model' => $configDB['model'] ?? 'gemini-1.5-flash',
                    'temperature' => $configDB['temperatura'] ?? 0.7,
                    'max_tokens' => $configDB['max_tokens'] ?? 8192
                ];
            }
            
            // Fallback a variables de entorno
            error_log('📊 Buscando configuración en variables de entorno...');
            $provider = getenv('IA_PROVIDER') ?: 'GEMINI';
            $apiKey = getenv('GEMINI_API_KEY');
            
            if (!$apiKey) {
                error_log('❌ No se encontró GEMINI_API_KEY en variables de entorno');
                return [
                    'provider' => 'GEMINI',
                    'api_key' => '',
                    'model' => 'gemini-1.5-flash',
                    'temperature' => 0.7,
                    'max_tokens' => 8192
                ];
            }
            
            error_log('✅ Usando GEMINI de variables de entorno');
            return [
                'provider' => 'GEMINI',
                'api_key' => $apiKey,
                'model' => getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];
            
        } catch (\Exception $e) {
            error_log('⚠️ Error en obtenerConfiguracionIA: ' . $e->getMessage());
            return [
                'provider' => 'GEMINI',
                'api_key' => '',
                'model' => 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];
        }
    }
    
    /**
     * Envía documento a Google Gemini Vision API
     */
    private function enviarAGemini($rutaArchivo, $documento, $configIA)
    {
        $prompt = $this->construirPromptAnalisis($documento['nombre_campo']);
        
        // Leer archivo y codificarlo en base64
        $contenidoArchivo = file_get_contents($rutaArchivo);
        $base64Content = base64_encode($contenidoArchivo);
        
        // Determinar tipo MIME
        $tipoMime = $documento['tipo_mime'];
        
        // Construir request para Gemini Vision
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configIA['model']}:generateContent?key={$configIA['api_key']}";
        
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => $prompt
                        ],
                        [
                            "inline_data" => [
                                "mime_type" => $tipoMime,
                                "data" => $base64Content
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => $configIA['temperature'],
                "maxOutputTokens" => $configIA['max_tokens']
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Error en Gemini API: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Respuesta inválida de Gemini API');
        }
        
        $textoRespuesta = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Intentar parsear como JSON
        $datosExtraidos = json_decode($textoRespuesta, true);
        if (!is_array($datosExtraidos)) {
            $datosExtraidos = ['texto_extraido' => $textoRespuesta];
        }

        // Extraer tokens separados de Gemini
        $tokensEntrada = 0;
        $tokensSalida = 0;
        $tokensTotales = 0;
        
        // Gemini devuelve usageMetadata con promptTokenCount y candidatesTokenCount
        if (isset($data['usageMetadata']['promptTokenCount']) && isset($data['usageMetadata']['candidatesTokenCount'])) {
            $tokensEntrada = (int)$data['usageMetadata']['promptTokenCount'];
            $tokensSalida = (int)$data['usageMetadata']['candidatesTokenCount'];
            $tokensTotales = (int)$data['usageMetadata']['totalTokenCount'];
        } 
        elseif (isset($data['usageMetadata']['totalTokenCount'])) {
            // Si solo viene total, usar ese valor
            $tokensTotales = (int)$data['usageMetadata']['totalTokenCount'];
            $tokensEntrada = 0;
            $tokensSalida = 0;
        }
        
        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.85,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }
    
    /**
     * Envía documento a OpenAI Vision API
     */
    private function enviarAOpenAI($rutaArchivo, $documento, $configIA)
    {
        $prompt = $this->construirPromptAnalisis($documento['nombre_campo']);
        
        // Leer archivo y codificarlo en base64
        $contenidoArchivo = file_get_contents($rutaArchivo);
        $base64Content = base64_encode($contenidoArchivo);
        
        // Determinar tipo MIME
        $tipoMime = $documento['tipo_mime'];
        
        // Construir request para OpenAI Vision
        $url = "https://api.openai.com/v1/chat/completions";
        
        $payload = [
            "model" => $configIA['model'],
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" => $prompt
                        ],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => "data:{$tipoMime};base64,{$base64Content}"
                            ]
                        ]
                    ]
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
        $httpCode = curl_getinfo($ch, CURLOPT_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('Error en OpenAI API: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Respuesta inválida de OpenAI API');
        }
        
        $textoRespuesta = $data['choices'][0]['message']['content'];
        
        // Intentar parsear como JSON
        $datosExtraidos = json_decode($textoRespuesta, true);
        if (!is_array($datosExtraidos)) {
            $datosExtraidos = ['texto_extraido' => $textoRespuesta];
        }
        
        // Extraer tokens separados de OpenAI
        $tokensEntrada = $data['usage']['prompt_tokens'] ?? 0;
        $tokensSalida = $data['usage']['completion_tokens'] ?? 0;
        $tokensTotales = $data['usage']['total_tokens'] ?? ($tokensEntrada + $tokensSalida);
        
        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.85,
            'tokens' => $tokensTotales,
            'prompt_tokens' => $tokensEntrada,
            'completion_tokens' => $tokensSalida
        ];
    }
    
    /**
     * Construye prompt contextual para análisis de documentos
     */
    private function construirPromptAnalisis($nombreCampo)
    {
        // Usar siempre el prompt unificado mejorado
        return $this->promptUnificoBelender($nombreCampo);
    }

    /**
     * Prompt ÚNICO que detecta automáticamente el tipo de documento y extrae campos ESPECÍFICOS Y MÍNIMOS
     * Detecta correctamente entre Vida Laboral, Cotización y otros documentos fiscales
     */
    private function promptUnificoBelender($nombreCampo): string
{
    return <<<PROMPT
Eres un extractor de datos de documentos españoles (AEAT y Seguridad Social).

REGLAS ABSOLUTAS
- Devuelve SOLO JSON válido (sin markdown, sin texto extra).
- No inventes datos. **NUNCA confundas fecha de emisión del documento con fecha de alta/inscripción.**
- Si un campo NO aparece EN EL DOCUMENTO: NO lo incluyas en JSON (nunca null, nunca vacío).
- EXCEPCIÓN: algunos campos como "estado_actual" en VIDA_LABORAL SIEMPRE deben incluirse.
- Importes: número decimal con punto (ej 1234.56). Nunca texto.
- Fechas: DD/MM/YYYY. Meses: YYYY-MM.
- Si no puedes completar por truncado: {"error":"TRUNCATED"}.
- Si es ilegible: {"error":"ILEGIBLE"}.

PASO 1: Detecta tipo_documento en:
"VIDA_LABORAL","COTIZACION","CIRBE","NOMINA","MODELO_100","MODELO_100_2022","PENULTIMA_RENTA","MODELO_111","MODELO_130","MODELO_131","MODELO_190","MODELO_303","MODELO_347","MODELO_390","CENSO","LABORAL_V2","PENSIONES","REVALORIZACION","SITUACION_AEAT","SITUACION_SS","TARJETA_NIF","TRIBUTOS_LOCALES","OTRO".

REGLAS DE CLASIFICACIÓN (CRÍTICO)
- Si aparecen términos: "TRAYECTORIA PROFESIONAL", "VIDA LABORAL", "HISTORIAL LABORAL", "INFORME DE VIDA" con tabla de EMPRESAS (altas/bajas)
  => tipo_documento = "VIDA_LABORAL" (PRIORIDAD MÁXIMA - incluso si hay otros campos)
- Si aparecen términos típicos de nómina como:
  "DEVENGOS", "DEDUCCIONES", "LÍQUIDO A PERCIBIR", "LIQUIDO A PERCIBIR", "BASE IRPF", "BASE S.S.", "CONTINGENCIAS COMUNES", "IRPF", "PERIODO DEVENGADO"
  => tipo_documento = "NOMINA" (aunque también aparezcan empresa, CIF, categoría, etc.).
- Si aparecen términos "MODELO 100 2022", "RENTA 2022", "DECLARACIÓN 2022" o título indica específicamente AÑO 2022
  => tipo_documento = "MODELO_100_2022"
- Si aparecen términos "PENÚLTIMA RENTA", "RENTA ANTERIOR", "DECLARACIÓN ANTERIOR", "RENTA AÑO ANTERIOR" (sin año específico claro como 2022)
  => tipo_documento = "PENULTIMA_RENTA"
- Si aparecen términos "DECLARACIÓN DE LA RENTA", "MODELO 100", "IMPUESTO SOBRE LA RENTA" y AÑO ES ACTUAL (2025, 2026, etc.) SIN MENCIÓN A 2022
  => tipo_documento = "MODELO_100"
- Solo usa "LABORAL_V2" si el documento es un contrato/condiciones laborales (p.ej. "contrato", "cláusulas", "jornada", "categoría", "salario" como condiciones) y NO hay estructura de nómina (devengos/deducciones/liquido).
- "COTIZACION" suele tener meses (enero, febrero...) y bases/cotización por mes (no devengos/deducciones).
- "CIRBE": Si aparecen términos "INFORME DE RIESGOS", "CENTRAL DE INFORMACIÓN DE RIESGOS", "CIRBE", "Banco de España"
  => tipo_documento = "CIRBE". Extrae datos identificativos aunque no haya operaciones declaradas.

PASO 2: Devuelve JSON con esta estructura mínima:
{
  "tipo_documento": "<uno de los anteriores>",
  "datos_principales": { ... },
  "confianza_lectura": 0.0
}

ESQUEMAS POR TIPO (solo los campos listados)

- VIDA_LABORAL:
  datos_principales{nombre_completo,dni_nie,numero_afiliacion_ss,periodo_total_cotizado,fecha_informe}
  y "situaciones":[{empresa,cif_empresa,fecha_alta,fecha_baja,estado_actual}]
  * Máximo 8 situaciones más recientes. Excluir VACACIONES/EXCEDENCIA/PRESTACION/INCAPACIDAD/SUSPENSION/MATERNIDAD.
  * REGLA ABSOLUTA: TODOS los campos SIEMPRE presentes en ESTE orden:
    1. "empresa" - nombre de la empresa
    2. "cif_empresa" - CIF (vacío "" si no existe)
    3. "fecha_alta" - DD/MM/YYYY
    4. "fecha_baja" - DD/MM/YYYY O "" (vacío SI NO hay baja)
    5. "estado_actual" - "activo" O "baja"
  * Ejemplo situación ACTIVA (sin fecha_baja):
    {"empresa":"HIPOTEA ASESORES SL","cif_empresa":"","fecha_alta":"04/07/2025","fecha_baja":"","estado_actual":"activo"}
  * Ejemplo situación CERRADA (con fecha_baja):
    {"empresa":"GRUPO NEGOCIADOR ARL","cif_empresa":"B123456","fecha_alta":"01/01/2021","fecha_baja":"03/07/2025","estado_actual":"baja"}
  * NUNCA omitas campos. Si no tienen valor, usa cadena vacía "".
  * Ordenar situaciones por fecha_alta: más reciente primero

- COTIZACION:
  datos_principales{nombre_completo,dni_nie,numero_afiliacion_ss,grupo_cotizacion}
  y "importe_cotizacion_ultimos_6":[{mes,empresa,base_cotizacion,importe_cotizacion}]
  
  **╔═══════════════════════════════════════════════════════════════════════════════════════╗**
  **║ VALIDACIÓN ESTRICTA DE MESES EN COTIZACIÓN (LEE ESTO PRIMERO)                        ║**
  **╚═══════════════════════════════════════════════════════════════════════════════════════╝**
  
  REGLA 1 - DETECTA VALORES VÁLIDOS:
  ✓ Un mes es VÁLIDO SOLO si:
    - Columna "Base Cotización" tiene UN NÚMERO decimal (ej: 1200.50)
    - Columna "Importe Cotización" tiene UN NÚMERO decimal (ej: 298.56)
    - AMBAS condiciones deben cumplirse
  
  REGLA 2 - QUÉ NO CONTAR COMO VÁLIDO:
  ✗ "Pendiente de actualizar" → NO es un número → EXCLUIR mes
  ✗ "---" (guión) → NO es un número → EXCLUIR mes
  ✗ Celda vacía/blanca → NO es un número → EXCLUIR mes
  ✗ "0" o "0.00" → No cuenta, EXCLUIR mes
  ✗ Un solo número (solo base O solo importe) → INCOMPLETO → EXCLUIR mes
  
  REGLA 3 - ALGORITMO DE EXTRACCIÓN:
  1. LEE la tabla mes por mes (cada celda)
  2. PARA CADA mes, comprueba AMBAS columnas (Base Y Importe)
  3. Si CUALQUIERA de las dos está vacía, dice "---", o dice "Pendiente" → SALTA ese mes completamente
  4. Solo si AMBAS tienen números → Incluye en resultado
  5. Máximo 6 meses (usa los más recientes con datos válidos)
  
  REGLA 4 - EJEMPLO REAL DEL PROBLEMA:
  ❌ INCORRECTO (Estamos viendo es esto):
    Tabla muestra: 2026 (Pendiente), 2025 (valores reales 1200.50, 298.56)
    Resultado MAL: Saca 2026-12, 2026-11, 2026-10, 2026-09, 2026-08, 2026-07
  
  ✅ CORRECTO (Debe hacer esto):
    Tabla muestra: 2026 (Pendiente), 2025 (valores reales 1200.50, 298.56)
    Resultado BIEN: Saca SOLO los meses de 2025 que tienen números
  
  REGLA 5 - NUNCA COMPLETES PATRONES:
  - Si ves un año con "Pendiente" → NO inventar meses del año entero
  - Si ves 3 meses con números → NO asumir que otros meses también tienen
  - Solo extrae lo que VES explícitamente en la tabla
  
  Orden final: Más reciente primero (YYYY-MM descendente), máximo 6 meses.

- CIRBE (INFORME DE RIESGOS CENTRAL BANCO DE ESPAÑA):
  datos_principales{codigo_identificativo,nombre_completo,estado_declaracion}
  * codigo_identificativo: Código/DNI/NIE del titular (ej: ES306501 9G)
  * nombre_completo: Nombre y apellidos EXACTAMENTE como aparece (ej: ROLDAN PINTOR ANTONIO JESUS)
  * estado_declaracion: SOLO tres valores posibles:
    - "no_declarado" => Si aparece "Titular no declarado en la Central de Información de Riesgos" o equivalente
    - "declarado" => Si hay operaciones/riesgos declarados
    - "sin_datos" => Si no puede determinarse
  * Si NO hay datos de operaciones: extraer MÍNIMO código y nombre. El estado_declaracion es CRÍTICO.
  * NUNCA inventes información de riesgos/operaciones si no existen en el PDF.

- NOMINA (CRÍTICO: este tipo debe activarse si hay DEVENGOS/DEDUCCIONES/LIQUIDO):
  datos_principales{
    periodo_devengo,
    fecha_emision,
    empresa_nombre,
    empresa_cif,
    trabajador_nombre,
    trabajador_dni_nie,
    categoria_profesional,
    jornada,
    bruto_total,
    liquido_a_percibir,
    total_deducciones,
    irpf_importe,
    irpf_porcentaje,
    base_irpf,
    base_contingencias_comunes,
    base_desempleo
  }
  * Si hay múltiples páginas/meses dentro del PDF, extrae SOLO la nómina más reciente por periodo_devengo.
  * "periodo_devengo": mes/año o rango. Si puedes, normaliza a "YYYY-MM".
  * Importes siempre numéricos. Si el documento tiene comas decimales, conviértelas a punto.

- MODELO_100 (DECLARACIÓN DE LA RENTA - CRÍTICO: EXTRAE DATOS REALES SOLO):
  datos_principales{
    ejercicio_fiscal,
    nif_contribuyente,
    nombre_contribuyente,
    tipo_declaracion,
    base_imponible_general,
    base_imponible_ahorro,
    resultado_declaracion,
    retenciones_aplicadas,
    pagos_fraccionados,
    rendimientos_trabajo,
    rendimientos_capital_inmobiliario,
    rendimientos_capital_mobiliario,
    ganancias_perdidas_patrimoniales,
    rendimientos_actividades_economicas,
    reducciones_deducciones,
    estado_civil,
    numero_hijos,
    nombre_conyuge
  }
  
  INSTRUCCIONES MUY IMPORTANTES PARA MODELO_100:
  *** NO INVENTES DATOS. Solo extrae lo que REALMENTE APARECE en el PDF. ***
  
  1. ejercicio_fiscal: Año 4 dígitos (YYYY). Si no aparece: omitir.
  2. nif_contribuyente: NIF/NIE del contribuyente principal. Buscar en "Datos identificativos" o sección inicial. Si no aparece: omitir.
  3. nombre_contribuyente: Nombre completo EXACTAMENTE como dice el PDF. Si no aparece: omitir.
  4. tipo_declaracion: SOLO "individual" o "conjunta". Buscar tipo de declaración o número de obligados. Si no aparece: omitir.
  5. base_imponible_general: Número decimal. La suma base = casilla 410 (base general) o equivalente. Si no existe en PDF: omitir.
  6. base_imponible_ahorro: Número decimal de base de ahorro. Si no existe en PDF: omitir.
  7. resultado_declaracion: Número decimal. Casilla 600 o "Resultado: A pagar/A devolver". POSITIVO=pagar, NEGATIVO=devolver. Si no aparece: omitir.
  8. retenciones_aplicadas: Total retenciones IRPF practicadas. Número decimal. Si no aparece: omitir.
  9. pagos_fraccionados: Total pagos a cuenta realizados. Número decimal. Si no aparece: omitir.
  10. rendimientos_trabajo: Casilla 100 o "Rendimientos del trabajo". Número decimal. Si no aparece: omitir.
  11. rendimientos_capital_inmobiliario: Casilla 200 o "Rendimientos del capital inmobiliario". Número decimal. Si no aparece: omitir.
  12. rendimientos_capital_mobiliario: Casilla 220 o "Rendimientos del capital mobiliario". Número decimal. Si no aparece: omitir.
  13. ganancias_perdidas_patrimoniales: Ganancias/pérdidas en venta de activos. Puede ser negativo. Si no aparece: omitir.
  14. rendimientos_actividades_economicas: Beneficios de actividad económica. Número decimal. Si no aparece: omitir.
  15. reducciones_deducciones: Reducciones por inversión (p.ej. fondos patrimoniales) o total deducciones. Número decimal. Si no aparece: omitir.
  16. estado_civil: SOLO valores reales: "soltero", "casado", "divorciado", "viudo", "pareja_hecho". Si no aparece: omitir.
  17. numero_hijos: Número entero (0, 1, 2, 3...). Si no aparece: omitir.
  18. nombre_conyuge: Nombre cónyuge SOLO si tipo_declaracion="conjunta" y aparece en PDF. Si no existe: omitir.

- PENULTIMA_RENTA (DECLARACIÓN DE LA RENTA AÑO ANTERIOR - IDÉNTICO A MODELO_100):
  datos_principales{
    ejercicio_fiscal,
    nif_contribuyente,
    nombre_contribuyente,
    tipo_declaracion,
    base_imponible_general,
    base_imponible_ahorro,
    resultado_declaracion,
    retenciones_aplicadas,
    pagos_fraccionados,
    rendimientos_trabajo,
    rendimientos_capital_inmobiliario,
    rendimientos_capital_mobiliario,
    ganancias_perdidas_patrimoniales,
    rendimientos_actividades_economicas,
    reducciones_deducciones,
    estado_civil,
    numero_hijos,
    nombre_conyuge
  }
  
  INSTRUCCIONES IDÉNTICAS A MODELO_100:
  *** NO INVENTES DATOS. Solo extrae lo que REALMENTE APARECE en el PDF. ***
  
  1. ejercicio_fiscal: Año 4 dígitos (YYYY) del ejercicio anterior (año ante-anterior al actual). Si no aparece: omitir.
  2. nif_contribuyente: NIF/NIE del contribuyente principal. Si no aparece: omitir.
  3. nombre_contribuyente: Nombre completo EXACTAMENTE como dice el PDF. Si no aparece: omitir.
  4. tipo_declaracion: SOLO "individual" o "conjunta". Si no aparece: omitir.
  5. base_imponible_general: Número decimal. Si no existe en PDF: omitir.
  6. base_imponible_ahorro: Número decimal de base de ahorro. Si no existe en PDF: omitir.
  7. resultado_declaracion: Número decimal. POSITIVO=pagar, NEGATIVO=devolver. Si no aparece: omitir.
  8. retenciones_aplicadas: Total retenciones IRPF practicadas. Número decimal. Si no aparece: omitir.
  9. pagos_fraccionados: Total pagos a cuenta realizados. Número decimal. Si no aparece: omitir.
  10. rendimientos_trabajo: Número decimal. Si no aparece: omitir.
  11. rendimientos_capital_inmobiliario: Número decimal. Si no aparece: omitir.
  12. rendimientos_capital_mobiliario: Número decimal. Si no aparece: omitir.
  13. ganancias_perdidas_patrimoniales: Puede ser negativo. Si no aparece: omitir.
  14. rendimientos_actividades_economicas: Número decimal. Si no aparece: omitir.
  15. reducciones_deducciones: Número decimal. Si no aparece: omitir.
  16. estado_civil: SOLO valores reales: "soltero", "casado", "divorciado", "viudo", "pareja_hecho". Si no aparece: omitir.
  17. numero_hijos: Número entero (0, 1, 2, 3...). Si no aparece: omitir.
  18. nombre_conyuge: Nombre cónyuge SOLO si tipo_declaracion="conjunta" y aparece en PDF. Si no existe: omitir.

- MODELO_100_2022 (DECLARACIÓN DE LA RENTA 2022 - IDÉNTICO A MODELO_100):
  datos_principales{
    ejercicio_fiscal,
    nif_contribuyente,
    nombre_contribuyente,
    tipo_declaracion,
    base_imponible_general,
    base_imponible_ahorro,
    resultado_declaracion,
    retenciones_aplicadas,
    pagos_fraccionados,
    rendimientos_trabajo,
    rendimientos_capital_inmobiliario,
    rendimientos_capital_mobiliario,
    ganancias_perdidas_patrimoniales,
    rendimientos_actividades_economicas,
    reducciones_deducciones,
    estado_civil,
    numero_hijos,
    nombre_conyuge
  }
  
  INSTRUCCIONES IDÉNTICAS A MODELO_100 (AÑOS: EJERCICIO 2022):
  *** NO INVENTES DATOS. Solo extrae lo que REALMENTE APARECE en el PDF. ***
  
  1. ejercicio_fiscal: Año 2022 (YYYY). Si no aparece: omitir.
  2. nif_contribuyente: NIF/NIE del contribuyente principal. Si no aparece: omitir.
  3. nombre_contribuyente: Nombre completo EXACTAMENTE como dice el PDF. Si no aparece: omitir.
  4. tipo_declaracion: SOLO "individual" o "conjunta". Si no aparece: omitir.
  5. base_imponible_general: Número decimal. Si no existe en PDF: omitir.
  6. base_imponible_ahorro: Número decimal de base de ahorro. Si no existe en PDF: omitir.
  7. resultado_declaracion: Número decimal. POSITIVO=pagar, NEGATIVO=devolver. Si no aparece: omitir.
  8. retenciones_aplicadas: Total retenciones IRPF practicadas. Número decimal. Si no aparece: omitir.
  9. pagos_fraccionados: Total pagos a cuenta realizados. Número decimal. Si no aparece: omitir.
  10. rendimientos_trabajo: Número decimal. Si no aparece: omitir.
  11. rendimientos_capital_inmobiliario: Número decimal. Si no aparece: omitir.
  12. rendimientos_capital_mobiliario: Número decimal. Si no aparece: omitir.
  13. ganancias_perdidas_patrimoniales: Puede ser negativo. Si no aparece: omitir.
  14. rendimientos_actividades_economicas: Número decimal. Si no aparece: omitir.
  15. reducciones_deducciones: Número decimal. Si no aparece: omitir.
  16. estado_civil: SOLO valores reales: "soltero", "casado", "divorciado", "viudo", "pareja_hecho". Si no aparece: omitir.
  17. numero_hijos: Número entero (0, 1, 2, 3...). Si no aparece: omitir.
  18. nombre_conyuge: Nombre cónyuge SOLO si tipo_declaracion="conjunta" y aparece en PDF. Si no existe: omitir.

- MODELO_190:
  datos_principales{nif_pagador,nombre_pagador,importe_ingresos_ejercicio,total_retenciones,numero_perceptores}

- MODELO_111:
  datos_principales{periodo_devengo,importe_total_rendimientos_trabajo,retenciones_practicadas_irpf,nif_empresa,nombre_empresa}

- MODELO_130:
  datos_principales{ejercicio,periodo,ingresos_integros,gastos_fiscalmente_deducibles,rendimiento_neto,pagos_fraccionados}

- MODELO_131:
  datos_principales{ejercicio,periodo,rendimiento_neto_por_modulos,pagos_fraccionados}

- MODELO_303:
  datos_principales{ejercicio,periodo,base_imponible_total,iva_repercutido,iva_soportado,resultado_liquidacion}

- MODELO_390:
  datos_principales{ejercicio,base_imponible_total_anual,iva_devengado_total,iva_deducible_total,resultado_liquidacion_anual}

- MODELO_347:
  datos_principales{
    nif_declarante,
    nombre_declarante,
    operaciones_terceros_superiores_3005_06:[{nif_tercero,nombre_tercero,importe_operacion}],
    importe_total_por_tercero
  }
  * Solo incluir terceros con importe_operacion > 3005.06

- CENSO (INSCRIPCIÓN EN EL REGISTRO MERCANTIL / ALTA AEAT):
  datos_principales{
    nombre_completo,
    nif,
    alta_obligaciones_fiscales,
    fecha_alta,
    fechas_modificaciones,
    epigrafe_iae
  }
  
  INSTRUCCIONES PARA CENSO - SIEMPRE INCLUIR TODOS LOS CAMPOS:
  *** Extrae lo que APARECE en el PDF. Si NO aparece, usa valor vacío. NUNCA omitas campos. ***
  
  1. nombre_completo: Nombre del empresario/profesional/razón social EXACTAMENTE como dice el PDF.
     - Si no aparece: usar "" (string vacío)
  
  2. nif: NIF/CIF/NIE del sujeto. Formato: 8 dígitos + 1 letra (ej: 30965019G) o números + letra/números para NIE.
     - Si no aparece: usar "" (string vacío)
  
  3. alta_obligaciones_fiscales: Array de strings con impuestos en los que está dado de alta.
     - Buscar sección "Obligaciones fiscales" o "Impuestos" o cualquier mención explícita de impuestos.
     - Valores posibles: "IVA", "IRPF", "IMPUTACION_IRPF", "CPFF", "IAE", etc.
     - SOLO incluir los que aparezcan explícitamente en el documento.
     - Ejemplo: ["IVA", "IRPF"] si aparecen ambos. Si solo aparece IVA: ["IVA"].
     - Si NO aparece sección de obligaciones: usar [] (array vacío)
  
  4. fecha_alta: Fecha de alta en el censo/registro AEAT. Formato DD/MM/YYYY. **CRÍTICO: NO CONFUNDIR CON FECHA DEL DOCUMENTO**
     - BUSCAR EXPLÍCITAMENTE en secciones como: "Historial", "Fechas", "Inscripción", "Desde", "Alta", "Registro mercantil", "Inscripción en el censo"
     - NUNCA es la fecha de emisión/firma del documento (ejemplo: "documento firmado el 13 de febrero de 2026" NO es fecha de alta)
     - Buscar patrones como "Alta desde", "Inscrito desde", "Fecha de inscripción", "A partir del", "Desde el día"
     - Si el documento dice "OBLIGADO TRIBUTARIO" sin mencionar cuándo se dio de alta: usar "" (vacío)
     - Si solo aparece fecha del documento/certificado sin referencia a fecha de alta: usar "" (vacío)
     - Si NO aparece explícitamente cuándo se dio de alta: usar "" (string vacío)
  
  5. fechas_modificaciones: Array de objetos con modificaciones registradas.
     Cada objeto debe tener:
     - "fecha": DD/MM/YYYY
     - "descripcion": descripción de qué se modificó (ej: "Cambio de domicilio", "Cambio de actividad", "Ampliación de obligaciones")
     - Si NO hay modificaciones registradas: usar [] (array vacío)
     - Si hay una o más modificaciones: incluir todas
     - Ejemplo: [{"fecha":"15/06/2023","descripcion":"Cambio de domicilio"},{"fecha":"01/01/2024","descripcion":"Ampliación de obligaciones"}]
  
  6. epigrafe_iae: Epígrafe IAE (Impuesto sobre Actividades Económicas).
     - Buscar sección "Epígrafes", "Actividad", "Sección", "División", código numérico (ej: 6311).
     - Formato: código numérico opcional con descripción (ej: "6311 - Asesoría fiscal, laboral o contable" o solo "6311" o solo "Asesoría fiscal")
     - Si hay múltiples epígrafes: array de strings ["6311 - Asesoría", "6320 - Otros servicios"]
     - Si hay un solo epígrafe: string simple (no array) "6311 - Asesoría fiscal"
     - Si NO aparece epígrafe IAE: usar "" (string vacío).

- LABORAL_V2 (contrato/condiciones, NO nómina):
  datos_principales{nombre_empresa,cif_empresa,fecha_inicio_contrato,tipo_contrato,categoria_profesional,jornada_laboral,salario_base,complementos}

- PENSIONES:
  datos_principales{organismo_emisor,tipo_pension,importe_bruto_anual,importe_neto_mensual,retenciones_irpf}

- REVALORIZACION:
  datos_principales{valor_actualizado,valor_catastral_anterior,porcentaje_revalorizacion,ubicacion_inmueble}

- SITUACION_AEAT:
  datos_principales{situacion_actual,importe_pendiente,fecha_actualizacion,estado}

- SITUACION_SS:
  datos_principales{situacion_seguridad_social,fecha_informe,estado_pago,importe_deuda}

- TARJETA_NIF:
  datos_principales{nombre_completo,dni_nie,sexo,fecha_nacimiento,fecha_expedicion,fecha_caducidad}

- TRIBUTOS_LOCALES:
  datos_principales{
    nombre_completo,dni_nif,tipo_impuesto,referencia_catastral,direccion_o_matricula,
    valor_catastral_suelo,valor_catastral_construccion,base_imponible,base_liquidable,
    cuota_integra,bonificaciones,importe_total,fecha_cobro,nrc,marca_modelo_potencia
  }

EJEMPLO DE VIDA_LABORAL CORRECTO:
{
  "tipo_documento": "VIDA_LABORAL",
  "datos_principales": {
    "nombre_completo": "ANTONIO JESUS ROLDAN PINTOR",
    "dni_nie": "030965019G",
    "numero_afiliacion_ss": "141025970279",
    "periodo_total_cotizado": "20 Años 6 meses 17 días",
    "fecha_informe": "15/02/2026"
  },
  "situaciones": [
    {
      "empresa": "HIPOTEA ASESORES SL",
      "cif_empresa": "",
      "fecha_alta": "04/07/2025",
      "fecha_baja": "",
      "estado_actual": "activo"
    },
    {
      "empresa": "GRUPO NEGOCIADOR ARL, S.L.",
      "cif_empresa": "B12345678",
      "fecha_alta": "01/01/2021",
      "fecha_baja": "03/07/2025",
      "estado_actual": "baja"
    }
  ],
  "confianza_lectura": 0.95
}

NOTA CRÍTICA: Todos los objetos en "situaciones" tienen LOS MISMOS 5 CAMPOS en el MISMO orden.
La primera situación tiene fecha_baja vacía ("") porque está activa.
La segunda tiene fecha_baja con valor porque está cerrada.
Ambas tienen cif_empresa, fecha_alta y estado_actual.

Responde SOLO JSON válido.
PROMPT;
}



    
    /**
     * Guarda el análisis en la tabla documento_analisis
     */
    private function guardarAnalisisEnBD($idExpediente, $documento, $resultadoIA, $configIA)
    {
        $em = $this->getDoctrine()->getManager();
        
        // Extrae tokens entrada y salida (compatible con Gemini y OpenAI)
        $tokensEntrada = $resultadoIA['prompt_tokens'] ?? $resultadoIA['tokens_input'] ?? 0;
        $tokensSalida = $resultadoIA['completion_tokens'] ?? $resultadoIA['tokens_output'] ?? 0;
        $tokensTotales = $resultadoIA['tokens'] ?? ($tokensEntrada + $tokensSalida);
        
        $sql = "
            INSERT INTO documento_analisis 
            (id_expediente, id_fichero_campo, nombre_documento, tipo_documento, 
             contenido_extraido, confianza, proveedor_ia, modelo_ia, 
             tokens_entrada, tokens_salida, tokens_usados, estado)
            VALUES 
            (:idExpediente, :idFicheroCampo, :nombreDoc, :tipoDoc, 
             :contenido, :confianza, :proveedor, :modelo,
             :tokensEntrada, :tokensSalida, :tokensTotales, 'procesado')
        ";
        
        $connection = $em->getConnection();
        $statement = $connection->prepare($sql);
        
        $statement->execute([
            ':idExpediente' => $idExpediente,
            ':idFicheroCampo' => $documento['id_fichero_campo'],
            ':nombreDoc' => $documento['nombre_archivo_original'] ?? $documento['nombre_fichero'],
            ':tipoDoc' => $documento['nombre_campo'],
            ':contenido' => json_encode($resultadoIA['datos']),
            ':confianza' => $resultadoIA['confianza'],
            ':proveedor' => $configIA['provider'],
            ':modelo' => $configIA['model'],
            ':tokensEntrada' => $tokensEntrada,
            ':tokensSalida' => $tokensSalida,
            ':tokensTotales' => $tokensTotales
        ]);
        
        return $connection->lastInsertId();
    }
    
    /**
     * Guarda errores en procesamiento
     */
    private function guardarErrorAnalisisEnBD($idExpediente, $documento, $mensajeError, $configIA)
    {
        $em = $this->getDoctrine()->getManager();
        
        $sql = "
            INSERT INTO documento_analisis 
            (id_expediente, id_fichero_campo, nombre_documento, tipo_documento, 
             proveedor_ia, modelo_ia, estado, mensaje_error)
            VALUES 
            (:idExpediente, :idFicheroCampo, :nombreDoc, :tipoDoc, 
             :proveedor, :modelo, 'error', :error)
        ";
        
        $connection = $em->getConnection();
        $statement = $connection->prepare($sql);
        
        $statement->execute([
            ':idExpediente' => $idExpediente,
            ':idFicheroCampo' => $documento['id_fichero_campo'],
            ':nombreDoc' => $documento['nombre_archivo_original'] ?? $documento['nombre_fichero'],
            ':tipoDoc' => $documento['nombre_campo'],
            ':proveedor' => $configIA['provider'],
            ':modelo' => $configIA['model'],
            ':error' => $mensajeError
        ]);
    }
    
    /**
     * Guarda el análisis consolidado de IA en BD
     * Simplemente guarda el JSON que devuelve IA en la columna contenido_extraido
     */
    private function guardarAnalisisConsolidadoEnBD($idExpediente, $datosConsolidados, $configIA)
    {
        $em = $this->getDoctrine()->getManager();
        
        error_log('[CONSOLIDADO] Guardando JSON en BD, expediente: ' . $idExpediente);
        
        // Convertir a JSON si no lo es
        if (is_array($datosConsolidados)) {
            $jsonConsolidado = json_encode($datosConsolidados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $jsonConsolidado = $datosConsolidados;
        }
        
        if ($jsonConsolidado === false) {
            error_log('[ERROR] Error al serializar JSON: ' . json_last_error_msg());
            throw new \Exception('Error al serializar JSON: ' . json_last_error_msg());
        }
        
        $connection = $em->getConnection();
        
        // Verificar si ya existe consolidado
        $sqlVerify = "SELECT id_analisis FROM documento_analisis 
                      WHERE id_expediente = :idExp AND tipo_documento = 'CONSOLIDADO' LIMIT 1";
        $stmtVerify = $connection->prepare($sqlVerify);
        $stmtVerify->execute([':idExp' => $idExpediente]);
        $existe = $stmtVerify->fetch();
        
        try {
            if ($existe) {
                // UPDATE
                $sql = "UPDATE documento_analisis 
                        SET contenido_extraido = :json,
                            proveedor_ia = :prov,
                            modelo_ia = :model,
                            estado = 'procesado',
                            fecha_analisis = NOW()
                        WHERE id_expediente = :idExp AND tipo_documento = 'CONSOLIDADO'";
            } else {
                // INSERT
                $sql = "INSERT INTO documento_analisis 
                        (id_expediente, nombre_documento, contenido_extraido, tipo_documento, proveedor_ia, modelo_ia, estado, fecha_analisis)
                        VALUES (:idExp, 'consolidado', :json, 'CONSOLIDADO', :prov, :model, 'procesado', NOW())";
            }
            
            $stmt = $connection->prepare($sql);
            $stmt->execute([
                ':idExp' => $idExpediente,
                ':json' => $jsonConsolidado,
                ':prov' => $configIA['provider'] ?? 'IA',
                ':model' => $configIA['model'] ?? 'desconocido'
            ]);
            
            error_log('[CONSOLIDADO] Guardado OK, ' . ($existe ? 'UPDATE' : 'INSERT'));
            
        } catch (\Exception $e) {
            error_log('[ERROR] SQL: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Valida si el formato es soportado
     */
    private function esFormatoSoportado($tipoMime)
    {
        $formatosSoportados = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain'
        ];
        
        return in_array($tipoMime, $formatosSoportados);
    }

    /**
     * Obtiene el tipo MIME basándose en la extensión del archivo
     */
    private function obtenerTipoMimePorExtension($nombreArchivo)
    {
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Obtiene análisis guardados de un expediente
     * 
     * @Route("/AJAX/analisis_documentos/{id}", name="api_obtener_analisis_documentos", methods={"GET"})
     */
    public function obtenerAnalisisDocumentosAction($id)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            
            // Validar expediente
            $expediente = $em->getRepository('AppBundle:Expediente')->find($id);
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener análisis
            $analisis = $em->getRepository('AppBundle:DocumentoAnalisis')->findByExpediente($id);
            
            $resultados = [];
            foreach ($analisis as $item) {
                $resultados[] = [
                    'id_analisis' => $item->getIdAnalisis(),
                    'nombre_documento' => $item->getNombreDocumento(),
                    'tipo_documento' => $item->getTipoDocumento(),
                    'contenido_extraido' => json_decode($item->getContenidoExtraido()),
                    'confianza' => $item->getConfianza(),
                    'estado' => $item->getEstado(),
                    'proveedor_ia' => $item->getProveedorIa(),
                    'modelo_ia' => $item->getModeloIa(),
                    'tokens_entrada' => $item->getTokensEntrada(),
                    'tokens_salida' => $item->getTokensSalida(),
                    'tokens_usados' => $item->getTokensUsados(),
                    'fecha_analisis' => $item->getFechaAnalisis()->format('Y-m-d H:i:s'),
                    'mensaje_error' => $item->getMensajeError()
                ];
            }
            
            return new JsonResponse([
                'success' => true,
                'total' => count($resultados),
                'analisis' => $resultados
            ], 200);
            
        } catch (\Exception $e) {
            $this->get('logger')->error('Error en obtenerAnalisisDocumentos: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al obtener análisis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene un análisis específico por ID
     * 
     * @Route("/AJAX/analisis/{id}", name="api_obtener_analisis_detalle", methods={"GET"})
     */
    public function obtenerAnalisisDetalleAction($id)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $analisis = $em->getRepository('AppBundle:DocumentoAnalisis')->find($id);
            
            if (!$analisis) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Análisis no encontrado'
                ], 404);
            }
            
            return new JsonResponse([
                'success' => true,
                'analisis' => [
                    'id_analisis' => $analisis->getIdAnalisis(),
                    'id_expediente' => $analisis->getIdExpediente(),
                    'nombre_documento' => $analisis->getNombreDocumento(),
                    'tipo_documento' => $analisis->getTipoDocumento(),
                    'contenido_extraido' => json_decode($analisis->getContenidoExtraido()),
                    'confianza' => $analisis->getConfianza(),
                    'estado' => $analisis->getEstado(),
                    'proveedor_ia' => $analisis->getProveedorIa(),
                    'modelo_ia' => $analisis->getModeloIa(),
                    'tokens_entrada' => $analisis->getTokensEntrada(),
                    'tokens_salida' => $analisis->getTokensSalida(),
                    'tokens_usados' => $analisis->getTokensUsados(),
                    'fecha_analisis' => $analisis->getFechaAnalisis()->format('Y-m-d H:i:s'),
                    'mensaje_error' => $analisis->getMensajeError()
                ]
            ], 200);
            
        } catch (\Exception $e) {
            $this->get('logger')->error('Error en obtenerAnalisisDetalle: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al obtener análisis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene especificaciones de extracción de documentos (para mostrar en modal)
     * 
     * @Route("/AJAX/especificaciones-documentos", name="api_especificaciones_documentos", methods={"GET"})
     */
    public function obtenerEspecificacionesDocumentosAction($idExpediente) 
    {
        try {
            $em = $this->getDoctrine()->getManager();
            
            // Validar expediente
            $expediente = $em->getRepository('AppBundle:Expediente')->find($idExpediente);
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener análisis guardados en BD
            $analisisRepo = $em->getRepository('AppBundle:DocumentoAnalisis');
            $analisisGuardados = $analisisRepo->findBy(['idExpediente' => $idExpediente]);
            
            $especificaciones = [];
            
            if (empty($analisisGuardados)) {
                // Si no hay análisis, retornar estructura vacía pero válida
                return new JsonResponse([
                    'success' => true,
                    'especificaciones' => [],
                    'total' => 0,
                    'mensaje' => 'No hay análisis disponibles. Haz clic en "Procesar documentos" para procesarlos.'
                ], 200);
            }
            
            // Procesar análisis guardados
            foreach ($analisisGuardados as $analisis) {
                // Decodificar JSON de contenido extraído
                $contenidoExtraido = json_decode($analisis->getContenidoExtraido(), true);
                
                // Construir estructura con datos reales
                // Obtener id_campo_hito_expediente desde la tabla fichero_campo
                $sqlFichero = "SELECT id_campo_hito_expediente FROM fichero_campo WHERE id_fichero_campo = :idFicheroCampo";
                $statementFichero = $em->getConnection()->prepare($sqlFichero);
                $statementFichero->execute([':idFicheroCampo' => $analisis->getIdFicheroCampo()]);
                $fichero = $statementFichero->fetch();

                $sqlInterviniente = "SELECT `id_hito_expediente` FROM `campo_hito_expediente`  WHERE `id_campo_hito_expediente` = :id_campo_hito_expediente";
                $statementInterviniente = $em->getConnection()->prepare($sqlInterviniente);
                $statementInterviniente->execute([':id_campo_hito_expediente' => $fichero['id_campo_hito_expediente']]);
                $interviniente = $statementInterviniente->fetch();
                
                $especificaciones[] = [
                    'id_analisis' => $analisis->getIdAnalisis(),
                    'id_campo_hito_expediente' => $fichero['id_campo_hito_expediente'] ?? null,
                    'id_hito_expediente' => $interviniente['id_hito_expediente'] ?? null,
                    'nombre_documento' => $analisis->getNombreDocumento(),
                    'tipo_documento' => $analisis->getTipoDocumento(),
                    'contenido_extraido' => $contenidoExtraido ?? [],
                    'confianza' => $analisis->getConfianza(),
                    'estado' => $analisis->getEstado(),
                    'proveedor_ia' => $analisis->getProveedorIa(),
                    'modelo_ia' => $analisis->getModeloIa(),
                    'fecha_analisis' => $analisis->getFechaAnalisis()->format('Y-m-d H:i:s'),
                    'mensaje_error' => $analisis->getMensajeError()
                ];
            }
            
            // Ordenar especificaciones según orden personalizado
            $orden = [
                'ACTIVAR DESCARGA BELENDER (Solo GN)',
                'SOLICITAR DOCUMENTACIÓN ACTUALIZADA (Solo GN)',
                'DNI - NIE - TARJETA RESIDENCIA',
                'CONTRATO DE TRABAJO',
                '3 ÚLTIMAS NÓMINAS',
                'CERTIFICADO RETENCIONES ÚLTIMO AÑO',
                'MOVIMIENTOS BANCARIOS 6 ÚLTIMOS MESES',
                'CONTRATO DE ALQUILER',
                '3 ÚLTIMOS RECIBOS PRÉSTAMO #1',
                'CUADRO AMORTIZACIÓN PRÉSTAMO #1',
                '3 ÚLTIMOS RECIBOS PRÉSTAMO #2',
                'CUADRO AMORTIZACIÓN PRÉSTAMO #2',
                '3 ÚLTIMOS RECIBOS PRÉSTAMO #3',
                'CUADRO AMORTIZACIÓN PRÉSTAMO #3',
                'CONVENIO DE DIVORCIO',
                'SENTENCIA DE DIVORCIO',
                'RECIBO AUTÓNOMO',
                'CERTIFICADO ESTADO CIVIL',
                'NOMBRAMIENTO',
                'ESCRITURAS DE SOCIEDADES',
                'CERTIFICADO DE MINUSVALÍA',
                'OTROS',
                'Documentación adicional',
                'RENTA ÚLTIMO AÑO',
                'PENÚLTIMA RENTA',
                'TRAYECTORIA PROFESIONAL',
                'MODELO TRIMESTRE IVA AÑO EN CURSO',
                'RESUMEN IVA AÑO ANTERIOR',
                'MODELO TRIMESTRE IRPF AÑO EN CURSO',
                'CERTIFICADO PENSIÓN',
                '100_2022',
                '100_2023 (Solo GN)',
                '100_2024 (Solo GN)',
                '111',
                '130 (Solo GN)',
                '131',
                '190',
                '347',
                '390 (Solo GN)',
                'Censo',
                'Cotizacion_V2',
                'Revalorizacion',
                'SituacionAEAT',
                'SituacionSS',
                'Laboral_V2 (Solo GN)',
                'CIRBE'
            ];
            
            usort($especificaciones, function($a, $b) use ($orden) {
                $nombreA = $a['tipo_documento'];
                $nombreB = $b['tipo_documento'];
                
                $indexA = array_search($nombreA, $orden);
                $indexB = array_search($nombreB, $orden);
                
                // Si no está en la lista, colocar al final
                if ($indexA === false) $indexA = PHP_INT_MAX;
                if ($indexB === false) $indexB = PHP_INT_MAX;
                
                return $indexA <=> $indexB;
            });
            
            return new JsonResponse([
                'success' => true,
                'especificaciones' => $especificaciones,
                'total' => count($especificaciones)
            ], 200);
            
        } catch (\Exception $e) {
            $this->get('logger')->error('Error en obtenerEspecificacionesDocumentos: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al obtener análisis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Antiguos datos de ejemplo (mantener como fallback)
     */
    private function obtenerEspecificacionesEstatica()
    {
        return [
            [
                'nombre_documento' => '100 (Renta 2020-2023)',
                'campos' => [
                    'Ejercicio fiscal',
                    'Base imponible general y del ahorro',
                    'Resultado de la declaración (a pagar/devolver)',
                    'Pagos fraccionados realizados',
                    'Retenciones aplicadas',
                    'Rendimientos netos del trabajo',
                    'Rendimientos netos del capital inmobiliario',
                    'Rendimientos netos del capital mobiliario',
                    'Ganancias o pérdidas patrimoniales',
                    'Rendimientos de actividades económicas',
                    'Reducciones y deducciones aplicadas',
                    'Tipo de declaración (individual o conjunta)',
                    'Estado civil y número de hijos',
                    'NIF y nombre del contribuyente y cónyuge (si aplica)'
                ]
            ],
            [
                'nombre_documento' => '111 (Certificado de Retenciones)',
                'campos' => [
                    'Periodo de devengo',
                    'Importe total de rendimientos del trabajo',
                    'Retenciones practicadas por IRPF',
                    'NIF de la empresa',
                    'Nombre de la empresa'
                ]
            ],
            [
                'nombre_documento' => '130 (Impuesto sobre la Renta de Actividades Económicas)',
                'campos' => [
                    'Ejercicio y periodo',
                    'Ingresos íntegros',
                    'Gastos fiscalmente deducibles',
                    'Rendimiento neto',
                    'Pagos fraccionados realizados'
                ]
            ],
            [
                'nombre_documento' => '131 (Régimen de Estimación Objetiva)',
                'campos' => [
                    'Ejercicio y periodo',
                    'Rendimiento neto por módulos',
                    'Pagos fraccionados realizados'
                ]
            ],
            [
                'nombre_documento' => '190 (Resumen Anual de Retenciones)',
                'campos' => [
                    'Importe total de ingresos del ejercicio',
                    'Total retenciones practicadas',
                    'Número de perceptores',
                    'NIF y nombre pagador'
                ]
            ],
            [
                'nombre_documento' => '303 (IVA Trimestral)',
                'campos' => [
                    'Base imponible total',
                    'IVA repercutido e IVA soportado',
                    'Resultado final de la autoliquidación',
                    'Ejercicio y periodo'
                ]
            ],
            [
                'nombre_documento' => '347 (Declaración de Operaciones con Terceros)',
                'campos' => [
                    'NIF y nombre del declarante',
                    'Operaciones con terceros que superan 3.005,06€',
                    'Importe total operaciones por tercero'
                ]
            ],
            [
                'nombre_documento' => '390 (Resumen Anual IVA)',
                'campos' => [
                    'Base imponible total anual',
                    'IVA devengado y deducible total',
                    'Resultado anual de la liquidación',
                    'Ejercicio'
                ]
            ],
            [
                'nombre_documento' => 'Censo (Registro Censo de Impuestos)',
                'campos' => [
                    'Nombre completo',
                    'NIF',
                    'Alta en obligaciones fiscales (IVA, IRPF, etc.)',
                    'Fecha de alta y modificaciones',
                    'Epígrafe IAE (actividad)'
                ]
            ],
            [
                'nombre_documento' => 'Cotización de Seguridad Social',
                'campos' => [
                    'Nombre completo',
                    'DNI/NIE',
                    'Nº afiliación Seguridad Social',
                    'Periodo total cotizado',
                    'Importe de cotización mes a mes (últimos 12 meses mínimo)',
                    'Variaciones en las bases de cotización',
                    'Grupo de cotización (si se muestra)',
                    'Empresas cotizantes en el periodo (si aparece)'
                ]
            ],
            [
                'nombre_documento' => 'Données Laborales',
                'campos' => [
                    'Nombre empresa y CIF',
                    'Fecha de inicio de contrato',
                    'Tipo de contrato',
                    'Categoría profesional',
                    'Jornada laboral (completa/parcial)',
                    'Salario base y complementos'
                ]
            ],
            [
                'nombre_documento' => 'Pensiones',
                'campos' => [
                    'Organismo emisor',
                    'Tipo de pensión (jubilación, viudedad, etc.)',
                    'Importe bruto anual',
                    'Importe neto mensual',
                    'Retenciones IRPF aplicadas'
                ]
            ],
            [
                'nombre_documento' => 'Revalorización de Inmueble',
                'campos' => [
                    'Valor actualizado del inmueble',
                    'Valor catastral anterior',
                    'Porcentaje de revalorización',
                    'Ubicación del inmueble'
                ]
            ],
            [
                'nombre_documento' => 'Situación AEAT',
                'campos' => [
                    'Situación fiscal actual (deudas, embargos, requerimientos)',
                    'Importe pendiente si lo hay',
                    'Fecha de actualización',
                    'Estado (al corriente / con incidencias)'
                ]
            ],
            [
                'nombre_documento' => 'Situación Seguridad Social',
                'campos' => [
                    'Situación en Seguridad Social',
                    'Fecha del informe',
                    'Si tiene deudas o está al corriente de pago',
                    'Importe de deuda si aplica'
                ]
            ],
            [
                'nombre_documento' => 'Tarjeta NIF',
                'campos' => [
                    'Nombre y apellidos',
                    'DNI/NIE',
                    'Fecha de nacimiento',
                    'Sexo',
                    'Fecha de expedición',
                    'Fecha de caducidad'
                ]
            ],
            [
                'nombre_documento' => 'Tributos Locales (Inmuebles/Vehículos)',
                'campos' => [
                    'Nombre completo',
                    'DNI/NIF del titular',
                    'Tipo de impuesto (vehículo/inmueble)',
                    'Referencia catastral (si aplica)',
                    'Dirección o matrícula del bien',
                    'Valor catastral suelo y construcción',
                    'Base imponible',
                    'Base liquidable',
                    'Cuota íntegra',
                    'Bonificaciones aplicadas',
                    'Importe total del recibo',
                    'Fecha de cobro',
                    'NRC (Número de referencia completo)',
                    'Marca, modelo y potencia fiscal del vehículo (si aplica)'
                ]
            ]
        ];

        return new JsonResponse([
            'success' => true,
            'especificaciones' => $especificaciones
        ], 200);
    }

    /**
     * Genera informe de especificaciones de extracción de documentos
     * 
     * @Route("/descargar/informe-especificaciones", name="descargar_informe_especificaciones", methods={"GET"})
     */
    public function descargarInformeEspecificacionesAction()
    {
        $especificaciones = [
            [
                'nombre_documento' => '100 (Renta 2020-2023)',
                'campos' => [
                    'Ejercicio fiscal',
                    'Base imponible general y del ahorro',
                    'Resultado de la declaración (a pagar/devolver)',
                    'Pagos fraccionados realizados',
                    'Retenciones aplicadas',
                    'Rendimientos netos del trabajo',
                    'Rendimientos netos del capital inmobiliario',
                    'Rendimientos netos del capital mobiliario',
                    'Ganancias o pérdidas patrimoniales',
                    'Rendimientos de actividades económicas',
                    'Reducciones y deducciones aplicadas',
                    'Tipo de declaración (individual o conjunta)',
                    'Estado civil y número de hijos',
                    'NIF y nombre del contribuyente y cónyuge (si aplica)'
                ]
            ],
            [
                'nombre_documento' => '111 (Certificado de Retenciones)',
                'campos' => [
                    'Periodo de devengo',
                    'Importe total de rendimientos del trabajo',
                    'Retenciones practicadas por IRPF',
                    'NIF de la empresa',
                    'Nombre de la empresa'
                ]
            ],
            [
                'nombre_documento' => '130 (Impuesto sobre la Renta de Actividades Económicas)',
                'campos' => [
                    'Ejercicio y periodo',
                    'Ingresos íntegros',
                    'Gastos fiscalmente deducibles',
                    'Rendimiento neto',
                    'Pagos fraccionados realizados'
                ]
            ],
            [
                'nombre_documento' => '131 (Régimen de Estimación Objetiva)',
                'campos' => [
                    'Ejercicio y periodo',
                    'Rendimiento neto por módulos',
                    'Pagos fraccionados realizados'
                ]
            ],
            [
                'nombre_documento' => '190 (Resumen Anual de Retenciones)',
                'campos' => [
                    'Importe total de ingresos del ejercicio',
                    'Total retenciones practicadas',
                    'Número de perceptores',
                    'NIF y nombre pagador'
                ]
            ],
            [
                'nombre_documento' => '303 (IVA Trimestral)',
                'campos' => [
                    'Base imponible total',
                    'IVA repercutido e IVA soportado',
                    'Resultado final de la autoliquidación',
                    'Ejercicio y periodo'
                ]
            ],
            [
                'nombre_documento' => '347 (Declaración de Operaciones con Terceros)',
                'campos' => [
                    'NIF y nombre del declarante',
                    'Operaciones con terceros que superan 3.005,06€',
                    'Importe total operaciones por tercero'
                ]
            ],
            [
                'nombre_documento' => '390 (Resumen Anual IVA)',
                'campos' => [
                    'Base imponible total anual',
                    'IVA devengado y deducible total',
                    'Resultado anual de la liquidación',
                    'Ejercicio'
                ]
            ],
            [
                'nombre_documento' => 'Censo (Registro Censo de Impuestos)',
                'campos' => [
                    'Nombre completo',
                    'NIF',
                    'Alta en obligaciones fiscales (IVA, IRPF, etc.)',
                    'Fecha de alta y modificaciones',
                    'Epígrafe IAE (actividad)'
                ]
            ],
            [
                'nombre_documento' => 'Cotización de Seguridad Social',
                'campos' => [
                    'Nombre completo',
                    'DNI/NIE',
                    'Nº afiliación Seguridad Social',
                    'Periodo total cotizado',
                    'Importe de cotización mes a mes (últimos 12 meses mínimo)',
                    'Variaciones en las bases de cotización',
                    'Grupo de cotización (si se muestra)',
                    'Empresas cotizantes en el periodo (si aparece)'
                ]
            ],
            [
                'nombre_documento' => 'Données Laborales',
                'campos' => [
                    'Nombre empresa y CIF',
                    'Fecha de inicio de contrato',
                    'Tipo de contrato',
                    'Categoría profesional',
                    'Jornada laboral (completa/parcial)',
                    'Salario base y complementos'
                ]
            ],
            [
                'nombre_documento' => 'Pensiones',
                'campos' => [
                    'Organismo emisor',
                    'Tipo de pensión (jubilación, viudedad, etc.)',
                    'Importe bruto anual',
                    'Importe neto mensual',
                    'Retenciones IRPF aplicadas'
                ]
            ],
            [
                'nombre_documento' => 'Revalorización de Inmueble',
                'campos' => [
                    'Valor actualizado del inmueble',
                    'Valor catastral anterior',
                    'Porcentaje de revalorización',
                    'Ubicación del inmueble'
                ]
            ],
            [
                'nombre_documento' => 'Situación AEAT',
                'campos' => [
                    'Situación fiscal actual (deudas, embargos, requerimientos)',
                    'Importe pendiente si lo hay',
                    'Fecha de actualización',
                    'Estado (al corriente / con incidencias)'
                ]
            ],
            [
                'nombre_documento' => 'Situación Seguridad Social',
                'campos' => [
                    'Situación en Seguridad Social',
                    'Fecha del informe',
                    'Si tiene deudas o está al corriente de pago',
                    'Importe de deuda si aplica'
                ]
            ],
            [
                'nombre_documento' => 'Tarjeta NIF',
                'campos' => [
                    'Nombre y apellidos',
                    'DNI/NIE',
                    'Fecha de nacimiento',
                    'Sexo',
                    'Fecha de expedición',
                    'Fecha de caducidad'
                ]
            ],
            [
                'nombre_documento' => 'Tributos Locales (Inmuebles/Vehículos)',
                'campos' => [
                    'Nombre completo',
                    'DNI/NIF del titular',
                    'Tipo de impuesto (vehículo/inmueble)',
                    'Referencia catastral (si aplica)',
                    'Dirección o matrícula del bien',
                    'Valor catastral suelo y construcción',
                    'Base imponible',
                    'Base liquidable',
                    'Cuota íntegra',
                    'Bonificaciones aplicadas',
                    'Importe total del recibo',
                    'Fecha de cobro',
                    'NRC (Número de referencia completo)',
                    'Marca, modelo y potencia fiscal del vehículo (si aplica)'
                ]
            ]
        ];

        // Generar contenido Markdown
        $markdown = "# Extracción Detallada de Datos de Documentos Belender\n\n";
        $markdown .= "**Fecha de generación:** " . (new \DateTime())->format('d/m/Y H:i:s') . "\n\n";
        $markdown .= "---\n\n";

        foreach ($especificaciones as $i => $doc) {
            $markdown .= "## " . ($i + 1) . ". " . $doc['nombre_documento'] . "\n\n";
            $markdown .= "**Datos a extraer:**\n\n";
            foreach ($doc['campos'] as $campo) {
                $markdown .= "- " . $campo . "\n";
            }
            $markdown .= "\n";
        }

        // Crear respuesta con archivo descargable
        $response = new \Symfony\Component\HttpFoundation\Response($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="Especificaciones_Documentos_' . (new \DateTime())->format('Y-m-d_H-i-s') . '.md"');

        return $response;
    }

    /**
     * Elimina un documento del informe y de la base de datos
     * 
     * @Route("/AJAX/eliminar_documento/{idDocumento}", name="api_eliminar_documento", methods={"DELETE"})
     */
    public function eliminarDocumentoPruebaAction(Request $request, $idDocumento)
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Documento no encontrado'
        ], 404);
    }
    public function eliminarDocumentoAction(Request $request, $idDocumento)
    {
        try {
            // Validar que el expediente existe
            $em = $this->getDoctrine()->getManager();
            
            // Intenta obtener el documento usando id_analisis (nombre real del campo)
            $sql = "SELECT * FROM documento_analisis WHERE id_analisis = :idDocumento";
            $connection = $em->getConnection();
            $statement = $connection->prepare($sql);
            $statement->execute([
                ':idDocumento' => $idDocumento
            ]);
            
            $documento = $statement->fetch();
            
            if (!$documento) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            // Obtener el ID real del documento (puede ser id_analisis o id_documento_analisis)
            $idDocReal = $documento['id_analisis'] ?? null;
            
            if (!$idDocReal) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No se pudo identificar el ID del documento'
                ], 400);
            }

            // Eliminar de la base de datos usando el ID real
            $sqlDelete = "DELETE FROM documento_analisis WHERE id_analisis = :idDocumento";
            $statementDelete = $connection->prepare($sqlDelete);
            $statementDelete->execute([':idDocumento' => $idDocReal]);


            return new JsonResponse([
                'success' => true,
                'message' => 'Documento eliminado correctamente',
                'id_documento' => $idDocReal
            ], 200);
            
        } catch (\Exception $e) {
            $this->get('logger')->error('Error en eliminarDocumento: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al eliminar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carga SOLO el consolidado del expediente (sin otros documentos)
     * @Route("/AJAX/consolidado/{idExpediente}", name="api_cargar_consolidado", methods={"GET"})
     */
    public function cargarConsolidadoAction($idExpediente)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            
            // Validar expediente
            $expediente = $em->getRepository('AppBundle:Expediente')->find($idExpediente);
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener consolidado usando query nativa
            $connection = $em->getConnection();
            $sql = "SELECT id_analisis, id_expediente, nombre_documento, contenido_extraido, 
                           tipo_documento, proveedor_ia, modelo_ia, estado, fecha_analisis
                    FROM documento_analisis
                    WHERE id_expediente = :idExp AND tipo_documento = 'CONSOLIDADO'
                    LIMIT 1";
            
            $stmt = $connection->prepare($sql);
            $stmt->execute([':idExp' => $idExpediente]);
            $consolidadoRow = $stmt->fetch();
            
            if (!$consolidadoRow) {
                // No existe consolidado
                return new JsonResponse([
                    'success' => false,
                    'existe' => false,
                    'message' => 'No existe consolidado para este expediente'
                ], 200);
            }
            
            // Parsear contenido extraído (es JSON)
            $contenido = $consolidadoRow['contenido_extraido'];
            if (is_string($contenido)) {
                $contenido = json_decode($contenido, true);
            }
            
            return new JsonResponse([
                'success' => true,
                'existe' => true,
                'consolidado' => [
                    'id_analisis' => $consolidadoRow['id_analisis'],
                    'nombre_documento' => $consolidadoRow['nombre_documento'],
                    'contenido' => $contenido ?? [],
                    'estado' => $consolidadoRow['estado'],
                    'fecha_analisis' => $consolidadoRow['fecha_analisis'],
                    'proveedor_ia' => $consolidadoRow['proveedor_ia'],
                    'modelo_ia' => $consolidadoRow['modelo_ia']
                ]
            ], 200);
            
        } catch (\Exception $e) {
            error_log('[ERROR] cargarConsolidado: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al cargar consolidado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa un Excel con IA y genera informe
     * @Route("/AJAX/procesar-excel-ia", name="api_procesar_excel_ia", methods={"POST"})
     */
    public function procesarExcelIAAction(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['hojas'])) {
                return new JsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
            }
            
            error_log('📊 Procesando Excel con IA. Hojas: ' . count($data['hojas']));
            
            // Obtener configuración IA
            $configIA = $this->obtenerConfiguracionIA();
            if (!$configIA || !isset($configIA['api_key']) || !$configIA['api_key']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API Key no configurada para proveedor IA'
                ], 500);
            }
            
            // Analizar estructura y contenido del Excel
            $estadisticasExcel = $this->analizarEstructuraExcel($data['hojas']);
            
            // Preparar prompt mejorado para IA
            $prompt = $this->construirPromptAnalisisExcel($data, $estadisticasExcel);
            
            error_log('📝 Prompt construido, enviando a IA: ' . ($configIA['provider'] ?? 'DESCONOCIDO'));
            
            // Generar informe usando IA
            $informe = null;
            
            if ($configIA['provider'] === 'GEMINI') {
                $informe = $this->generarInformeGemini($prompt, $configIA);
            } else if ($configIA['provider'] === 'OPENAI') {
                $informe = $this->generarInformeOpenAI($prompt, $configIA);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Proveedor IA no soportado: ' . $configIA['provider']
                ], 500);
            }
            
            if (!$informe) {
                error_log('❌ IA no devolvió respuesta');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'IA no pudo generar el informe'
                ], 500);
            }
            
            error_log('✅ Informe generado exitosamente');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Informe generado correctamente',
                'informe' => $informe
            ]);
            
        } catch (\Exception $e) {
            error_log('❌ Error en procesarExcelIA: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analiza la estructura y estadísticas del Excel
     */
    private function analizarEstructuraExcel($hojas): array
    {
        $estadisticas = [
            'total_hojas' => count($hojas),
            'hojas_analisis' => [],
            'tipos_datos_detectados' => []
        ];
        
        foreach ($hojas as $hoja) {
            $datos = $hoja['datos'] ?? [];
            
            if (empty($datos)) {
                continue;
            }
            
            $filas = count($datos);
            $columnas = count((array)$datos[0]);
            $columnaNames = array_keys((array)$datos[0]);
            
            // Detectar tipos de datos
            $tiposDetectados = $this->detectarTiposDatos($datos, $columnaNames);
            
            // Calcular estadísticas por columna
            $estadisticasColumnas = [];
            foreach ($columnaNames as $col) {
                $valores = array_column($datos, $col);
                $tiposCol = array_count_values(array_map(fn($v) => $this->detectarTipoDato($v), $valores));
                
                $estadisticasColumnas[$col] = [
                    'tipo_predominante' => array_key_first($tiposCol),
                    'valores_unicos' => count(array_unique($valores)),
                    'valores_vacios' => count(array_filter($valores, fn($v) => empty($v))),
                    'rango_numerico' => $this->calcularRangoNumerico($valores)
                ];
            }
            
            $estadisticas['hojas_analisis'][] = [
                'nombre' => $hoja['nombre'],
                'filas' => $filas,
                'columnas' => $columnas,
                'nombres_columnas' => $columnaNames,
                'estadisticas_columnas' => $estadisticasColumnas,
                'tipos_datos' => $tiposDetectados
            ];
            
            // Agregar tipos detectados globales
            foreach ($tiposDetectados as $tipo => $cant) {
                $estadisticas['tipos_datos_detectados'][$tipo] = 
                    ($estadisticas['tipos_datos_detectados'][$tipo] ?? 0) + $cant;
            }
        }
        
        return $estadisticas;
    }

    /**
     * Detecta tipos de datos en todas las celdas
     */
    private function detectarTiposDatos($datos, $columnas): array
    {
        $tipos = [];
        
        foreach ($columnas as $col) {
            $valores = array_column($datos, $col);
            foreach ($valores as $v) {
                $tipo = $this->detectarTipoDato($v);
                $tipos[$tipo] = ($tipos[$tipo] ?? 0) + 1;
            }
        }
        
        return $tipos;
    }

    /**
     * Detecta el tipo de un valor individual
     */
    private function detectarTipoDato($valor): string
    {
        if (empty($valor) || $valor === null) return 'vacio';
        if (is_numeric($valor)) return 'numerico';
        if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', trim($valor))) return 'fecha';
        if (preg_match('/^[0-9]{8,}[A-Z]?$/', trim($valor))) return 'identificacion';
        if (filter_var($valor, FILTER_VALIDATE_EMAIL)) return 'email';
        return 'texto';
    }

    /**
     * Calcula rango numérico de una columna
     */
    private function calcularRangoNumerico($valores): ?array
    {
        $numericos = array_filter($valores, fn($v) => is_numeric($v));
        
        if (empty($numericos)) return null;
        
        $numericos = array_map(fn($v) => (float)$v, $numericos);
        
        return [
            'minimo' => min($numericos),
            'maximo' => max($numericos),
            'promedio' => array_sum($numericos) / count($numericos),
            'cantidad' => count($numericos)
        ];
    }

    /**
     * Construye un prompt mejorado para análisis de Excel
     */
    private function construirPromptAnalisisExcel($data, $estadisticas): string
    {
        $prompt = <<<PROMPT
TAREA: Análisis Profesional y Detallado de Datos de Excel

INFORMACIÓN DEL ARCHIVO:
- Nombre: {$data['nombreArchivo']}
- Total de hojas: {$estadisticas['total_hojas']}
- Tipos de datos detectados: {$this->formatearTiposDetectados($estadisticas['tipos_datos_detectados'])}

ESTRUCTURA DETALLADA DE LAS HOJAS:

PROMPT;
        
        // Agregar detalles de cada hoja
        foreach ($estadisticas['hojas_analisis'] as $idx => $hoja) {
            $prompt .= "\n=== HOJA " . ($idx + 1) . ": {$hoja['nombre']} ===\n";
            $prompt .= "Dimensiones: {$hoja['filas']} filas x {$hoja['columnas']} columnas\n";
            $prompt .= "Columnas: " . implode(", ", $hoja['nombres_columnas']) . "\n\n";
            
            // Estadísticas por columna
            $prompt .= "ANÁLISIS POR COLUMNA:\n";
            foreach ($hoja['estadisticas_columnas'] as $col => $stats) {
                $prompt .= "  • {$col}:\n";
                $prompt .= "    - Tipo predominante: {$stats['tipo_predominante']}\n";
                $prompt .= "    - Valores únicos: {$stats['valores_unicos']}\n";
                $prompt .= "    - Valores vacíos: {$stats['valores_vacios']}\n";
                
                if ($stats['rango_numerico']) {
                    $rango = $stats['rango_numerico'];
                    $prompt .= "    - Rango: {$rango['minimo']} a {$rango['maximo']}\n";
                    $prompt .= "    - Promedio: " . number_format($rango['promedio'], 2) . "\n";
                }
            }
        }
        
        // Datos reales
        $prompt .= "\n=== DATOS DETALLADOS ===\n\n";
        foreach ($data['hojas'] as $hoja) {
            $prompt .= "HOJA: {$hoja['nombre']}\n";
            
            if (isset($hoja['datos']) && is_array($hoja['datos']) && !empty($hoja['datos'])) {
                // Mostrar en formato tabla
                $prompt .= $this->formatearDatosEnTabla($hoja['datos']);
            }
            $prompt .= "\n";
        }
        // Solicitud de análisis
        $prompt .= <<<PROMPT

=== REQUERIMIENTOS DEL ANÁLISIS ===

Genera un INFORME PROFESIONAL EN HTML con exactamente 3 secciones:

1. **TÍTULO**
   - Título descriptivo basado en el contenido del archivo
   - Breve descripción del tipo de datos

2. **ANÁLISIS CUANTITATIVO**
   - Estadísticas principales (totales, promedios, rangos, mínimos, máximos)
   - Distribuciones de datos relevantes
   - Anomalías o inconsistencias detectadas
   - Presenta los datos en tablas claras con encabezados
   - IMPORTANTE: NO UNIFICAR ITEMS DEL MISMO TIPO CON DIFERENTES FECHAS O PERÍODOS
   - Mostrar COMPARATIVAS EXPLÍCITAS: "préstamo en junio contra préstamo en diciembre", "hipoteca 2024 contra hipoteca 2025", etc.
   - Cada producto/item debe aparecer SEPARADO E IDENTIFICABLE con su fecha, período o característica diferenciadora
   - Las comparativas deben indicar claramente diferencias en montos, tasas, estado, etc.

3. **CONCLUSIONES**
   - Hallazgos principales
   - Interpretación del significado de los datos
   - Observaciones relevantes y patrones identificados

FORMATO REQUERIDO:
- HTML bien formateado con estilos inline profesionales
- Usa <h2> para el título principal
- Usa <h3> para secciones secundarias
- Usa <table> con bordes para datos tabulares (bordes: 1px solid #0066cc)
- Encabezados de tabla con fondo azul (#0066cc) y texto blanco
- Filas alternadas en gris claro (#f9f9f9) para mejor legibilidad
- Colores profesionales: azules (#0066cc), grises (#333, #666, #f9f9f9)
- NO incluyas ```html ni bloques de código
- Apto para imprimir (márgenes razonables)
- Máximo 2-3 tablas (resume datos si hay muchos)
- Usa <ul> y <li> para listas de conclusiones

TONE: Profesional, conciso, analítico

PROMPT;
        
        return $prompt;
    }

    /**
     * Formatea tipos de datos detectados
     */
    private function formatearTiposDetectados($tipos): string
    {
        $resultado = [];
        foreach ($tipos as $tipo => $cantidad) {
            $resultado[] = "$tipo ($cantidad)";
        }
        return implode(", ", $resultado);
    }

    /**
     * Formatea datos en tabla para el prompt
     */
    private function formatearDatosEnTabla($datos): string
    {
        if (empty($datos)) return "(Sin datos)\n";
        
        $salida = "```\n";
        
        // Encabezados
        $columnas = array_keys((array)$datos[0]);
        $salida .= implode(" | ", $columnas) . "\n";
        $salida .= str_repeat("-", 80) . "\n";
        
        // Datos (limitar a primeras 20 filas para no saturar el prompt)
        $filas = array_slice($datos, 0, 20);
        foreach ($filas as $fila) {
            $valores = [];
            foreach ($columnas as $col) {
                $val = $fila[$col] ?? '';
                $val = substr((string)$val, 0, 20); // Limitar a 20 caracteres
                $valores[] = $val;
            }
            $salida .= implode(" | ", $valores) . "\n";
        }
        
        if (count($datos) > 20) {
            $salida .= "... y " . (count($datos) - 20) . " filas más\n";
        }
        
        $salida .= "```\n";
        
        return $salida;
    }

    /**
     * Genera y guarda el consolidado completo del expediente (server-side)
     * @Route("/AJAX/generar-consolidado/{idExpediente}", name="api_generar_consolidado", methods={"POST"})
     */
    public function generarConsolidadoAction(Request $request, $idExpediente)
    {
        try {
            $em = $this->getDoctrine()->getManager();

            // Validar expediente
            $expediente = $em->getRepository('AppBundle:Expediente')->find($idExpediente);
            if (!$expediente) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }

            error_log('🔵 Iniciando generarConsolidadoAction para expediente: ' . $idExpediente);

            // Obtener configuración IA
            $configIA = $this->obtenerConfiguracionIA();
            if (!$configIA || !isset($configIA['api_key']) || !$configIA['api_key']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API Key no configurada para proveedor IA'
                ], 500);
            }

            // Consultar documentos existentes en BD (no consolidado)
            $connection = $em->getConnection();
            $sqlDocs = "SELECT id_analisis, nombre_documento, tipo_documento, contenido_extraido, confianza, fecha_analisis, estado
                        FROM documento_analisis
                        WHERE id_expediente = :idExpediente
                        AND tipo_documento != 'CONSOLIDADO'
                        AND estado = 'procesado'";
            $stmtDocs = $connection->prepare($sqlDocs);
            $stmtDocs->execute([':idExpediente' => $idExpediente]);
            $docsFromDb = $stmtDocs->fetchAll();

            if (empty($docsFromDb)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No hay documentos procesados para consolidar'
                ], 400);
            }

            $documentosExistentes = [];
            foreach ($docsFromDb as $d) {
                $contenidoDoc = $d['contenido_extraido'];
                if (is_string($contenidoDoc)) {
                    $contenidoDoc = json_decode($contenidoDoc, true);
                    if (!is_array($contenidoDoc)) $contenidoDoc = [];
                }
                $datos = $contenidoDoc['datos_principales'] ?? $contenidoDoc;
                $documentosExistentes[] = [
                    'id_analisis' => $d['id_analisis'],
                    'nombre_documento' => $d['nombre_documento'],
                    'tipo' => $d['tipo_documento'],
                    'datos' => $datos,
                    'confianza' => $d['confianza'],
                    'fecha_analisis' => $d['fecha_analisis'],
                    'estado' => $d['estado']
                ];
            }

            // Merge: key by id_analisis (único para cada documento)
            // IMPORTANTE: NO usar nombre_documento como clave porque puede haber duplicados
            // Ejemplo: MODELO_100 de PEREZ y MODELO_100 de ALVARO tienen el mismo nombre
            // pero son documentos DIFERENTES que no deben sobrescribirse
            $mergedById = [];
            foreach ($documentosExistentes as $doc) {
                $key = $doc['id_analisis'];
                $mergedById[$key] = $doc;
            }

            $mergedDocs = array_values($mergedById);

            // Construir payload para IA
            $datosConsolidado = [
                'cantidad_documentos' => count($mergedDocs),
                'documentos' => $mergedDocs,
                'proveedor' => $configIA['provider'] ?? 'desconocido'
            ];

            // Construir prompt para consolidación
            $prompt = $this->construirPromptConsolidacionDatos($datosConsolidado);

            // Generar respuesta de IA (debe ser SOLO JSON)
            $respuestaIA = null;
            if ($configIA['provider'] === 'GEMINI') {
                $respuestaIA = $this->generarInformeGemini($prompt, $configIA);
            } else if ($configIA['provider'] === 'OPENAI') {
                $respuestaIA = $this->generarInformeOpenAI($prompt, $configIA);
            }

            if (!$respuestaIA) {
                error_log('[ERROR] IA no devolvio respuesta');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No se pudo generar el consolidado con IA'
                ], 500);
            }

            // PARSEAR RESPUESTA JSON DE IA
            // Limpiar posibles caracteres extras o bloques markdown
            $jsonLimpio = $respuestaIA;
            $jsonLimpio = preg_replace('/^```json\s*/i', '', $jsonLimpio);
            $jsonLimpio = preg_replace('/\s*```$/i', '', $jsonLimpio);
            $jsonLimpio = trim($jsonLimpio);
            
            $datosConsolidadosIA = json_decode($jsonLimpio, true);
            
            if (!is_array($datosConsolidadosIA)) {
                error_log('[ERROR] IA devolvio JSON invalido: ' . substr($respuestaIA, 0, 200));
                return new JsonResponse([
                    'success' => false,
                    'message' => 'La IA devolvio un JSON invalido'
                ], 500);
            }

            // Guardar JSON consolidado en BD
            try {
                $this->guardarAnalisisConsolidadoEnBD($idExpediente, $datosConsolidadosIA, $configIA);
                error_log('[CONSOLIDADO] Guardado exitoso en BD');
            } catch (\Exception $e) {
                error_log('[ERROR] Guardando consolidado: ' . $e->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Error al guardar consolidado: ' . $e->getMessage()
                ], 500);
            }

            // Devolver JSON al frontend para que construya HTML
            return new JsonResponse([
                'success' => true,
                'consolidado' => $datosConsolidadosIA
            ], 200);

        } catch (\Exception $e) {
            error_log('❌ Excepción en generarConsolidadoAction: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera informe ejecutivo del consolidado usando IA
     * @Route("/AJAX/generar-informe-ejecutivo/{idExpediente}", name="api_generar_informe_ejecutivo", methods={"POST"})
     */
    public function generarInformeEjecutivoAction(Request $request, $idExpediente)
    {
        try {
            error_log('🔵 Iniciando generarInformeEjecutivo para expediente: ' . $idExpediente);
            
            $em = $this->getDoctrine()->getManager();
            $contentRaw = $request->getContent();
            error_log('📦 Contenido recibido longitud: ' . strlen($contentRaw));
            
            $datosConsolidado = json_decode($contentRaw, true);

            if (!$datosConsolidado) {
                error_log('❌ Datos inválidos, JSON error: ' . json_last_error_msg());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Datos del consolidado inválidos: ' . json_last_error_msg()
                ], 400);
            }

            error_log('✅ Datos consolidado procesados: ' . json_encode(array_keys($datosConsolidado)));

            // --- Reunir TODOS los documentos procesados del expediente y mergear con los enviados
            $documentosEnviados = $datosConsolidado['documentos'] ?? [];

            // Consultar documentos existentes en BD (no consolidado)
            $connection = $em->getConnection();
            $sqlDocs = "SELECT id_analisis, nombre_documento, tipo_documento, contenido_extraido, confianza, fecha_analisis, estado
                        FROM documento_analisis
                        WHERE id_expediente = :idExpediente
                        AND tipo_documento != 'CONSOLIDADO'
                        AND estado = 'procesado'";
            $stmtDocs = $connection->prepare($sqlDocs);
            $stmtDocs->execute([':idExpediente' => $idExpediente]);
            $docsFromDb = $stmtDocs->fetchAll();

            $documentosExistentes = [];
            foreach ($docsFromDb as $d) {
                $contenidoDoc = $d['contenido_extraido'];
                if (is_string($contenidoDoc)) {
                    $contenidoDoc = json_decode($contenidoDoc, true);
                    if (!is_array($contenidoDoc)) $contenidoDoc = [];
                }
                $datos = $contenidoDoc['datos_principales'] ?? $contenidoDoc;
                $documentosExistentes[] = [
                    'id_analisis' => $d['id_analisis'],
                    'nombre_documento' => $d['nombre_documento'],
                    'tipo' => $d['tipo_documento'],
                    'datos' => $datos,
                    'confianza' => $d['confianza'],
                    'fecha_analisis' => $d['fecha_analisis'],
                    'estado' => $d['estado']
                ];
            }

            // Merge documentos: key by id_analisis (ÚNICO para cada documento)
            // CRÍTICO: NO usar nombre_documento porque documentos del mismo tipo pero diferentes personas
            // resultarían en sobrescritura. Ejemplo: MODELO_100 PEREZ vs MODELO_100 ALVARO
            $mergedById = [];
            foreach ($documentosExistentes as $doc) {
                $key = $doc['id_analisis'];
                $mergedById[$key] = $doc;
            }
            foreach ($documentosEnviados as $doc) {
                // Hacer merge consultando también por nombre si id_analisis no existe (caso frontend)
                $key = $doc['id_analisis'] ?? ($doc['nombre_documento'] ?? uniqid());
                // Normalizar campos esperados
                $mergedById[$key] = array_merge($mergedById[$key] ?? [], [
                    'id_analisis' => $doc['id_analisis'] ?? ($mergedById[$key]['id_analisis'] ?? null),
                    'nombre_documento' => $doc['nombre_documento'] ?? ($mergedById[$key]['nombre_documento'] ?? null),
                    'tipo' => $doc['tipo'] ?? ($mergedById[$key]['tipo'] ?? null),
                    'datos' => $doc['datos'] ?? ($mergedById[$key]['datos'] ?? []),
                    'confianza' => $doc['confianza'] ?? ($mergedById[$key]['confianza'] ?? null),
                    'fecha_analisis' => $doc['fecha_analisis'] ?? ($mergedById[$key]['fecha_analisis'] ?? null),
                    'estado' => $doc['estado'] ?? ($mergedById[$key]['estado'] ?? null)
                ]);
            }

            $mergedDocs = array_values($mergedById);

            // Guardar consolidado combinado en BD (insert/update)
            try {
                $this->guardarAnalisisConsolidadoEnBD($idExpediente, $mergedDocs, $configIA);
                error_log('✅ Consolidado combinado guardado en BD con ' . count($mergedDocs) . ' documentos');
            } catch (\Exception $e) {
                error_log('❌ Error guardando consolidado combinado: ' . $e->getMessage());
            }

            // Reemplazar datosConsolidado por la versión mergeada para construir prompt
            $datosConsolidado = [
                'cantidad_documentos' => count($mergedDocs),
                'documentos' => $mergedDocs,
                'proveedor' => $datosConsolidado['proveedor'] ?? ($configIA['provider'] ?? 'desconocido')
            ];
            
            // Validar expediente
            $expediente = $em->getRepository('AppBundle:Expediente')->find($idExpediente);
            if (!$expediente) {
                error_log('❌ Expediente no encontrado: ' . $idExpediente);
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener configuración IA
            error_log('⚙️ Obteniendo configuración IA...');
            $configIA = $this->obtenerConfiguracionIA();
            error_log('✅ Configuración IA: Provider=' . ($configIA['provider'] ?? 'UNKNOWN') . ', Model=' . ($configIA['model'] ?? 'UNKNOWN'));
            
            if (!$configIA || !isset($configIA['api_key']) || !$configIA['api_key']) {
                error_log('❌ API Key no configurada para proveedor: ' . ($configIA['provider'] ?? 'UNKNOWN'));
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API Key no configurada. Revisa la configuración de IA.'
                ], 500);
            }
            
            // Construir prompt para informe ejecutivo
            error_log('📝 Construyendo prompt...');
            $prompt = $this->construirPromptInformeEjecutivo($datosConsolidado);
            error_log('📝 Prompt construido, longitud: ' . strlen($prompt));
            
            // Generar informe usando IA
            $resultadoIA = null;
            
            if ($configIA['provider'] === 'GEMINI') {
                error_log('📡 Enviando a Gemini...');
                $resultadoIA = $this->generarInformeGemini($prompt, $configIA);
            } else if ($configIA['provider'] === 'OPENAI') {
                error_log('📡 Enviando a OpenAI...');
                $resultadoIA = $this->generarInformeOpenAI($prompt, $configIA);
            } else {
                error_log('❌ Proveedor IA no soportado: ' . $configIA['provider']);
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Proveedor IA no soportado: ' . $configIA['provider']
                ], 500);
            }
            
            if (!$resultadoIA) {
                error_log('❌ Error al generar informe con IA (resultadoIA es null)');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No se pudo generar informe. Revisa los logs del servidor.'
                ], 500);
            }
            
            error_log('✅ Informe generado correctamente, longitud: ' . strlen($resultadoIA));
            
            return new JsonResponse([
                'success' => true,
                'informe' => $resultadoIA
            ], 200);
            
        } catch (\Exception $e) {
            error_log('❌ EXCEPCIÓN en generarInformeEjecutivo: ' . $e->getMessage());
            error_log('Stack: ' . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construye el prompt para generar informe ejecutivo
     */
    /**
     * Construir prompt para consolidación - IA devuelve SOLO JSON
     */
    private function construirPromptConsolidacionDatos($datosConsolidado): string
    {
        $cantidad = $datosConsolidado['cantidad_documentos'] ?? 0;
        $documentos = $datosConsolidado['documentos'] ?? [];
        
        // Sanitizar documentos
        $documentosSanitizados = [];
        foreach ($documentos as $doc) {
            $documentosSanitizados[] = [
                'nombre' => $doc['nombre_documento'] ?? '--',
                'tipo' => $doc['tipo'] ?? '--',
                'datos' => is_array($doc['datos']) ? $doc['datos'] : '--',
                'confianza' => $doc['confianza'] ?? '--'
            ];
        }
        
        $datosJSON = json_encode($documentosSanitizados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return <<<PROMPT
TAREA: Consolidar datos financieros de expediente en JSON completo

Tienes $cantidad documentos de un expediente que contienen información de:
- Identificación personal (DNI, nombre, estado civil, hijos)
- Ingresos (trabajo, capital inmobiliario, ganancias patrimoniales)
- Retenciones y pagos fraccionados
- Actividad económica (si aplica)
- IVA (si aplica)
- Seguridad Social y cotizaciones
- Situación fiscal general

DOCUMENTOS A CONSOLIDAR:
$datosJSON

INSTRUCCIÓN CRÍTICA:
Devuelve ÚNICAMENTE un JSON válido consolidando TODOS los datos extraídos.
NO inventes datos. SOLO usa lo que aparece en los documentos.
Si un campo no aparece en ningún documento: OMÍTELO del JSON (no uses null, no lo dejes vacío).

DETECCIÓN DE MÚLTIPLES INTERVINIENTES (CRÍTICO):
1. **IDENTIFICA CADA PERSONA ÚNICA POR SU DNI/NIF** - Esta es la clave fundamental.
2. Si encuentras 2 DNIs diferentes en los documentos = 2 intervinientes DIFERENTES.
3. Si encuentras 1 DNI = 1 interviniente.
4. Ejemplos de múltiples intervinientes: solicitante + cónyuge, solicitante + avalista, etc.
5. AGRUPA todos los datos de la MISMA PERSONA (mismo DNI) en UN SOLO objeto dentro del array.
6. SEPARA en objetos diferentes si los DNI/NIFs son DISTINTOS.
7. NUNCA mezcles datos de diferentes personas en el mismo objeto.

ALGORITMO DE CONSOLIDACIÓN:
- Paso 1: Extrae TODOS los DNI/NIF únicos de los documentos.
- Paso 2: Para CADA DNI/NIF único, crea UN elemento en el array "intervinientes".
- Paso 3: En cada elemento, incluye TODOS los datos que correspondan a ese DNI.
- Paso 4: Si un documento tiene datos de múltiples DNIs, asigna cada dato a su DNI correspondiente.

UNIFICACIÓN DE CAMPOS POR INTERVINIENTE (ALGORITMO CRÍTICO - LEE ESTO 3 VECES):

⚠️ PUNTO CRÍTICO: Cada interviniente tiene DOCUMENTOS DIFERENTES
- PEREZ puede tener: MODELO_100, COTIZACION, SITUACION_AEAT
- ALVARO puede tener: VIDA_LABORAL, COTIZACION, VIDA_LABORAL
- Los tipos de documentos SON DIFERENTES pero ambos pueden contener los MISMOS CAMPOS

**PASO CERO (ANÁLISIS GLOBAL - OBSERVA TODOS LOS DOCUMENTOS):**
Analiza TODOS los documentos de TODOS los intervinientes (sin agrupar por persona).
Para CADA tipo de campo posible, pregúntate:
- ¿Existe INGRESOS (base_cotizacion, rendimientos, salarios) en ALGÚN documento de CUALQUIER tipo? → ✓ GLOBAL
- ¿Existe RETENCIONES (IRPF, pagos) en ALGÚN documento? → ✓ GLOBAL
- ¿Existe SEGURIDAD_SOCIAL (periodo_cotizado, numero_afiliacion) en ALGÚN documento? → ✓ GLOBAL
- ¿Existe SITUACION_FISCAL (estado, resultado_declaracion) en ALGÚN documento? → ✓ GLOBAL
- ¿Existe ACTIVIDAD_ECONOMICA en ALGÚN documento? → ✓ GLOBAL
- ¿Existe IVA en ALGÚN documento? → ✓ GLOBAL

Ejemplo: Si PEREZ tiene MODELO_100 (con ingresos) y ALVARO tiene COTIZACION (con bases de cotización) → INGRESOS es GLOBAL

**PASO UNO (CREAR INTERVINIENTES POR DNI):**
Para CADA DNI único:
- Crea UN objeto interviniente
- Reúne TODOS los documentos de este DNI (sin importar tipo)
- Ejemplo: ALVARO tiene [VIDA_LABORAL doc1, COTIZACION doc2]

**PASO DOS (LLENAR CAMPOS - BUSCAR EN TODOS LOS DOCUMENTOS DE CADA DNI):**
Para CADA interviniente (agrupado por DNI):
  Para CADA campo en CAMPOS_GLOBALES (identificados en PASO CERO):
    - Busca ese campo en TODOS los documentos de este DNI (TODOS, sin restricción de tipo)
    - EJEMPLO: Buscas INGRESOS en ALVARO:
      * Miras VIDA_LABORAL → busca salarios, bases de cotización, rendimientos
      * Miras COTIZACION → busca bases de cotización, que son un tipo de ingreso
      * Si encuentras algo en CUALQUIER documento → incluye el campo "ingresos"
    - Si encuentras datos → Incluye el campo con los datos encontrados
    - Si NO encuentras en NINGÚN documento de este DNI → Incluye el campo VACÍO {}

LISTA DE BÚSQUEDA POR TIPO DE DOCUMENTO (COMPLETA):

CAMPOS "INGRESOS" pueden venir de:
- MODELO_100, PENULTIMA_RENTA, MODELO_100_2022 → rendimientos_trabajo, rendimientos_capital_inmobiliario, ganancias_patrimoniales, rendimientos_actividades_economicas
- NOMINA → salario_bruto, complementos
- VIDA_LABORAL → salarios por empresa (en el historial)
- COTIZACION → bases_cotizacion (son rendimientos de trabajo)
- MODELO_130, MODELO_131 → ingresos_integros, rendimiento_neto
- MODELO_111, MODELO_190 → importe_ingresos_ejercicio

CAMPOS "RETENCIONES" pueden venir de:
- MODELO_100, PENULTIMA_RENTA, MODELO_100_2022 → retenciones_aplicadas, pagos_fraccionados
- NOMINA → retenciones_irpf, descuentos
- MODELO_111 → retenciones_practicadas_irpf
- MODELO_190 → total_retenciones
- MODELO_130, MODELO_131 → pagos_fraccionados

CAMPOS "SEGURIDAD_SOCIAL" pueden venir de:
- COTIZACION → bases_cotizacion, meses_cotizacion, periodo_cotizado
- VIDA_LABORAL → numero_afiliacion_ss, historial de empresas (fechas alta/baja), periodo_total_cotizado
- SITUACION_SS → situacion_seguridad_social, estado_pago, importe_deuda
- PENSIONES → importe_neto_mensual, organismo_emisor

CAMPOS "SITUACION_FISCAL" pueden venir de:
- MODELO_100, PENULTIMA_RENTA, MODELO_100_2022 → resultado_declaracion, base_imponible_general, estado declaracion
- SITUACION_AEAT → situacion_actual, importe_pendiente, estado
- CENSO → estado_inscripcion

CAMPOS "ACTIVIDAD_ECONOMICA" pueden venir de:
- MODELO_130, MODELO_131 → rendimiento_neto, ingresos_integros, gastos_fiscalmente_deducibles
- CENSO → epigrafe_iae, actividades_registradas
- VIDA_LABORAL → si hay episodes de trabajo por cuenta propia

CAMPOS "IVA" pueden venir de:
- MODELO_303 → iva_repercutido, iva_soportado, resultado_liquidacion
- MODELO_390 → iva_devengado_total, iva_deducible_total

CRÍTICO: Busca CADA campo en TODOS estos tipos de documento para CADA interviniente, sin limitarte a un solo tipo

EJEMPLO A EVITAR (INCORRECTO):
{
  "intervinientes": [
    {
      "identificacion_persona": {...},
      "ingresos": {...},           ← Encontrado en MODELO_100
      "retenciones": {...},        ← Encontrado en MODELO_100
      "situacion_fiscal": {...},   ← Encontrado en SITUACION_AEAT
      "seguridad_social": {...}    ← Encontrado en COTIZACION
    },
    {
      "identificacion_persona": {...},
      "seguridad_social": {...}    ← ❌ SOLO este. NO buscó ingresos/retenciones en VIDA_LABORAL y COTIZACION
    }
  ]
}

EJEMPLO CORRECTO:
{
  "intervinientes": [
    {
      "identificacion_persona": {...},
      "ingresos": {
        "rendimientos_trabajo": 25000
      },
      "retenciones": {
        "retenciones_irpf": 8000
      },
      "situacion_fiscal": {
        "estado": "al corriente"
      },
      "seguridad_social": {
        "periodo_cotizado": "10 años"
      }
    },
    {
      "identificacion_persona": {...},
      "ingresos": {
        "rendimientos_trabajo": 18000  ← BUSCÓ en todos los documentos, ENCONTRÓ en COTIZACION (bases)
      },
      "retenciones": {
        "retenciones_irpf": 900         ← BUSCÓ en todos los documentos, ENCONTRÓ en COTIZACION o VIDA_LABORAL
      },
      "situacion_fiscal": {},           ← BUSCÓ pero NO encontró en ningún documento - VACÍO pero PRESENTE
      "seguridad_social": {
        "periodo_cotizado": "5 años",
        "numero_afiliacion_ss": "123456789"
      }
    }
  ]
}

ESTRUCTURA DEL JSON A GENERAR:

⛔ REGLA ABSOLUTAMENTE CRÍTICA - LEER PRIMERO:

**QUÉ HACER:**
- Si encontraste datos → "ingresos": { "rendimientos_trabajo": 25000 } ✓
- Si es un campo global pero NO encontraste datos → "ingresos": {} ✓
- Incluye SIEMPRE campos globales en TODOS los intervinientes

**QUÉ NUNCA HACER:**
- Arrays vacíos: "ingresos": [] ← ❌❌❌ NUNCA
- Null values: "ingresos": null ← ❌❌❌ NUNCA
- Strings vacíos: "ingresos": "" ← ❌❌❌ NUNCA
- Omitir campos globales: si ingresos es global, DEBE estar en TODOS ← ❌❌❌ NUNCA

FORMATEO CORRECTO:
- "ingresos": { "rendimientos_trabajo": 25000, "rendimientos_capital_inmobiliario": 5000 }  ← Con datos
- "ingresos": {}  ← Sin datos (pero PRESENTE porque es global)
- "ingresos": { "rendimientos_trabajo": 0 }  ← Con un dato si lo encuentras

CHECKLIST PREVIO - DEBES HACER ESTO PRIMERO:
1. **PASO GLOBAL:** Analiza TODO el expediente (sin agrupar por DNI todavía)
   - Lista todos los campos que existen en ALGÚN documento: [ ingresos?, retenciones?, SS?, situacion_fiscal?, actividad_economica?, iva? ]
   - Ejemplo: Si encuentras "ingresos" en Doc1 (DNI A) y "retenciones" en Doc2 (DNI B) → CAMPOS_GLOBALES = [ingresos, retenciones]

2. **PASO POR DNI:** Para cada DNI identificado:
   - Reúne TODOS los documentos de este DNI
   - Busca CADA campo en CAMPOS_GLOBALES en los documentos de este DNI
   - TODOS los campos globales DEBEN estar en el JSON de este interviniente (con datos o vacíos {})

3. **PASO FINAL:** Antes de generar JSON, verifica:
   - Persona 1 tiene: [ingresos, retenciones, seguridad_social, situacion_fiscal]
   - Persona 2 tiene: [ingresos, retenciones, seguridad_social, situacion_fiscal]  ← EXACTAMENTE los mismos campos
   - Cada campo está presente (aunque esté vacío {})

{
  "intervinientes": [
    {
      "identificacion_persona": {
        "nombre": "Nombre completo exacto",
        "nif": "DNI/NIE",
        "estado_civil": "soltero/casado/divorciado/viudo",
        "hijos": número entero
      },
      "ingresos": {
        "rendimientos_trabajo": número decimal,
        "rendimientos_capital_inmobiliario": número decimal,
        "rendimientos_capital_mobiliario": número decimal,
        "ganancias_patrimoniales": número decimal,
        "rendimientos_actividades_economicas": número decimal
      },
      "retenciones": {
        "retenciones_irpf": número decimal,
        "pagos_fraccionados": número decimal
      },
      "actividad_economica": {
        "rendimiento_neto": número decimal,
        "ingresos": número decimal,
        "gastos": número decimal
      },
      "iva": {
        "iva_repercutido": número decimal,
        "iva_soportado": número decimal,
        "resultado": número decimal
      },
      "seguridad_social": {
        "periodo_cotizado": "texto (ej: '12 años')",
        "base_media": número decimal
      },
      "situacion_fiscal": {
        "estado": "texto descriptivo (ej: 'al corriente', 'deudor', etc)"
      }
    },
    ... (más intervinientes si aplica)
  ],
  "conclusion": {
    "resumen": "Resumen ejecutivo breve del análisis financiero",
    "capacidad_pago": "Análisis de capacidad de pago observada",
    "solidez_financiera": "Evaluación de solidez financiera",
    "observaciones": "Observaciones relevantes para evaluación crediticia"
  }
}

REGLAS ESTRICTAS:
1. SOLO números decimales en campos numéricos (ej 1200.50, NO 1.200,50)
2. NO inventes datos que no aparezcan en los documentos
3. **DIFERENCIA CRÍTICA ENTRE "CAMPO GLOBAL" Y "DATO DENTRO DE CAMPO":**
   - Si un CAMPO es GLOBAL (existe en ALGÚN interviniente) → DEBE ESTAR EN TODOS los intervinientes
   - Si un CAMPO es global pero un interviniente NO tiene datos → OMITE el campo completamente (no incluyas {} vacío)
   - Solo incluye campos que tienen AL MENOS UN DATO real dentro
   - EJEMPLO: Si INGRESOS existe globalmente y Persona A tiene datos de ingresos pero Persona B no → NO incluyas "ingresos": {} en Persona B
4. Las fechas deben ser DD/MM/YYYY o YYYY-MM si es período
5. Texto exacto como aparece en los documentos

RESUMEN DE REGLA PRINCIPAL:
- Si un TIPO DE CAMPO aparece en CUALQUIER documento → ese campo DEBE estar en TODOS los intervinientes
- Si un interviniente NO tiene datos de un campo global → INCLÚYELO VACÍO {} (no lo omitas)

SECCIÓN CONCLUSIÓN (ANÁLISIS CRÍTICO):
DEbes generar un análisis profesional basado EN LOS DATOS EXTRAÍDOS:
- resumen: Síntesis breve de la situación financiera (máx 2-3 líneas)
- capacidad_pago: Evalúa si los ingresos son suficientes para obligaciones observadas
- solidez_financiera: Analiza estabilidad laboral (años cotizados), diversez de ingresos, cantidad de hijos a cargo
- observaciones: Notas sobre riesgos, fortalezas o datos relevantes para evaluación crediticia

VALIDACIÓN FINAL (LEE ESTO JUSTO ANTES DE ENVIAR):
Antes de generar el JSON final, VERIFICA EXPLÍCITAMENTE:

1. Cuenta los DNI únicos: ¿Cuántos intervinientes hay?
2. Identifica los campos GLOBALES que aparecen en ALGÚN interviniente (cualquiera):
   - ¿Hay INGRESOS en algún documento de algún DNI? → SÍ/NO → GLOBAL
   - ¿Hay RETENCIONES en algún documento de algún DNI? → SÍ/NO → GLOBAL
   - ¿Hay SEGURIDAD_SOCIAL en algún documento de algún DNI? → SÍ/NO → GLOBAL
   - ¿Hay SITUACION_FISCAL en algún documento de algún DNI? → SÍ/NO → GLOBAL
   - ¿Hay ACTIVIDAD_ECONOMICA en algún documento de algún DNI? → SÍ/NO → GLOBAL
   - ¿Hay IVA en algún documento de algún DNI? → SÍ/NO → GLOBAL
3. Para CADA interviniente, VERIFICA que tenga EXACTAMENTE estos campos globales:
   - Si INGRESOS es global → **MUST INCLUDE** "ingresos" (con datos o vacío {})
   - Si RETENCIONES es global → **MUST INCLUDE** "retenciones" (con datos o vacío {})
   - Si SEGURIDAD_SOCIAL es global → **MUST INCLUDE** "seguridad_social" (con datos o vacío {})
   - Etc.
4. **NUNCA** incluyas arrays vacíos [] o null values
5. Para CADA campo incluido, verifica: ¿Tiene AL MENOS UN par clave-valor dentro? 
   - SÍ → Correcto: "ingresos": { "rendimientos_trabajo": 25000 }
   - NO → Debe estar vacío: "ingresos": {}
6. Después de esta validación, GENERA el JSON final

IMPORTANTE: Devuelve SOLO el JSON válido, sin explicaciones, sin markdown, sin etiquetas de código.
PROMPT;
    }

    private function construirPromptInformeEjecutivo($datosConsolidado): string
    {
        $cantidad = $datosConsolidado['cantidad_documentos'] ?? 0;
        $documentos = $datosConsolidado['documentos'] ?? [];
        $proveedor = $datosConsolidado['proveedor'] ?? 'desconocido';
        
        // Sanitizar documentos antes de encodificar
        $documentosSanitizados = [];
        foreach ($documentos as $doc) {
            $documentosSanitizados[] = [
                'nombre_documento' => $doc['nombre_documento'] ?? '--',
                'tipo' => $doc['tipo'] ?? '--',
                'datos' => is_array($doc['datos']) ? $doc['datos'] : (is_object($doc['datos']) ? (array)$doc['datos'] : '--'),
                'confianza' => isset($doc['confianza']) ? $doc['confianza'] : '--',
                'fecha_analisis' => $doc['fecha_analisis'] ?? '--',
                'estado' => $doc['estado'] ?? '--'
            ];
        }
        
        $datosJSON = json_encode($documentosSanitizados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if ($datosJSON === false) {
            error_log('❌ Error al codificar documentos como JSON: ' . json_last_error_msg());
            $datosJSON = json_encode(['error' => 'No se pudieron procesar los documentos correctamente']);
        }
        
        return <<<PROMPT
Eres un analista financiero profesional especializado en evaluación de expedientes inmobiliarios y crediticios.

DATOS EXTRAÍDOS DEL EXPEDIENTE:
{$datosJSON}

INSTRUCCIÓN CRÍTICA - LEER PRIMERO:
Tu única tarea es generar HTML de formato COMPACTO Y LEGIBLE para impresión/PDF.
- Todas las tablas DEBEN tener width: 100%
- Fuentes pequeñas pero legibles (11px datos, 12px encabezados)
- Márgenes y padding consistentes
- NUNCA truncar contenido
- MÁXIMO 2 columnas por tabla (Campo | Valor)
- Si hay múltiples intervinientes: UNA TABLA POR INTERVINIENTE

TAREA ESPECÍFICA:
Generar un INFORME CONSOLIDADO DE DATOS FINANCIEROS en HTML puro SIN DECORACIONES, estructurado así:

ESTRUCTURA OBLIGATORIA (DEBE COMPLETARSE SIEMPRE):

1. ENCABEZADO
Título: <h1 style="color: #0066cc; font-size: 18px;">INFORME CONSOLIDADO DE DATOS FINANCIEROS</h1>

2. RESUMEN EJECUTIVO (1 tabla: Campo | Valor)
Incluir:
- Total de documentos analizados
- Período cubierto
- Conclusión general sobre la situación financiera

3. SITUACIÓN DE INTERVINIENTES
UNA TABLA POR CADA INTERVINIENTE (vertical, 2 columnas):
Crear tabla separada para cada persona con formato Campo | Valor:
- Nombre/Identificación
- Situación laboral y empleador
- Ingresos netos mensuales
- Deducciones y obligaciones
- Activos reportados
- Pasivos y deudas disponibles

4. ANÁLISIS CONSOLIDADO (1 tabla: Aspecto | Valor)
- Capacidad de pago total
- Indicadores de endeudamiento
- Liquidez estimada
- Calificación general de riesgo

5. OBSERVACIONES FINALES (1 tabla: Tipo | Descripción)
- Fortalezas evidentes
- Riesgos identificados
- Documentación faltante u óptima

ESTRUCTURA DE TABLAS:
- CADA tabla tiene MÁXIMO 2 COLUMNAS: Campo (30% ancho) | Valor (70% ancho)
- CADA interviniente es una tabla independiente con su propio título <h3>
- Fuente: font-size 11px en datos, 12px en encabezados
- Padding: 12px en todas las celdas
- Márgenes: 20px entre secciones

NOTA: NUNCA hagas tablas con muchas columnas. Es mejor hacer una tabla vertical por interviniente que una tabla horizontal con muchas columnas.

FORMATO HTML - REGLAS OBLIGATORIAS:

✓ COMIENZA DIRECTAMENTE CON: <h1 style="margin: 0 0 20px 0; font-size: 26px; color: #1a1a1a; font-weight: bold;">INFORME CONSOLIDADO DE DATOS FINANCIEROS</h1>
✓ TODA la información va en TABLAS (no en párrafos)
✓ Estructura de tablas: MÁXIMO 2 COLUMNAS (Campo | Valor)
✓ Font-size: 11px para contenido, 12px para encabezados
✓ Encabezados de tabla: <th style="padding: 8px; text-align: left; font-weight: bold; color: #333; width: 30%;">Campo</th> para col1, <th style="padding: 8px; text-align: left; font-weight: bold; color: #333;">Valor</th> para col2
✓ Datos: <td style="padding: 8px; color: #555; font-size: 11px;">Valor</td>
✓ Filas alternas: background-color blanco o #f9f9f9
✓ Ancho: width: 100% en tablas con border-collapse: collapse
✓ Cada sección <h2 style="margin: 15px 0 10px 0; font-size: 14px; color: #1a1a1a; font-weight: bold;">Nombre</h2> seguida de su tabla
✓ Cada interviniente con su encabezado <h3 style="background-color: #ff8800; color: white; padding: 12px; margin: 20px 0 15px 0; font-weight: bold; font-size: 15px;">Interviniente</h3>
✓ TODO formato va en atributo style (SOLO inline styles)
✓ Colores: #1a1a1a (títulos), #333 (encabezados), #555 (datos)
✓ NO incluyas <html>, <head>, <body>, <meta>, <doctype>
✓ NO USES: ul, li, viñetas, listas con puntos, párrafos largos
✓ NO escribas caracteres de código: NO \`, NO #, NO *, NO **, NO _, NO ~~
✓ Márgenes en tablas: margin: 0
✓ Márgenes en títulos: margin: 15px 0 10px 0;

EJEMPLO DE TABLA CORRECTA - COPIA ESTE FORMATO:
<h2 style="margin: 15px 0 10px 0; font-size: 14px; color: #1a1a1a; font-weight: bold;">Sección</h2>
<table style="width: 100%; border-collapse: collapse; font-size: 11px;">
<thead>
<tr style="background-color: #f5f5f5; border-bottom: 1px solid #ccc;">
<th style="padding: 8px; text-align: left; font-weight: bold; color: #333; width: 30%;">Campo</th>
<th style="padding: 8px; text-align: left; font-weight: bold; color: #333;">Valor</th>
</tr>
</thead>
<tbody>
<tr style="background-color: white; border-bottom: 1px solid #ddd;">
<td style="padding: 8px; color: #555;">Dato1</td>
<td style="padding: 8px; color: #555;">Valor1</td>
</tr>
<tr style="background-color: #f9f9f9; border-bottom: 1px solid #ddd;">
<td style="padding: 8px; color: #555;">Dato2</td>
<td style="padding: 8px; color: #555;">Valor2</td>
</tr>
</tbody>
</table>

INSTRUCCIÓN CRÍTICA:
Debes COMPLETAR TODO el informe en una sola respuesta. Si no tienes todos los datos, usa "--" o "No disponible". 
NUNCA truncques ni cierres prematuramente. 
SIEMPRE cierra con una sección final de observaciones.

COMIENZA AHORA, DIRECTAMENTE CON EL HTML:
PROMPT;
    }
    
    

    /**
     * Genera informe usando Gemini
     */
    private function generarInformeGemini($prompt, $configIA)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$configIA['model']}:generateContent?key={$configIA['api_key']}";
            
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
                    "temperature" => 0,
                    "maxOutputTokens" => 8192
                ]
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log('🌐 Gemini HTTP Code: ' . $httpCode);
            
            if ($curlError) {
                error_log('❌ Curl Error en Gemini: ' . $curlError);
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("❌ Error Gemini (" . $httpCode . "): " . substr($response, 0, 500));
                return null;
            }
            
            if (!$response) {
                error_log('❌ Respuesta vacía de Gemini');
                return null;
            }
            
            $responseData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('❌ Error al decodificar respuesta Gemini: ' . json_last_error_msg());
                return null;
            }
            
            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                error_log('❌ Estructura de respuesta Gemini inesperada: ' . json_encode($responseData));
                return null;
            }
            
            return $responseData['candidates'][0]['content']['parts'][0]['text'];
        } catch (\Exception $e) {
            error_log('❌ Excepción en generarInformeGemini: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera informe usando OpenAI
     */
    private function generarInformeOpenAI($prompt, $configIA)
    {
        try {
            $url = "https://api.openai.com/v1/chat/completions";
            
            $payload = [
                "model" => $configIA['model'],
                "messages" => [
                    [
                        "role" => "user",
                        "content" => $prompt
                    ]
                ],
                "temperature" => 0,
                "max_tokens" => 8192
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $configIA['api_key']
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log('🌐 OpenAI HTTP Code: ' . $httpCode);
            
            if ($curlError) {
                error_log('❌ Curl Error en OpenAI: ' . $curlError);
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("❌ Error OpenAI (" . $httpCode . "): " . substr($response, 0, 500));
                return null;
            }
            
            if (!$response) {
                error_log('❌ Respuesta vacía de OpenAI');
                return null;
            }
            
            $responseData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('❌ Error al decodificar respuesta OpenAI: ' . json_last_error_msg());
                return null;
            }
            
            if (!isset($responseData['choices'][0]['message']['content'])) {
                error_log('❌ Estructura de respuesta OpenAI inesperada: ' . json_encode($responseData));
                return null;
            }
            
            return $responseData['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            error_log('❌ Excepción en generarInformeOpenAI: ' . $e->getMessage());
            return null;
        }
    }
}
