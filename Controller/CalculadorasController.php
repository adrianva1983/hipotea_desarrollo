<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AgenteColaborador as AgenteColaboradorEntidad;
use AppBundle\Entity\CampoHito as CampoHitoEntidad;
use AppBundle\Entity\CampoHitoExpediente as CampoHitoExpedienteEntidad;
use AppBundle\Entity\CampoHitoExpedienteColaboradores as CampoHitoExpedienteColaboradoresEntidad;
use AppBundle\Entity\ClienteFactura as ClienteFacturaEntidad;
use AppBundle\Entity\Dispositivo as DispositivoEntidad;
use AppBundle\Entity\Documento as DocumentoEntidad;
use AppBundle\Entity\EmisorFactura as EmisorFacturaEntidad;
use AppBundle\Entity\EntidadColaboradora as EntidadColaboradoraEntidad;
use AppBundle\Entity\Expediente as ExpedienteEntidad;
use AppBundle\Entity\ExpedienteEmail as ExpedienteEmailEntidad;
use AppBundle\Entity\Factura as FacturaEntidad;
use AppBundle\Entity\Fase as FaseEntidad;
use AppBundle\Entity\FicheroCampo as FicheroCampoEntidad;
use AppBundle\Entity\ImagenFichero as ImagenFicheroEntidad;
use AppBundle\Entity\GrupoCamposHito as GrupoCamposHitoEntidad;
use AppBundle\Entity\GrupoHitoExpediente as GrupoHitoExpedienteEntidad;
use AppBundle\Entity\Hito as HitoEntidad;
use AppBundle\Entity\HitoExpediente as HitoExpedienteEntidad;
use AppBundle\Entity\Inmobiliaria as InmobiliariaEntidad;
use AppBundle\Entity\LineaFactura as LineaFacturaEntidad;
use AppBundle\Entity\Log as LogEntidad;
use AppBundle\Entity\Noticia as NoticiaEntidad;
use AppBundle\Entity\NoticiaUsuario as NoticiaUsuarioEntidad;
use AppBundle\Entity\Notificacion as NotificacionEntidad;
use AppBundle\Entity\OpcionesCampo as OpcionesCampoEntidad;
use AppBundle\Entity\RegistrarActividad;
use AppBundle\Entity\SeguimientoHorario as SeguimientoHorarioEntidad;
use AppBundle\Entity\Usuario as UsuarioEntidad;
use AppBundle\Form\AgenteColaborador as AgenteColaboradorFormulario;
use AppBundle\Form\CampoHito as CampoHitoFormulario;
use AppBundle\Form\CampoHitoExpedienteBanco as CampoHitoExpedienteBancoFormulario;
use AppBundle\Form\CampoHitoExpedienteColaboradores as CampoHitoExpedienteColaboradoresFormulario;
use AppBundle\Form\CampoHitoExpedienteDesplegable as CampoHitoExpedienteDesplegableFormulario;
use AppBundle\Form\CampoHitoExpedienteEmail as CampoHitoExpedienteEmailFormulario;
use AppBundle\Form\CampoHitoExpedienteFecha as CampoHitoExpedienteFechaFormulario;
use AppBundle\Form\CampoHitoExpedienteFichero as CampoHitoExpedienteFicheroFormulario;
use AppBundle\Form\CampoHitoExpedienteFicheroCliente as CampoHitoExpedienteFicheroClienteFormulario;
use AppBundle\Form\CampoHitoExpedienteNotaria as CampoHitoExpedienteNotariaFormulario;
use AppBundle\Form\CampoHitoExpedienteTasadora as CampoHitoExpedienteTasadoraFormulario;
use AppBundle\Form\CampoHitoExpedienteTexto as CampoHitoExpedienteTextoFormulario;
use AppBundle\Form\CampoHitoModificar as CampoHitoModificarFormulario;
use AppBundle\Form\ClienteFactura as ClienteFacturaFormulario;
use AppBundle\Form\CompletarDatosCliente;
use AppBundle\Form\CrearCliente;
use AppBundle\Form\Documento as DocumentoFormulario;
use AppBundle\Form\EmisorFactura as EmisorFacturaFormulario;
use AppBundle\Form\EntidadColaboradora as EntidadColaboradoraFormulario;
use AppBundle\Form\Estadisticas;
use AppBundle\Form\Expediente as ExpedienteFormulario;
use AppBundle\Form\ExpedienteEmail as ExpedienteEmailFormulario;
use AppBundle\Form\ExpedienteEmailCheckboxes;
use AppBundle\Form\Factura as FacturaFormulario;
use AppBundle\Form\Fase as FaseFormulario;
use AppBundle\Form\FaseModificar as FaseModificarFormulario;
use AppBundle\Form\FicheroCampo as FicheroCampoFormulario;
use AppBundle\Form\GrupoCamposHito as GrupoCamposHitoFormulario;
use AppBundle\Form\GrupoCamposHitoModificar as GrupoCamposHitoModificarFormulario;
use AppBundle\Form\Hito as HitoFormulario;
use AppBundle\Form\HitoExpediente as HitoExpedienteFormulario;
use AppBundle\Form\HitoModificar as HitoModificarFormulario;
use AppBundle\Form\Inmobiliaria as InmobiliariaFormulario;
use AppBundle\Form\LineaFactura as LineaFacturaFormulario;
use AppBundle\Form\Noticia as NoticiaFormulario;
use AppBundle\Form\NoticiaUsuario as NoticiaUsuarioFormulario;
use AppBundle\Form\Notificacion as NotificacionFormulario;
use AppBundle\Form\CancelarExpediente;
use AppBundle\Form\NotificacionExpediente;
use AppBundle\Form\OpcionesCampo as OpcionesCampoFormulario;
use AppBundle\Form\OpcionesCampoModificar as OpcionesCampoModificarFormulario;
use AppBundle\Form\RecuperarUsuario as RecuperarUsuarioFormulario;
use AppBundle\Form\RegistrarUsuario as RegistrarUsuarioFormulario;
use AppBundle\Form\SeguimientoHorario as SeguimientoHorarioFormulario;
use AppBundle\Form\Usuario as UsuarioFormulario;
use AppBundle\Form\UsuarioCliente as UsuarioClienteFormulario;
use AppBundle\Form\UsuarioClienteModificar as UsuarioClienteModificarFormulario;
use AppBundle\Form\UsuarioModificar as UsuarioModificarFormulario;
use AppBundle\Form\UsuarioInmobiliaria as UsuarioInmobiliariaFormulario;
use AppBundle\Form\UsuarioInmobiliariaModificar as UsuarioInmobiliariaModificarFormulario;
use AppBundle\Form\SeguimientoExpediente as FormularioSeguimientoExpediente;
use AppBundle\Entity\SeguimientoExpediente as SeguimientoExpedienteEntidad;
use AppBundle\Entity\ConceptoSeguimientoExpediente as ConceptoSeguimientoExpedienteEntidad;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Psr\Log\LoggerInterface;

