<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Expediente;
use AppBundle\Entity\Fase;
use AppBundle\Entity\Hito;
use AppBundle\Entity\GrupoCamposHito;
use AppBundle\Entity\CampoHito;
use AppBundle\Entity\HitoExpediente;
use AppBundle\Entity\GrupoHitoExpediente;
use AppBundle\Entity\CampoHitoExpediente;
use AppBundle\Entity\Usuario;
use AppBundle\Form\SimuladorInicioType;
use AppBundle\Form\SimuladorDatosClienteType;
use AppBundle\Form\SimuladorPrecioMaximoType;
use AppBundle\Form\SimuladorCuotaGastosType;
use AppBundle\Form\SimuladorRiesgoType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use DateTime;

/**
 * SimuladorViabilidadController
 * 
 * Controlador para el simulador de estudio previo de viabilidad hipotecaria.
 * Gestiona un flujo de 4 pasos para recopilar datos del cliente y evaluar
 * la viabilidad de una solicitud de hipoteca mediante un sistema de semáforo.
 * 
 * FLUJO OBLIGATORIO (sin posibilidad de saltar pasos):
 * inicio -> paso1 -> paso2 -> paso3 -> paso4 -> resultado -> descargarPdf / enviarAHipotea
 */
class SimuladorViabilidadController extends Controller
{
    const SIMULADOR_SESSION_KEY = 'simulador_viabilidad';

    /**
     * Acción única: Simulador completo en una sola pantalla
     * GET: Muestra formulario con 5 pasos navegables
     * POST: Procesa datos según _accion (paso0, paso1, paso2, paso3, paso4, calcular_resultado)
     */
    public function simuladorCompletoAction(Request $request)
    {
        $usuarioActual = $this->getUser();
        if (!$usuarioActual) {
            return $this->redirectToRoute('iniciar_sesion');
        }

        $simulador = $this->getSimuladorSessionData($request) ?? [];
        $pasoActual = $request->query->get('paso', 0);
        $accion = $request->request->get('_accion');

        // Procesar POST según la acción
        if ($request->isMethod('POST')) {
            try {
                switch ($accion) {
                    case 'paso0':
                        $simulador = $this->procesarPaso0($request, $simulador);
                        break;
                    case 'paso1':
                        $simulador = $this->procesarPaso1($request, $simulador);
                        break;
                    case 'paso2':
                        $simulador = $this->procesarPaso2($request, $simulador);
                        break;
                    case 'paso3':
                        $simulador = $this->procesarPaso3($request, $simulador);
                        break;
                    case 'paso4':
                        $simulador = $this->procesarPaso4($request, $simulador);
                        break;
                    case 'calcular_resultado':
                        $simulador = $this->evaluarResultadoSemaforo($simulador);
                        $pasoActual = 'resultado';
                        break;
                }
                $this->saveSimuladorSessionData($request, $simulador);
            } catch (\Exception $e) {
                error_log('Error: ' . $e->getMessage());
                $this->addFlash('error', 'Error: ' . $e->getMessage());
            }
        }

        // Renderizar template único con todos los pasos
        return $this->render('@App/Backoffice/SimuladorViabilidad/simulador_completo.html.twig', [
            'titulo' => 'Simulador de Viabilidad Hipotecaria',
            'simulador' => $simulador,
            'paso_actual' => $pasoActual,
            'formulario_inicio' => $this->createForm(SimuladorInicioType::class)->createView(),
            'formulario_cliente' => $this->createForm(SimuladorDatosClienteType::class)->createView(),
            'formulario_precio' => $this->createForm(SimuladorPrecioMaximoType::class)->createView(),
            'formulario_cuota' => $this->createForm(SimuladorCuotaGastosType::class)->createView(),
            'formulario_riesgo' => $this->createForm(SimuladorRiesgoType::class)->createView(),
        ]);
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function inicioAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo');
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function paso1DatosClienteAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo', ['paso' => 1]);
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function paso2PrecioMaximoAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo', ['paso' => 2]);
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function paso3CuotaGastosAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo', ['paso' => 3]);
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function paso4RiesgoAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo', ['paso' => 4]);
    }

    /**
     * Para compatibilidad: redirige a simulador_completo
     */
    public function resultadoAction(Request $request)
    {
        return $this->redirectToRoute('simulador_completo', ['paso' => 'resultado']);
    }

