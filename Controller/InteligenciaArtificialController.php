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
     * Procesa texto de cualquier origen y extrae datos para expediente
     * 
     * Input:
     * {
     *   "texto": "Me llamo Juan García, DNI 12345678A...",
     *   "tipo_entrada": "kommo"  // opcional: kommo, email, formulario
     * }
     * 
     * Output:
     * {
     *   "success": true,
     *   "valores_texto": { "192": "Juan García", "194": "12345678A" },
     *   "valores_opcion": { "193": 102, "226": 113 },
     *   "hitos_asociados": [1, 2, 3],
     *   "campos_detectados": 18,
     *   "confianza_promedio": 0.92,
     *   "campos_no_encontrados": ["696"]
     * }
     * 
     * @Route("/API/procesar_texto_para_expediente", name="api_procesar_texto_para_expediente", methods={"POST"})
     */
    public function procesarTextoParaExpedienteAction(Request $request)
    {
        try {
            // Validar que sea POST
            if (!$request->isMethod('POST')) {
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'Método no permitido. Use POST.'
                ], 400);
            }

            // Obtener datos del request
            $isJson = strpos($request->headers->get('Content-Type', ''), 'application/json') !== false;
            
            if ($isJson) {
                $body = json_decode($request->getContent(), true) ?? [];
                $texto = $body['texto'] ?? null;
                $tipoEntrada = $body['tipo_entrada'] ?? 'kommo';
            } else {
                $texto = $request->request->get('texto');
                $tipoEntrada = $request->request->get('tipo_entrada', 'kommo');
            }

            // Validar que el texto no esté vacío
            if (!$texto || empty(trim($texto))) {
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'El texto no puede estar vacío.'
                ], 400);
            }

            $this->logger->info('📝 Procesando texto para expediente. Tipo: ' . $tipoEntrada . '. Longitud: ' . strlen($texto));

            // Obtener configuración IA
            $configIA = $this->obtenerConfiguracionIA();

            error_log('InteligenciaArtificialController: Configuracion IA obtenida: ');

            error_log('InteligenciaArtificialController: Configuracion IA obtenida: ' . json_encode($configIA));
            
            if (empty($configIA['api_key'])) {
                $this->logger->error('❌ No se encontró configuración IA activa');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'No hay configuración de IA activa.'
                ], 500);
            }

            // Enviar a IA para extraer datos
            $resultadoIA = $this->extraerDatosConIA($texto, $tipoEntrada, $configIA);
            
            if (!$resultadoIA || !isset($resultadoIA['datos'])) {
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'Error al procesar el texto con IA.'
                ], 500);
            }

            // Obtener mapeo de campos a hitos
            $mapeoHitos = $this->obtenerMapeoHitos();
            
            // Mapear datos extraídos a estructura de expediente
            $datosMapeos = $this->mapearCamposExtraidos($resultadoIA['datos'], $mapeoHitos);

            // Extraer hitos únicos
            $hitosAsociados = [];
            foreach ($datosMapeos['valores_texto'] as $idCampo => $valor) {
                if (isset($mapeoHitos[$idCampo])) {
                    $hitosAsociados[] = $mapeoHitos[$idCampo];
                }
            }
            foreach ($datosMapeos['valores_opcion'] as $idCampo => $idOpcion) {
                if (isset($mapeoHitos[$idCampo])) {
                    $hitosAsociados[] = $mapeoHitos[$idCampo];
                }
            }
            $hitosAsociados = array_unique($hitosAsociados);

            $this->logger->info('✅ Datos extraídos exitosamente. Campos detectados: ' . count($datosMapeos['valores_texto']) . '+' . count($datosMapeos['valores_opcion']));

            return new JsonResponse([
                'success' => true,
                'valores_texto' => $datosMapeos['valores_texto'],
                'valores_opcion' => $datosMapeos['valores_opcion'],
                'hitos_asociados' => array_values($hitosAsociados),
                'campos_detectados' => count($datosMapeos['valores_texto']) + count($datosMapeos['valores_opcion']),
                'confianza_promedio' => $resultadoIA['confianza'] ?? 0.80,
                'campos_no_encontrados' => $datosMapeos['campos_no_encontrados'] ?? [],
                'proveedor' => $configIA['provider']
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('❌ Error en procesarTextoParaExpediente: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'mensaje' => 'Error al procesar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae datos del texto usando IA
     */
    private function extraerDatosConIA($texto, $tipoEntrada, $configIA)
    {
        try {
            $prompt = $this->construirPromptExtracion($texto, $tipoEntrada);

            $resultadoIA = null;
            
            if ($configIA['provider'] === 'GEMINI') {
                $resultadoIA = $this->enviarAGeminiTexto($texto, $prompt, $configIA);
            } elseif ($configIA['provider'] === 'OPENAI') {
                $resultadoIA = $this->enviarAOpenAITexto($texto, $prompt, $configIA);
            } elseif ($configIA['provider'] === 'OLLAMA') {
                $resultadoIA = $this->enviarAOllamaTexto($texto, $prompt, $configIA);
            }

            if (!$resultadoIA) {
                throw new \Exception('No se obtuvo respuesta del proveedor IA');
            }

            return $resultadoIA;

        } catch (\Exception $e) {
            $this->logger->error('Error en extraerDatosConIA: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Construye el prompt dinámico para extraer datos
     */
    private function construirPromptExtracion($texto, $tipoEntrada)
    {
        $prompt = <<<EOT
Analiza el siguiente texto y extrae TODOS los datos relevantes en formato JSON estructurado.

INSTRUCCIONES CRÍTICAS:
1. DEVUELVE SOLO JSON válido, sin texto adicional
2. Si un dato NO está en el texto, NO lo incluyas (omitir es mejor que adivinar)
3. Formatea números sin separadores de miles: 2000 NO 2.000
4. Fechas en formato dd/mm/yyyy
5. Los campos opcionales pueden omitirse si no aparecen en el texto

ESTRUCTURA JSON ESPERADA:
{
  "datos_personales": {
    "nombre_completo": "string",
    "dni": "string (sin espacios ni guiones)",
    "email": "string",
    "telefono": "string (sin espacios ni caracteres especiales)",
    "apellidos": "string",
    "provincia": "string",
    "municipio": "string"
  },
  "datos_economicos": {
    "ingresos_mensuales": "número",
    "numero_pagas_extra": "número (0-4)",
    "importe_paga_extra": "número",
    "prestamos_mensuales": "número",
    "aportacion": "número",
    "gastos_totales": "número",
    "precio_inmueble": "número"
  },
  "datos_laborales": {
    "situacion_laboral": "autonomo|contrato_indefinido|contrato_temporal|funcionario|empresario",
    "antiguedad_laboral": "menos_1_anio|un_anio|mas_2_anios"
  },
  "datos_riesgo": {
    "tiene_impagados": "boolean"
  },
  "segundo_titular": {
    "ingresos_mensuales_dos": "número",
    "numero_pagas_extra_dos": "número",
    "importe_paga_extra_dos": "número",
    "prestamos_mensuales_dos": "número",
    "situacion_laboral_dos": "autonomo|contrato_indefinido|contrato_temporal|funcionario|empresario",
    "antiguedad_laboral_dos": "menos_1_anio|un_anio|mas_2_anios",
    "tiene_impagados_dos": "boolean"
  }
}

TEXTO A ANALIZAR:
$texto
EOT;

        return $prompt;
    }

    /**
     * Mapea datos extraídos a campos de Hipotea
     */
    private function mapearCamposExtraidos($datosIA, $mapeoHitos)
    {
        $valoresTexto = [];
        $valoresOpcion = [];
        $camposNoEncontrados = [];

        if (!is_array($datosIA)) {
            return [
                'valores_texto' => $valoresTexto,
                'valores_opcion' => $valoresOpcion,
                'campos_no_encontrados' => $camposNoEncontrados
            ];
        }

        // Extraer datos personales
        if (isset($datosIA['datos_personales']) && is_array($datosIA['datos_personales'])) {
            $dp = $datosIA['datos_personales'];
            
            if (!empty($dp['nombre_completo'])) {
                $valoresTexto[192] = $dp['nombre_completo'];
            }
            if (!empty($dp['dni'])) {
                $valoresTexto[194] = strtoupper(preg_replace('/[^A-Z0-9]/', '', $dp['dni']));
            }
            if (!empty($dp['email'])) {
                $valoresTexto[407] = $valoresTexto[696] = $dp['email'];
            }
            if (!empty($dp['telefono'])) {
                $valoresTexto[408] = $valoresTexto[695] = preg_replace('/[^0-9]/', '', $dp['telefono']);
            }
            if (!empty($dp['apellidos'])) {
                $valoresTexto[694] = $dp['apellidos'];
            }
            if (!empty($dp['nombre'])) {
                $valoresTexto[693] = $dp['nombre'];
            }
            if (!empty($dp['provincia'])) {
                $valoresTexto[689] = $dp['provincia'];
            }
            if (!empty($dp['municipio'])) {
                $valoresTexto[458] = $dp['municipio'];
            }
        }

        // Extraer datos económicos
        if (isset($datosIA['datos_economicos']) && is_array($datosIA['datos_economicos'])) {
            $de = $datosIA['datos_economicos'];
            
            if (isset($de['ingresos_mensuales']) && $de['ingresos_mensuales'] > 0) {
                $valoresTexto[225] = number_format($de['ingresos_mensuales'], 2, '.', '');
                $valoresTexto[228] = number_format($de['ingresos_mensuales'] * 12 + (($de['numero_pagas_extra'] ?? 0) * ($de['importe_paga_extra'] ?? 0)), 2, '.', '');
            }
            if (isset($de['numero_pagas_extra'])) {
                $valoresOpcion[226] = 111 + intval($de['numero_pagas_extra']);
            }
            if (isset($de['importe_paga_extra']) && $de['importe_paga_extra'] > 0) {
                $valoresTexto[227] = number_format($de['importe_paga_extra'], 2, '.', '');
            }
            if (isset($de['aportacion']) && $de['aportacion'] > 0) {
                $valoresTexto[699] = number_format($de['aportacion'], 0, ',', '.') . ' €';
                $valoresTexto[181] = number_format($de['aportacion'], 2, '.', '');
                $valoresTexto[182] = number_format($de['aportacion'], 2, '.', '');
            }
            if (isset($de['precio_inmueble']) && $de['precio_inmueble'] > 0) {
                $valoresTexto[691] = number_format($de['precio_inmueble'], 0, ',', '.') . ' €';
                $valoresTexto[413] = number_format($de['precio_inmueble'], 2, '.', '');
                $valoresTexto[180] = number_format($de['precio_inmueble'], 2, '.', '');
            }
        }

        // Extraer datos laborales
        if (isset($datosIA['datos_laborales']) && is_array($datosIA['datos_laborales'])) {
            $dl = $datosIA['datos_laborales'];
            
            if (!empty($dl['situacion_laboral'])) {
                $opcionEmpleado = $this->mapearSituacionLaboral($dl['situacion_laboral']);
                if ($opcionEmpleado) {
                    $valoresOpcion[193] = $opcionEmpleado;
                }
                $opcionContrato = $this->mapearTipoContrato($dl['situacion_laboral']);
                if ($opcionContrato) {
                    $valoresOpcion[221] = $opcionContrato;
                }
                // Campo 690: Etiqueta laboral
                $etiquetaLaboral = $this->mapearEtiquetaLaboral($dl['situacion_laboral']);
                if ($etiquetaLaboral) {
                    $valoresTexto[690] = $etiquetaLaboral;
                }
            }
            if (!empty($dl['antiguedad_laboral'])) {
                $textoAntiguedad = $this->mapearAntiguedadLaboral($dl['antiguedad_laboral']);
                if ($textoAntiguedad) {
                    $valoresTexto[223] = $textoAntiguedad;
                }
            }
        }

        // Extraer datos de riesgo
        if (isset($datosIA['datos_riesgo']) && is_array($datosIA['datos_riesgo'])) {
            $dr = $datosIA['datos_riesgo'];
            if (isset($dr['tiene_impagados'])) {
                $valoresOpcion[244] = $dr['tiene_impagados'] ? 123 : 124;
            }
        }

        // Segundo titular
        if (isset($datosIA['segundo_titular']) && is_array($datosIA['segundo_titular'])) {
            $st = $datosIA['segundo_titular'];
            
            if (isset($st['ingresos_mensuales_dos']) && $st['ingresos_mensuales_dos'] > 0) {
                $valoresTexto[555] = number_format($st['ingresos_mensuales_dos'], 2, '.', '');
                $valoresTexto[552] = number_format($st['ingresos_mensuales_dos'] * 12 + (($st['numero_pagas_extra_dos'] ?? 0) * ($st['importe_paga_extra_dos'] ?? 0)), 2, '.', '');
            }
            if (isset($st['numero_pagas_extra_dos'])) {
                $valoresOpcion[554] = 522 + intval($st['numero_pagas_extra_dos']);
            }
            if (isset($st['importe_paga_extra_dos']) && $st['importe_paga_extra_dos'] > 0) {
                $valoresTexto[553] = number_format($st['importe_paga_extra_dos'], 2, '.', '');
            }
            if (!empty($st['situacion_laboral_dos'])) {
                $opcionEmpleadoDos = $this->mapearSituacionLaboral($st['situacion_laboral_dos'], true);
                if ($opcionEmpleadoDos) {
                    $valoresOpcion[547] = $opcionEmpleadoDos;
                }
                $opcionContratoDos = $this->mapearTipoContrato($st['situacion_laboral_dos'], true);
                if ($opcionContratoDos) {
                    $valoresOpcion[549] = $opcionContratoDos;
                }
            }
            if (isset($st['antiguedad_laboral_dos'])) {
                $textoAntiguedadDos = $this->mapearAntiguedadLaboral($st['antiguedad_laboral_dos']);
                if ($textoAntiguedadDos) {
                    $valoresTexto[541] = $textoAntiguedadDos;
                }
            }
            if (isset($st['tiene_impagados_dos'])) {
                $valoresOpcion[559] = $st['tiene_impagados_dos'] ? 534 : 535;
            }
        }

        // Opciones por defecto
        $valoresOpcion[673] = 688;  // Origen → Calculadora
        $valoresOpcion[179] = 71;   // Para qué → Adquirir propiedad
        $valoresOpcion[640] = 608;  // Cuántas propiedades → Una
        $valoresOpcion[456] = 355;  // Titulares → Uno (se actualizará si hay segundo titular)
        
        if (isset($datosIA['segundo_titular']) && is_array($datosIA['segundo_titular']) && !empty($datosIA['segundo_titular'])) {
            $valoresOpcion[456] = 356; // Dos titulares
        }

        // Fecha actual
        $valoresTexto[688] = (new \DateTime())->format('d/m/Y');

        error_log('Campos texto extraídos: ' . print_r($valoresTexto, true));

        return [
            'valores_texto' => array_filter($valoresTexto, function($v) { return $v !== null && $v !== ''; }),
            'valores_opcion' => array_filter($valoresOpcion, function($v) { return $v !== null; }),
            'campos_no_encontrados' => $camposNoEncontrados
        ];
    }

    /**
     * Mapea situación laboral a opción ID
     */
    private function mapearSituacionLaboral($situacion, $esSegundoTitular = false)
    {
        if ($esSegundoTitular) {
            $mapeo = [
                'autonomo' => 497,
                'contrato_indefinido' => 499,
                'contrato_temporal' => 499,
                'funcionario' => 499,
                'empresario' => 500
            ];
        } else {
            $mapeo = [
                'autonomo' => 97,
                'contrato_indefinido' => 102,
                'contrato_temporal' => 102,
                'funcionario' => 102,
                'empresario' => 103
            ];
        }
        
        return $mapeo[$situacion] ?? null;
    }

    /**
     * Mapea tipo de contrato a opción ID
     */
    private function mapearTipoContrato($situacion, $esSegundoTitular = false)
    {
        if ($esSegundoTitular) {
            $mapeo = [
                'autonomo' => 556,
                'contrato_indefinido' => 515,
                'contrato_temporal' => 520,
                'funcionario' => 518,
                'empresario' => 556
            ];
        } else {
            $mapeo = [
                'autonomo' => 555,
                'contrato_indefinido' => 104,
                'contrato_temporal' => 109,
                'funcionario' => 107,
                'empresario' => 555
            ];
        }
        
        return $mapeo[$situacion] ?? null;
    }

    /**
     * Mapea situación laboral a etiqueta de texto
     */
    private function mapearEtiquetaLaboral($situacion)
    {
        $mapeo = [
            'autonomo' => 'Autónomo',
            'contrato_indefinido' => 'Empleado (contrato indefinido)',
            'contrato_temporal' => 'Empleado (contrato temporal)',
            'funcionario' => 'Funcionario',
            'empresario' => 'Empresario / Mercantil'
        ];
        
        return $mapeo[$situacion] ?? null;
    }

    /**
     * Mapea antigüedad laboral a texto
     */
    private function mapearAntiguedadLaboral($antiguedad)
    {
        $mapeo = [
            'menos_1_anio' => 'Menos de 1 año',
            'un_anio' => '1 año',
            'mas_2_anios' => 'Más de 2 años'
        ];
        
        return $mapeo[$antiguedad] ?? null;
    }

    /**
     * Obtiene mapeo de campo_id => hito_id desde BD
     */
    private function obtenerMapeoHitos()
    {
        $doctrine = $this->getDoctrine();
        $mapeo = [];

        try {
            $fases = $doctrine->getRepository('AppBundle:Fase')->findBy([], ['orden' => 'ASC']);

            foreach ($fases as $fase) {
                $hitos = $doctrine->getRepository('AppBundle:Hito')->findBy(
                    ['idFase' => $fase],
                    ['orden' => 'ASC']
                );

                foreach ($hitos as $hito) {
                    $gruposCampos = $doctrine->getRepository('AppBundle:GrupoCamposHito')->findBy(
                        ['idHito' => $hito],
                        ['orden' => 'ASC']
                    );

                    foreach ($gruposCampos as $grupo) {
                        $campos = $doctrine->getRepository('AppBundle:CampoHito')->findBy(
                            ['idGrupoCamposHito' => $grupo],
                            ['orden' => 'ASC']
                        );

                        foreach ($campos as $campo) {
                            $mapeo[$campo->getIdCampoHito()] = $hito->getIdHito();
                        }
                    }
                }
            }

            $this->logger->info('✅ Mapeo de hitos cargado. Campos totales: ' . count($mapeo));

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener mapeo de hitos: ' . $e->getMessage());
        }

        return $mapeo;
    }

    /**
     * Envía texto a Google Gemini
     */
    private function enviarAGeminiTexto($texto, $prompt, $configIA)
    {
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
            throw new \Exception('Error HTTP Gemini (' . $httpCode . '): ' . substr($response, 0, 200));
        }

        $data = json_decode($response, true);

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Respuesta inválida de Gemini');
        }

        $textoRespuesta = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Limpiar markdown JSON si es necesario
        $textoRespuesta = preg_replace('/```json\s*|\s*```/', '', $textoRespuesta);
        
        $datosExtraidos = json_decode($textoRespuesta, true);

        if (!is_array($datosExtraidos)) {
            throw new \Exception('Respuesta de IA no es JSON válido');
        }

        return [
            'datos' => $datosExtraidos,
            'confianza' => 0.90,
            'proveedor' => 'GEMINI'
        ];
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
            $em = $this->getDoctrine()->getManager();

            // Intentar obtener de BD
            $sql = "SELECT * FROM ia_config WHERE activo = 1 AND es_proveedor_por_defecto = 1 LIMIT 1";
            $connection = $em->getConnection();
            $statement = $connection->prepare($sql);
            $statement->execute();
            $configDB = $statement->fetch();

            if ($configDB && !empty($configDB['api_key'])) {
                $this->logger->info('✅ Config IA desde BD: ' . $configDB['provider']);
                return [
                    'provider' => $configDB['provider'] ?? 'GEMINI',
                    'api_key' => $configDB['api_key'],
                    'model' => $configDB['model'] ?? 'gemini-1.5-flash',
                    'temperature' => $configDB['temperatura'] ?? 0.7,
                    'max_tokens' => $configDB['max_tokens'] ?? 8192
                ];
            }

            // Fallback a variables de entorno
            $apiKey = getenv('GEMINI_API_KEY') ?: getenv('OPENAI_API_KEY') ?: getenv('OLLAMA_API_URL');

            if (!$apiKey) {
                $this->logger->warning('⚠️ No se encontró configuración IA');
                return ['api_key' => '', 'provider' => 'GEMINI'];
            }

            return [
                'provider' => 'GEMINI',
                'api_key' => getenv('GEMINI_API_KEY'),
                'model' => 'gemini-1.5-flash',
                'temperature' => 0.7,
                'max_tokens' => 8192
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error en obtenerConfiguracionIA: ' . $e->getMessage());
            return ['api_key' => '', 'provider' => 'GEMINI'];
        }
    }
}
