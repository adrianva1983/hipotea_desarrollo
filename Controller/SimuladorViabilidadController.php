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
use AppBundle\Entity\VistaRotacionComerciales;
use AppBundle\Form\SimuladorInicioType;
use AppBundle\Form\SimuladorDatosClienteType;
use AppBundle\Form\SimuladorPrecioMaximoType;
use AppBundle\Form\SimuladorCuotaGastosType;
use AppBundle\Form\SimuladorRiesgoType;
use AppBundle\Entity\SimuladorUsoEmail;
use AppBundle\Entity\FicheroCampo;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use DateTime;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

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
        // Determinar paso inicial: si se pasa ?paso= en la URL lo respetamos,
        // si no se pasa, por defecto cargamos Paso 1 cuando el usuario está logueado,
        // y Paso 0 cuando no lo está (caso de acceso público).
        $pasoQuery = $request->query->get('paso', null);
        if ($pasoQuery === null) {
            $pasoActual = $usuarioActual ? 1 : 0;
        } else {
            // mantener el valor proporcionado (puede ser 'resultado' u otro)
            $pasoActual = is_numeric($pasoQuery) ? intval($pasoQuery) : $pasoQuery;
        }
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

    public function simuladorCompletoWebAction(Request $request)
    {
        // Acción pública: no requiere autenticación.
        // Arranca siempre en paso 0 (aviso legal) y permite completar el simulador sin login.
        $simulador = $this->getSimuladorSessionData($request) ?? [];

        $pasoQuery = $request->query->get('paso', null);
        if ($pasoQuery === null) {
            $pasoActual = 0;
        } else {
            $pasoActual = is_numeric($pasoQuery) ? intval($pasoQuery) : $pasoQuery;
        }

        $accion = $request->request->get('_accion');

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
                error_log('Error (web): ' . $e->getMessage());
                $this->addFlash('error', 'Error: ' . $e->getMessage());
            }
        }

        return $this->render('@App/Backoffice/SimuladorViabilidad/simulador_completo_web.html.twig', [
            'titulo'             => 'Simulador de Viabilidad Hipotecaria',
            'simulador'          => $simulador,
            'paso_actual'        => $pasoActual,
            'formulario_inicio'  => $this->createForm(SimuladorInicioType::class)->createView(),
            'formulario_cliente' => $this->createForm(SimuladorDatosClienteType::class)->createView(),
            'formulario_precio'  => $this->createForm(SimuladorPrecioMaximoType::class)->createView(),
            'formulario_cuota'   => $this->createForm(SimuladorCuotaGastosType::class)->createView(),
            'formulario_riesgo'  => $this->createForm(SimuladorRiesgoType::class)->createView(),
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
    public function enviarAHipoteaAction(Request $request, UserPasswordEncoderInterface $passwordEncoder, Swift_Mailer $mailer)
    {
        $usuarioActual = $this->getUser();
        $usuarioEsColaborador = $usuarioActual && $this->isGranted('ROLE_COLABORADOR');

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

        // ===== CHECK DE LÍMITE DE USOS POR EMAIL (idéntico a calculadoraAvanzadaTestAjaxAction) =====
        $emailCliente = $datosCliente['email'] ?? null;
        $maxUsos = $this->getParameter('simulador_max_usos');
        $whatsappContacto = $this->getParameter('simulador_whatsapp_contacto');
        $nombreCliente = $datosCliente['nombre'] ?? 'Usuario';

        $body = json_decode($request->getContent(), true) ?? [];
        $contaruso = isset($body['contaruso']) && ($body['contaruso'] === true || $body['contaruso'] === 'true');
        $tipo = 'simulador_viabilidad';

        if (!empty($emailCliente) && $contaruso) {
            try {
                $em = $this->getDoctrine()->getManager();
                
                // QueryBuilder idéntico a calculadoraAvanzadaTestAjaxAction
                $qb = $em->createQueryBuilder();
                $qb->select('u')
                    ->from('AppBundle:SimuladorUsoEmail', 'u')
                    ->where('u.email = :email')
                    ->andWhere('u.tipo = :tipo')
                    ->setParameter('email', $emailCliente)
                    ->setParameter('tipo', $tipo);
                $usoEmail = $qb->getQuery()->getOneOrNullResult();
                
                // Refresh explícito para garantizar que leemos el valor actual de la BD
                if ($usoEmail) {
                    $em->refresh($usoEmail);
                    error_log('=== CHECK LÍMITE (enviarAHipotea): Email ' . $emailCliente . ' - Usos actuales: ' . $usoEmail->getUsos());
                }
                
                // Bloquear si se alcanzó el límite
                if ($usoEmail && $usoEmail->getUsos() >= $maxUsos) {
                    error_log('=== LÍMITE ALCANZADO EN ENVIAR A HIPOTEA: ' . $emailCliente . ' (usos: ' . $usoEmail->getUsos() . '/' . $maxUsos . ')');
                    return new JsonResponse([
                        'success' => false,
                        'limite' => true,
                        'nombre' => $nombreCliente,
                        'email' => $emailCliente,
                        'whatsappContacto' => $whatsappContacto,
                        'mensaje' => 'No ha sido posible procesar tu solicitud porque este simulador está limitado a ' . $maxUsos . ' usos. Si deseas realizar más simulaciones, puedes solicitarlo poniéndote en contacto con nosotros desde este enlace'
                    ], 200);
                }
            } catch (\Exception $e) {
                error_log('Error al verificar límite en enviarAHipotea: ' . $e->getMessage());
                // Continuar sin bloquear si hay error
            }
        }


        try {
            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            
            // ── PASO 0: NORMALIZAR EL DNI DESDE EL INICIO ────────────────────────
            $dni = trim(strtoupper($datosCliente['dni'] ?? ''));
            if (empty($dni)) {
                return new JsonResponse(['success' => false, 'mensaje' => 'DNI inválido o vacío.'], 400);
            }

            // ── PASO 1: BUSCAR O CREAR EL CLIENTE PRIMERO ───────────────────────
            $repoUsuario    = $doctrine->getRepository(Usuario::class);
            $clienteUsuario = null;
            $clienteCreado  = false;

            // Buscar primero por NIF (normalizado), luego por email
            if (!empty($dni)) {
                $clienteUsuario = $repoUsuario->findOneBy(['nif' => $dni]);
            }
            if (!$clienteUsuario && !empty($datosCliente['email'])) {
                $clienteUsuario = $repoUsuario->findOneBy(['email' => trim(strtolower($datosCliente['email']))]);
            }

            if (!$clienteUsuario) {
                $nombreCompleto  = trim($datosCliente['nombre'] ?? 'Cliente Simulador');
                $partesNombre    = explode(' ', $nombreCompleto, 2);
                $soloNombre      = $partesNombre[0];
                $soloApellidos   = $partesNombre[1] ?? '';

                $clienteUsuario = (new Usuario())
                    ->setUsername($soloNombre)
                    ->setApellidos($soloApellidos)
                    ->setEmail(trim(strtolower($datosCliente['email'] ?? '')))
                    ->setNif($dni)  // Usar el DNI normalizado
                    ->setTelefonoMovil($datosCliente['telefono'] ?? '')
                    ->setRole('ROLE_CLIENTE')
                    ->setEstado(true);

                if ($usuarioActual) {
                    $inmobiliariaColaborador = $usuarioActual->getIdInmobiliaria();
                    if ($inmobiliariaColaborador) {
                        $clienteUsuario->setIdInmobiliaria($inmobiliariaColaborador);
                    }
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

            // ── PASO 2: VERIFICAR SI YA EXISTE EXPEDIENTE PARA ESTE CLIENTE HOY ──
            $expedientePrevio = null;
            $hoy = new DateTime();
            $hoy->setTime(0, 0, 0); // Inicio del día
            $repoExpediente = $doctrine->getRepository(Expediente::class);
            
            // Buscar expediente para este CLIENTE (ya confirmados) creado hoy
            // Usar innerJoin para garantizar que el cliente existe y coincide
            $expedientesHoy = $repoExpediente->createQueryBuilder('e')
                ->innerJoin('e.idCliente', 'c')
                ->where('e.idCliente = :idCliente')
                ->andWhere('e.fechaCreacion >= :hoy')
                ->setParameter(':idCliente', $clienteUsuario->getIdUsuario())
                ->setParameter(':hoy', $hoy)
                ->orderBy('e.fechaCreacion', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

            if (!empty($expedientesHoy)) {
                // Ya existe expediente para este cliente hoy → lo actualizaremos
                $expedientePrevio = $expedientesHoy[0];
            }

            // ── PASO 3: OBTENER FASE INICIAL ──────────────────────────────────────
            $faseInicial = $doctrine->getRepository(Fase::class)->findOneBy(['tipo' => 0]);
            if (!$faseInicial) {
                throw new \Exception('No se encontró la fase inicial.');
            }

            // ── PASO 4: PREPARAR DATOS DEL EXPEDIENTE ─────────────────────────────
            $precioInmueble = (float)($datosSimulador['precio_inmueble'] ?? 0);
            $viviendaLabel  = 'Simulador de Viabilidad';
            if ($precioInmueble > 0) {
                $viviendaLabel .= ' - ' . number_format($precioInmueble, 0, ',', '.') . ' €';
            }

            // ── PASO 5: CREAR O ACTUALIZAR EXPEDIENTE ─────────────────────────────
            $expedienteActualizado = false;
            
            if ($expedientePrevio) {
                // ACTUALIZAR expediente existente
                $expediente = $expedientePrevio;
                $expediente
                    ->setVivienda($viviendaLabel)
                    ->setTexto('Expediente actualizado desde el Simulador de Viabilidad.')
                    ->setFechaModificacion(new DateTime());
                $expedienteActualizado = true;
            } else {
                // CREAR nuevo expediente
                $expediente = (new Expediente())
                    ->setEstado(1)
                    ->setIdCliente($clienteUsuario)
                    ->setIdFaseActual($faseInicial)
                    ->setVivienda($viviendaLabel)
                    ->setTexto('Expediente creado desde el Simulador de Viabilidad.');

                // Asignar colaborador SOLO si el usuario actual es un colaborador
                if ($usuarioEsColaborador) {
                    $expediente->setIdColaborador($usuarioActual);
                }

                // Asignación del asesor hipotecario
                // - Si el usuario es colaborador: asignar el comercial de su inmobiliaria (si existe).
                // - Si no es colaborador: usar la rueda rotativa.
                if ($usuarioEsColaborador) {
                    $inmobiliaria = $usuarioActual->getIdInmobiliaria();
                    if ($inmobiliaria && $inmobiliaria->getIdComercial()) {
                        $expediente->setIdComercial($inmobiliaria->getIdComercial());
                    } else {
                        // Si no hay comercial en la inmobiliaria, caer a la rueda rotativa
                        $repoRotacion = $doctrine->getRepository(VistaRotacionComerciales::class);
                        $comercialRotativo = $repoRotacion->createQueryBuilder('v')
                            ->orderBy('v.ultimaAsignacion', 'ASC')
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if ($comercialRotativo != null) {
                            $comercial = $doctrine->getRepository(Usuario::class)->findOneBy([
                                'idUsuario' => $comercialRotativo->getIdUsuario()
                            ]);
                            if ($comercial) {
                                $expediente->setIdComercial($comercial);
                            }
                        }
                    }
                } else {
                    // No es colaborador: usar la rueda rotativa
                    $repoRotacion = $doctrine->getRepository(VistaRotacionComerciales::class);
                    $comercialRotativo = $repoRotacion->createQueryBuilder('v')
                        ->orderBy('v.ultimaAsignacion', 'ASC')
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                    if ($comercialRotativo != null) {
                        $comercial = $doctrine->getRepository(Usuario::class)->findOneBy([
                            'idUsuario' => $comercialRotativo->getIdUsuario()
                        ]);
                        if ($comercial) {
                            $expediente->setIdComercial($comercial);
                        }
                    }
                }
            }

            $em->persist($expediente);

            // ── PASO 6: CREAR HITOS Y CAMPOS DEL EXPEDIENTE ─────────────────────────
            // Mapa de idCampoHito → valor del cliente (mismo patrón que el JS en Expediente.html.twig)
            $nombreCompleto = trim(($clienteUsuario->getUsername() ?? '') . ' ' . ($clienteUsuario->getApellidos() ?? ''));
            $nif            = $clienteUsuario->getNif() ?? '';
            $telefono       = $clienteUsuario->getTelefonoMovil() ?? '';
            $email          = $clienteUsuario->getEmail() ?? '';
            $nombre         = $clienteUsuario->getUsername() ?? '';
            $apellidos      = $clienteUsuario->getApellidos() ?? '';
            $provincia      = $clienteUsuario->getProvincia() ?? '';
            $municipio      = $clienteUsuario->getMunicipio() ?? '';

            // Datos económicos del simulador - TITULAR UNO
            $numTitulares           = (int)($datosSimulador['num_titulares'] ?? 1);
            $ingresosMensuales  = (float)($datosSimulador['ingresos_mensuales'] ?? 0);
            $numeroPagas        = (int)($datosSimulador['numero_pagas'] ?? 0);
            $importePagas       = (float)($datosSimulador['importe_pagas'] ?? 0);
            $aportacion         = (float)($datosSimulador['aportacion'] ?? 0);
            $prestamos          = (float)($datosSimulador['prestamos_mensuales'] ?? 0);
            $situacionLaboral   = $datosSimulador['situacion_laboral'] ?? '';
            $antiguedadLaboral  = $datosSimulador['antiguedad_laboral'] ?? '';
            $tieneImpagados     = !empty($datosSimulador['tiene_impagados']);
            $gastosTotales      = (float)($datosSimulador['gastos_totales_aproximados'] ?? 0);
            $ingresosAnuales    = $ingresosMensuales * 12 + $numeroPagas * $importePagas;
            $etiquetasLaboral   = [
                'autonomo'             => 'Autónomo',
                'contrato_indefinido'  => 'Empleado (contrato indefinido)',
                'contrato_temporal'    => 'Empleado (contrato temporal)',
                'funcionario'          => 'Funcionario',
                'empresario'           => 'Empresario / Mercantil',
            ];
            $etiquetaLaboral = isset($etiquetasLaboral[$situacionLaboral]) ? $etiquetasLaboral[$situacionLaboral] : $situacionLaboral;
            
            // Campo 223: Antigüedad en la empresa actual (mapear AQUÍ, ANTES de usarla en $valorPorCampo)
            $antiguedadEmpresamap = '';
            if ($antiguedadLaboral === 'menos_1_anio')    $antiguedadEmpresamap = 'Menos de 1 año';
            elseif ($antiguedadLaboral === 'un_anio')     $antiguedadEmpresamap = '1 año';
            elseif ($antiguedadLaboral === 'mas_2_anios') $antiguedadEmpresamap = 'Más de 2 años';
            
            $importeHipoteca = ($precioInmueble > 0 && $precioInmueble > $aportacion) ? ($precioInmueble - $aportacion) + $gastosTotales : 0;

            // Datos económicos del simulador - TITULAR DOS (si existe)
            $ingresosMensualesDos   = (float)($datosSimulador['ingresos_mensuales_dos'] ?? 0);
            $numeroPagasDos         = (int)($datosSimulador['numero_pagas_dos'] ?? 0);
            $importePagasDos        = (float)($datosSimulador['importe_pagas_dos'] ?? 0);
            $situacionLaboralDos    = $datosSimulador['situacion_laboral_dos'] ?? '';
            $antiguedadLaboralDos   = $datosSimulador['antiguedad_laboral_dos'] ?? '';
            $tieneImpagadosDos      = !empty($datosSimulador['tiene_impagados_dos']) || $tieneImpagados; // Si T1 tiene impagos, asumir T2 también
            $etiquetaLaboralDos     = isset($etiquetasLaboral[$situacionLaboralDos]) ? $etiquetasLaboral[$situacionLaboralDos] : $situacionLaboralDos;
            $ingresosAnualesDos     = $ingresosMensualesDos * 12 + $numeroPagasDos * $importePagasDos;
            
            // Campo 541: Antigüedad en empresa actual - Titular 2
            $antiguedadEmpresamapDos = '';
            if ($antiguedadLaboralDos === 'menos_1_anio')    $antiguedadEmpresamapDos = 'Menos de 1 año';
            elseif ($antiguedadLaboralDos === 'un_anio')     $antiguedadEmpresamapDos = '1 año';
            elseif ($antiguedadLaboralDos === 'mas_2_anios') $antiguedadEmpresamapDos = 'Más de 2 años';

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
                223 => $antiguedadEmpresamap,                                                      // Antigüedad en la empresa actual
                //462 => $aportacion > 0        ? number_format($aportacion, 2, '.', '')        : '', // Ahorro disponible
                688 => (new DateTime())->format('d/m/Y'),                                                             // Fecha del Lead (hoy)
                690 => $etiquetaLaboral,                                                                               // Trabajo o Estado Laboral
                691 => $precioInmueble > 0  ? number_format($precioInmueble, 0, ',', '.') . ' €' : '',                // Valor del Inmueble
                699 => $aportacion > 0      ? number_format($aportacion, 0, ',', '.') . ' €'     : '',                // Cuánto ahorro aportas
                413 => $precioInmueble > 0  ? number_format($precioInmueble, 2, '.', '')          : '',                // Precio (sin impuestos)
                181 => $aportacion > 0      ? number_format($aportacion, 2, '.', '')              : '',                // Cantidad que aportas para la compra
                405 => $importeHipoteca > 0 ? number_format($importeHipoteca, 2, '.', '')         : '',                // Importe Hipoteca
                182 => $aportacion > 0      ? number_format($aportacion, 2, '.', '')              : '',                // Ahorro actual
                180 => $precioInmueble > 0  ? number_format($precioInmueble, 2, '.', '')          : '',                // Importe compraventa
                
                // SEGUNDO TITULAR - Datos Económicos
                555 => $ingresosMensualesDos > 0 ? number_format($ingresosMensualesDos, 2, '.', '') : '',          // Nómina mensual (neto) - Titular 2
                553 => $importePagasDos > 0      ? number_format($importePagasDos, 2, '.', '')      : '',          // Importe paga extra - Titular 2
                552 => $ingresosAnualesDos > 0   ? number_format($ingresosAnualesDos, 2, '.', '')   : '',          // Ingresos netos anuales - Titular 2
                541 => $antiguedadEmpresamapDos,                                                                      // Antigüedad en la empresa actual - Titular 2
            ];
            // Campos de selección (setIdOpcionesCampo) — usamos los IDs de opción del HTML real
            // Campo 226: Número pagas extras (111=0, 112=1, 113=2, 114=3, 115=4)
            $opcionNumeroPagas = $numeroPagas >= 0 && $numeroPagas <= 4 ? (111 + $numeroPagas) : null;

            // Campo 193: Tipo de empleo (97=Autónomo, 102=Emplead@, 103=Mercantil)
            $opcionTipoEmpleo = null;
            if ($situacionLaboral === 'autonomo')                                                 $opcionTipoEmpleo = 97;
            elseif (in_array($situacionLaboral, ['contrato_indefinido','contrato_temporal','funcionario'])) $opcionTipoEmpleo = 102;
            elseif ($situacionLaboral === 'empresario')                                           $opcionTipoEmpleo = 103;

            // Campo 221: Tipo de contrato (104=Indefinido tiempo completo, 105=Indefinido tiempo parcial, 
            // 106=Indefinido discontinuo, 107=Funcionario, 108=Interinidad, 109=Temporal tiempo completo, 
            // 110=Temporal tiempo parcial, 357=Militar, 555=Personal laboral fijo)
            $opcionTipoContrato = null;
            if ($situacionLaboral === 'contrato_indefinido')       $opcionTipoContrato = 104; // Asumimos indefinido tiempo completo
            elseif ($situacionLaboral === 'contrato_temporal')     $opcionTipoContrato = 109; // Asumimos temporal tiempo completo
            elseif ($situacionLaboral === 'funcionario')           $opcionTipoContrato = 107;
            elseif ($situacionLaboral === 'autonomo')              $opcionTipoContrato = 555; // Personal laboral fijo (similar a autónomo)

            // Campo 244: ¿Tiene impagados? (123=Sí, 124=No)
            $opcionImpagados = $tieneImpagados ? 123 : 124;

            // SEGUNDO TITULAR - Opciones
            // Campo 554: Número pagas extras (522=0, 523=1, 524=2, 525=3, 526=4)
            $opcionNumeroPagasDos = $numeroPagasDos >= 0 && $numeroPagasDos <= 4 ? (522 + $numeroPagasDos) : null;

            // Campo 547: Tipo de empleo - Titular 2 (497=Autónomo, 498=Pensionista, 499=Empleado, 500=Mercantil)
            $opcionTipoEmpleoDos = null;
            if ($situacionLaboralDos === 'autonomo')                                              $opcionTipoEmpleoDos = 497;
            elseif (in_array($situacionLaboralDos, ['contrato_indefinido','contrato_temporal','funcionario'])) $opcionTipoEmpleoDos = 499;
            elseif ($situacionLaboralDos === 'empresario')                                        $opcionTipoEmpleoDos = 500;

            // Campo 549: Tipo de contrato - Titular 2 (515=Indefinido TC, 516=Indefinido PT, 517=Indefinido discontinuo,
            // 518=Funcionario, 519=Interinidad, 520=Temporal TC, 521=Temporal PT, 514=Militar, 556=Personal laboral fijo)
            $opcionTipoContratoDos = null;
            if ($situacionLaboralDos === 'contrato_indefinido')       $opcionTipoContratoDos = 515; // Asumimos indefinido tiempo completo
            elseif ($situacionLaboralDos === 'contrato_temporal')     $opcionTipoContratoDos = 520; // Asumimos temporal tiempo completo
            elseif ($situacionLaboralDos === 'funcionario')           $opcionTipoContratoDos = 518;
            elseif ($situacionLaboralDos === 'autonomo')              $opcionTipoContratoDos = 556; // Personal laboral fijo (similar a autónomo)

            // Campo 559: ¿Tiene impagados? - Titular 2 (534=Sí, 535=No)
            $opcionImpagadosDos = $tieneImpagadosDos ? 534 : 535;

            // Campo 456: ¿Cuántos titulares sois? (355=Uno, 356=Dos)
            $opcionNumTitulares = ($numTitulares === 2) ? 356 : 355;

            $opcionPorCampo = [
                226 => $opcionNumeroPagas,  // Número pagas extras
                193 => $opcionTipoEmpleo,   // Tipo de empleo
                221 => $opcionTipoContrato, // Tipo de contrato
                244 => $opcionImpagados,    // ¿Tiene impagados?
                673 => 688,                 // Origen → "Calculadora"
                179 => 71,                  // ¿Para qué necesitas la hipoteca? → "Adquirir una propiedad"
                640 => 608,                 // ¿Cuántas propiedades hipotecar? → "Una"
                
                // SEGUNDO TITULAR - Opciones
                554 => $opcionNumeroPagasDos,  // Número pagas extras - Titular 2
                547 => $opcionTipoEmpleoDos,   // Tipo de empleo - Titular 2
                549 => $opcionTipoContratoDos, // Tipo de contrato - Titular 2
                559 => $opcionImpagadosDos,    // ¿Tiene impagados? - Titular 2
                456 => $opcionNumTitulares,    // ¿Cuántos titulares sois?
            ];

            // ── PASO 7: CREAR O ACTUALIZAR HITOS Y CAMPOS DEL EXPEDIENTE ══════════════
            if ($expedienteActualizado) {
                // CASO: ACTUALIZACIÓN del expediente existente
                // Actualizar los valores de los campos existentes con los nuevos datos
                $repoCampoExpediente = $doctrine->getRepository(CampoHitoExpediente::class);
                
                foreach ($valorPorCampo as $idCampo => $valor) {
                    if ($valor === '') continue;
                    
                    // Buscar campo existente en el expediente
                    $campoExpediente = $repoCampoExpediente->findOneBy([
                        'idExpediente' => $expediente,
                        'idCampoHito' => $idCampo
                    ]);
                    
                    if ($campoExpediente) {
                        $campoExpediente->setValor($valor)->setFechaModificacion(new DateTime());
                        $em->persist($campoExpediente);
                    }
                }
                
                foreach ($opcionPorCampo as $idCampo => $idOpcion) {
                    if ($idOpcion === null) continue;
                    
                    // Buscar campo existente en el expediente
                    $campoExpediente = $repoCampoExpediente->findOneBy([
                        'idExpediente' => $expediente,
                        'idCampoHito' => $idCampo
                    ]);
                    
                    if ($campoExpediente && method_exists($campoExpediente, 'setIdOpcionesCampo')) {
                        $opcion = $doctrine->getRepository('AppBundle:OpcionesCampo')->find($idOpcion);
                        if ($opcion) {
                            $campoExpediente->setIdOpcionesCampo($opcion)->setFechaModificacion(new DateTime());
                            $em->persist($campoExpediente);
                        }
                    }
                }
                
            } else {
                // CASO: NUEVO expediente
                // Crear todos los hitos y campos desde cero
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
            } // Fin del if/else

            $em->flush();

            // Generar y asignar referencia única solo para expedientes nuevos
            if (!$expedienteActualizado) {
                $referenciaService = $this->get('app.referencia_expediente');
                $referenciaService->asignarReferenciaAExpediente($expediente);
                $em->flush();
            }

            if ($expedienteActualizado) {
                $msg = $clienteCreado
                    ? 'Cliente registrado y expediente actualizado correctamente.'
                    : 'Expediente actualizado con los nuevos datos del simulador correctamente.';
            } else {
                $msg = $clienteCreado
                    ? 'Cliente registrado y expediente asignado al asesor hipotecario correctamente.'
                    : 'Expediente creado y asignado al asesor hipotecario correctamente.';
            }

            $from = array($this->getParameter('mailer_user') => 'Hipotea');

            // Variables para la plantilla de correo (usar valores disponibles, con fallback)
            $variablesTwig = [
                'resultado' => true,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'importe_fijo' => $precioInmueble ?? 0,
                'gastos' => $gastosTotales ?? 0,
                'entrada' => $aportacion ?? 0,
                'importe_total' => $importeHipoteca ?? 0,
                'notario' => $datosSimulador['notario'] ?? 0,
                'registro' => $datosSimulador['registro'] ?? 0,
                'gestoria' => $datosSimulador['gestoria'] ?? 0,
                'tasacion' => $datosSimulador['tasacion'] ?? 0,
                'tipo_interes_ccaa' => $datosSimulador['tipo_interes_ccaa'] ?? 0,
            ];

            $mensaje = (new Swift_Message('Aquí tienes el resultado de tu consulta hipotecaria!'))
                ->setFrom($from)
                ->setTo($clienteUsuario->getEmail())
                //->setTo('adrianva1983@gmail.com')
                ->setBody($this->renderView('@App/Backoffice/Correo/ResultadoSimuladorWebCliente.html.twig', $variablesTwig), 'text/html');
            
            // PROBANDO CON PDF ADJUNTO
            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);

            // El HTML del modal llega con rutas relativas (/assets/...).
            // Para que wkhtmltopdf pueda cargar imágenes/CSS, convertirlas a absolutas.
            $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
            $informeHtmlParaPdf = (string) $informeHtml;

            // Prioridad 1: usar rutas locales file:/// para assets estáticos del proyecto.
            // Esto evita fallos de carga por SSL/proxy/ngrok en wkhtmltopdf.
            $webDir = realpath($this->getParameter('kernel.root_dir') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'web');
            if ($webDir) {
                $webDirUri = 'file:///' . ltrim(str_replace('\\', '/', $webDir), '/');

                $informeHtmlParaPdf = str_replace('src="/assets/', 'src="' . $webDirUri . '/assets/', $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace("src='/assets/", "src='" . $webDirUri . "/assets/", $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace('href="/assets/', 'href="' . $webDirUri . '/assets/', $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace("href='/assets/", "href='" . $webDirUri . "/assets/", $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace('url(/assets/', 'url(' . $webDirUri . '/assets/', $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace('url("/assets/', 'url("' . $webDirUri . '/assets/', $informeHtmlParaPdf);
                $informeHtmlParaPdf = str_replace("url('/assets/", "url('" . $webDirUri . "/assets/", $informeHtmlParaPdf);
            }

            $informeHtmlParaPdf = preg_replace(
                '~(src|href)=(["\'])/(?!/)([^"\']+)\2~i',
                '$1=$2' . $baseUrl . '/$3$2',
                $informeHtmlParaPdf
            );
            $informeHtmlParaPdf = preg_replace(
                '~url\((["\']?)/(?!/)([^)"\']+)\1\)~i',
                'url($1' . $baseUrl . '/$2$1)',
                $informeHtmlParaPdf
            );

            // Guardar copia de depuración del HTML que se va a pasar a wkhtmltopdf
            try {
                $debugHtmlPath = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'debug_informe_' . $nombre_pdf . '.html';
                @file_put_contents($debugHtmlPath, $informeHtmlParaPdf);
            } catch (\Throwable $e) {
                // No bloquear el flujo por fallos al guardar el archivo de debug
            }

            $this->get('knp_snappy.pdf')->generateFromHtml(
                $this->renderView('@App/Backoffice/Correo/ResultadoSimuladorWebPDF.html.twig', [
                    'informe_html' => $informeHtmlParaPdf,
                ]),
                // $contenido,
                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
                [
                    'encoding' => 'UTF-8',
                    'enable-local-file-access' => true,
                    'load-error-handling' => 'ignore',
                    'load-media-error-handling' => 'ignore',
                    'print-media-type' => true,
                    // Ajustes para mejorar coincidencia visual con navegador
                    'disable-smart-shrinking' => false,
                    'zoom' => 1,
                    'javascript-delay' => 1500,
                    'no-stop-slow-scripts' => true,
                    'page-size' => 'A4',
                    'margin-top' => '12mm',
                    'margin-bottom' => '12mm',
                    'dpi' => 300,
                ],
                true
            );
            
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));

            // ── ADJUNTAR PDF AL CAMPO HITO 754 (Documentación adicional) DEL EXPEDIENTE ──
            try {
                $nombreFicheroPdf = 'calculadora_' . $nombre_pdf . '.pdf';
                $campoHito754 = $doctrine->getRepository(CampoHito::class)->find(754);
                if ($campoHito754) {
                    $campoHitoExp754 = $doctrine->getRepository(CampoHitoExpediente::class)->findOneBy([
                        'idExpediente' => $expediente,
                        'idCampoHito'  => $campoHito754,
                    ]);
                    if ($campoHitoExp754) {
                        // Eliminar fichero anterior si existe
                        $ficheroPrevio = $doctrine->getRepository(FicheroCampo::class)->findOneBy([
                            'idCampoHitoExpediente' => $campoHitoExp754,
                        ]);
                        if ($ficheroPrevio) {
                            $em->remove($ficheroPrevio);
                        }

                        $campoHitoExp754->setValor('Informe_Simulador_Viabilidad')
                                        ->setFechaModificacion(new DateTime());
                        $em->persist($campoHitoExp754);

                        $ficheroCampo = (new FicheroCampo())
                            ->setNombreFichero($nombreFicheroPdf)
                            ->setIdCampoHito($campoHito754)
                            ->setIdCampoHitoExpediente($campoHitoExp754)
                            ->setIdExpediente($expediente);
                        $em->persist($ficheroCampo);
                        $em->flush();
                    }
                }
            } catch (\Throwable $eFichero) {
                error_log('Error adjuntando PDF al campo hito 754: ' . $eFichero->getMessage());
            }

            // Pasar variables adicionales a la plantilla y actualizar el body para cliente
            $variablesTwig['informe_html'] = $informeHtmlParaPdf;
            $variablesTwig['fecha'] = (new \DateTime())->format('d/m/Y');
            $variablesTwig['expediente_id'] = $expediente->getIdExpediente();

            // Mapear valores del simulador si están disponibles (seguro para la plantilla)
            $variablesTwig['numTitulares'] = $datosSimulador['num_titulares'] ?? ($simulador['num_titulares'] ?? null);
            $variablesTwig['edadTitularUno'] = $datosSimulador['edad_titular_uno'] ?? ($simulador['cliente']['edad'] ?? null);
            $variablesTwig['edadTitularDos'] = $datosSimulador['edad_titular_dos'] ?? null;
            $variablesTwig['plazoAmortizacion'] = $datosSimulador['plazo_amortizacion'] ?? ($simulador['cuota']['plazoAmortizacion'] ?? null);
            $variablesTwig['aportacionInicial'] = $datosSimulador['aportacion_inicial'] ?? ($simulador['precio']['aportacionNecesaria'] ?? $aportacion ?? 0);
            $variablesTwig['destinoCompra'] = $datosSimulador['destino_compra'] ?? ($simulador['tipoOperacion'] ?? null);
            $variablesTwig['obraNuevaText'] = !empty($datosSimulador['obra_nueva']) ? 'Sí' : 'No';
            $variablesTwig['comunidadAutonoma'] = $datosSimulador['comunidad_autonoma'] ?? null;
            $variablesTwig['discapacidad'] = $datosSimulador['discapacidad'] ?? null;
            $variablesTwig['familiaNumerosa'] = $datosSimulador['familia_numerosa'] ?? null;
            $variablesTwig['monoparental'] = $datosSimulador['monoparental'] ?? null;
            $variablesTwig['vpo'] = $datosSimulador['vpo'] ?? null;
            $variablesTwig['ingresosMensuales'] = $datosSimulador['ingresos_mensuales'] ?? ($ingresosMensuales ?? 0);
            $variablesTwig['numPagasExtra'] = $datosSimulador['numero_pagas'] ?? ($numeroPagas ?? 0);
            $variablesTwig['importePagaExtra'] = $datosSimulador['importe_paga_extra'] ?? ($importePagas ?? 0);
            $variablesTwig['prestamosMensuales'] = $datosSimulador['prestamos_mensuales'] ?? ($prestamos ?? 0);
            $variablesTwig['ingresosMensualesDos'] = $datosSimulador['ingresos_mensuales_dos'] ?? ($ingresosMensualesDos ?? 0);
            $variablesTwig['numPagasExtraDos'] = $datosSimulador['numero_pagas_dos'] ?? ($numeroPagasDos ?? 0);
            $variablesTwig['importePagaExtraDos'] = $datosSimulador['importe_paga_extra_dos'] ?? ($importePagasDos ?? 0);
            $variablesTwig['prestamosMensualesDos'] = $datosSimulador['prestamos_mensuales_dos'] ?? ($prestamosMensualesDos ?? 0);
            $variablesTwig['edad'] = $datosSimulador['edad'] ?? null;
            $variablesTwig['valorInmueble'] = $datosSimulador['valor_inmueble'] ?? ($precioInmueble ?? 0);
            $variablesTwig['valor_inmueble'] = $variablesTwig['valorInmueble'];
            $variablesTwig['valorViviendaActual'] = $datosSimulador['valor_vivienda_actual'] ?? null;
            $variablesTwig['hipotecaActual'] = $datosSimulador['hipoteca_actual'] ?? null;
            $variablesTwig['aportacionTrasVenta'] = $datosSimulador['aportacion_tras_venta'] ?? null;
            $variablesTwig['honorariosInmobiliaria'] = $datosSimulador['honorarios_inmobiliaria'] ?? ($variablesTwig['gastos_inmobiliaria'] ?? 0);
            $variablesTwig['producto'] = $datosSimulador['producto'] ?? null;
            $variablesTwig['cuota'] = $variablesTwig['importe_fijo'] ?? ($simulador['cuota']['cuotaHipotecariaEstimada'] ?? 0);
            $variablesTwig['amortizacion'] = $variablesTwig['amortizacion'] ?? ($variablesTwig['plazoAmortizacion'] ?? ($simulador['cuota']['plazoAmortizacion'] ?? 0));

            $mensaje->setBody($this->renderView('@App/Backoffice/Correo/ResultadoSimuladorWebCliente.html.twig', $variablesTwig), 'text/html');
            // FIN PROBANDO CON PDF ADJUNTO                
            

            // Enviar el primer correo solo si la petición proviene de la ruta pública del web (/web/simulador-viabilidad)
            try {
                $referer = $request->headers->get('referer', '');
                $refPath = $referer ? parse_url($referer, PHP_URL_PATH) : '';
                $shouldSend = $refPath === '/web/simulador-viabilidad';
            } catch (\Throwable $e) {
                $shouldSend = false;
            }

            if ($shouldSend) {
                $mailer->send($mensaje);
            } else {
                error_log('Mailer suppressed (primary): referer path mismatch. Referer=' . ($referer ?? ''));
            }

            // Ahora enviar un segundo correo específico a Hipotea (copia separada)
            try {
                $hipoteaEmail = 'info@hipotea.com';
                //$hipoteaEmail = 'adrian.verdecia@semillaproyectos.com';
                $variablesTwig['origen'] = 'hipotea';
                $variablesTwig['email'] = $email;
                $variablesTwig['telefono'] = $telefono;

                $mensajeHipotea = (new Swift_Message('Nuevo resultado de simulador - Expediente #' . $expediente->getIdExpediente()))
                    ->setFrom($from)
                    ->setTo($hipoteaEmail)
                    ->setBody($this->renderView('@App/Backoffice/Correo/ResultadoSimuladorWebCliente.html.twig', $variablesTwig), 'text/html');

                // Adjuntar el mismo PDF generado
                $pdfPath = $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf';
                if (file_exists($pdfPath)) {
                    $mensajeHipotea->attach(Swift_Attachment::fromPath($pdfPath)->setFilename('Hipotea_Result_' . $nombre_pdf . '.pdf'));
                }

                // Enviar el correo específico a Hipotea solo si la petición proviene de la ruta pública del web
                try {
                    $referer2 = $request->headers->get('referer', '');
                    $refPath2 = $referer2 ? parse_url($referer2, PHP_URL_PATH) : '';
                    $shouldSendHip = $refPath2 === '/web/simulador-viabilidad';
                } catch (\Throwable $e) {
                    $shouldSendHip = false;
                }

                if ($shouldSendHip) {
                    $mailer->send($mensajeHipotea);
                } else {
                    error_log('Mailer suppressed (hipotea): referer path mismatch. Referer=' . ($referer2 ?? ''));
                }
            } catch (\Throwable $e) {
                // Registrar pero no bloquear la respuesta al usuario
                error_log('Error enviando correo a Hipotea: ' . $e->getMessage());
            }
            
            // ===== INCREMENTAR CONTADOR DE USOS =====
            // Solo se cuenta cuando se envía exitosamente a Hipotea
            $emailCliente = $datosCliente['email'] ?? null;
            $tipo = 'simulador_viabilidad'; // Redefinir para garantizar scope
            if (!empty($emailCliente) && ($contaruso)) {
                try {
                    error_log('=== INCREMENTAR CONTADOR: Iniciando para ' . $emailCliente);
                    error_log('Tipo a buscar/crear: ' . $tipo);
                    
                    // Obtener EntityManager fresco (importante si hay timeout o error previo)
                    $emContador = $this->getDoctrine()->getManager();
                    if (!$emContador->isOpen()) {
                        error_log('EntityManager cerrado, reabriendo...');
                        $emContador = $this->getDoctrine()->resetManager();
                    }
                    
                    $qb = $emContador->createQueryBuilder();
                    $qb->select('u')
                        ->from('AppBundle:SimuladorUsoEmail', 'u')
                        ->where('u.email = :email')
                        ->andWhere('u.tipo = :tipo')
                        ->setParameter('email', $emailCliente)
                        ->setParameter('tipo', $tipo);
                    $usoEmail = $qb->getQuery()->getOneOrNullResult();
                    error_log('Búsqueda realizada para email=' . $emailCliente . ' tipo=' . $tipo . ': ' . ($usoEmail ? 'ENCONTRADO (ID: ' . $usoEmail->getId() . ', usos: ' . $usoEmail->getUsos() . ')' : 'NO ENCONTRADO'));
                    
                    if (!$usoEmail) {
                        // Crear nuevo registro
                        error_log('Creando nuevo registro para email=' . $emailCliente . ' tipo=' . $tipo);
                        $usoEmail = new SimuladorUsoEmail();
                        $usoEmail->setEmail($emailCliente);
                        $usoEmail->setTipo($tipo);
                        $usoEmail->setUsos(1);
                        $usoEmail->setPrimerUso(new \DateTime());
                        $usoEmail->setUltimoUso(new \DateTime());
                        $emContador->persist($usoEmail);
                    } else {
                        // Incrementar existente
                        error_log('Incrementando registro existente: usos actual=' . $usoEmail->getUsos());
                        $usosAntes = $usoEmail->getUsos();
                        $usoEmail->incrementarUsos();
                        error_log('Usos después del incremento: ' . $usoEmail->getUsos());
                        $emContador->persist($usoEmail);
                    }
                    error_log('Antes de flush...');
                    $emContador->flush();
                    error_log('Contador incrementado exitosamente para: ' . $emailCliente);
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $eUnique) {
                    // RACE CONDITION: Otro proceso insertó el registro justo ahora
                    error_log('RACE CONDITION detectada: Registro duplicado. Reintentando búsqueda y actualización...');
                    try {
                        $emContador->clear(); // Limpiar Entity Manager corrupto
                        $emContador = $this->getDoctrine()->resetManager();
                        
                        // Reintentar búsqueda del registro que ahora debe existir
                        $qb = $emContador->createQueryBuilder();
                        $qb->select('u')
                            ->from('AppBundle:SimuladorUsoEmail', 'u')
                            ->where('u.email = :email')
                            ->andWhere('u.tipo = :tipo')
                            ->setParameter('email', $emailCliente)
                            ->setParameter('tipo', $tipo);
                        $usoEmail = $qb->getQuery()->getOneOrNullResult();
                        
                        if ($usoEmail) {
                            error_log('Reintentos exitoso: Registro encontrado. Incrementando usos de ' . $usoEmail->getUsos() . ' a ' . ($usoEmail->getUsos() + 1));
                            $usoEmail->incrementarUsos();
                            $emContador->persist($usoEmail);
                            $emContador->flush();
                            error_log('Contador incrementado correctamente (después de race condition)');
                        }
                    } catch (\Throwable $eReintento) {
                        error_log('ERROR en reintento de race condition: ' . $eReintento->getMessage());
                    }
                } catch (\Throwable $eContador) {
                    // NO bloquear la respuesta, solo registrar
                    error_log('ERROR al incrementar contador: ' . $eContador->getMessage());
                    error_log('Trace: ' . $eContador->getTraceAsString());
                }
            }
            
            return new JsonResponse([
                'success' => true, 
                'mensaje' => $msg, 
                'actualizado' => $expedienteActualizado,
                'expediente_id' => $expediente->getIdExpediente()
            ]);

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
	 * Lee JSON del request body y retorna JSON serializable (sin objetos Symfony)
	 * 
	 * Entrada esperada:
	 * {
	 *   "datos": {
	 *     "tipo_calculo": 2,
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
        
		
		// ===== SOPORTAR AMBAS ESTRUCTURAS JSON =====
		// Paso 3 envía: { "datos": { ... } }
		// Paso 2 envía: { "tipo_calculo": ..., "email": ..., ... }
		if (isset($data['datos']) && is_array($data['datos'])) {
			$datos = $data['datos'];
		} elseif (isset($data['tipo_calculo'])) {
			// Paso 2 envía todo en la raíz
			$datos = $data;
		} else {
			return new JsonResponse([
				'error' => true,
				'message' => 'No se recibieron datos para procesar',
				'importe_fijo' => 0
			], 400);
		}
        $contaruso = isset($datos['contaruso']) && ($datos['contaruso'] === true || $datos['contaruso'] === 'true');
        $tipo = 'simulador_viabilidad';
		// ===== CHECK DE LÍMITE DE USOS POR EMAIL =====
		$email = $datos['email'] ?? null;
		$nombre = $datos['nombre'] ?? null;
		$maxUsos = $this->getParameter('simulador_max_usos');
		$whatsappContacto = $this->getParameter('simulador_whatsapp_contacto');
		
		// DEBUG: Log de email y nombre recibidos
		error_log('=== SIMULADOR LIMITE CHECK ===');
        error_log('contaruso3: ' . ($contaruso));
		error_log('Email recibido1: ' . ($email ? $email : 'VACÍO/NULL'));
		error_log('Nombre recibido1: ' . ($nombre ? $nombre : 'VACÍO/NULL')); 
		error_log('Datos completos del payload1: ' . json_encode($datos));
		error_log('=== FIN DEBUG ===');
		
        if ($contaruso) {
            error_log('Contar uso es verdadero, procediendo a verificacion de limite');
            if (!empty($email)) {
                error_log('Email recibido1 no vacío, entrando al bloque de verificación');
                $em = $this->getDoctrine()->getManager();
                error_log('EntityManager obtenido');
                try {
                    // Usar QueryBuilder en lugar de getRepository para evitar el repositorio personalizado
                    $qb = $em->createQueryBuilder();
                    $qb->select('u')
                        ->from('AppBundle:SimuladorUsoEmail', 'u')
                        ->where('u.email = :email')
                        ->andWhere('u.tipo = :tipo')
                        ->setParameter('email', $email)
                        ->setParameter('tipo', $tipo);
                    $usoEmail = $qb->getQuery()->getOneOrNullResult();
                    error_log('QueryBuilder ejecutado, resultado: ' . ($usoEmail ? 'encontrado' : 'no encontrado'));
                    
                    // IMPORTANTE: Hacer refresh explícito para garantizar que leemos el valor actual de la BD
                    // Esto evita problemas de caché cuando hay múltiples peticiones rápidas
                    if ($usoEmail) {
                        $em->refresh($usoEmail);
                        error_log('Registro refrescado desde BD. Usos actuales: ' . $usoEmail->getUsos());
                    }
                } catch (\Exception $e) {
                    error_log('Error al ejecutar QueryBuilder: ' . $e->getMessage());
                    error_log('Continuando sin verificación de límite');
                    $usoEmail = null;
                }
                
                if ($usoEmail && $usoEmail->getUsos() >= $maxUsos) {
                    error_log('Límite alcanzado para ' . $email);
                    // Límite alcanzado
                    return new JsonResponse([
                        'error' => false,
                        'limite' => true,
                        'nombre' => $nombre,
                        'email' => $email,
                        'whatsappContacto' => $whatsappContacto,
                        'mensaje' => 'No ha sido posible enviarte el resultado porque este simulador está limitado a ' . $maxUsos . ' usos.'
                    ], 200);
                }
                error_log('No hay límite alcanzado, continuando');
            }
        }
		
		// DEBUG: Verificar que llegamos aquí
		error_log('Iniciando cálculo de forma normal para email: ' . ($email ?: 'sin email'));

            // ===== MISMA BASE DE CÁLCULO QUE CalculadorasController (precio máximo) =====
            $tipoCalculo = intval($datos['tipo_calculo'] ?? 2);
            $valorInmueble = floatval($datos['valor_inmueble'] ?? 0);

            $numTitulares = intval($datos['num_titulares'] ?? 1);
            if ($numTitulares < 1 || $numTitulares > 2) {
                $numTitulares = 1;
            }

            $edadTitularUno = intval($datos['edad_titular_uno'] ?? ($datos['edad'] ?? 0));
            $edadTitularDos = intval($datos['edad_titular_dos'] ?? 0);
            $edad = max($edadTitularUno, $edadTitularDos);
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

            $calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
            if ($tipoCalculo === 1) {
                $calculadora->setTipo(1);
                $calculadora->setValorInmueble($valorInmueble);
            } else {
                $calculadora->setTipo(2);
            }
            
            // Inicializar $producto para evitar undefined variable
            $producto = null;
            $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest');

            // ===== CONVERTIR PRODUCTO STRING A INT SI ES NECESARIO =====
            $productoValue = $datos['producto'] ?? 1;
            if ($productoValue === 'cambio_de_casa') {
                $productoValue = 4;
            } elseif ($productoValue === 'hipoteca_80') {
                $productoValue = 1;
            } elseif ($productoValue === 'premium') {
                $productoValue = 2;
            } elseif ($productoValue === 'sin_compromiso') {
                $productoValue = 3;
            }
            $productoInt = intval($productoValue);

            $formData = [
                'tipo' => intval($datos['tipo_calculo'] ?? 1),
                'numTitulares' => $numTitulares,
                'edad' => $edad,
                'edadTitularUno' => $edadTitularUno,
                'edadTitularDos' => $edadTitularDos,
                'plazoAmortizacion' => intval($datos['plazo'] ?? 25),
                'aportacionInicial' => floatval($datos['aportacion'] ?? 0),
                'destinoCompra' => intval($datos['destino_compra'] ?? 1),
                'obraNueva' => (bool) ($datos['obra_nueva'] ?? false),
                'comunidadAutonoma' => intval($datos['comunidad_autonoma'] ?? 4),
                'minusvaliaFamiliaNumerosa' => (bool) ($datos['minusvalia_familia_numerosa'] ?? false),
                'familiaNumerosa' => (bool) ($datos['familia_numerosa'] ?? false),
                'monoparental' => (bool) ($datos['monoparental'] ?? false),
                'vpo' => (bool) ($datos['vpo'] ?? false),
                'ingresosMensuales' => floatval($datos['ingresos_mensuales'] ?? 0),
                'numPagasExtra' => intval($datos['numero_pagas'] ?? 0),
                'importePagaExtra' => floatval($datos['importe_pagas'] ?? 0),
                'prestamosMensuales' => floatval($datos['prestamos_mensuales'] ?? 0),
                'ingresosMensualesDos' => floatval($datos['ingresos_mensuales_dos'] ?? 0),
                'numPagasExtraDos' => intval($datos['numero_pagas_dos'] ?? 0),
                'importePagaExtraDos' => floatval($datos['importe_pagas_dos'] ?? 0),
                'prestamosMensualesDos' => floatval($datos['prestamos_mensuales_dos'] ?? 0),
                'valorInmueble' => floatval($datos['valor_inmueble'] ?? 0),
                'producto' => $productoInt,
                'honorariosInmobiliaria' => floatval($datos['gastos_inmobiliaria'] ?? $datos['honorarios'] ?? 0),
                // ===== CAMPOS ESPECÍFICOS DE "CAMBIO DE CASA" (PRODUCTO 4) =====
                'valorViviendaActual' => floatval($datos['valor_vivienda_actual'] ?? 0),
                'hipotecaActual' => floatval($datos['hipoteca_actual'] ?? 0),
                'aportacionTrasVenta' => floatval($datos['aportacion_tras_venta'] ?? 0),
            ];

            // ===== WORKAROUND: Capturar valores booleanos ANTES del submit() =====
            // El FormType está corruptiendo false → 1, así que guardamos antes y restauramos después
            $obraNuevaOriginal = (bool) ($datos['obra_nueva'] ?? false);
            $familiaNumerosaOriginal = (bool) ($datos['familia_numerosa'] ?? false);
            $minusvaliaFamiliaNumerosaOriginal = (bool) ($datos['minusvalia_familia_numerosa'] ?? false);
            $vpoOriginal = (bool) ($datos['vpo'] ?? false);
            $monoparentalOriginal = (bool) ($datos['monoparental'] ?? false);

            $formulario->submit($formData, false);
            $calculadora = $formulario->getData();
            
            // ===== RESTAURAR VALORES BOOLEANOS DESPUÉS DEL SUBMIT() =====
            // El FormType estaba forzando estos valores a 1, restauramos aquí
            $calculadora->setObraNueva($obraNuevaOriginal);
            $calculadora->setFamiliaNumerosa($familiaNumerosaOriginal);
            $calculadora->setMinusvaliaFamiliaNumerosa($minusvaliaFamiliaNumerosaOriginal);
            $calculadora->setVpo($vpoOriginal);
            $calculadora->setMonoparental($monoparentalOriginal);



            // ===== DEBUG: mostrar EXACTAMENTE QUÉ VALORES TIENE LA ENTIDAD DESPUÉS DEL SUBMIT() + RESTAURACIÓN =====
            error_log('=== POST-SUBMIT STATE (AFTER BOOLEAN RESTORATION) ===');
            error_log('Input formData: ' . json_encode($formData));
            error_log('Boolean values before submit: obraNueva=' . ($obraNuevaOriginal ? 'true' : 'false') . ', vpo=' . ($vpoOriginal ? 'true' : 'false'));
            error_log('Entity values after submit() + restoration:');
            error_log('  - producto: ' . $calculadora->getProducto());
            error_log('  - obraNueva: ' . ($calculadora->getObraNueva() ? 'TRUE' : 'FALSE'));
            error_log('  - vpo: ' . ($calculadora->getVpo() ? 'TRUE' : 'FALSE'));
            error_log('  - familiaNumerosa: ' . ($calculadora->getFamiliaNumerosa() ? 'TRUE' : 'FALSE'));
            error_log('  - minusvaliaFamiliaNumerosa: ' . ($calculadora->getMinusvaliaFamiliaNumerosa() ? 'TRUE' : 'FALSE'));
            error_log('  - monoparental: ' . ($calculadora->getMonoparental() ? 'TRUE' : 'FALSE'));
            error_log('  - tipo: ' . $calculadora->getTipo());
            error_log('  - plazoAmortizacion: ' . $calculadora->getPlazoAmortizacion());
            error_log('  - aportacionInicial: ' . $calculadora->getAportacionInicial());
            error_log('  - edad: ' . $edad);
            error_log('CAMBIO DE CASA campos:');
            error_log('  - valorViviendaActual: ' . ($calculadora->getValorViviendaActual() ?? 'NULL'));
            error_log('  - hipotecaActual: ' . ($calculadora->getHipotecaActual() ?? 'NULL'));
            error_log('  - aportacionTrasVenta: ' . ($calculadora->getAportacionTrasVenta() ?? 'NULL'));
            error_log('OTROS campos:');
            error_log('  - honorariosInmobiliaria: ' . ($calculadora->getHonorariosInmobiliaria() ?? 'NULL'));
            error_log('=== END POST-SUBMIT STATE ===');

            $resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());
			
            // ===== CALCULAR IMPORTE_PRESTAMO SI NO VIENE =====
			// Si tipo 1 (calcular cuota): importe_prestamo = valor_inmueble - aportacion
			// Si tipo 2 (calcular máximo): importe_prestamo = importe_maximo - aportacion
			$aportacion = floatval($datos['aportacion'] ?? 0);
			if (empty($resultado['importe_prestamo'])) {
                if ($tipoCalculo == 1) {
                    // Tipo 1: Se conoce el valor de la vivienda, calcular lo que hay que prestar
                    $gastos = floatval($resultado['gastos'] ?? 0);
                    $resultado['importe_prestamo'] = ($valorInmueble + $gastos) - $aportacion;
				} elseif ($tipoCalculo == 2 && !empty($resultado['importe_maximo'])) {
					// Tipo 2: Se calcula el máximo, ahora calcular lo que hay que prestar
					$resultado['importe_prestamo'] = floatval($resultado['importe_maximo']) - $aportacion;
				}
			}
			
			// ===== CALCULAR PORCENTAJE_FINANCIACION SI NO VIENE DE CalculadoraAvanzada =====
            if (empty($resultado['porcentaje_financiacion']) && !empty($resultado['importe_prestamo']) && !empty($valorInmueble)) {
                // Caso estándar: porcentaje = importe_prestamo / valor_inmueble
                $porc = ($resultado['importe_prestamo'] / $valorInmueble) * 100;

                // Si es "Cambio de casa" (producto 4) el cálculo debe tener en cuenta
                // la vivienda que se vende y la hipoteca pendiente sobre ella.
                // En frontend usamos: base = valor_inmueble + valor_vivienda_actual
                // importe_financiado = valor_inmueble + gastos - aportacion
                // porcentaje = importe_financiado / base
                try {
                    $producto = $calculadora->getProducto();
                } catch (\Throwable $e) {
                    $producto = null;
                }

                if ($producto == 4) {
                    $valorViviendaActual = floatval($datos['valor_vivienda_actual'] ?? 0);
                    $hipotecaActual = floatval($datos['hipoteca_actual'] ?? 0);
                    $gastos = floatval($resultado['gastos'] ?? 0);
                    $aport = floatval($datos['aportacion'] ?? $aportacion ?? 0);

                    // Base de financiación: sumar ambas viviendas cuando exista valor de la vivienda actual
                    $baseFinanciacion = ($valorViviendaActual > 0) ? ($valorInmueble + $valorViviendaActual) : $valorInmueble;

                    // Importe financiado que absorbe la financiación: precio + gastos - aportación
                    $importeFinanciado = ($valorInmueble + $gastos) - $aport;

                    if ($baseFinanciacion > 0) {
                        $porc = ($importeFinanciado / $baseFinanciacion) * 100;
                    } else {
                        $porc = 0;
                    }
                }

                $resultado['porcentaje_financiacion'] = $porc;
            }
			
			// ===== DEBUG: Log de datos y resultado SIN RECÁLCULOS =====
			error_log('=== SIMULADOR AJAX DEBUG ===');
			error_log('Entrada: tipo_calculo=' . $tipoCalculo . ', edad=' . $edad . ', ingresos=' . $ingresosMensuales . ', plazo=' . intval($datos['plazo'] ?? 25));
			error_log('Entrada gastos_inmobiliaria: ' . floatval($datos['gastos_inmobiliaria'] ?? $datos['honorarios'] ?? 0));
			error_log('Salida directa de calcularAvanzada: ' . json_encode($resultado));
			error_log('=== FIN DEBUG ===');
			
			// ===== RETORNAR RESPUESTA (formato completo para JavaScript) =====
			// Corregir importe_fijo para reflejar el importe del préstamo (tipo 1) o máximo (tipo 2)
			$importeFijo = $resultado['importe_fijo'] ?? 0;
			if ($tipoCalculo == 1 && !empty($resultado['importe_prestamo'])) {
				// Tipo 1: calcular cuota para esta propiedad → importe_fijo = importe del préstamo solicitado
				$importeFijo = round($resultado['importe_prestamo'], 2);
			} elseif ($tipoCalculo == 2 && !empty($resultado['importe_maximo'])) {
				// Tipo 2: calcular máximo precio → importe_fijo = precio máximo
				$importeFijo = round($resultado['importe_maximo'], 2);
			}
			
			$responseData = [
				'error' => false,
				'importe_fijo' => $importeFijo,
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
				'vinculaciones' => round($resultado['vinculaciones'] ?? 0, 2),
				// Campos adicionales para calculadora avanzada (Tipo 1: Cambio de casa)
				'cuota_fija' => round($resultado['cuota_fija'] ?? 0, 2),
				'cuota_variable' => round($resultado['cuota_variable'] ?? 0, 2),
				'cuota_mixta' => round($resultado['cuota_mixta'] ?? 0, 2),
				'cuota_fija_final' => round($resultado['cuota_fija_final'] ?? 0, 2),
				'cuota_variable_final' => round($resultado['cuota_variable_final'] ?? 0, 2),
				'cuota_mixta_final' => round($resultado['cuota_mixta_final'] ?? 0, 2),
				'tipo_fijo' => round(($resultado['tipo_fijo'] ?? 0), 4),
				'tipo_variable' => round(($resultado['tipo_variable'] ?? 0), 4),
				'tipo_mixto' => round(($resultado['tipo_mixto'] ?? 0), 4),
				'tipo_luego_mixto' => round(($resultado['tipo_luego_mixto'] ?? 0), 4),
				'intereses' => round($resultado['intereses'] ?? 0, 2),
				'importe_total' => round($resultado['importe_total'] ?? 0, 2),
				'importe_variable' => round($resultado['importe_variable'] ?? 0, 2),
				'con_interes_fijo' => (bool) ($resultado['con_interes_fijo'] ?? false),
				'con_interes_variable' => (bool) ($resultado['con_interes_variable'] ?? false),
				'con_entrada_fijo' => round($resultado['con_entrada_fijo'] ?? 0, 2),
				'con_entrada_variable' => round($resultado['con_entrada_variable'] ?? 0, 2),
				'valor_inmueble' => round($valorInmueble ?? 0, 2),
				'valor_vivienda_actual' => round($datos['valor_vivienda_actual'] ?? 0, 2),
				'hipoteca_actual' => round($datos['hipoteca_actual'] ?? 0, 2),
				'aportacion_tras_venta' => round($datos['aportacion_tras_venta'] ?? 0, 2),
				'escritura_compra_impuesto_transmisiones' => round($resultado['escritura_compra_impuesto_transmisiones'] ?? 0, 2),
				'gastos_inmobiliaria' => round($resultado['gastos_inmobiliaria'] ?? $resultado['honorarios_inmobiliaria'] ?? $datos['gastos_inmobiliaria'] ?? $datos['honorarios'] ?? 0, 2),
                'producto' => $producto ?? ''
			];
			
			return new JsonResponse($responseData, 200);
	}
}