use AppBundle\Utils\UsuariosNombreCompleto;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Exception;
use ReflectionClass;
use ReflectionException;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PDFMerger;

class CalculadorasController extends Controller
{
	private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
	}

    public function calculadoraSencillaAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraSencilla');
		$formulario->handleRequest($request);
		$variablesTwig = array(
			'titulo' => 'Calculadora Sencilla',
			'calculadora_sencilla' => $formulario->createView(),
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$formulario->getData()->calcularHipoteca();
			$variablesTwig['hipoteca'] = $formulario->getData()->getHipoteca()['capital_less_initial_amount'];
			$variablesTwig['pago_mensual'] = $formulario->getData()->getHipoteca()['fee'];
			$variablesTwig['hipoteca_total_con_interes'] = $formulario->getData()->getHipoteca()['capital_less_initial_amount'] + $formulario->getData()->getHipoteca()['interest_discharged_total'];
			$variablesTwig['total_con_anticipo'] = $formulario->getData()->getPrecioTotal() + $formulario->getData()->getHipoteca()['interest_discharged_total'];
			$variablesTwig['resultado'] = null;
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraSencilla.html.twig', $variablesTwig);
	}

	public function calculadoraAvanzadaAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzada');
		$formulario->handleRequest($request);
		// dump($request);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
			'iva_label' => 'IVA',
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			// $respuesta['importe_fijo'] = $resultado_f['importe'];
			// $respuesta['importe_variable'] = $resultado_v['importe'];
			// $respuesta['entrada'] = $aportacion_inicial;
			// $respuesta['gastos'] = $gastos;
			// $respuesta['interes_fijo'] = $interes_fijo_l;
			// $respuesta['interes_variable'] = $interes_variable_l;
			// $respuesta['tipo_calculo'] = $tipo_calculo;
			// $respuesta['cuota_fija'] = $resultado['cuota'];
			// $respuesta['cuota_variable'] = $resultado['cuota'];
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				// $variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
					// $variablesTwig['tipo_calculo'] = $formulario->getData()->getTipo();
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					// $variablesTwig['importe_variable'] = $resultado['importe_variable'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					// $variablesTwig['interes_fijo'] = $resultado['interes_fijo'];
					// $variablesTwig['interes_variable'] = $resultado['interes_variable'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					// $variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
					// $variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			// $variablesTwig['hipoteca_total_con_interes'] = $resultado['capital_less_initial_amount'] + $resultado['interest_discharged_total'];
			// $variablesTwig['total_con_anticipo'] = $formulario->getData()->getPrecioTotal() + $resultado['interest_discharged_total'];
			$variablesTwig['resultado'] = null;
				// Ajustar etiqueta IVA/IGIC según CCAA y obra nueva
				try {
					$ccaa = $formulario->getData()->getComunidadAutonoma();
					$nueva = $formulario->getData()->getObraNueva();
					$variablesTwig['iva_label'] = ($nueva && $ccaa == '5') ? 'IGIC' : 'IVA';
				} catch (\Exception $e) {
					$variablesTwig['iva_label'] = 'IVA';
				}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraAvanzada.html.twig', $variablesTwig);
	}

	public function calculadoraAvanzadaTestAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest');
		$formulario->handleRequest($request);
		// dump($request);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
			'iva_label' => 'IVA',
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			// $respuesta['importe_fijo'] = $resultado_f['importe'];
			// $respuesta['importe_variable'] = $resultado_v['importe'];
			// $respuesta['entrada'] = $aportacion_inicial;
			// $respuesta['gastos'] = $gastos;
			// $respuesta['interes_fijo'] = $interes_fijo_l;
			// $respuesta['interes_variable'] = $interes_variable_l;
			// $respuesta['tipo_calculo'] = $tipo_calculo;
			// $respuesta['cuota_fija'] = $resultado['cuota'];
			// $respuesta['cuota_variable'] = $resultado['cuota'];
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				// $variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
                if (array_key_exists('hipoteca_actual',$resultado)) {
				    $variablesTwig['hipoteca_actual'] = $resultado['hipoteca_actual'];
                }
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					// $variablesTwig['tipo_calculo'] = $formulario->getData()->getTipo();
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					// $variablesTwig['importe_variable'] = $resultado['importe_variable'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					// $variablesTwig['interes_fijo'] = $resultado['interes_fijo'];
					// $variablesTwig['interes_variable'] = $resultado['interes_variable'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					// $variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
					// $variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;					
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			// $variablesTwig['hipoteca_total_con_interes'] = $resultado['capital_less_initial_amount'] + $resultado['interest_discharged_total'];
			// $variablesTwig['total_con_anticipo'] = $formulario->getData()->getPrecioTotal() + $resultado['interest_discharged_total'];
			$variablesTwig['resultado'] = null;
				// Ajustar etiqueta IVA/IGIC según CCAA y obra nueva
				try {
					$ccaa = $formulario->getData()->getComunidadAutonoma();
					$nueva = $formulario->getData()->getObraNueva();
					$variablesTwig['iva_label'] = ($nueva && $ccaa == '5') ? 'IGIC' : 'IVA';
				} catch (\Exception $e) {
					$variablesTwig['iva_label'] = 'IVA';
				}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraAvanzadaTest.html.twig', $variablesTwig);
	}

	public function calculadoraAvanzadaIAAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaIA');
		$formulario->handleRequest($request);
		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada IA',
			'calculadora_avanzada' => $formulario->createView(),
			'iva_label' => 'IVA',
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzadaIA($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'] ?? 0;
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'] ?? 0;
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'] ?? 0;
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'] ?? 0;
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'] ?? 0;
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'] ?? 0;
				$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'] ?? 0;
				$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'] ?? 0;
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $resultado['hipoteca_actual'] ?? $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'] ?? 0;
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'] ?? 0;
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'] ?? 0;
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'] ?? 0;
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'] ?? 0;
				$variablesTwig['intereses'] = $resultado['intereses'] ?? 0;
				$variablesTwig['importe_total'] = $resultado['importe_total'] ?? 0;
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'] ?? 0;
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'] ?? 0;
				$variablesTwig['notario'] = $resultado['notario'] ?? 0;
				$variablesTwig['registro'] = $resultado['registro'] ?? 0;
				$variablesTwig['gestoria'] = $resultado['gestoria'] ?? 0;
				$variablesTwig['obraNueva'] = $resultado['obraNueva'] ?? 0;
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'] ?? 0;
				$variablesTwig['importe_iva'] = $resultado['importe_iva'] ?? 0;
				$variablesTwig['tipo_interes_ccaa'] = ($resultado['tipo_interes_ccaa'] ?? 0) * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'] ?? 0;
					$variablesTwig['cuota'] = $resultado['cuota'] ?? 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'] ?? 0;
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'] ?? 0;
					$variablesTwig['notario'] = $resultado['notario'] ?? 0;
					$variablesTwig['registro'] = $resultado['registro'] ?? 0;
					$variablesTwig['gestoria'] = $resultado['gestoria'] ?? 0;
					$variablesTwig['tasacion'] = $resultado['tasacion'] ?? 0;
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'] ?? 0;
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'] ?? 0;
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + ($resultado['gastos'] ?? 0) - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = ($resultado['tipo_interes_ccaa'] ?? 0) * 100;					
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = null;
				try {
					$ccaa = $formulario->getData()->getComunidadAutonoma();
					$nueva = $formulario->getData()->getObraNueva();
					$variablesTwig['iva_label'] = ($nueva && $ccaa == '5') ? 'IGIC' : 'IVA';
				} catch (\Exception $e) {
					$variablesTwig['iva_label'] = 'IVA';
				}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraAvanzadaIA.html.twig', $variablesTwig);
	}

	public function calculadoraAvanzadaWebAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest');
		$formulario->handleRequest($request);
		// dump($formulario);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
			'iva_label' => 'IVA',
		);
		// if ($formulario->isSubmitted() && $formulario->isValid()) {
		if ($formulario->isSubmitted()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = null;
			// Ajustar etiqueta IVA/IGIC según CCAA y obra nueva (como en calculadoraAvanzadaAction)
			try {
				$ccaa = $formulario->getData()->getComunidadAutonoma();
				$nueva = $formulario->getData()->getObraNueva();
				$variablesTwig['iva_label'] = ($nueva && $ccaa == '5') ? 'IGIC' : 'IVA';
			} catch (\Exception $e) {
				$variablesTwig['iva_label'] = 'IVA';
			}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraAvanzadaWeb.html.twig', $variablesTwig);
	}

	public function calculadoraAvanzadaSubmitAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest');
		$formulario->handleRequest($request);
		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
		);
		// if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = null;
		// }
		return $this->render('@App/Backoffice/Extras/CalculadoraAvanzadaWeb.html.twig', $variablesTwig);
	}

	public function calculadoraComparativaAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraComparativa');
		$formulario->handleRequest($request);
		// dump($request);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Comparativa',
			'calculadora_comparativa' => $formulario->createView(),
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularComparativa($this->getDoctrine()->getManager());
			$destino = $formulario->getData()->getDestino();
			$tipoHipoteca = $formulario->getData()->getTipoHipoteca();
			$variablesTwig['tipoOferta'] = $resultado['tipoOferta'];
			$tipoOferta = $resultado['tipoOferta'];
			$variablesTwig['nombreOferta'] = $resultado['nombreOferta'];
			$variablesTwig['destino'] = $resultado['destino'];
			$variablesTwig['tipo'] = $resultado['tipo'];
			$variablesTwig['plazoAmortizacion'] = $resultado['plazoAmortizacion'];
			$variablesTwig['margen'] = $resultado['margen'];

			if ($destino == 1) { //Comprar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Variable'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			} else { // Mejorar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Variable'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			}
			$variablesTwig['resultado'] = null;
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraComparativa.html.twig', $variablesTwig);
	}

	public function calculadoraComparativaWebAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraComparativa');
		$formulario->handleRequest($request);
		// dump($request);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Comparativa',
			'calculadora_comparativa' => $formulario->createView(),
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularComparativa($this->getDoctrine()->getManager());
			$destino = $formulario->getData()->getDestino();
			$tipoHipoteca = $formulario->getData()->getTipoHipoteca();
			$variablesTwig['tipoOferta'] = $resultado['tipoOferta'];
			$variablesTwig['nombreOferta'] = $resultado['nombreOferta'];
			$tipoOferta = $resultado['tipoOferta'];
			$variablesTwig['destino'] = $resultado['destino'];
			$variablesTwig['tipo'] = $resultado['tipo'];
			$variablesTwig['plazoAmortizacion'] = $resultado['plazoAmortizacion'];
			$variablesTwig['margen'] = $resultado['margen'];

			if ($destino == 1) { //Comprar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			} else { // Mejorar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			}
			$variablesTwig['resultado'] = null;
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraComparativaWeb.html.twig', $variablesTwig);
	}

	public function calculadoraComparativaSubmitAction(Request $request)
	{
		$formulario = $this->createForm('AppBundle\Form\CalculadoraComparativa');
		$formulario->handleRequest($request);
		// dump($formulario);
		// die();
		$variablesTwig = array(
			'titulo' => 'Calculadora Comparativa',
			'calculadora_comparativa' => $formulario->createView(),
		);
		// if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularComparativa($this->getDoctrine()->getManager());
			$destino = $formulario->getData()->getDestino();
			$tipoHipoteca = $formulario->getData()->getTipoHipoteca();
			$variablesTwig['tipoOferta'] = $resultado['tipoOferta'];
			$variablesTwig['nombreOferta'] = $resultado['nombreOferta'];
			$tipoOferta = $resultado['tipoOferta'];
			$variablesTwig['destino'] = $resultado['destino'];
			$variablesTwig['tipo'] = $resultado['tipo'];
			$variablesTwig['plazoAmortizacion'] = $resultado['plazoAmortizacion'];
			$variablesTwig['margen'] = $resultado['margen'];

			if ($destino == 1) { //Comprar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			} else { // Mejorar
				if($tipoOferta == 'Fijo'){ // Compara con una de tipo fijo
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];
						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}elseif ($tipoOferta == 'Mixto'){
					if($tipoHipoteca == 1) { // Fijo
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];

						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
					}elseif($tipoHipoteca == 2) { // Mixta
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}elseif($tipoHipoteca == 3) { // Variable
						$variablesTwig['cuota_mensual_original'] = $resultado['cuota_mensual_original'];
						$variablesTwig['cuota_mensual_oferta'] = $resultado['cuota_mensual_oferta'];
						$variablesTwig['cuota_mensual_ahorro'] = $resultado['cuota_mensual_ahorro'];

						$variablesTwig['cuota_mensual_original_variable'] = $resultado['cuota_mensual_original_variable'];
						$variablesTwig['cuota_mensual_oferta_variable'] = $resultado['cuota_mensual_oferta_variable'];
						$variablesTwig['cuota_mensual_ahorro_variable'] = $resultado['cuota_mensual_ahorro_variable'];

						$variablesTwig['intereses_original'] = $resultado['intereses_original'];
						$variablesTwig['intereses_oferta'] = $resultado['intereses_oferta'];
						$variablesTwig['intereses_ahorro'] = $resultado['intereses_ahorro'];
						
						$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
						$variablesTwig['primerosAnios'] = $resultado['primerosAnios'];
					}
				}
			}
			$variablesTwig['resultado'] = null;
		// }
		return $this->render('@App/Backoffice/Extras/CalculadoraComparativaWeb.html.twig', $variablesTwig);
	}

    public function calculadoraCuotaWebAction(Request $request, Swift_Mailer $mailer)
	{
		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

        $calculadora->setTipo(1); // <- Aquí­ defines el valor por defecto

        $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
        $formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
			'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
			'iva_label' => 'IVA',
		);

		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
                if (array_key_exists('hipoteca_actual',$resultado)) {
				    $variablesTwig['hipoteca_actual'] = $resultado['hipoteca_actual'];
                }
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;

				$variablesTwig['edad'] = $formulario->getData()->getEdad();
				$variablesTwig['valorInmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
				$variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
				$variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
				$variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
				$variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
				$variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
				$variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
				$variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
				$variablesTwig['honorariosInmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['producto'] = $formulario->getData()->getTextProducto();

			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
		$variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
		$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();

		// Ajustar etiqueta IVA/IGIC según CCAA y obra nueva para el PDF/email
		try {
			$ccaa = $formulario->getData()->getComunidadAutonoma();
			$nueva = $formulario->getData()->getObraNueva();
			$variablesTwig['iva_label'] = ($nueva && $ccaa == '5') ? 'IGIC' : 'IVA';
		} catch (\Exception $e) {
			$variablesTwig['iva_label'] = 'IVA';
		}
	
		// VERIFICAR LÍMITE DE USOS ANTES DE ENVIAR EMAILS
		$limitAlcanzado = $this->registrarUsoCalculadora($request, $formularioEnviarCalculadora->getData()->getEmail(), 'calculadora_cuota');
		if ($limitAlcanzado) {
			$variablesTwig['error_limit_reached'] = 'Ha alcanzado el límite de 3 usos para esta calculadora.';
		} else {
			// SOLO ENVIAR EMAILS SI NO HA ALCANZADO EL LÍMITE

            $from = array($this->getParameter('mailer_user') => 'Hipotea');
			$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
				->setFrom($from)
				->setTo($formularioEnviarCalculadora->getData()->getEmail())
				// ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');

            // PROBANDO CON PDF ADJUNTO
            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
            $this->get('knp_snappy.pdf')->generateFromHtml(
                $this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig',$variablesTwig),
                // $contenido,
                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
                [],
                true
            );
            
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            // FIN PROBANDO CON PDF ADJUNTO

            $mailer->send($mensaje);
            // Ahora para Hipotea
            $mensaje = (new Swift_Message('Consulta calculadora cuota'))
				->setFrom($from)
				->setTo('info@hipotea.com')
				//->setTo('adrian.verdecia@semillaproyectos.com')
				// ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');

            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            $mailer->send($mensaje);
			}
		}
		$variablesTwig['whatsappContacto'] = $this->getParameter('simulador_whatsapp_contacto');
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

    public function calculadoraPrecioMaximoWebAction(Request $request, Swift_Mailer $mailer)
	{
		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

        $calculadora->setTipo(2); // <- Aquí defines el valor por defecto

        $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
        $formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

        $variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
            'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
            'tipo_calculo' => 'importe_maximo'
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;

                    $variablesTwig['numTitulares'] = $formulario->getData()->getNumTitulares();
                    $variablesTwig['edadTitularUno'] = $formulario->getData()->getEdadTitularUno();
                    $variablesTwig['edadTitularDos'] = $formulario->getData()->getEdadTitularDos();
                    $variablesTwig['plazoAmortizacion'] = $formulario->getData()->getPlazoAmortizacion();
                    $variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
                    $variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
                    $variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
                    $variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
                    $variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
                    $variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
                    $variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
                    $variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
                    $variablesTwig['ingresosMensuales'] = $formulario->getData()->getIngresosMensuales();
                    $variablesTwig['numPagasExtra'] = $formulario->getData()->getNumPagasExtra();
                    $variablesTwig['importePagaExtra'] = $formulario->getData()->getImportePagaExtra();
                    $variablesTwig['prestamosMensuales'] = $formulario->getData()->getPrestamosMensuales();
                    $variablesTwig['ingresosMensualesDos'] = $formulario->getData()->getIngresosMensualesDos();
                    $variablesTwig['numPagasExtraDos'] = $formulario->getData()->getNumPagasExtraDos();
                    $variablesTwig['importePagaExtraDos'] = $formulario->getData()->getImportePagaExtraDos();
                    $variablesTwig['prestamosMensualesDos'] = $formulario->getData()->getPrestamosMensualesDos();
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
			$variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();

			// VERIFICAR LÍMITE DE USOS ANTES DE ENVIAR EMAILS
			$limitAlcanzado = $this->registrarUsoCalculadora($request, $formularioEnviarCalculadora->getData()->getEmail(), 'calculadora_precio_maximo');
			if ($limitAlcanzado) {
				$variablesTwig['error_limit_reached'] = 'Ha alcanzado el límite de 3 usos para esta calculadora.';
			} else {
				// SOLO ENVIAR EMAILS SI NO HA ALCANZADO EL LÍMITE

				$from = array($this->getParameter('mailer_user') => 'Hipotea');
				$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
					->setFrom($from)
					->setTo($formularioEnviarCalculadora->getData()->getEmail())
	                // ->setTo('fernando.lopez@weeduu.es')
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
	            
	            // PROBANDO CON PDF ADJUNTO
	            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
	            $this->get('knp_snappy.pdf')->generateFromHtml(
	                $this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig',$variablesTwig),
	                // $contenido,
	                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
	                [],
	                true
	            );
	            
	            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
	            // FIN PROBANDO CON PDF ADJUNTO

	            $mailer->send($mensaje);

	            // Ahora para Hipotea
	            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
				$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
	            $mensaje = (new Swift_Message('Consulta calculadora precio máximo'))
					->setFrom($from)
					->setTo('info@hipotea.com')
					//->setTo('adrian.verdecia@semillaproyectos.com')
	                // ->setTo('fernando.lopez@weeduu.es')
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
	            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
	            $mailer->send($mensaje);
			}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

    public function calculadoraCambioDeCasaWebAction(Request $request, Swift_Mailer $mailer)
	{
		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

        $calculadora->setTipo(1); // <- Aquí defines el valor por defecto
        $calculadora->setProducto(4); // <- Aquí defines el valor por defecto

        $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
        $formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

        $variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
            'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
            'cambioDeCasa' => true
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;

				$variablesTwig['edad'] = $formulario->getData()->getEdad();
				$variablesTwig['valorInmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
				$variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
				$variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
				$variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
				$variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
				$variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
				$variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
				$variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
				$variablesTwig['honorariosInmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['producto'] = $formulario->getData()->getTextProducto();

				$variablesTwig['valorViviendaActual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipotecaActual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacionTrasVenta'] = $formulario->getData()->getAportacionTrasVenta();


                

			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
			$variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
			
			// VERIFICAR LÍMITE DE USOS ANTES DE ENVIAR EMAILS
			$limitAlcanzado = $this->registrarUsoCalculadora($request, $formularioEnviarCalculadora->getData()->getEmail(), 'calculadora_cambio_casa');
			if ($limitAlcanzado) {
				$variablesTwig['error_limit_reached'] = 'Ha alcanzado el límite de 3 usos para esta calculadora.';
			} else {
				// SOLO ENVIAR EMAILS SI NO HA ALCANZADO EL LÍMITE

				$from = array($this->getParameter('mailer_user') => 'Hipotea');
				$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
					->setFrom($from)
					->setTo($formularioEnviarCalculadora->getData()->getEmail())
	                // ->setTo('fernando.lopez@weeduu.es')
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
	            // PROBANDO CON PDF ADJUNTO
	            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
	            $this->get('knp_snappy.pdf')->generateFromHtml(
	                $this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig',$variablesTwig),
	                // $contenido,
	                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
	                [],
	                true
	            );
	            
	            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
	            // FIN PROBANDO CON PDF ADJUNTO
	            $mailer->send($mensaje);

	            // Ahora para Hipotea
	            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
				$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
	            $mensaje = (new Swift_Message('Consulta calculadora cuota'))
					->setFrom($from)
					->setTo('info@hipotea.com')
					//->setTo('adrian.verdecia@semillaproyectos.com')
	                // ->setTo('fernando.lopez@weeduu.es')
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
	            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
	            $mailer->send($mensaje);
			}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

    public function calculadoraPrecioMaximoWebNMAction(Request $request, Swift_Mailer $mailer)
	{
		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

        $calculadora->setTipo(2); // <- Aquí­ defines el valor por defecto

        $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
        $formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

        $variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
            'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
            'tipo_calculo' => 'importe_maximo'
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;

                    $variablesTwig['numTitulares'] = $formulario->getData()->getNumTitulares();
                    $variablesTwig['edadTitularUno'] = $formulario->getData()->getEdadTitularUno();
                    $variablesTwig['edadTitularDos'] = $formulario->getData()->getEdadTitularDos();
                    $variablesTwig['plazoAmortizacion'] = $formulario->getData()->getPlazoAmortizacion();
                    $variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
                    $variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
                    $variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
                    $variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
                    $variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
                    $variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
                    $variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
                    $variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
                    $variablesTwig['ingresosMensuales'] = $formulario->getData()->getIngresosMensuales();
                    $variablesTwig['numPagasExtra'] = $formulario->getData()->getNumPagasExtra();
                    $variablesTwig['importePagaExtra'] = $formulario->getData()->getImportePagaExtra();
                    $variablesTwig['prestamosMensuales'] = $formulario->getData()->getPrestamosMensuales();
                    $variablesTwig['ingresosMensualesDos'] = $formulario->getData()->getIngresosMensualesDos();
                    $variablesTwig['numPagasExtraDos'] = $formulario->getData()->getNumPagasExtraDos();
                    $variablesTwig['importePagaExtraDos'] = $formulario->getData()->getImportePagaExtraDos();
                    $variablesTwig['prestamosMensualesDos'] = $formulario->getData()->getPrestamosMensualesDos();
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
			// $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();

            $from = array($this->getParameter('mailer_user') => 'Hipotea');
			$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
				->setFrom($from)
				->setTo($formularioEnviarCalculadora->getData()->getEmail())
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            // PROBANDO CON PDF ADJUNTO
            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
            $this->get('knp_snappy.pdf')->generateFromHtml(
                $this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig',$variablesTwig),
                // $contenido,
                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
                [],
                true
            );
            
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            // FIN PROBANDO CON PDF ADJUNTO
            $mailer->send($mensaje);

            // Ahora para Hipotea
            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
            $mensaje = (new Swift_Message('NUEVO MILENIO: Consulta calculadora precio máximo'))
				->setFrom($from)
				->setTo('info@hipotea.com')
				//->setTo('adrian.verdecia@semillaproyectos.com')
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            $mailer->send($mensaje);

            // Ahora para Nuevo Milenio
            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
            $mensaje = (new Swift_Message('Consulta calculadora precio máximo'))
				->setFrom($from)
				->setTo('direccion@nuevomilenio-inmo.com')
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            $mailer->send($mensaje);
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

	public function calculadoraPrecioMaximoWebIHSAction(Request $request, Swift_Mailer $mailer)
	{
		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

        $calculadora->setTipo(2); // <- Aquí defines el valor por defecto

        $formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
        $formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

        $variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
            'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
            'tipo_calculo' => 'importe_maximo'
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final',$resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final',$resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final',$resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;

                    $variablesTwig['numTitulares'] = $formulario->getData()->getNumTitulares();
                    $variablesTwig['edadTitularUno'] = $formulario->getData()->getEdadTitularUno();
                    $variablesTwig['edadTitularDos'] = $formulario->getData()->getEdadTitularDos();
                    $variablesTwig['plazoAmortizacion'] = $formulario->getData()->getPlazoAmortizacion();
                    $variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
                    $variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
                    $variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
                    $variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
                    $variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
                    $variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
                    $variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
                    $variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
                    $variablesTwig['ingresosMensuales'] = $formulario->getData()->getIngresosMensuales();
                    $variablesTwig['numPagasExtra'] = $formulario->getData()->getNumPagasExtra();
                    $variablesTwig['importePagaExtra'] = $formulario->getData()->getImportePagaExtra();
                    $variablesTwig['prestamosMensuales'] = $formulario->getData()->getPrestamosMensuales();
                    $variablesTwig['ingresosMensualesDos'] = $formulario->getData()->getIngresosMensualesDos();
                    $variablesTwig['numPagasExtraDos'] = $formulario->getData()->getNumPagasExtraDos();
                    $variablesTwig['importePagaExtraDos'] = $formulario->getData()->getImportePagaExtraDos();
                    $variablesTwig['prestamosMensualesDos'] = $formulario->getData()->getPrestamosMensualesDos();
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
			// $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();

            $from = array($this->getParameter('mailer_user') => 'Hipotea');
			$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
				->setFrom($from)
				->setTo($formularioEnviarCalculadora->getData()->getEmail())
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            // PROBANDO CON PDF ADJUNTO
            $nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
            $this->get('knp_snappy.pdf')->generateFromHtml(
                $this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig',$variablesTwig),
                // $contenido,
                $this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
                [],
                true
            );
            
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            // FIN PROBANDO CON PDF ADJUNTO
            $mailer->send($mensaje);

            // Ahora para Hipotea
            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
            $mensaje = (new Swift_Message('IHS Inmobiliaria: Consulta calculadora precio máximo'))
				->setFrom($from)
				->setTo('info@hipotea.com')
				//->setTo('adrian.verdecia@semillaproyectos.com')
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            $mailer->send($mensaje);

            // Ahora para IHS
            $variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
            $mensaje = (new Swift_Message('Consulta calculadora precio máximo'))
				->setFrom($from)
				->setTo('info@ihs.es')
                // ->setTo('fernando.lopez@weeduu.es')
				->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
            $mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR .'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
            $mailer->send($mensaje);
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

	public function calculadoraPrecioMaximoWebClienteAction(Request $request, Swift_Mailer $mailer)
	{
		// Email de la inmobiliaria cliente pasado por query string (?email=info@ejemplo.es)
		$emailClienteRaw = $request->query->get('email', '');
		$emailCliente = filter_var(trim($emailClienteRaw), FILTER_VALIDATE_EMAIL) ? trim($emailClienteRaw) : '';

		$calculadora = new \AppBundle\Entity\CalculadoraAvanzada();
		$enviarCalculadora = new \AppBundle\Entity\EnvioCalculadora();

		$calculadora->setTipo(2);

		$formulario = $this->createForm('AppBundle\Form\CalculadoraAvanzadaTest', $calculadora);
		$formularioEnviarCalculadora = $this->createForm('AppBundle\Form\EnviarCalculadora', $enviarCalculadora);

		$formulario->handleRequest($request);
		$formularioEnviarCalculadora->handleRequest($request);

		$variablesTwig = array(
			'titulo' => 'Calculadora Avanzada',
			'calculadora_avanzada' => $formulario->createView(),
			'formularioEnviarCalculadora' => $formularioEnviarCalculadora->createView(),
			'tipo_calculo' => 'importe_maximo'
		);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$resultado = $formulario->getData()->calcularAvanzada($doctrine = $this->getDoctrine()->getManager());
			if ($formulario->getData()->getTipo() == 1) {
				$variablesTwig['valor_inmueble'] = $formulario->getData()->getValorInmueble();
				$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
				$variablesTwig['importe_variable'] = $resultado['importe_variable'];
				$variablesTwig['amortizacion'] = $resultado['amortizacion'];
				$variablesTwig['entrada'] = $resultado['entrada'];
				$variablesTwig['interes_fijo'] = $resultado['con_interes_fijo'];
				$variablesTwig['interes_variable'] = $resultado['con_interes_variable'];
				$variablesTwig['con_entrada_fijo'] = $resultado['con_entrada_fijo'];
				$variablesTwig['con_entrada_variable'] = $resultado['con_entrada_variable'];
				$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
				$variablesTwig['cuota_fija'] = $resultado['cuota_fija'];
				$variablesTwig['cuota_variable'] = $resultado['cuota_variable'];
				$variablesTwig['cuota_mixta'] = $resultado['cuota_mixta'];
				$variablesTwig['mensaje'] = $resultado['mensaje'];
				if (array_key_exists('cuota_fija_final', $resultado)) {
					$variablesTwig['cuota_fija_final'] = $resultado['cuota_fija_final'];
				} else {
					$variablesTwig['cuota_fija_final'] = 0;
				}
				if (array_key_exists('cuota_variable_final', $resultado)) {
					$variablesTwig['cuota_variable_final'] = $resultado['cuota_variable_final'];
				} else {
					$variablesTwig['cuota_variable_final'] = 0;
				}
				if (array_key_exists('cuota_mixta_final', $resultado)) {
					$variablesTwig['cuota_mixta_final'] = $resultado['cuota_mixta_final'];
				} else {
					$variablesTwig['cuota_mixta_final'] = 0;
				}
				$variablesTwig['valor_vivienda_actual'] = $formulario->getData()->getValorViviendaActual();
				$variablesTwig['hipoteca_actual'] = $formulario->getData()->getHipotecaActual();
				$variablesTwig['aportacion_tras_venta'] = $formulario->getData()->getAportacionTrasVenta();
				$variablesTwig['gastos'] = $resultado['gastos'];
				$variablesTwig['tipo_fijo'] = $resultado['tipo_fijo'];
				$variablesTwig['tipo_variable'] = $resultado['tipo_variable'];
				$variablesTwig['tipo_mixto'] = $resultado['tipo_mixto'];
				$variablesTwig['tipo_luego_mixto'] = $resultado['tipo_luego_mixto'];
				$variablesTwig['intereses'] = $resultado['intereses'];
				$variablesTwig['importe_total'] = $resultado['importe_total'];
				$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
				$variablesTwig['tasacion'] = $resultado['tasacion'];
				$variablesTwig['vinculaciones'] = $resultado['vinculaciones'];
				$variablesTwig['notario'] = $resultado['notario'];
				$variablesTwig['registro'] = $resultado['registro'];
				$variablesTwig['gestoria'] = $resultado['gestoria'];
				$variablesTwig['obraNueva'] = $resultado['obraNueva'];
				$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
				$variablesTwig['importe_iva'] = $resultado['importe_iva'];
				$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
			} else {
				if ($resultado['importe_fijo'] > 0) {
					$variablesTwig['valor_inmueble'] = $resultado['importe_fijo'];
					$variablesTwig['importe_fijo'] = $resultado['importe_fijo'];
					$variablesTwig['amortizacion'] = $resultado['amortizacion'];
					$variablesTwig['entrada'] = $resultado['entrada'];
					$variablesTwig['gastos'] = $resultado['gastos'];
					$variablesTwig['cuota'] = $resultado['cuota'];
					$variablesTwig['mensaje'] = $resultado['mensaje'];
					$variablesTwig['tipo_calculo'] = $resultado['tipo_calculo'];
					$variablesTwig['obraNueva'] = $resultado['obraNueva'];
					$variablesTwig['escritura_compra_impuesto_transmisiones'] = $resultado['escritura_compra_impuesto_transmisiones'];
					$variablesTwig['notario'] = $resultado['notario'];
					$variablesTwig['registro'] = $resultado['registro'];
					$variablesTwig['gestoria'] = $resultado['gestoria'];
					$variablesTwig['tasacion'] = $resultado['tasacion'];
					$variablesTwig['tipo_importe_maximo'] = $resultado['tipo_importe_maximo'];
					$variablesTwig['gastos_inmobiliaria'] = $formulario->getData()->getHonorariosInmobiliaria();
					$variablesTwig['importe_iva'] = $resultado['importe_iva'];
					$variablesTwig['importe_total'] = $resultado['importe_fijo'] + $resultado['gastos'] - $resultado['entrada'];
					$variablesTwig['tipo_interes_ccaa'] = $resultado['tipo_interes_ccaa'] * 100;
					$variablesTwig['numTitulares'] = $formulario->getData()->getNumTitulares();
					$variablesTwig['edadTitularUno'] = $formulario->getData()->getEdadTitularUno();
					$variablesTwig['edadTitularDos'] = $formulario->getData()->getEdadTitularDos();
					$variablesTwig['plazoAmortizacion'] = $formulario->getData()->getPlazoAmortizacion();
					$variablesTwig['aportacionInicial'] = $formulario->getData()->getAportacionInicial();
					$variablesTwig['destinoCompra'] = $formulario->getData()->getTextDestinoCompra();
					$variablesTwig['obraNuevaText'] = $formulario->getData()->getTextObraNueva();
					$variablesTwig['comunidadAutonoma'] = $formulario->getData()->getTextComunidadAutonoma();
					$variablesTwig['discapacidad'] = $formulario->getData()->getTextMinusvaliaFamiliaNumerosa();
					$variablesTwig['familiaNumerosa'] = $formulario->getData()->getTextFamiliaNumerosa();
					$variablesTwig['monoparental'] = $formulario->getData()->getTextMonoparental();
					$variablesTwig['vpo'] = $formulario->getData()->getTextVpo();
					$variablesTwig['ingresosMensuales'] = $formulario->getData()->getIngresosMensuales();
					$variablesTwig['numPagasExtra'] = $formulario->getData()->getNumPagasExtra();
					$variablesTwig['importePagaExtra'] = $formulario->getData()->getImportePagaExtra();
					$variablesTwig['prestamosMensuales'] = $formulario->getData()->getPrestamosMensuales();
					$variablesTwig['ingresosMensualesDos'] = $formulario->getData()->getIngresosMensualesDos();
					$variablesTwig['numPagasExtraDos'] = $formulario->getData()->getNumPagasExtraDos();
					$variablesTwig['importePagaExtraDos'] = $formulario->getData()->getImportePagaExtraDos();
					$variablesTwig['prestamosMensualesDos'] = $formulario->getData()->getPrestamosMensualesDos();
				} else {
					$variablesTwig['importe_fijo'] = 0;
					$variablesTwig['mensaje'] = $resultado['mensaje'];
				}
			}
			$variablesTwig['resultado'] = true;
			$variablesTwig['nombre'] = $formularioEnviarCalculadora->getData()->getNombre();
			$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();

			// VERIFICAR LÍMITE DE USOS ANTES DE ENVIAR EMAILS
			$limitAlcanzado = $this->registrarUsoCalculadora($request, $formularioEnviarCalculadora->getData()->getEmail(), 'calculadora_precio_maximo');
			if ($limitAlcanzado) {
				$variablesTwig['error_limit_reached'] = 'Ha alcanzado el límite de 3 usos para esta calculadora.';
			} else {
				$from = array($this->getParameter('mailer_user') => 'Hipotea');

				// Email al usuario final
				$mensaje = (new Swift_Message('¡Aquí tienes el resultado de tu consulta hipotecaria!'))
					->setFrom($from)
					->setTo($formularioEnviarCalculadora->getData()->getEmail())
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');

				$nombre_pdf = substr(str_shuffle(MD5(microtime())), 0, 10);
				$this->get('knp_snappy.pdf')->generateFromHtml(
					$this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWebPDF.html.twig', $variablesTwig),
					$this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf',
					[],
					true
				);
				$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
				$mailer->send($mensaje);

				// Email a Hipotea (con indicación del cliente si aplica)
				$variablesTwig['email'] = $formularioEnviarCalculadora->getData()->getEmail();
				$variablesTwig['telefono'] = $formularioEnviarCalculadora->getData()->getTelefono();
				$asuntoHipotea = 'Consulta calculadora precio máximo';
				if ($emailCliente !== '') {
					$asuntoHipotea .= ' | Cliente: ' . $emailCliente;
				}
				$mensaje = (new Swift_Message($asuntoHipotea))
					->setFrom($from)
					->setTo('info@hipotea.com')
					//->setTo('adrian.verdecia@semillaproyectos.com')
					->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
				$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
				$mailer->send($mensaje);

				// Email a la inmobiliaria cliente (solo si se proporcionó un email válido)
				if ($emailCliente !== '') {
					$mensaje = (new Swift_Message('Consulta calculadora precio máximo'))
						->setFrom($from)
						->setTo($emailCliente)
						->setBody($this->renderView('@App/Backoffice/Correo/ResultadoCalculadoraCuotaWeb.html.twig', $variablesTwig), 'text/html');
					$mensaje->attach(Swift_Attachment::fromPath($this->getParameter('files_directory') . DIRECTORY_SEPARATOR . 'calculadora_' . $nombre_pdf . '.pdf')->setFilename('Hipotea: Tu resultado.pdf'));
					$mailer->send($mensaje);
				}
			}
		}
		return $this->render('@App/Backoffice/Extras/CalculadoraCuotaWeb.html.twig', $variablesTwig);
	}

	/**
	 * Registrar uso de calculadora web (genérico para todas las calculadoras)
	 * Solo se registra si la ruta es /web/*
	 * Limita a 3 usos por email y tipo de calculadora
	 * 
	 * @param Request $request
	 * @param string $email Email del usuario
	 * @param string $tipoCalculadora Identificador único
	 * @return bool true si se alcanzó el límite de 3 usos, false si ok
	 */
	private function registrarUsoCalculadora(Request $request, string $email, string $tipoCalculadora): bool
	{
		// Solo registrar si viene de la ruta /web/*
		$referer = $request->headers->get('referer', '');
		$refPath = $referer ? parse_url($referer, PHP_URL_PATH) : '';
		if (strpos($refPath, '/web/') !== 0) {
			error_log('registrarUsoCalculadora: Ruta no es /web/*, ignorando. Referer: ' . $referer);
			return false;
		}

		if (empty($email)) {
			error_log('registrarUsoCalculadora: Email vacío, no se registra el uso');
			return false;
		}

		try {
			error_log('=== CONTADOR CALCULADORA: Iniciando para ' . $email . ' tipo=' . $tipoCalculadora);
			
			$emContador = $this->getDoctrine()->getManager();
			if (!$emContador->isOpen()) {
				error_log('EntityManager cerrado, reabriendo...');
				$emContador = $this->getDoctrine()->resetManager();
			}
			
			// Buscar registro existente
			$qb = $emContador->createQueryBuilder();
			$qb->select('u')
				->from('AppBundle:SimuladorUsoEmail', 'u')
				->where('u.email = :email')
				->andWhere('u.tipo = :tipo')
				->setParameter('email', $email)
				->setParameter('tipo', $tipoCalculadora);
			$usoEmail = $qb->getQuery()->getOneOrNullResult();
			
			error_log('Búsqueda realizada para email=' . $email . ' tipo=' . $tipoCalculadora . ': ' . ($usoEmail ? 'ENCONTRADO (ID: ' . $usoEmail->getId() . ', usos: ' . $usoEmail->getUsos() . ')' : 'NO ENCONTRADO'));
			
			if (!$usoEmail) {
				error_log('Creando nuevo registro para email=' . $email . ' tipo=' . $tipoCalculadora);
				$usoEmail = new \AppBundle\Entity\SimuladorUsoEmail();
				$usoEmail->setEmail($email);
				$usoEmail->setTipo($tipoCalculadora);
				$usoEmail->setUsos(1);
				$usoEmail->setPrimerUso(new \DateTime());
				$usoEmail->setUltimoUso(new \DateTime());
				$emContador->persist($usoEmail);
				$emContador->flush();
				error_log('Nuevo registro creado para: ' . $email . ' tipo=' . $tipoCalculadora . ' con 1 uso');
				return false; // Primer uso, sin límite
			} else {
				// VERIFICAR LÍMITE ANTES DE INCREMENTAR
				if ($usoEmail->getUsos() >= 3) {
					error_log('LÍMITE YA ALCANZADO: ' . $email . ' ya tiene ' . $usoEmail->getUsos() . ' usos de ' . $tipoCalculadora . '. NO se incrementa.');
					return true; // Ya alcanzó límite, no incrementar
				}
				
				error_log('Incrementando registro existente: usos actual=' . $usoEmail->getUsos());
				$usoEmail->incrementarUsos();
				$emContador->persist($usoEmail);
				$emContador->flush();
				error_log('Contador actualizado para: ' . $email . ' tipo=' . $tipoCalculadora . ' nuevos usos: ' . $usoEmail->getUsos());
				
				// Verificar si después del incremento se alcanzó el límite de 3 usos
				if ($usoEmail->getUsos() >= 3) {
					error_log('LÍMITE ALCANZADO (después de incrementar): ' . $email . ' ha alcanzado 3 usos de ' . $tipoCalculadora);
					return true; // Límite alcanzado ahora
				}
				
				return false; // Ok, sin límite alcanzado aún
			}
			
		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $eUnique) {
			error_log('RACE CONDITION detectada en registrarUsoCalculadora. Reintentando...');
			try {
				$emContador->clear();
				$emContador = $this->getDoctrine()->resetManager();
				
				$qb = $emContador->createQueryBuilder();
				$qb->select('u')
					->from('AppBundle:SimuladorUsoEmail', 'u')
					->where('u.email = :email')
					->andWhere('u.tipo = :tipo')
					->setParameter('email', $email)
					->setParameter('tipo', $tipoCalculadora);
				$usoEmail = $qb->getQuery()->getOneOrNullResult();
				
				if ($usoEmail) {
					// VERIFICAR LÍMITE ANTES DE INCREMENTAR en reintento
					if ($usoEmail->getUsos() >= 3) {
						error_log('LÍMITE YA ALCANZADO (reintento): ' . $email . ' ya tiene ' . $usoEmail->getUsos() . ' usos. NO se incrementa.');
						return true;
					}
					
					error_log('Reintento exitoso: Incrementando usos de ' . $usoEmail->getUsos() . ' a ' . ($usoEmail->getUsos() + 1));
					$usoEmail->incrementarUsos();
					$emContador->persist($usoEmail);
					$emContador->flush();
					
					// Verificar límite después de reintentos
					if ($usoEmail->getUsos() >= 3) {
						error_log('LÍMITE ALCANZADO (reintento): ' . $email . ' ha alcanzado 3 usos de ' . $tipoCalculadora);
						return true;
					}
				}
				return false;
			} catch (\Throwable $eReintento) {
				error_log('ERROR en reintento de race condition: ' . $eReintento->getMessage());
				return false;
			}
		} catch (\Throwable $e) {
			error_log('ERROR al registrar contador de calculadora: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Resetear usos de calculadoras para un usuario (email)
	 * Acción para admin, comerciales y técnicos
	 */
	public function resetearUsosCalculadorasAction(Request $request)
	{
		// Validar que es admin, comercial o técnico
		$usuario = $this->getUser();
		if (!$usuario) {
			throw $this->createAccessDeniedException('Acceso denegado');
		}

		$doctrine = $this->getDoctrine();
		$em = $doctrine->getManager();
		$repositorio = $doctrine->getRepository('AppBundle:SimuladorUsoEmail');
		
		$variablesTwig = array(
			'titulo' => 'Resetear Usos de Calculadoras',
			'email_buscado' => '',
			'registros' => array(),
			'mensaje' => '',
			'tipo_mensaje' => ''
		);

		// Si es una petición GET (sin búsqueda), cargar los últimos registros para mostrar listado por defecto
		if (!$request->isMethod('POST')) {
			try {
				$registrosPorDefecto = $repositorio->findBy(array(), array('ultimoUso' => 'DESC'), 100);
				$variablesTwig['registros'] = $registrosPorDefecto;
			} catch (\Exception $e) {
				// en caso de error, dejamos el listado vacío y registramos log
				error_log('ERROR cargando registros por defecto en resetearUsosCalculadorasAction: ' . $e->getMessage());
			}
		}

		// Si hay búsqueda por email
		if ($request->request->has('email_buscar')) {
			$email = trim($request->request->get('email_buscar', ''));
			
			if (empty($email)) {
				$variablesTwig['mensaje'] = 'Por favor, ingresa un email válido.';
				$variablesTwig['tipo_mensaje'] = 'warning';
			} else {
				// Buscar registros de uso para este email
				$registros = $repositorio->findBy(array('email' => $email));
				$variablesTwig['email_buscado'] = $email;
				$variablesTwig['registros'] = $registros;
				
				if (empty($registros)) {
					$variablesTwig['mensaje'] = 'No se encontraron registros de uso para este email.';
					$variablesTwig['tipo_mensaje'] = 'info';
				}
			}
		}

		// Si hay confirmación de reseteo
		if ($request->request->has('email_resetear') && $request->request->has('tipo_confirmado')) {
			$email = trim($request->request->get('email_resetear', ''));
			$tipoConfirmado = $request->request->get('tipo_confirmado', '');
			
			if (!empty($email)) {
				try {
					// Si tipo_confirmado es "*", resetear todos
					if ($tipoConfirmado === '*') {
						$registros = $repositorio->findBy(array('email' => $email));
						$cantidad = count($registros);
						
						foreach ($registros as $registro) {
							$em->remove($registro);
						}
						$em->flush();
						
						$variablesTwig['mensaje'] = sprintf('Se han eliminado %d registros de uso para %s. Los contadores se han reseteado.', $cantidad, $email);
						$variablesTwig['tipo_mensaje'] = 'success';
					} else {
						// Resetear solo el tipo específico
						$registro = $repositorio->findOneBy(array('email' => $email, 'tipo' => $tipoConfirmado));
						
						if ($registro) {
							$em->remove($registro);
							$em->flush();
							
							$variablesTwig['mensaje'] = sprintf('El contador de "%s" ha sido reseteado para %s.', $tipoConfirmado, $email);
							$variablesTwig['tipo_mensaje'] = 'success';
						} else {
							$variablesTwig['mensaje'] = 'No se encontró registro para resetear.';
							$variablesTwig['tipo_mensaje'] = 'warning';
						}
					}
					
					// Limpiar campos de búsqueda
					// Recargar listado por defecto tras reseteo (evita tener que recargar la página)
					$variablesTwig['email_buscado'] = '';
					try {
						$variablesTwig['registros'] = $repositorio->findBy(array(), array('ultimoUso' => 'DESC'), 100);
					} catch (\Exception $e) {
						$variablesTwig['registros'] = array();
						error_log('ERROR recargando registros tras reset: ' . $e->getMessage());
					}
				} catch (\Exception $e) {
					$variablesTwig['mensaje'] = 'Error al resetear: ' . $e->getMessage();
					$variablesTwig['tipo_mensaje'] = 'danger';
				}
			}
		}
		error_log('Renderizando resetearUsosCalculadorasAction con variables: ' . print_r($variablesTwig, true));

		return $this->render('@App/Backoffice/Extras/ResetearUsosCalculadoras.html.twig', $variablesTwig);
	}
}