    /**
     * PASO 21: Enviar a Hipotea
     * Flujo: buscar cliente por DNI (ROLE_CLIENTE) → si no existe buscar por email
     *        → si no existe crearlo como nuevo cliente.
     * Luego crear el expediente asignado al colaborador actual y al asesor hipotecario
     * (comercial) de su inmobiliaria.
     */
    public function enviarAHipoteaAction(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $usuarioActual = $this->getUser();
        if (!$usuarioActual) {
            return new JsonResponse(['success' => false, 'mensaje' => 'No autenticado'], 401);
        }

        // Leer datos del cuerpo JSON (AJAX) o del formulario
        $isJson = strpos($request->headers->get('Content-Type', ''), 'application/json') !== false;
        $simulador = [];
        if ($isJson) {
            $body           = json_decode($request->getContent(), true) ?? [];
            $datosCliente   = $body['cliente'] ?? null;
            $datosSimulador = $body['simulador'] ?? [];
            $informeHtml    = $body['informe_html'] ?? '';
        } else {
            $datosSimulador = [];
            $simulador    = $this->getSimuladorSessionData($request);
            $datosCliente = $simulador['cliente'] ?? null;
            $informeHtml  = $request->request->get('informe_html', '');
        }

        if (!$datosCliente || empty($datosCliente['dni'])) {
            return new JsonResponse(['success' => false, 'mensaje' => 'Faltan los datos del cliente. Por favor, completa el Paso 1.'], 400);
        }

        try {
            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();

            // ── 1. BUSCAR O CREAR EL CLIENTE ───────────────────────────────────
            $repoUsuario    = $doctrine->getRepository(Usuario::class);
            $clienteUsuario = null;
            $clienteCreado  = false;

            // Buscar primero por NIF, luego por email
            if (!empty($datosCliente['dni'])) {
                $clienteUsuario = $repoUsuario->findOneBy(['nif' => $datosCliente['dni']]);
            }
            if (!$clienteUsuario && !empty($datosCliente['email'])) {
                $clienteUsuario = $repoUsuario->findOneBy(['email' => $datosCliente['email']]);
            }

            if (!$clienteUsuario) {
                $nombreCompleto  = trim($datosCliente['nombre'] ?? 'Cliente Simulador');
                $partesNombre    = explode(' ', $nombreCompleto, 2);
                $soloNombre      = $partesNombre[0];
                $soloApellidos   = $partesNombre[1] ?? '';

                $clienteUsuario = (new Usuario())
                    ->setUsername($soloNombre)
                    ->setApellidos($soloApellidos)
                    ->setEmail($datosCliente['email'] ?? '')
                    ->setNif($datosCliente['dni'] ?? '')
                    ->setTelefonoMovil($datosCliente['telefono'] ?? '')
                    ->setRole('ROLE_CLIENTE')
                    ->setEstado(true);

                $inmobiliariaColaborador = $usuarioActual->getIdInmobiliaria();
                if ($inmobiliariaColaborador) {
                    $clienteUsuario->setIdInmobiliaria($inmobiliariaColaborador);
                }

                // fechaRegistro ya la asigna el constructor automáticamente

                $passwordAleatorio = bin2hex(random_bytes(8));
                $clienteUsuario->setPassword(
                    $passwordEncoder->encodePassword($clienteUsuario, $passwordAleatorio)
                );

                $em->persist($clienteUsuario);
                $em->flush();
                $clienteCreado = true;
            }

            // ── 2. FASE INICIAL ────────────────────────────────────────────────
            $faseInicial = $doctrine->getRepository(Fase::class)->findOneBy(['tipo' => 0]);
            if (!$faseInicial) {
                throw new \Exception('No se encontró la fase inicial.');
            }

            // ── 3. TEXTO DEL EXPEDIENTE ────────────────────────────────────────
            $textoExpediente = 'Expediente creado desde el Simulador de Viabilidad.';

            // ── 4. CREAR EXPEDIENTE ────────────────────────────────────────────
            // El constructor ya asigna fechaCreacion y fechaModificacion automáticamente.
            // whatsappAutomatico y whatsappAutomaticoEnviado ya tienen default = false en la entidad.
            $precioInmueble = (float)($datosSimulador['precio_inmueble'] ?? 0);
            $viviendaLabel  = 'Simulador de Viabilidad';
            if ($precioInmueble > 0) {
                $viviendaLabel .= ' - ' . number_format($precioInmueble, 0, ',', '.') . ' €';
            }

            $expediente = (new Expediente())
                ->setEstado(1)
                ->setIdCliente($clienteUsuario)
                ->setIdColaborador($usuarioActual)
                ->setIdFaseActual($faseInicial)
                ->setVivienda($viviendaLabel)
                ->setTexto($textoExpediente);

            // Asesor hipotecario asignado a la inmobiliaria del colaborador
            $inmobiliaria = $usuarioActual->getIdInmobiliaria();
            if ($inmobiliaria && $inmobiliaria->getIdComercial()) {
                $expediente->setIdComercial($inmobiliaria->getIdComercial());
            }

            $em->persist($expediente);

            // ── 5. CREAR HITOS Y CAMPOS DEL EXPEDIENTE ─────────────────────────
            // Mapa de idCampoHito → valor del cliente (mismo patrón que el JS en Expediente.html.twig)
            $nombreCompleto = trim(($clienteUsuario->getUsername() ?? '') . ' ' . ($clienteUsuario->getApellidos() ?? ''));
            $nif            = $clienteUsuario->getNif() ?? '';
            $telefono       = $clienteUsuario->getTelefonoMovil() ?? '';
            $email          = $clienteUsuario->getEmail() ?? '';
            $nombre         = $clienteUsuario->getUsername() ?? '';
            $apellidos      = $clienteUsuario->getApellidos() ?? '';
            $provincia      = $clienteUsuario->getProvincia() ?? '';
            $municipio      = $clienteUsuario->getMunicipio() ?? '';

            // Datos económicos del simulador
            $ingresosMensuales  = (float)($datosSimulador['ingresos_mensuales'] ?? 0);
            $numeroPagas        = (int)($datosSimulador['numero_pagas'] ?? 0);
            $importePagas       = (float)($datosSimulador['importe_pagas'] ?? 0);
            $aportacion         = (float)($datosSimulador['aportacion'] ?? 0);
            $prestamos          = (float)($datosSimulador['prestamos_mensuales'] ?? 0);
            $situacionLaboral   = $datosSimulador['situacion_laboral'] ?? '';
            $antiguedadLaboral  = $datosSimulador['antiguedad_laboral'] ?? '';
            $tieneImpagados     = !empty($datosSimulador['tiene_impagados']);
            $ingresosAnuales    = $ingresosMensuales * 12 + $numeroPagas * $importePagas;
            $etiquetasLaboral   = [
                'autonomo'             => 'Autónomo',
                'contrato_indefinido'  => 'Empleado (contrato indefinido)',
                'contrato_temporal'    => 'Empleado (contrato temporal)',
                'funcionario'          => 'Funcionario',
                'empresario'           => 'Empresario / Mercantil',
            ];
            $etiquetaLaboral = isset($etiquetasLaboral[$situacionLaboral]) ? $etiquetasLaboral[$situacionLaboral] : $situacionLaboral;
            $importeHipoteca = ($precioInmueble > 0 && $precioInmueble > $aportacion) ? $precioInmueble - $aportacion : 0;

            // Campos de texto (setValor)
            $valorPorCampo = [
                192 => $nombreCompleto,                                                           // Nombre completo
                194 => strtoupper($nif),                                                          // DNI/NIE
                407 => $email,                                                                    // Email
                408 => $telefono,                                                                 // Teléfono
                693 => $nombre,                                                                   // Nombre (solo)
                694 => $apellidos,                                                                // Apellidos (solo)
                695 => $telefono,                                                                 // Teléfono (copia)
                696 => $email,                                                                    // Email (copia)
                689 => $provincia,                                                                // Provincia
                458 => $municipio,                                                                // Municipio
                225 => $ingresosMensuales > 0 ? number_format($ingresosMensuales, 2, '.', '') : '', // Nómina mensual neta
                227 => $importePagas > 0      ? number_format($importePagas, 2, '.', '')      : '', // Importe paga extra
                228 => $ingresosAnuales > 0   ? number_format($ingresosAnuales, 2, '.', '')   : '', // Ingresos netos anuales
                462 => $aportacion > 0        ? number_format($aportacion, 2, '.', '')        : '', // Ahorro disponible
                688 => (new DateTime())->format('d/m/Y'),                                                             // Fecha del Lead (hoy)
                690 => $etiquetaLaboral,                                                                               // Trabajo o Estado Laboral
                691 => $precioInmueble > 0  ? number_format($precioInmueble, 0, ',', '.') . ' €' : '',                // Valor del Inmueble
                699 => $aportacion > 0      ? number_format($aportacion, 0, ',', '.') . ' €'     : '',                // Cuánto ahorro aportas
                413 => $precioInmueble > 0  ? number_format($precioInmueble, 2, '.', '')          : '',                // Precio (sin impuestos)
                181 => $aportacion > 0      ? number_format($aportacion, 2, '.', '')              : '',                // Cantidad que aportas para la compra
                405 => $importeHipoteca > 0 ? number_format($importeHipoteca, 2, '.', '')         : '',                // Importe Hipoteca
                182 => $aportacion > 0      ? number_format($aportacion, 2, '.', '')              : '',                // Ahorro actual
                180 => $precioInmueble > 0  ? number_format($precioInmueble, 2, '.', '')          : '',                // Importe compraventa
            ];

            // Campos de selección (setIdOpcionesCampo) — usamos los IDs de opción del HTML real
            // Campo 226: Número pagas extras (111=0, 112=1, 113=2, 114=3, 115=4)
            $opcionNumeroPagas = $numeroPagas >= 0 && $numeroPagas <= 4 ? (111 + $numeroPagas) : null;

            // Campo 193: Tipo de empleo (97=Autónomo, 102=Emplead@, 103=Mercantil)
            $opcionTipoEmpleo = null;
            if ($situacionLaboral === 'autonomo')                                                 $opcionTipoEmpleo = 97;
            elseif (in_array($situacionLaboral, ['contrato_indefinido','contrato_temporal','funcionario'])) $opcionTipoEmpleo = 102;
            elseif ($situacionLaboral === 'empresario')                                           $opcionTipoEmpleo = 103;

            // Campo 244: ¿Tiene impagados? (123=Sí, 124=No)
            $opcionImpagados = $tieneImpagados ? 123 : 124;

            $opcionPorCampo = [
                226 => $opcionNumeroPagas,  // Número pagas extras
                193 => $opcionTipoEmpleo,   // Tipo de empleo
                244 => $opcionImpagados,    // ¿Tiene impagados?
                673 => 688,                 // Origen → "Calculadora"
                179 => 71,                  // ¿Para qué necesitas la hipoteca? → "Adquirir una propiedad"
                640 => 608,                 // ¿Cuántas propiedades hipotecar? → "Una"
            ];

            $fases = $doctrine->getRepository(Fase::class)->findBy([], ['orden' => 'ASC']);

            foreach ($fases as $fase) {
                $hitos = $doctrine->getRepository(Hito::class)->findBy(
                    ['idFase' => $fase], ['orden' => 'ASC']
                );
                foreach ($hitos as $hito) {
                    $hitoExpediente = (new HitoExpediente())
                        ->setIdHito($hito)
                        ->setIdExpediente($expediente)
                        ->setFechaModificacion(new DateTime())
                        ->setEstado(0);

                    $gruposCamposHito = $doctrine->getRepository(GrupoCamposHito::class)->findBy(
                        ['idHito' => $hito], ['orden' => 'ASC']
                    );

                    foreach ($gruposCamposHito as $grupoCamposHito) {
                        $grupoHitoExpediente = (new GrupoHitoExpediente())
                            ->setIdHitoExpediente($hitoExpediente)
                            ->setIdGrupoCamposHito($grupoCamposHito);

                        $camposHito = $doctrine->getRepository(CampoHito::class)->findBy(
                            ['idGrupoCamposHito' => $grupoCamposHito], ['orden' => 'ASC']
                        );

                        foreach ($camposHito as $campoHito) {
                            $campoHitoExpediente = (new CampoHitoExpediente())
                                ->setIdCampoHito($campoHito)
                                ->setIdHitoExpediente($hitoExpediente)
                                ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                                ->setIdExpediente($expediente)
                                ->setFechaModificacion(new DateTime());

                            if ($campoHito->getTipo() == 4) {
                                $campoHitoExpediente->setObligatorio(1)->setSolicitarAlColaborador(1);
                            }

                            // Pre-rellenar datos del cliente en los campos conocidos
                            $idCampo = $campoHito->getIdCampoHito();
                            if (isset($valorPorCampo[$idCampo]) && $valorPorCampo[$idCampo] !== '') {
                                $campoHitoExpediente->setValor($valorPorCampo[$idCampo]);
                            }
                            if (isset($opcionPorCampo[$idCampo]) && $opcionPorCampo[$idCampo] !== null) {
                                if (method_exists($campoHitoExpediente, 'setIdOpcionesCampo')) {
                                    $opcion = $doctrine->getRepository('AppBundle:OpcionesCampo')->find($opcionPorCampo[$idCampo]);
                                    if ($opcion) {
                                        $campoHitoExpediente->setIdOpcionesCampo($opcion);
                                    }
                                }
                            }

                            $em->persist($campoHitoExpediente);
                        }

                        $em->persist($grupoHitoExpediente);
                    }

                    $em->persist($hitoExpediente);
                }
            }

            $em->flush();

            $msg = $clienteCreado
                ? 'Cliente registrado y expediente asignado al asesor hipotecario correctamente.'
                : 'Expediente creado y asignado al asesor hipotecario correctamente.';
            return new JsonResponse(['success' => true, 'mensaje' => $msg]);

        } catch (\Throwable $e) {
            error_log('Error en enviarAHipoteaAction: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'mensaje' => 'Error al crear el expediente: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PASO 22: Descargar PDF con resultado del simulador
     * 
     * Genera un PDF profesional con:
     * - Datos del cliente
     * - Análisis económico (precio, cuota, gastos)
     * - Análisis de riesgo
     * - Resultado semáforo
     * - Motivos y sugerencias
     */
    public function descargarResultadoAction(Request $request)
    {
        $usuarioActual = $this->getUser();
        if (!$usuarioActual) {
            return $this->redirectToRoute('iniciar_sesion');
        }

        $simulador = $this->getSimuladorSessionData($request);

        if (!$this->validarSimuladorCompleto($simulador)) {
            $this->addFlash('error', 'El simulador no está completo.');
            return $this->redirectToRoute('simulador_resultado');
        }

        try {
            // Renderizar HTML desde template Twig
            $html = $this->renderView('@App/Backoffice/SimuladorViabilidad/resultado_pdf.html.twig', [
                'simulador' => $simulador,
                'usuario' => $usuarioActual,
                'fecha_generacion' => new DateTime()
            ]);

            // Generar PDF con KnpSnappyBundle
            $snappy = $this->get('knp_snappy.pdf');
            
            $pdf = $snappy->getOutputFromHtml($html, [
                'page-size' => 'A4',
                'margin-top' => '10mm',
                'margin-right' => '10mm',
                'margin-bottom' => '10mm',
                'margin-left' => '10mm',
                'encoding' => 'UTF-8',
                'no-outline' => null,
                'print-media-type' => null,
                'enable-local-file-access' => null
            ]);

            // Preparar nombre del archivo
            $dni = $simulador['cliente']['dni'] ?? 'SinDNI';
            $dni = preg_replace('/[^A-Za-z0-9]/', '', $dni);
            $nombreArchivo = 'Simulador_Viabilidad_' . $dni . '.pdf';

            // Devolver respuesta con PDF para descarga
            $response = new Response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . urlencode($nombreArchivo) . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

            return $response;

        } catch (\Exception $e) {
            error_log('Error en descargarResultadoAction: ' . $e->getMessage());
            $this->addFlash('error', 'Error al generar el PDF: ' . $e->getMessage());
            return $this->redirectToRoute('simulador_completo');
        }
    }

    /**
     * AJAX: Calcular precio máximo de vivienda
     * Recibe datos del formulario Paso 2 y devuelve el precio máximo calculado
     */
    public function calcularPrecioMaximoAjaxAction(Request $request)
    {
        try {
            // Validar que sea una solicitud POST
            if (!$request->isMethod('POST')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Método no permitido'
                ], 400);
            }

            // Extraer datos del formulario
            $post = $request->request->all();

            // Campos del formulario
            $ingresosMensuales = floatval($post['simulador_precio_maximo']['ingresosMensuales'] ?? 0);
            $numPagasExtra = intval($post['simulador_precio_maximo']['numPagasExtra'] ?? 0);
            $importePagaExtra = floatval($post['simulador_precio_maximo']['importePagaExtra'] ?? 0);
            $prestamosMensuales = floatval($post['simulador_precio_maximo']['prestamosMensuales'] ?? 0);
            $aportacionInicial = floatval($post['simulador_precio_maximo']['aportacionInicial'] ?? 0);
            $plazoAmortizacion = intval($post['simulador_precio_maximo']['plazoAmortizacion'] ?? 25);

            // Validaciones básicas
            if ($ingresosMensuales <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Los ingresos deben ser mayores que cero', 
                    'post' => $post,
                    'ingresosMensuales' => $ingresosMensuales,
                ], 400);
            }

            if ($aportacionInicial < 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'La aportación inicial no puede ser negativa'
                ], 400);
            }

            if ($plazoAmortizacion < 5 || $plazoAmortizacion > 40) {
                $plazoAmortizacion = 25; // Valor por defecto
            }

            // Cálculo de ingresos totales anuales
            $ingresosAnuales = ($ingresosMensuales * 12) + ($numPagasExtra * $importePagaExtra);
            $ingresoMensualPromedio = $ingresosAnuales / 12;

            // Cálculo de obligaciones mensuales
            $obligacionesMensuales = $prestamosMensuales;

            // Capacidad de pago mensual (30% del ingreso neto)
            $ratioMaximoEsfuerzo = 0.30;
            $capacidadPagoMensual = ($ingresoMensualPromedio - $obligacionesMensuales) * $ratioMaximoEsfuerzo;

            // Si la capacidad es negativa o muy baja, no es viable
            if ($capacidadPagoMensual <= 100) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Capacidad de pago insuficiente',
                    'debug' => 'Capacidad: ' . $capacidadPagoMensual
                ], 400);
            }

            // Calcular importe máximo a financiar basado en:
            // cuota mensual = (Capital * (i/12)) / (1 - (1 + i/12)^(-n))
            // Usando tipo de interés estimado del 3% anual
            $tipoIntereAnual = 0.03;
            $tipoIntereMensual = $tipoIntereAnual / 12;
            $numCuotas = $plazoAmortizacion * 12;

            // Fórmula: capacidadPago = importe * (i/12) / (1 - (1 + i/12)^(-n))
            // Despejando: importe = capacidadPago * (1 - (1 + i/12)^(-n)) / (i/12)
            $divisor = (1 - pow(1 + $tipoIntereMensual, -$numCuotas));
            if ($divisor <= 0) {
                throw new \Exception('Cálculo determinista fallido');
            }
            
            $importeFinanciable = ($capacidadPagoMensual * $divisor) / $tipoIntereMensual;

            // Si el cálculo da valores inválidos
            if (!is_finite($importeFinanciable) || $importeFinanciable <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No se pudo calcular el importe financiable',
                    'debug' => 'Importe: ' . var_export($importeFinanciable, true)
                ], 400);
            }

            // Precio máximo = importe financiable + aportación
            $precioMaximo = $importeFinanciable + $aportacionInicial;

            // Gastos aproximados (notaría, registro, gestoría, tasación, etc.)
            // Aproximadamente 8-10% del precio de compra
            $gastos = $precioMaximo * 0.09;

            return new JsonResponse([
                'success' => true,
                'importe_fijo' => round($precioMaximo, 2),
                'entrada' => round($aportacionInicial, 2),
                'gastos' => round($gastos, 2),
                'cuota' => round($capacidadPagoMensual, 2),
                'mensaje' => 'Cálculo completado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            error_log('Error en calcularPrecioMaximoAjax: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Error del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== MÉTODOS PROCESADORES DE PASOS =====

    private function procesarPaso0(Request $request, array $simulador): array
    {
        $form = $this->createForm(SimuladorInicioType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            throw new \Exception('Formulario de aviso legal inválido');
        }

        $data = $form->getData();
        if ($data['tipoOperacion'] !== 'compra_vivienda') {
            throw new \Exception('Solo aceptamos solicitudes de compra de vivienda');
        }

        $simulador['paso_actual'] = 0;
        $simulador['aceptaAvisoLegal'] = $data['aceptaAvisoLegal'];
        $simulador['tipoOperacion'] = $data['tipoOperacion'];
        $simulador['fecha_inicio'] = new DateTime();

        return $simulador;
    }

    private function procesarPaso1(Request $request, array $simulador): array
    {
        if (!isset($simulador['aceptaAvisoLegal']) || !$simulador['aceptaAvisoLegal']) {
            throw new \Exception('Debe aceptar el aviso legal primero');
        }

        $form = $this->createForm(SimuladorDatosClienteType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            throw new \Exception('Datos del cliente inválidos');
        }

        $data = $form->getData();
        $simulador['paso_actual'] = 1;
        $simulador['cliente'] = [
            'nombre' => $data['nombre'],
            'dni' => $data['dni'],
            'telefono' => $data['telefono'],
            'email' => $data['email']
        ];

        return $simulador;
    }

    private function procesarPaso2(Request $request, array $simulador): array
    {
        if (!isset($simulador['cliente'])) {
            throw new \Exception('Datos del cliente requeridos');
        }

        $form = $this->createForm(SimuladorPrecioMaximoType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            throw new \Exception('Datos de precio inválidos');
        }

        $datosFormulario = $form->getData();
        $calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
        $calculadora->setTipo(2);

        if (isset($datosFormulario['numTitulares'])) {
            $calculadora->setNumTitulares($datosFormulario['numTitulares']);
        }
        if (isset($datosFormulario['ingresosMensuales'])) {
            $calculadora->setIngresosMensuales($datosFormulario['ingresosMensuales']);
        }
        if (isset($datosFormulario['aportacionInicial'])) {
            $calculadora->setAportacionInicial($datosFormulario['aportacionInicial']);
        }
        if (isset($datosFormulario['plazoAmortizacion'])) {
            $calculadora->setPlazoAmortizacion($datosFormulario['plazoAmortizacion']);
        }

        $resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());

        if (!isset($resultado['importe_fijo']) || $resultado['importe_fijo'] <= 0) {
            throw new \Exception('No se pudo calcular el precio máximo');
        }

        $precioMaximo = $resultado['importe_fijo'];
        $aportacion = $resultado['entrada'];
        $financiacion = $precioMaximo - $aportacion;
        $porcentajeFinanciacion = ($financiacion / $precioMaximo) * 100;

        $simulador['paso_actual'] = 2;
        $simulador['precio'] = [
            'precioMaximoRecomendado' => $precioMaximo,
            'aportacionNecesaria' => $aportacion,
            'importePrestamo' => $financiacion,
            'cuotaHipotecariaEstimada' => $resultado['cuota'] ?? 0,
            'gastosTotalesAproximados' => $resultado['gastos'],
            'porcentajeFinanciacion' => $porcentajeFinanciacion,
        ];

        return $simulador;
    }

    private function procesarPaso3(Request $request, array $simulador): array
    {
        if (!isset($simulador['precio'])) {
            throw new \Exception('Datos de precio requeridos');
        }

        $form = $this->createForm(SimuladorCuotaGastosType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            throw new \Exception('Datos de cuota inválidos');
        }

        $datosFormulario = $form->getData();
        $precioMaximo = $simulador['precio']['precioMaximoRecomendado'];
        $aportacion = $simulador['precio']['aportacionNecesaria'];
        $plazoAmortizacion = $datosFormulario['plazoAmortizacion'] ?? 25;

        $calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
        $calculadora->setTipo(1);
        $calculadora->setValorInmueble($precioMaximo);
        $calculadora->setAportacionInicial($aportacion);
        $calculadora->setPlazoAmortizacion($plazoAmortizacion);

        $resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());

        if (!isset($resultado['importe_fijo']) || $resultado['importe_fijo'] <= 0) {
            throw new \Exception('No se pudo calcular la cuota');
        }

        $importePrestamo = $precioMaximo - $aportacion;
        $porcentajeFinanciacion = ($importePrestamo / $precioMaximo) * 100;
        $tipoInteres = $datosFormulario['tipoInteres'] ?? 'fijo';
        $cuotaEstimada = $resultado['cuota_fija'] ?? 0;

        $simulador['paso_actual'] = 3;
        $simulador['cuota'] = [
            'plazoAmortizacion' => $plazoAmortizacion,
            'tipoInteres' => $tipoInteres,
            'gastosTotalesAproximados' => $resultado['gastos'],
            'aportacionNecesaria' => $aportacion,
            'importePrestamo' => $importePrestamo,
            'cuotaHipotecariaEstimada' => $cuotaEstimada,
            'porcentajeFinanciacion' => $porcentajeFinanciacion,
        ];

        return $simulador;
    }

    private function procesarPaso4(Request $request, array $simulador): array
    {
        if (!isset($simulador['cuota'])) {
            throw new \Exception('Datos de cuota requeridos');
        }

        $form = $this->createForm(SimuladorRiesgoType::class);
        $form->handleRequest($request);

        if (!$form->isValid()) {
            throw new \Exception('Datos de riesgo inválidos');
        }

        $data = $form->getData();
        $simulador['paso_actual'] = 4;
        $simulador['riesgo'] = [
            'tienePrestamosImpagados' => $data['tienePrestamosImpagados'],
            'situacionLaboral' => $data['situacionLaboral'],
            'antiguedadLaboral' => $data['antiguedadLaboral']
        ];

        return $simulador;
    }

    // ===== MÉTODOS PRIVADOS =====

    private function getSimuladorSessionData(Request $request)
    {
        return $request->getSession()->get(self::SIMULADOR_SESSION_KEY, null);
    }

    private function saveSimuladorSessionData(Request $request, array $data)
    {
        $request->getSession()->set(self::SIMULADOR_SESSION_KEY, $data);
    }

    private function clearSimuladorSessionData(Request $request)
    {
        $request->getSession()->remove(self::SIMULADOR_SESSION_KEY);
    }

    private function validarSimuladorCompleto($simulador)
    {
        if (!$simulador) {
            return false;
        }

        $pasos_requeridos = ['aceptaAvisoLegal', 'cliente', 'precio', 'cuota', 'riesgo', 'resultado'];
        foreach ($pasos_requeridos as $clave) {
            if (!isset($simulador[$clave])) {
                return false;
            }
        }

        return true;
    }

    private function evaluarResultadoSemaforo(array $simulador)
    {
        if ($this->esResultadoRojo($simulador)) {
            $semaforo = 'rojo';
            $mensaje = 'Su solicitud de hipoteca no es viable en este momento.';
        } elseif ($this->esResultadoVerde($simulador)) {
            $semaforo = 'verde';
            $mensaje = 'Su solicitud de hipoteca tiene buena viabilidad.';
        } else {
            $semaforo = 'amarillo';
            $mensaje = 'Su solicitud de hipoteca podría ser viable con ciertas condiciones.';
        }

        $motivos = $this->generarMotivosResultado($simulador);
        $sugerencias = $this->generarSugerenciasResultado($simulador);

        $simulador['resultado'] = [
            'semaforo' => $semaforo,
            'mensaje' => $mensaje,
            'motivos' => $motivos,
            'sugerencias' => $sugerencias,
            'fecha_evaluacion' => new DateTime()
        ];

        return $simulador;
    }

    private function esResultadoRojo(array $simulador)
    {
        if (!isset($simulador['riesgo']) || !isset($simulador['cuota'])) {
            return false;
        }

        $riesgo = $simulador['riesgo'];
        $cuota = $simulador['cuota'];

        if ($riesgo['tienePrestamosImpagados'] === true) {
            return true;
        }

        if ($cuota['porcentajeFinanciacion'] > 100) {
            return true;
        }

        if ($riesgo['antiguedadLaboral'] === 'menos_1_anio') {
            return true;
        }

        return false;
    }

    private function esResultadoVerde(array $simulador)
    {
        if (!isset($simulador['riesgo']) || !isset($simulador['cuota'])) {
            return false;
        }

        $riesgo = $simulador['riesgo'];
        $cuota = $simulador['cuota'];

        if ($riesgo['tienePrestamosImpagados'] === true) {
            return false;
        }

        if ($cuota['porcentajeFinanciacion'] > 90) {
            return false;
        }

        $situacion = $riesgo['situacionLaboral'];
        $antiguedad = $riesgo['antiguedadLaboral'];

        if ($situacion === 'funcionario') {
            return true;
        }

        if ($situacion === 'contrato_indefinido') {
            if ($antiguedad === 'un_anio' || $antiguedad === 'mas_2_anios') {
                return true;
            }
        }

        if ($situacion === 'autonomo') {
            if ($antiguedad === 'mas_2_anios') {
                return true;
            }
        }

        return false;
    }

    private function generarMotivosResultado(array $simulador)
    {
        $motivos = [];

        if (!isset($simulador['riesgo']) || !isset($simulador['cuota'])) {
            return $motivos;
        }

        $riesgo = $simulador['riesgo'];
        $cuota = $simulador['cuota'];

        if ($riesgo['tienePrestamosImpagados']) {
            $motivos[] = [
                'tipo' => 'critico',
                'mensaje' => 'Tiene préstamos impagados.',
                'codigo' => 'PRESTAMOS_IMPAGADOS'
            ];
        }

        if ($cuota['porcentajeFinanciacion'] > 100) {
            $motivos[] = [
                'tipo' => 'critico',
                'mensaje' => sprintf('Financiación del %.2f%% (no viable).', $cuota['porcentajeFinanciacion']),
                'codigo' => 'FINANCIACION_EXCESIVA'
            ];
        } elseif ($cuota['porcentajeFinanciacion'] > 90) {
            $motivos[] = [
                'tipo' => 'advertencia',
                'mensaje' => sprintf('Financiación alta (%.2f%%).', $cuota['porcentajeFinanciacion']),
                'codigo' => 'FINANCIACION_ALTA'
            ];
        } else {
            $motivos[] = [
                'tipo' => 'positivo',
                'mensaje' => sprintf('Financiación adecuada (%.2f%%).', $cuota['porcentajeFinanciacion']),
                'codigo' => 'FINANCIACION_CORRECTA'
            ];
        }

        if ($riesgo['antiguedadLaboral'] === 'menos_1_anio') {
            $motivos[] = [
                'tipo' => 'critico',
                'mensaje' => 'Antigüedad laboral menor a 1 año.',
                'codigo' => 'ANTIGUEDAD_INSUFICIENTE'
            ];
        }

        return $motivos;
    }

    private function generarSugerenciasResultado(array $simulador)
    {
        $sugerencias = [];

        if (!isset($simulador['riesgo']) || !isset($simulador['cuota'])) {
            return $sugerencias;
        }

        $riesgo = $simulador['riesgo'];
        $cuota = $simulador['cuota'];
        $semaforo = $simulador['resultado']['semaforo'] ?? '';

        if ($riesgo['tienePrestamosImpagados']) {
            $sugerencias[] = [
                'prioridad' => 'alta',
                'mensaje' => 'Regularice sus préstamos impagados.',
                'codigo' => 'REGULARIZAR_IMPAGOS'
            ];
        }

        if ($cuota['porcentajeFinanciacion'] > 90) {
            $sugerencias[] = [
                'prioridad' => 'media',
                'mensaje' => 'Aumente su aportación inicial.',
                'codigo' => 'AUMENTAR_APORTACION'
            ];
        }

        if ($riesgo['antiguedadLaboral'] === 'menos_1_anio') {
            $sugerencias[] = [
                'prioridad' => 'alta',
                'mensaje' => 'Espere a tener 1 año de antigüedad laboral.',
                'codigo' => 'ESPERAR_ANTIGUEDAD'
            ];
        }

        if ($semaforo === 'verde') {
            $sugerencias[] = [
                'prioridad' => 'baja',
                'mensaje' => 'Su perfil es adecuado. Contacte con nuestro equipo.',
                'codigo' => 'CONTACTAR_ASESOR'
            ];
        }

        return $sugerencias;
    }

    private function construirDescripcionVivienda(array $simulador)
    {
        $descripcion = [];

        if (isset($simulador['precio'])) {
            $precio = $simulador['precio'];
            $descripcion[] = 'Precio máximo: ?' . number_format($precio['precioMaximoRecomendado'] ?? 0, 2, ',', '.');
        }

        if (isset($simulador['cuota'])) {
            $cuota = $simulador['cuota'];
            if (isset($cuota['cuotaHipotecariaEstimada']) && $cuota['cuotaHipotecariaEstimada'] > 0) {
                $descripcion[] = 'Cuota: ?' . number_format($cuota['cuotaHipotecariaEstimada'], 2, ',', '.') . '/mes';
            }
        }

        return implode(' | ', $descripcion);
    }

    private function construirObservacionesSimulador(array $simulador)
    {
        $lineas = [];
        $lineas[] = str_repeat('=', 60);
        $lineas[] = 'EXPEDIENTE GENERADO DESDE SIMULADOR DE VIABILIDAD';
        $lineas[] = str_repeat('=', 60);
        $lineas[] = '';

        // Cliente
        if (isset($simulador['cliente'])) {
            $cliente = $simulador['cliente'];
            $lineas[] = 'DATOS DEL CLIENTE:';
            $lineas[] = '  Nombre: ' . ($cliente['nombre'] ?? 'N/A');
            $lineas[] = '  DNI: ' . ($cliente['dni'] ?? 'N/A');
            $lineas[] = '  Teléfono: ' . ($cliente['telefono'] ?? 'N/A');
            $lineas[] = '  Email: ' . ($cliente['email'] ?? 'N/A');
            $lineas[] = '';
        }

        // Económica
        if (isset($simulador['precio']) && isset($simulador['cuota'])) {
            $precio = $simulador['precio'];
            $cuota = $simulador['cuota'];
            $lineas[] = 'ANÁLISIS ECONÓMICO:';
            $lineas[] = '  Precio máximo: ?' . number_format($precio['precioMaximoRecomendado'] ?? 0, 2, ',', '.');
            $lineas[] = '  Cuota mensual: ?' . number_format($cuota['cuotaHipotecariaEstimada'] ?? 0, 2, ',', '.');
            $lineas[] = '  Financiación: ' . number_format($cuota['porcentajeFinanciacion'] ?? 0, 1, ',', '.') . '%';
            $lineas[] = '  Gastos aprox.: ?' . number_format($cuota['gastosTotalesAproximados'] ?? 0, 2, ',', '.');
            $lineas[] = '';
        }

        // Riesgo
        if (isset($simulador['riesgo'])) {
            $riesgo = $simulador['riesgo'];
            $lineas[] = 'ANÁLISIS DE RIESGO:';
            $lineas[] = '  Impagos: ' . ($riesgo['tienePrestamosImpagados'] ? 'SÍ' : 'NO');
            $lineas[] = '  Situación: ' . ($riesgo['situacionLaboral'] ?? 'N/A');
            $lineas[] = '  Antigüedad: ' . ($riesgo['antiguedadLaboral'] ?? 'N/A');
            $lineas[] = '';
        }

        // Resultado
        if (isset($simulador['resultado'])) {
            $resultado = $simulador['resultado'];
            $lineas[] = 'RESULTADO:';
            $lineas[] = '  Semáforo: ' . strtoupper($resultado['semaforo'] ?? 'N/A');
            $lineas[] = '  Mensaje: ' . ($resultado['mensaje'] ?? 'N/A');
            $lineas[] = '';
        }

        $lineas[] = 'Generado: ' . (new DateTime())->format('d/m/Y H:i:s');

        return implode("\n", $lineas);
    }

    private function crearHitosYCamposExpediente($managerEntidad, Expediente $expediente, $doctrine)
    {
        try {
            $hitos = $doctrine->getRepository(Hito::class)->findAll();

            foreach ($hitos as $hito) {
                $hitoExpediente = (new HitoExpediente())
                    ->setIdExpediente($expediente)
                    ->setIdHito($hito)
                    ->setFechaModificacion(new DateTime());

                $gruposCamposHito = $doctrine->getRepository(GrupoCamposHito::class)->findBy(['idHito' => $hito], ['orden' => 'ASC']);

                foreach ($gruposCamposHito as $grupoCamposHito) {
                    $grupoHitoExpediente = (new GrupoHitoExpediente())
                        ->setIdHitoExpediente($hitoExpediente)
                        ->setIdGrupoCamposHito($grupoCamposHito);

                    $camposHito = $doctrine->getRepository(CampoHito::class)->findBy(['idGrupoCamposHito' => $grupoCamposHito], ['orden' => 'ASC']);

                    foreach ($campoHito as $campoHito) {
                        $campoHitoExpediente = (new CampoHitoExpediente())
                            ->setIdCampoHito($campoHito)
                            ->setIdHitoExpediente($hitoExpediente)
                            ->setIdGrupoHitoExpediente($grupoHitoExpediente)
                            ->setIdExpediente($expediente)
                            ->setFechaModificacion(new DateTime());

                        if ($campoHito->getTipo() == 4) {
                            $campoHitoExpediente->setObligatorio(1)->setSolicitarAlColaborador(1);
                        }

                        $managerEntidad->persist($campoHitoExpediente);
                    }

                    $managerEntidad->persist($grupoHitoExpediente);
                }

                $managerEntidad->persist($hitoExpediente);
            }

            $managerEntidad->flush();

        } catch (\Exception $e) {
            error_log('Error al crear hitos: ' . $e->getMessage());
        }
    }

    /**
	 * Acción AJAX para calcular el precio máximo usando CalculadoraAvanzada
	 * 
	 * IMPORTANTE: Utiliza exactamente la MISMA lógica que calculadoraAvanzadaSubmitAction
	 * en CalculadorasController.php para garantizar resultados idénticos.
	 * 
	 * Entrada esperada:
	 * {
	 *   "datos": {
	 *     "edad": 35,
	 *     "ingresos_mensuales": 2000,
	 *     "aportacion": 50000,
	 *     "plazo": 25,
	 *     "num_titulares": 1,
	 *     "comunidad_autonoma": 4,
	 *     "destino_compra": 1,
	 *     "obra_nueva": 0,
	 *     "familia_numerosa": 0,
	 *     "monoparental": 0,
	 *     "vpo": 0,
	 *     "minusvalia_familia_numerosa": 0
	 *   }
	 * }
	 */
	public function calculadoraAvanzadaTestAjaxAction(Request $request)
	{
		// Obtener datos JSON del request
		$data = json_decode($request->getContent(), true);
		
		if (!isset($data['datos']) || empty($data['datos'])) {
			return new JsonResponse([
				'error' => true,
				'message' => 'No se recibieron datos para procesar',
				'importe_fijo' => 0
			], 400);
		}
		
		try {
			$datos = $data['datos'];
			
			// ===== VALIDACIONES BÁSICAS =====
			$edad = intval($datos['edad'] ?? 0);
			if (!$edad || $edad < 18 || $edad > 75) {
				return new JsonResponse([
					'error' => true,
					'message' => 'Edad inválida (18-75 años). Recibido: ' . $edad,
					'importe_fijo' => 0
				], 400);
			}
			
			$ingresosMensuales = floatval($datos['ingresos_mensuales'] ?? 0);
			if ($ingresosMensuales <= 0) {
				return new JsonResponse([
					'error' => true,
					'message' => 'Ingresos mensuales inválidos (> 0). Recibido: ' . $ingresosMensuales,
					'importe_fijo' => 0
				], 400);
			}
			
			// ===== USAR LA MISMA FORMA QUE calculadoraPrecioMaximoWebAction =====
			
			// PASO 1: Pre-crear entidad - por defecto tipo 2 (precio máximo)
			$tipoCalculo = intval($datos['tipo_calculo'] ?? 2);
			$valorInmueble = floatval($datos['valor_inmueble'] ?? 0);
			$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
			$calculadora->setTipo(2); // Siempre tipo 2 para obtener parámetros correctos
			
			// PASO 2: Crear formulario con la entidad pre-configurada
			$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
			
			// PASO 3: Poblar desde JSON
			$formulario->submit($datos, false); // false = no valida campos no enviados
			
			// PASO 4: Obtener entidad poblada (ahora con tipo 2 conservado y datos JSON)
			$calculadora = $formulario->getData();
			
			// PASO 4b: MAPEO EXPLÍCITO de campos que podrían no mapearse automáticamente (snake_case → camelCase)
			if (isset($datos['comunidad_autonoma'])) {
				$calculadora->setComunidadAutonoma(intval($datos['comunidad_autonoma']));
			}
			if (isset($datos['destino_compra'])) {
				$calculadora->setDestinoCompra(intval($datos['destino_compra']));
			}
			if (isset($datos['obra_nueva'])) {
				$calculadora->setObraNueva(intval($datos['obra_nueva']));
			}
			if (isset($datos['familia_numerosa'])) {
				$calculadora->setFamiliaNumerosa(intval($datos['familia_numerosa']));
			}
			if (isset($datos['monoparental'])) {
				$calculadora->setMonoparental(intval($datos['monoparental']));
			}
			if (isset($datos['vpo'])) {
				$calculadora->setVpo(intval($datos['vpo']));
			}
			if (isset($datos['minusvalia_familia_numerosa'])) {
				$calculadora->setMinusvaliaFamiliaNumerosa(intval($datos['minusvalia_familia_numerosa']));
			}
			if (isset($datos['valor_inmueble']) && $datos['valor_inmueble'] > 0) {
				$calculadora->setValorInmueble(floatval($datos['valor_inmueble']));
			}
			
			// MAPEO DE DATOS DEL SEGUNDO TITULAR (si existen)
			if (isset($datos['edad_titular_dos']) && $datos['edad_titular_dos'] > 0) {
				$calculadora->setEdadTitularDos(intval($datos['edad_titular_dos']));
			}
			if (isset($datos['ingresos_mensuales_dos']) && $datos['ingresos_mensuales_dos'] > 0) {
				$calculadora->setIngresosMensualesDos(floatval($datos['ingresos_mensuales_dos']));
			}
			if (isset($datos['numero_pagas_dos']) && $datos['numero_pagas_dos'] >= 0) {
				$calculadora->setNumPagasExtraDos(intval($datos['numero_pagas_dos']));
			}
			if (isset($datos['importe_pagas_dos']) && $datos['importe_pagas_dos'] > 0) {
				$calculadora->setImportePagaExtraDos(floatval($datos['importe_pagas_dos']));
			}
			if (isset($datos['prestamos_mensuales_dos']) && $datos['prestamos_mensuales_dos'] >= 0) {
				$calculadora->setPrestamosMensualesDos(floatval($datos['prestamos_mensuales_dos']));
			}
			
			// PASO 5: Asegurar valores por defecto para campos críticos que podrían estar nulos
			// Esto evita Inf/NaN en cálculos si el formulario no inicializó correctamente
			if (!$calculadora->getAportacionInicial() || $calculadora->getAportacionInicial() <= 0) {
				$calculadora->setAportacionInicial(floatval($datos['aportacion'] ?? 20000));
			}
			if (!$calculadora->getPlazoAmortizacion() || $calculadora->getPlazoAmortizacion() <= 0) {
				$calculadora->setPlazoAmortizacion(intval($datos['plazo'] ?? 25));
			}
			if (!$calculadora->getEdadTitularUno() || $calculadora->getEdadTitularUno() <= 0) {
				$calculadora->setEdadTitularUno($edad);
			}
			if (!$calculadora->getIngresosMensuales() || $calculadora->getIngresosMensuales() <= 0) {
				$calculadora->setIngresosMensuales($ingresosMensuales);
			}
			if (!$calculadora->getNumTitulares() || $calculadora->getNumTitulares() <= 0) {
				$calculadora->setNumTitulares(intval($datos['num_titulares'] ?? 1));
			}
			
			// PASO 6: EJECUTAR CÁLCULO
			$resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());
			
			// PASO 7: Si tipo=3 (precio conocido), recalcular gastos y cuota sobre el valor_inmueble real
			if ($tipoCalculo === 3 && $valorInmueble > 0) {
				$aportacion    = floatval($datos['aportacion'] ?? 0);
				$plazo         = intval($datos['plazo'] ?? 25);
				$obraNueva     = intval($datos['obra_nueva'] ?? 0);
				$isVpo         = intval($datos['vpo'] ?? 0);
				$destinoCompra = intval($datos['destino_compra'] ?? 1);
				$habitual      = ($destinoCompra === 1);
				
				// Obtener tipo_interes_ccaa (decimal) del resultado tipo=2
				// El resultado tipo=2 devuelve tipo_interes_ccaa como decimal (ej: 0.08)
				$tipo_interes_ccaa = $resultado['tipo_interes_ccaa'] ?? 0;
				$tipo_importe_maximo_decimal = ($resultado['tipo_importe_maximo'] ?? 0) / 100;
				
				// Costes fijos del resultado (vienen de Parametros, mismos para cualquier precio)
				$tasacion   = $resultado['tasacion'] ?? 0;
				$notario    = $resultado['notario']  ?? 0;
				$registro   = $resultado['registro'] ?? 0;
				$gestoria   = $resultado['gestoria'] ?? 0;
				$vinculaciones = $resultado['vinculaciones'] ?? 0;
				
				// Recalcular ITP/IVA sobre el valor_inmueble real
				if ($obraNueva) {
					$tipo_iva   = ($isVpo && $habitual) ? 0.04 : 0.10;
					$importe_iva = $valorInmueble * $tipo_iva;
					$escritura_impuesto = 0;
				} else {
					$importe_iva = 0;
					$escritura_impuesto = $valorInmueble * $tipo_interes_ccaa;
				}
				
				$gastos = $tasacion + $notario + $registro + $gestoria + $vinculaciones
					+ $escritura_impuesto + $importe_iva;
				
				// Calcular cuota (replicando fórmula calculoSencillo de la entidad)
				$loan = $valorInmueble + $gastos - $aportacion;
				$np   = $plazo * 12;
				if (!$tipo_importe_maximo_decimal) {
					$cuota = ($np > 0) ? round($loan / $np, 2) : 0;
				} else {
					$rPeriod = pow(1 + $tipo_importe_maximo_decimal / 12, 1) - 1;
					$rFactor = pow($rPeriod + 1, $np);
					$cuota   = round($loan * (($rPeriod * $rFactor) / ($rFactor - 1)), 2);
				}
				
				$importePrestamo       = $loan;
				$porcentajeFinanciacion = ($valorInmueble > 0) ? round(($importePrestamo / $valorInmueble) * 100, 2) : 0;
				
				// Sobreescribir resultado con valores recalculados
				$resultado['importe_fijo']            = $valorInmueble;
				$resultado['cuota']                   = $cuota;
				$resultado['gastos']                  = $gastos;
				$resultado['entrada']                 = $aportacion;
				$resultado['importe_prestamo']        = $importePrestamo;
				$resultado['porcentaje_financiacion'] = $porcentajeFinanciacion;
				$resultado['tasacion']                = $tasacion;
				$resultado['notario']                 = $notario;
				$resultado['registro']                = $registro;
				$resultado['gestoria']                = $gestoria;
				$resultado['vinculaciones']           = $vinculaciones;
				$resultado['importe_iva']             = $importe_iva;
				$resultado['escritura_compra_impuesto_transmisiones'] = $escritura_impuesto;
				$resultado['amortizacion']            = $plazo;
				$resultado['tipo_calculo']            = 'cuota-precio-fijo';
				$resultado['mensaje']                 = '';
			}
			
			// ===== DEBUG: Log de datos y resultado =====
			error_log('=== SIMULADOR AJAX DEBUG ===');
			error_log('Entrada: edad=' . $edad . ', ingresos=' . $ingresosMensuales . ', plazo=' . intval($datos['plazo'] ?? 25));
			error_log('Salida: importe=' . ($resultado['importe_fijo'] ?? 0) . ', gastos=' . ($resultado['gastos'] ?? 0) . ', cuota=' . ($resultado['cuota'] ?? 0));
			error_log('=== FIN DEBUG ===');
			
			// ===== RETORNAR RESPUESTA (formato idéntico a calculadoraAvanzadaSubmitAction) =====
			return new JsonResponse([
				'error' => false,
				'importe_fijo' => round($resultado['importe_fijo'] ?? 0, 2),
				'entrada' => round($resultado['entrada'] ?? 0, 2),
				'gastos' => round($resultado['gastos'] ?? 0, 2),
				'cuota' => round($resultado['cuota'] ?? 0, 2),
				'amortizacion' => $resultado['amortizacion'] ?? intval($datos['plazo'] ?? 25),
				'mensaje' => $resultado['mensaje'] ?? 'Cálculo completado exitosamente',
				'tipo_calculo' => $resultado['tipo_calculo'] ?? 'importe-maximo',
				'obraNueva' => $resultado['obraNueva'] ?? false,
				'tasacion' => $resultado['tasacion'] ?? 0,
				'notario' => $resultado['notario'] ?? 0,
				'registro' => $resultado['registro'] ?? 0,
				'gestoria' => $resultado['gestoria'] ?? 0,
				'tipo_importe_maximo' => $resultado['tipo_importe_maximo'] ?? 0,
				'importe_iva' => $resultado['importe_iva'] ?? 0,
				'tipo_interes_ccaa' => ($resultado['tipo_interes_ccaa'] ?? 0) * 100,
				'importe_prestamo' => round($resultado['importe_prestamo'] ?? 0, 2),
				'porcentaje_financiacion' => round($resultado['porcentaje_financiacion'] ?? 0, 2),
				'vinculaciones' => round($resultado['vinculaciones'] ?? 0, 2)
			], 200);
			
		} catch (\Exception $e) {
			error_log('Error en calculadoraAvanzadaTestAjaxAction: ' . $e->getMessage());
			return new JsonResponse([
				'error' => true,
				'message' => 'Error al calcular: ' . $e->getMessage(),
				'importe_fijo' => 0
			], 500);
		}
	}
}
