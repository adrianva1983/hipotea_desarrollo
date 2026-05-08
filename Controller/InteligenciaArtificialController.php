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
            error_log('InteligenciaArtificialController: 1️⃣ Iniciando procesarTextoParaExpediente...');
            
            // Validar que sea POST
            if (!$request->isMethod('POST')) {
                error_log('InteligenciaArtificialController: ❌ No es POST, es: ' . $request->getMethod());
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'Método no permitido. Use POST.'
                ], 400);
            }

            error_log('InteligenciaArtificialController: 2️⃣ Request es POST, extrayendo datos...');

            // Obtener datos del request
            $isJson = strpos($request->headers->get('Content-Type', ''), 'application/json') !== false;

            if ($isJson) {
                error_log('InteligenciaArtificialController: Content-Type es JSON');
                $body = json_decode($request->getContent(), true) ?? [];
                $texto = $body['texto'] ?? null;
                $tipoEntrada = $body['tipo_entrada'] ?? 'kommo';
                $grupos = isset($body['grupos']) ? $body['grupos'] : null;
            } else {
                error_log('InteligenciaArtificialController: Content-Type no es JSON, usando request->request');
                $texto = $request->request->get('texto');
                $tipoEntrada = $request->request->get('tipo_entrada', 'kommo');
                $gruposRaw = $request->request->get('grupos');
                $grupos = $gruposRaw ? (is_array($gruposRaw) ? $gruposRaw : explode(',', $gruposRaw)) : null;
            }

            error_log('InteligenciaArtificialController: Texto extraído: ' . (empty($texto) ? 'VACÍO' : substr($texto, 0, 50)) . '...');
            error_log('InteligenciaArtificialController: Grupos: ' . ($grupos ? json_encode($grupos) : 'no (modo legado)'));

            // Validar que el texto no esté vacío
            if (!$texto || empty(trim($texto))) {
                error_log('InteligenciaArtificialController: ❌ Texto vacío o nulo');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'El texto no puede estar vacío.'
                ], 400);
            }

            error_log('InteligenciaArtificialController: 3️⃣ Texto validado OK. Tipo entrada: ' . $tipoEntrada);

            // Obtener configuración IA
            error_log('InteligenciaArtificialController: 4️⃣ Obteniendo configuración IA de BD...');
            $configIA = $this->obtenerConfiguracionIA();

            error_log('InteligenciaArtificialController: Configuración IA obtenida: ' . json_encode([
                'provider' => $configIA['provider'] ?? 'DESCONOCIDO',
                'tiene_api_key' => !empty($configIA['api_key']),
                'model' => $configIA['model'] ?? 'desconocido'
            ]));

            if (empty($configIA['api_key'])) {
                error_log('InteligenciaArtificialController: ❌ NO HAY API_KEY CONFIGURADA. Config actual: ' . json_encode($configIA));
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'No hay configuración de IA activa.'
                ], 500);
            }

            error_log('InteligenciaArtificialController: 5️⃣ Configuración IA OK.');

            // ── MODO DINÁMICO (grupos especificados) ─────────────────────────
            if ($grupos && count($grupos) > 0) {
                $gruposInt = array_map('intval', $grupos);
                error_log('InteligenciaArtificialController: MODO DINÁMICO - grupos: ' . json_encode($gruposInt));

                $promptDinamico = $this->construirPromptDinamico($texto, $gruposInt);
                $resultadoIA = $this->extraerDatosConIA($texto, $tipoEntrada, $configIA, $promptDinamico);

                if (!$resultadoIA || !isset($resultadoIA['datos'])) {
                    return new JsonResponse([
                        'success' => false,
                        'mensaje' => 'Error al procesar el texto con IA. ' . ($resultadoIA['error'] ?? '')
                    ], 500);
                }

                $datosMapeos = $this->mapearCamposExtraidosDinamico($resultadoIA['datos'], $gruposInt);
                $modo = 'dinamico';
            } else {
                // ── MODO LEGADO (sin grupos, prompt hardcodeado) ──────────────
                error_log('InteligenciaArtificialController: MODO LEGADO - sin grupos');

                $resultadoIA = $this->extraerDatosConIA($texto, $tipoEntrada, $configIA);

                if (!$resultadoIA || !isset($resultadoIA['datos'])) {
                    error_log('InteligenciaArtificialController: ❌ extraerDatosConIA() falló. Resultado: ' . json_encode($resultadoIA));
                    return new JsonResponse([
                        'success' => false,
                        'mensaje' => 'Error al procesar el texto con IA. ' . ($resultadoIA['error'] ?? '')
                    ], 500);
                }

                $mapeoHitos = $this->obtenerMapeoHitos();
                $datosMapeos = $this->mapearCamposExtraidos($resultadoIA['datos'], $mapeoHitos);
                $modo = 'legado';
            }

            error_log('InteligenciaArtificialController: Mapeo completado: ' . count($datosMapeos['valores_texto']) . ' textos + ' . count($datosMapeos['valores_opcion']) . ' opciones');

            // Extraer hitos únicos
            $mapeoHitosAll = $this->obtenerMapeoHitos();
            $hitosAsociados = [];
            foreach (array_merge(array_keys($datosMapeos['valores_texto']), array_keys($datosMapeos['valores_opcion'])) as $idCampo) {
                if (isset($mapeoHitosAll[$idCampo])) {
                    $hitosAsociados[] = $mapeoHitosAll[$idCampo];
                }
            }
            $hitosAsociados = array_values(array_unique($hitosAsociados));

            error_log('InteligenciaArtificialController: ✅ Procesamiento exitoso. Hitos únicos: ' . count($hitosAsociados));

            return new JsonResponse([
                'success'               => true,
                'valores_texto'         => $datosMapeos['valores_texto'],
                'valores_opcion'        => $datosMapeos['valores_opcion'],
                'hitos_asociados'       => $hitosAsociados,
                'campos_detectados'     => count($datosMapeos['valores_texto']) + count($datosMapeos['valores_opcion']),
                'confianza_promedio'    => $resultadoIA['confianza'] ?? 0.80,
                'campos_no_encontrados' => $datosMapeos['campos_no_encontrados'] ?? [],
                'proveedor'             => $configIA['provider'],
                'modo'                  => $modo,
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
    private function extraerDatosConIA($texto, $tipoEntrada, $configIA, $promptExterno = null)
    {
        try {
            error_log('InteligenciaArtificialController: 8️⃣ extraerDatosConIA() - Proveedor: ' . $configIA['provider']);
            
            $prompt = $promptExterno !== null ? $promptExterno : $this->construirPromptExtracion($texto, $tipoEntrada);
            error_log('InteligenciaArtificialController: Prompt construído, longitud: ' . strlen($prompt));

            $resultadoIA = null;
            
            if ($configIA['provider'] === 'GEMINI') {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAGeminiTexto()...');
                $resultadoIA = $this->enviarAGeminiTexto($texto, $prompt, $configIA);
            } elseif ($configIA['provider'] === 'OPENAI') {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAOpenAITexto()...');
                $resultadoIA = $this->enviarAOpenAITexto($texto, $prompt, $configIA);
            } elseif ($configIA['provider'] === 'OLLAMA') {
                error_log('InteligenciaArtificialController: 9️⃣ Llamando enviarAOllamaTexto()...');
                $resultadoIA = $this->enviarAOllamaTexto($texto, $prompt, $configIA);
            } else {
                error_log('InteligenciaArtificialController: ❌ Provider desconocido: ' . $configIA['provider']);
            }

            error_log('InteligenciaArtificialController: Resultado de proveedor: ' . json_encode([
                'tiene_datos' => isset($resultadoIA),
                'datos_estructurados' => isset($resultadoIA['datos']),
                'confianza' => $resultadoIA['confianza'] ?? 'N/A',
                'error' => $resultadoIA['error'] ?? 'ninguno'
            ]));

            if (!$resultadoIA) {
                error_log('InteligenciaArtificialController: ❌ El proveedor devolvió NULL');
                throw new \Exception('No se obtuvo respuesta del proveedor IA');
            }

            error_log('InteligenciaArtificialController: ✅ extraerDatosConIA() completado exitosamente');
            return $resultadoIA;

        } catch (\Exception $e) {
            error_log('InteligenciaArtificialController: ❌ Error en extraerDatosConIA: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
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
6. DETECCIÓN DE ESTADO CIVIL: Si dice "casado/a", "soltero/a", "divorciado/a", "separado/a", "viudo/a", "pareja de hecho", incluye en estado_civil
   - Si especifica régimen matrimonial (gananciales, separación de bienes), inclúyelo
   - Ejemplos: "soy casado" → "casado", "casada en gananciales" → "casado en gananciales", "pareja de hecho" → "pareja de hecho"

ESTRUCTURA JSON ESPERADA:
{
  "datos_personales": {
    "nombre_completo": "string",
    "dni": "string (sin espacios ni guiones)",
    "email": "string",
    "telefono": "string (sin espacios ni caracteres especiales)",
    "apellidos": "string",
    "provincia": "string",
    "municipio": "string",
    "estado_civil": "soltero|casado|divorciado|separado|viudo|pareja de hecho (INCLUIR RÉGIMEN SI SE MENCIONA: casado en gananciales, casado en separación de bienes)",
    "nacionalidad": "string"
  },
  "datos_economicos": {
    "ingresos_mensuales": "número",
    "numero_pagas_extra": "número (0-4)",
    "importe_paga_extra": "número",
    "prestamos_mensuales": "número",
    "aportacion": "número",
    "gastos_totales": "número",
    "precio_inmueble": "número",
    "Nombre empresa": "string"
  },
  "datos_laborales": {
    "situacion_laboral": "autonomo|contrato_indefinido|contrato_temporal|funcionario|empresario",
    "antiguedad_laboral": "menos_1_anio|un_anio|mas_2_anios",
    "empresa": "string",
    "puesto": "string",
    "tipo_contrato": "indefinido|temporal|autonomo|funcionario"
  },
  "datos_riesgo": {
    "tiene_impagados": "boolean"
  },
  "datos_bancarios": {
    "banco": "nombre del banco (ej: BBVA, Caixa, Santander)"
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
            
            // Nacionalidad
            if (!empty($dp['nacionalidad'])) {
                $nacionalidad = $dp['nacionalidad'];
                // Mapear a campos 195, 247, 509, 570
                $valoresTexto[195] = $nacionalidad;
                $valoresTexto[247] = $nacionalidad;
                $valoresTexto[509] = $nacionalidad;
                $valoresTexto[570] = $nacionalidad;
            }
            
            // Estado civil
            if (!empty($dp['estado_civil'])) {
                $estadoCivil = strtolower($dp['estado_civil']);
                
                // Mapear a opción correcta
                $opcionEstadoCivil = null;
                
                // Gananciales (89 es la opción 189 en el mapeo)
                if (strpos($estadoCivil, 'gananciales') !== false) {
                    $opcionEstadoCivil = 189;
                }
                // Separación de bienes
                elseif (strpos($estadoCivil, 'separación') !== false) {
                    $opcionEstadoCivil = 82;
                }
                // Si dice "casado" sin especificar → gananciales por defecto
                elseif (strpos($estadoCivil, 'casado') !== false) {
                    $opcionEstadoCivil = 189;
                }
                // Soltero
                elseif (strpos($estadoCivil, 'solt') !== false) {
                    $opcionEstadoCivil = 81;
                }
                // Divorciado
                elseif (strpos($estadoCivil, 'divorc') !== false) {
                    $opcionEstadoCivil = 85;
                }
                // Separado
                elseif (strpos($estadoCivil, 'separ') !== false) {
                    $opcionEstadoCivil = 84;
                }
                // Viudo
                elseif (strpos($estadoCivil, 'viud') !== false) {
                    $opcionEstadoCivil = 86;
                }
                // Pareja de hecho
                elseif (strpos($estadoCivil, 'pareja') !== false || strpos($estadoCivil, 'unión') !== false) {
                    $opcionEstadoCivil = 83;
                }
                
                if ($opcionEstadoCivil) {
                    // Campos 198 y 507 usan opciones
                    $valoresOpcion[198] = $opcionEstadoCivil;
                    $valoresOpcion[507] = $opcionEstadoCivil;
                }
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

    // =========================================================================
    // MÉTODOS DINÁMICOS (prompt y mapeo construidos desde BD en tiempo real)
    // =========================================================================

    /**
     * Obtiene los CampoHito de los grupos indicados junto con sus OpcionesCampo.
     * Retorna: [ id_campo_hito => ['campo' => CampoHito, 'opciones' => [OpcionesCampo, ...]] ]
     */
    private function obtenerCamposDeGrupos(array $grupoIds)
    {
        $doctrine = $this->getDoctrine();
        $resultado = [];

        foreach ($grupoIds as $grupoId) {
            $grupo = $doctrine->getRepository('AppBundle:GrupoCamposHito')->find((int)$grupoId);
            if (!$grupo) {
                error_log('InteligenciaArtificialController: obtenerCamposDeGrupos - grupo no encontrado: ' . $grupoId);
                continue;
            }

            $campos = $doctrine->getRepository('AppBundle:CampoHito')->findBy(
                ['idGrupoCamposHito' => $grupo],
                ['orden' => 'ASC']
            );

            foreach ($campos as $campo) {
                $opciones = [];
                if (in_array($campo->getTipo(), [2, 3])) {
                    $opciones = $doctrine->getRepository('AppBundle:OpcionesCampo')->findBy(
                        ['idCampoHito' => $campo],
                        ['orden' => 'ASC']
                    );
                }
                $resultado[$campo->getIdCampoHito()] = [
                    'campo'   => $campo,
                    'opciones' => $opciones,
                ];
            }
        }

        error_log('InteligenciaArtificialController: obtenerCamposDeGrupos - total campos: ' . count($resultado));
        return $resultado;
    }

    /**
     * Construye un prompt dinámico basado en los campos de la BD.
     * La IA debe devolver JSON plano: { "id_campo_hito": "valor_extraído" }
     */
    private function construirPromptDinamico($texto, array $grupoIds)
    {
        $camposBD = $this->obtenerCamposDeGrupos($grupoIds);
        $descripcionCampos = '';

        foreach ($camposBD as $idCampo => $info) {
            $campo = $info['campo'];
            if ($campo->getTipo() == 4) continue; // Ficheros: no extraíbles de texto

            if (in_array($campo->getTipo(), [2, 3]) && !empty($info['opciones'])) {
                $valoresOpciones = array_map(function ($op) { return $op->getValor(); }, $info['opciones']);
                $descripcionCampos .= '  "' . $idCampo . '": /* ' . $campo->getNombre() . ' — SOLO uno de estos valores: ' . implode(' | ', $valoresOpciones) . " */\n";
            } elseif ($campo->getTipo() == 6) {
                $descripcionCampos .= '  "' . $idCampo . '": /* ' . $campo->getNombre() . " — fecha dd/mm/yyyy */\n";
            } elseif ($campo->getTipo() == 5) {
                $descripcionCampos .= '  "' . $idCampo . '": /* ' . $campo->getNombre() . " — email */\n";
            } else {
                $descripcionCampos .= '  "' . $idCampo . '": /* ' . $campo->getNombre() . " — texto */\n";
            }
        }

        $prompt = 'Analiza el siguiente texto y extrae los datos solicitados en formato JSON plano.' . "\n\n";
        $prompt .= 'INSTRUCCIONES CRÍTICAS:' . "\n";
        $prompt .= '1. Devuelve SOLO un objeto JSON válido, sin texto adicional ni bloques markdown.' . "\n";
        $prompt .= '2. Las claves del JSON son exactamente los IDs numéricos indicados (como strings).' . "\n";
        $prompt .= '3. Si un dato NO aparece en el texto, omite esa clave completamente.' . "\n";
        $prompt .= '4. Para campos con opciones predefinidas, devuelve EXACTAMENTE uno de los valores listados.' . "\n";
        $prompt .= '5. Números sin separadores de miles: 2000 NO 2.000. Sin símbolo de moneda.' . "\n\n";
        $prompt .= 'CAMPOS A EXTRAER:' . "\n";
        $prompt .= '{' . "\n";
        $prompt .= $descripcionCampos;
        $prompt .= '}' . "\n\n";
        $prompt .= 'TEXTO A ANALIZAR:' . "\n";
        $prompt .= $texto;

        error_log('InteligenciaArtificialController: construirPromptDinamico - campos en prompt: ' . count($camposBD) . ', longitud: ' . strlen($prompt));
        return $prompt;
    }

    /**
     * Mapea la respuesta plana de la IA { "id_campo": valor } a { valores_texto, valores_opcion }.
     */
    private function mapearCamposExtraidosDinamico(array $datosIA, array $grupoIds)
    {
        $camposBD = $this->obtenerCamposDeGrupos($grupoIds);
        $valoresTexto        = [];
        $valoresOpcion       = [];
        $camposNoEncontrados = [];

        foreach ($datosIA as $idCampoStr => $valor) {
            $idCampo = (int)$idCampoStr;
            if (!isset($camposBD[$idCampo]) || $valor === null || $valor === '') {
                continue;
            }

            $campo = $camposBD[$idCampo]['campo'];
            $tipo  = $campo->getTipo();

            if (in_array($tipo, [2, 3])) {
                // Campo de opción: buscar coincidencia exacta primero
                $valorNorm      = strtolower(trim((string)$valor));
                $opcionEncontrada = null;

                foreach ($camposBD[$idCampo]['opciones'] as $opcion) {
                    if (strtolower(trim($opcion->getValor())) === $valorNorm) {
                        $opcionEncontrada = $opcion->getIdOpcionesCampo();
                        break;
                    }
                }

                // Fallback: búsqueda parcial
                if ($opcionEncontrada === null) {
                    foreach ($camposBD[$idCampo]['opciones'] as $opcion) {
                        $opcionNorm = strtolower(trim($opcion->getValor()));
                        if (strpos($opcionNorm, $valorNorm) !== false || strpos($valorNorm, $opcionNorm) !== false) {
                            $opcionEncontrada = $opcion->getIdOpcionesCampo();
                            break;
                        }
                    }
                }

                if ($opcionEncontrada !== null) {
                    $valoresOpcion[$idCampo] = $opcionEncontrada;
                } else {
                    $camposNoEncontrados[] = $idCampo;
                    error_log('InteligenciaArtificialController: mapearDinamico - sin opción para campo ' . $idCampo . ' valor \'' . $valor . '\'');
                }
            } else {
                // Campo de texto / email / fecha / número
                $valoresTexto[$idCampo] = (string)$valor;
            }
        }

        error_log('InteligenciaArtificialController: mapearDinamico - ' . count($valoresTexto) . ' textos, ' . count($valoresOpcion) . ' opciones, ' . count($camposNoEncontrados) . ' no encontrados');

        return [
            'valores_texto'         => $valoresTexto,
            'valores_opcion'        => $valoresOpcion,
            'campos_no_encontrados' => $camposNoEncontrados,
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
                        $umbralSimilitud = 0.80; // 80% de similitud

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

    public function crearPromptExpedienteAction(Request $request)
    {
        $grupoIds = [4];
        $camposBD = $this->obtenerCamposDeGruposMod($grupoIds);
        // Construir prompt dinámico
        $prompt = $this->construirPromptExtractor($camposBD);
        return new JsonResponse([
            'success' => true,
            'camposBD' => $camposBD,
            'prompt' => $prompt, 
            'mensaje' => 'Prompt generado con éxito',
        ], 200);
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

        2. CAMPOS DE SELECCIÓN [SELECCIÓN]:
           - Si es un campo de opción/desplegable, intenta deducir el ID de la opción si lo conoces
           - Si no estás seguro, devuelve la descripción del valor de forma clara
           - Ejemplo: Si dice "origen Kommo" → {"id": "673", "valor": "Kommo"} o {"id": "673", "valor": "688"} si sabes el ID

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

            $configIA = $this->obtenerConfiguracionIA();

            if (empty($configIA['api_key'])) {
                error_log('InteligenciaArtificialController: ❌ procesarTextoExpedienteAction - NO HAY API_KEY CONFIGURADA');
                return new JsonResponse([
                    'success' => false,
                    'mensaje' => 'No hay configuración de IA activa.'
                ], 500);
            }

            $grupoIds = [4];
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

