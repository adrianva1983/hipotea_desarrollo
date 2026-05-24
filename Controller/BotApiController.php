<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Entity\CalculadoraSencilla;
use AppBundle\Entity\CalculadoraAvanzada;
use AppBundle\Entity\CalculadoraComparativa;
use AppBundle\Entity\Parametros;

class BotApiController extends Controller
{
	public function calcularSencillaAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);
		
		$contentType = $request->headers->get('Content-Type');
		if ($contentType && strpos($contentType, 'application/json') !== false) {
			$jsonRecibido = json_decode($request->getContent(), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
			}
		} else {
			$jsonRecibido = array(
				'precioTotal' => $request->request->get('precioTotal'),
				'aportacionInicial' => $request->request->get('aportacionInicial'),
				'tasaInteres' => $request->request->get('tasaInteres'),
				'plazoAmortizacion' => $request->request->get('plazoAmortizacion'),
			);
		}

		$requiredKeys = array('precioTotal', 'aportacionInicial', 'tasaInteres', 'plazoAmortizacion');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		if ($isValid) {
			$calculadora = new CalculadoraSencilla();
			$calculadora->setPrecioTotal((double)$jsonRecibido['precioTotal']);
			$calculadora->setAportacionInicial((double)$jsonRecibido['aportacionInicial']);
			$calculadora->setTasaInteres((double)$jsonRecibido['tasaInteres']);
			$calculadora->setPlazoAmortizacion((int)$jsonRecibido['plazoAmortizacion']);

			$validador = $this->get('validator');
			$violaciones = $validador->validate($calculadora);

			if (count($violaciones) === 0) {
				$resultadoHipoteca = $calculadora->calcularHipoteca();
				$respuesta['datos'] = array(
					'hipoteca' => $resultadoHipoteca['capital_less_initial_amount'],
					'pago_mensual' => $resultadoHipoteca['fee'],
					'hipoteca_total_con_interes' => $resultadoHipoteca['capital_less_initial_amount'] + $resultadoHipoteca['interest_discharged_total'],
					'total_con_anticipo' => $calculadora->getPrecioTotal() + $resultadoHipoteca['interest_discharged_total']
				);
			} else {
				$respuesta['errorlevel'] = 1;
				foreach ($violaciones as $violacion) {
					$respuesta['errores'][] = array(
						'propiedad' => $violacion->getPropertyPath(),
						'mensaje' => $violacion->getMessage()
					);
				}
			}
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	public function calcularCuotaGastosAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);

		$jsonRecibido = $this->parseRequestData($request);
		if ($jsonRecibido === null) {
			return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
		}

		$requiredKeys = array('valorInmueble', 'plazoAmortizacion');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		if ($isValid) {
			$plazo = (int)$jsonRecibido['plazoAmortizacion'];
			$edadTit1 = isset($jsonRecibido['edadTitularUno']) ? (int)$jsonRecibido['edadTitularUno'] : (isset($jsonRecibido['edad']) ? (int)$jsonRecibido['edad'] : 0);
			$edadTit2 = isset($jsonRecibido['edadTitularDos']) ? (int)$jsonRecibido['edadTitularDos'] : 0;
			$maxEdad = max($edadTit1, $edadTit2);
			
			if ($maxEdad > 0 && ($maxEdad + $plazo) > 75) {
				$respuesta['errorlevel'] = 1;
				$respuesta['errores'][] = array(
					'propiedad' => 'plazoAmortizacion',
					'mensaje' => 'La suma de la edad del titular mayor y el plazo no puede superar 75 años.'
				);
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}

			$calculadora = new CalculadoraAvanzada();
			$calculadora->setTipo(1);
			$this->populateCalculadoraAvanzada($calculadora, $jsonRecibido);

			$validador = $this->get('validator');
			$violaciones = $validador->validate($calculadora);

			if (count($violaciones) === 0) {
				$resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());
				$respuesta['datos'] = $this->postProcessCalculoAvanzado($resultado, $calculadora, $jsonRecibido);
			} else {
				$respuesta['errorlevel'] = 1;
				foreach ($violaciones as $violacion) {
					$respuesta['errores'][] = array(
						'propiedad' => $violacion->getPropertyPath(),
						'mensaje' => $violacion->getMessage()
					);
				}
			}
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	public function calcularPrecioMaximoAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);

		$jsonRecibido = $this->parseRequestData($request);
		if ($jsonRecibido === null) {
			return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
		}

		$requiredKeys = array('ingresosMensuales', 'plazoAmortizacion', 'comunidadAutonoma');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		if ($isValid) {
			$plazo = (int)$jsonRecibido['plazoAmortizacion'];
			$edadTit1 = isset($jsonRecibido['edadTitularUno']) ? (int)$jsonRecibido['edadTitularUno'] : (isset($jsonRecibido['edad']) ? (int)$jsonRecibido['edad'] : 0);
			$edadTit2 = isset($jsonRecibido['edadTitularDos']) ? (int)$jsonRecibido['edadTitularDos'] : 0;
			$maxEdad = max($edadTit1, $edadTit2);
			
			if ($maxEdad > 0 && ($maxEdad + $plazo) > 75) {
				$respuesta['errorlevel'] = 1;
				$respuesta['errores'][] = array(
					'propiedad' => 'plazoAmortizacion',
					'mensaje' => 'La suma de la edad del titular mayor y el plazo no puede superar 75 años.'
				);
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}

			$calculadora = new CalculadoraAvanzada();
			$calculadora->setTipo(2);
			$this->populateCalculadoraAvanzada($calculadora, $jsonRecibido);

			$validador = $this->get('validator');
			$violaciones = $validador->validate($calculadora);

			if (count($violaciones) === 0) {
				$resultado = $calculadora->calcularAvanzada($this->getDoctrine()->getManager());
				$respuesta['datos'] = $this->postProcessCalculoAvanzado($resultado, $calculadora, $jsonRecibido);
			} else {
				$respuesta['errorlevel'] = 1;
				foreach ($violaciones as $violacion) {
					$respuesta['errores'][] = array(
						'propiedad' => $violacion->getPropertyPath(),
						'mensaje' => $violacion->getMessage()
					);
				}
			}
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	public function calcularComparativaAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);

		$jsonRecibido = $this->parseRequestData($request);
		if ($jsonRecibido === null) {
			return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
		}

		$requiredKeys = array('destino', 'tipoHipoteca', 'importeHipoteca', 'oferta');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		if ($isValid) {
			$calculadora = new CalculadoraComparativa();
			$this->populateCalculadoraComparativa($calculadora, $jsonRecibido);

			$validador = $this->get('validator');
			$violaciones = $validador->validate($calculadora);

			if (count($violaciones) === 0) {
				$resultado = $calculadora->calcularComparativa($this->getDoctrine()->getManager());
				$respuesta['datos'] = $resultado;
			} else {
				$respuesta['errorlevel'] = 1;
				foreach ($violaciones as $violacion) {
					$respuesta['errores'][] = array(
						'propiedad' => $violacion->getPropertyPath(),
						'mensaje' => $violacion->getMessage()
					);
				}
			}
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	public function simularViabilidadAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);

		$jsonRecibido = $this->parseRequestData($request);
		if ($jsonRecibido === null) {
			return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
		}

		$requiredKeys = array('ingresosMensuales', 'tienePrestamosImpagados', 'situacionLaboral', 'antiguedadLaboral');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		$edadTit1 = isset($jsonRecibido['edadTitularUno']) ? (int)$jsonRecibido['edadTitularUno'] : (isset($jsonRecibido['edad']) ? (int)$jsonRecibido['edad'] : 0);
		if ($edadTit1 <= 0) {
			$isValid = false;
		}

		if ($isValid) {
			$plazoAmortizacion = isset($jsonRecibido['plazoAmortizacion']) ? (int)$jsonRecibido['plazoAmortizacion'] : 30;
			$edadTit2 = isset($jsonRecibido['edadTitularDos']) ? (int)$jsonRecibido['edadTitularDos'] : 0;
			$maxEdad = max($edadTit1, $edadTit2);
			
			if ($maxEdad > 0 && ($maxEdad + $plazoAmortizacion) > 75) {
				$respuesta['errorlevel'] = 1;
				$respuesta['errores'][] = array(
					'propiedad' => 'plazoAmortizacion',
					'mensaje' => 'La suma de la edad del titular mayor y el plazo no puede superar 75 años.'
				);
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}

			$em = $this->getDoctrine()->getManager();
			
			// Paso 2: Calcular precio máximo (Calculadora Avanzada, tipo = 2)
			$calcMax = new CalculadoraAvanzada();
			$calcMax->setTipo(2);
			$this->populateCalculadoraAvanzada($calcMax, $jsonRecibido);
			
			$resultadoPaso2 = $calcMax->calcularAvanzada($em);
			
			if (!isset($resultadoPaso2['importe_fijo']) || $resultadoPaso2['importe_fijo'] <= 0) {
				$respuesta['errorlevel'] = 3;
				$respuesta['errores'][] = array('mensaje' => 'No se pudo calcular el precio máximo con los ingresos y aportación aportados.');
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}

			$precioMaximo = $resultadoPaso2['importe_fijo'];
			$aportacion = $resultadoPaso2['entrada'];
			$financiacion = $precioMaximo - $aportacion;
			$porcentajeFinanciacion = ($financiacion / $precioMaximo) * 100;

			// Paso 3: Calcular cuota y gastos (Calculadora Avanzada, tipo = 1)
			$calcCuota = new CalculadoraAvanzada();
			$calcCuota->setTipo(1);
			$this->populateCalculadoraAvanzada($calcCuota, $jsonRecibido);
			$calcCuota->setValorInmueble($precioMaximo);
			$calcCuota->setAportacionInicial($aportacion);
			
			$resultadoPaso3 = $calcCuota->calcularAvanzada($em);
			
			if (!isset($resultadoPaso3['importe_fijo']) || $resultadoPaso3['importe_fijo'] <= 0) {
				$respuesta['errorlevel'] = 3;
				$respuesta['errores'][] = array('mensaje' => 'No se pudo calcular la cuota y gastos estimados.');
				return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
			}

			$importePrestamo = $precioMaximo - $aportacion;
			$porcentajeFinanciacion3 = ($importePrestamo / $precioMaximo) * 100;
			$cuotaEstimada = isset($resultadoPaso3['cuota_fija']) ? $resultadoPaso3['cuota_fija'] : (isset($resultadoPaso3['cuota']) ? $resultadoPaso3['cuota'] : 0);
			$gastosTotalesAproximados = isset($resultadoPaso3['gastos']) ? $resultadoPaso3['gastos'] : 0;

			// Build simulator state
			$simulador = [
				'cliente' => [
					'nombre' => isset($jsonRecibido['nombre']) ? $jsonRecibido['nombre'] : '',
					'dni' => isset($jsonRecibido['dni']) ? $jsonRecibido['dni'] : '',
					'telefono' => isset($jsonRecibido['telefono']) ? $jsonRecibido['telefono'] : '',
					'email' => isset($jsonRecibido['email']) ? $jsonRecibido['email'] : '',
				],
				'precio' => [
					'precioMaximoRecomendado' => round($precioMaximo, 2),
					'aportacionNecesaria' => round($aportacion, 2),
					'importePrestamo' => round($financiacion, 2),
					'cuotaHipotecariaEstimada' => round(isset($resultadoPaso2['cuota']) ? $resultadoPaso2['cuota'] : 0, 2),
					'gastosTotalesAproximados' => round($resultadoPaso2['gastos'], 2),
					'porcentajeFinanciacion' => round($porcentajeFinanciacion, 2),
				],
				'cuota' => [
					'plazoAmortizacion' => (int)$plazoAmortizacion,
					'tipoInteres' => 'fijo',
					'gastosTotalesAproximados' => round($gastosTotalesAproximados, 2),
					'aportacionNecesaria' => round($aportacion, 2),
					'importePrestamo' => round($importePrestamo, 2),
					'cuotaHipotecariaEstimada' => round($cuotaEstimada, 2),
					'porcentajeFinanciacion' => round($porcentajeFinanciacion3, 2),
				],
				'riesgo' => [
					'tienePrestamosImpagados' => (bool)$jsonRecibido['tienePrestamosImpagados'],
					'situacionLaboral' => $jsonRecibido['situacionLaboral'],
					'antiguedadLaboral' => $jsonRecibido['antiguedadLaboral']
				]
			];

			// Evaluate Semaphore
			$simulador = $this->evaluarResultadoSemaforo($simulador);
			$respuesta['datos'] = $simulador;
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}

	private function parseRequestData(Request $request)
	{
		$contentType = $request->headers->get('Content-Type');
		if ($contentType && strpos($contentType, 'application/json') !== false) {
			$data = json_decode($request->getContent(), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}
			return $data;
		}
		return $request->request->all();
	}

	private function populateCalculadoraAvanzada(CalculadoraAvanzada $calculadora, array $data)
	{
		if (isset($data['tipo'])) {
			$calculadora->setTipo((int)$data['tipo']);
		}
		if (isset($data['numTitulares'])) {
			$calculadora->setNumTitulares((int)$data['numTitulares']);
		} else {
			$calculadora->setNumTitulares(1);
		}
		
		$edadTitularUno = isset($data['edadTitularUno']) ? (int)$data['edadTitularUno'] : (isset($data['edad']) ? (int)$data['edad'] : 0);
		$edadTitularDos = isset($data['edadTitularDos']) ? (int)$data['edadTitularDos'] : 0;
		
		$calculadora->setEdadTitularUno($edadTitularUno);
		$calculadora->setEdadTitularDos($edadTitularDos);
		
		$edadMin = 0;
		if ($edadTitularUno > 0 && $edadTitularDos > 0) {
			$edadMin = min($edadTitularUno, $edadTitularDos);
		} elseif ($edadTitularUno > 0) {
			$edadMin = $edadTitularUno;
		}
		$calculadora->setEdad($edadMin ?: $edadTitularUno);

		if (isset($data['valorInmueble'])) {
			$calculadora->setValorInmueble((double)$data['valorInmueble']);
		}
		if (isset($data['aportacionInicial'])) {
			$calculadora->setAportacionInicial((double)$data['aportacionInicial']);
		}
		if (isset($data['honorariosInmobiliaria'])) {
			$calculadora->setHonorariosInmobiliaria((double)$data['honorariosInmobiliaria']);
		}
		if (isset($data['destinoCompra'])) {
			$calculadora->setDestinoCompra((int)$data['destinoCompra']);
		}
		if (isset($data['producto'])) {
			$productoValue = $data['producto'];
			if ($productoValue === 'cambio_de_casa') {
				$productoValue = 4;
			} elseif ($productoValue === 'hipoteca_80') {
				$productoValue = 1;
			} elseif ($productoValue === 'premium') {
				$productoValue = 2;
			} elseif ($productoValue === 'sin_compromiso') {
				$productoValue = 3;
			}
			$calculadora->setProducto((int)$productoValue);
		}
		if (isset($data['valorViviendaActual'])) {
			$calculadora->setValorViviendaActual((double)$data['valorViviendaActual']);
		}
		if (isset($data['hipotecaActual'])) {
			$calculadora->setHipotecaActual((double)$data['hipotecaActual']);
		}
		if (isset($data['aportacionTrasVenta'])) {
			$calculadora->setAportacionTrasVenta((double)$data['aportacionTrasVenta']);
		}
		if (isset($data['plazoAmortizacion'])) {
			$calculadora->setPlazoAmortizacion((int)$data['plazoAmortizacion']);
		}
		if (isset($data['ingresosMensuales'])) {
			$calculadora->setIngresosMensuales((double)$data['ingresosMensuales']);
		}
		if (isset($data['numPagasExtra'])) {
			$calculadora->setNumPagasExtra((int)$data['numPagasExtra']);
		}
		if (isset($data['importePagaExtra'])) {
			$calculadora->setImportsPagaExtra((double)$data['importePagaExtra']);
		}
		if (isset($data['prestamosMensuales'])) {
			$calculadora->setPrestamosMensuales((double)$data['prestamosMensuales']);
		}
		if (isset($data['ingresosMensualesDos'])) {
			$calculadora->setIngresosMensualesDos((double)$data['ingresosMensualesDos']);
		}
		if (isset($data['numPagasExtraDos'])) {
			$calculadora->setNumPagasExtraDos((int)$data['numPagasExtraDos']);
		}
		if (isset($data['importePagaExtraDos'])) {
			$calculadora->setImportePagaExtraDos((double)$data['importePagaExtraDos']);
		}
		if (isset($data['prestamosMensualesDos'])) {
			$calculadora->setPrestamosMensualesDos((double)$data['prestamosMensualesDos']);
		}

		// Booleans / Strings
		if (isset($data['tipologiaOperacion'])) {
			$calculadora->setTipologiaOperacion((int)$data['tipologiaOperacion']);
		}
		if (isset($data['comunidadAutonoma'])) {
			$calculadora->setComunidadAutonoma((int)$data['comunidadAutonoma']);
		}
		if (isset($data['obraNueva'])) {
			$calculadora->setObraNueva((bool)$data['obraNueva']);
		}
		if (isset($data['minusvaliaFamiliaNumerosa'])) {
			$calculadora->setMinusvaliaFamiliaNumerosa((bool)$data['minusvaliaFamiliaNumerosa']);
		}
		if (isset($data['familiaNumerosa'])) {
			$calculadora->setFamiliaNumerosa((bool)$data['familiaNumerosa']);
		}
		if (isset($data['monoparental'])) {
			$calculadora->setMonoparental((bool)$data['monoparental']);
		}
		if (isset($data['vpo'])) {
			$calculadora->setVpo((bool)$data['vpo']);
		}
	}

	private function populateCalculadoraComparativa(CalculadoraComparativa $calculadora, array $data)
	{
		if (isset($data['destino'])) {
			$calculadora->setDestino((int)$data['destino']);
		}
		if (isset($data['tipoHipoteca'])) {
			$calculadora->setTipoHipoteca((int)$data['tipoHipoteca']);
		}
		if (isset($data['aniosPendientesHipoteca'])) {
			$calculadora->setAniosPendientesHipoteca((int)$data['aniosPendientesHipoteca']);
		}
		if (isset($data['plazoAmortizacion'])) {
			$calculadora->setPlazoAmortizacion((int)$data['plazoAmortizacion']);
		}
		if (isset($data['aniosPlazoFijo'])) {
			$calculadora->setAniosPlazoFijo((int)$data['aniosPlazoFijo']);
		}
		if (isset($data['plazoTotal'])) {
			$calculadora->setPlazoTotal((int)$data['plazoTotal']);
		}
		if (isset($data['importeHipoteca'])) {
			$calculadora->setImporteHipoteca((double)$data['importeHipoteca']);
		}
		if (isset($data['tipo'])) {
			$calculadora->setTipo((double)$data['tipo']);
		}
		if (isset($data['revision'])) {
			$calculadora->setRevision((double)$data['revision']);
		}
		if (isset($data['tipoFijo'])) {
			$calculadora->setTipoFijo((double)$data['tipoFijo']);
		}
		if (isset($data['tipoVariable'])) {
			$calculadora->setTipoVariable((double)$data['tipoVariable']);
		}
		if (isset($data['revisionVariable'])) {
			$calculadora->setRevisionVariable((double)$data['revisionVariable']);
		}
		if (isset($data['tipoMixta'])) {
			$calculadora->setTipoMixta((double)$data['tipoMixta']);
		}
		if (isset($data['revisionMixta'])) {
			$calculadora->setRevisionMixta((double)$data['revisionMixta']);
		}
		if (isset($data['aniosMixta'])) {
			$calculadora->setAniosMixta((int)$data['aniosMixta']);
		}
		if (isset($data['oferta'])) {
			$calculadora->setOferta((int)$data['oferta']);
		}
		if (isset($data['edad'])) {
			$calculadora->setEdad((int)$data['edad']);
		}

		// Personalizada
		if (isset($data['persoTipoHipoteca'])) {
			$calculadora->setPersoTipoHipoteca((int)$data['persoTipoHipoteca']);
		}
		if (isset($data['persoVinculacion'])) {
			$calculadora->setPersoVinculacion((int)$data['persoVinculacion']);
		}
		if (isset($data['persoAnios'])) {
			$calculadora->setPersoAnios((int)$data['persoAnios']);
		}
		if (isset($data['persoTipo'])) {
			$calculadora->setPersoTipo((double)$data['persoTipo']);
		}
		if (isset($data['persoRevision'])) {
			$calculadora->setPersoRevision((double)$data['persoRevision']);
		}
	}

	private function postProcessCalculoAvanzado(array $resultado, CalculadoraAvanzada $calculadora, array $data)
	{
		$tipoCalculo = (int)$calculadora->getTipo();
		$valorInmueble = (double)$calculadora->getValorInmueble();
		$aportacion = (double)$calculadora->getAportacionInicial();
		
		// ===== CALCULAR IMPORTE_PRESTAMO SI NO VIENE =====
		if (empty($resultado['importe_prestamo'])) {
			if ($tipoCalculo === 1) {
				$gastos = isset($resultado['gastos']) ? (double)$resultado['gastos'] : 0;
				$resultado['importe_prestamo'] = ($valorInmueble + $gastos) - $aportacion;
			} elseif ($tipoCalculo === 2 && !empty($resultado['importe_maximo'])) {
				$resultado['importe_prestamo'] = (double)$resultado['importe_maximo'] - $aportacion;
			} else {
				$resultado['importe_prestamo'] = 0;
			}
		}

		// ===== CALCULAR PORCENTAJE_FINANCIACION SI NO VIENE =====
		if (empty($resultado['porcentaje_financiacion']) && !empty($resultado['importe_prestamo']) && !empty($valorInmueble)) {
			$porc = ($resultado['importe_prestamo'] / $valorInmueble) * 100;
			$producto = (int)$calculadora->getProducto();
			if ($producto === 4) {
				$valorViviendaActual = (double)$calculadora->getValorViviendaActual();
				$gastos = isset($resultado['gastos']) ? (double)$resultado['gastos'] : 0;
				$baseFinanciacion = ($valorViviendaActual > 0) ? ($valorInmueble + $valorViviendaActual) : $valorInmueble;
				$importeFinanciado = ($valorInmueble + $gastos) - $aportacion;
				if ($baseFinanciacion > 0) {
					$porc = ($importeFinanciado / $baseFinanciacion) * 100;
				} else {
					$porc = 0;
				}
			}
			$resultado['porcentaje_financiacion'] = $porc;
		}

		// ===== CORREGIR IMPORTE_FIJO =====
		$importeFijo = isset($resultado['importe_fijo']) ? $resultado['importe_fijo'] : 0;
		if ($tipoCalculo === 1 && !empty($resultado['importe_prestamo'])) {
			$importeFijo = $resultado['importe_prestamo'];
		} elseif ($tipoCalculo === 2 && !empty($resultado['importe_maximo'])) {
			$importeFijo = $resultado['importe_maximo'];
		}

		// Build response array
		$datosRespuesta = [
			'importe_fijo' => round($importeFijo, 2),
			'entrada' => round(isset($resultado['entrada']) ? $resultado['entrada'] : 0, 2),
			'gastos' => round(isset($resultado['gastos']) ? $resultado['gastos'] : 0, 2),
			'cuota' => round(isset($resultado['cuota']) ? $resultado['cuota'] : 0, 2),
			'amortizacion' => isset($resultado['amortizacion']) ? (int)$resultado['amortizacion'] : (int)$calculadora->getPlazoAmortizacion(),
			'mensaje' => isset($resultado['mensaje']) ? $resultado['mensaje'] : 'Cálculo completado exitosamente',
			'tipo_calculo' => isset($resultado['tipo_calculo']) ? $resultado['tipo_calculo'] : ($tipoCalculo === 1 ? 'cuota' : 'importe-maximo'),
			'obraNueva' => isset($resultado['obraNueva']) ? (bool)$resultado['obraNueva'] : (bool)$calculadora->getObraNueva(),
			'tasacion' => isset($resultado['tasacion']) ? round($resultado['tasacion'], 2) : 0,
			'notario' => isset($resultado['notario']) ? round($resultado['notario'], 2) : 0,
			'registro' => isset($resultado['registro']) ? round($resultado['registro'], 2) : 0,
			'gestoria' => isset($resultado['gestoria']) ? round($resultado['gestoria'], 2) : 0,
			'tipo_importe_maximo' => isset($resultado['tipo_importe_maximo']) ? round($resultado['tipo_importe_maximo'], 2) : 0,
			'importe_iva' => isset($resultado['importe_iva']) ? round($resultado['importe_iva'], 2) : 0,
			'tipo_interes_ccaa' => isset($resultado['tipo_interes_ccaa']) ? round($resultado['tipo_interes_ccaa'] * 100, 4) : 0,
			'importe_prestamo' => round(isset($resultado['importe_prestamo']) ? $resultado['importe_prestamo'] : 0, 2),
			'porcentaje_financiacion' => round(isset($resultado['porcentaje_financiacion']) ? $resultado['porcentaje_financiacion'] : 0, 2),
			'vinculaciones' => round(isset($resultado['vinculaciones']) ? $resultado['vinculaciones'] : 0, 2),
			
			// Additional fields for Type 1
			'cuota_fija' => round(isset($resultado['cuota_fija']) ? $resultado['cuota_fija'] : 0, 2),
			'cuota_variable' => round(isset($resultado['cuota_variable']) ? $resultado['cuota_variable'] : 0, 2),
			'cuota_mixta' => round(isset($resultado['cuota_mixta']) ? $resultado['cuota_mixta'] : 0, 2),
			'cuota_fija_final' => round(isset($resultado['cuota_fija_final']) ? $resultado['cuota_fija_final'] : 0, 2),
			'cuota_variable_final' => round(isset($resultado['cuota_variable_final']) ? $resultado['cuota_variable_final'] : 0, 2),
			'cuota_mixta_final' => round(isset($resultado['cuota_mixta_final']) ? $resultado['cuota_mixta_final'] : 0, 2),
			'tipo_fijo' => round(isset($resultado['tipo_fijo']) ? $resultado['tipo_fijo'] : 0, 4),
			'tipo_variable' => round(isset($resultado['tipo_variable']) ? $resultado['tipo_variable'] : 0, 4),
			'tipo_mixto' => round(isset($resultado['tipo_mixto']) ? $resultado['tipo_mixto'] : 0, 4),
			'tipo_luego_mixto' => round(isset($resultado['tipo_luego_mixto']) ? $resultado['tipo_luego_mixto'] : 0, 4),
			'intereses' => round(isset($resultado['intereses']) ? $resultado['intereses'] : 0, 2),
			'importe_total' => round(isset($resultado['importe_total']) ? $resultado['importe_total'] : 0, 2),
			'importe_variable' => round(isset($resultado['importe_variable']) ? $resultado['importe_variable'] : 0, 2),
			'con_interes_fijo' => isset($resultado['con_interes_fijo']) ? (bool)$resultado['con_interes_fijo'] : false,
			'con_interes_variable' => isset($resultado['con_interes_variable']) ? (bool)$resultado['con_interes_variable'] : false,
			'con_entrada_fijo' => round(isset($resultado['con_entrada_fijo']) ? $resultado['con_entrada_fijo'] : 0, 2),
			'con_entrada_variable' => round(isset($resultado['con_entrada_variable']) ? $resultado['con_entrada_variable'] : 0, 2),
			
			'valor_inmueble' => round($valorInmueble, 2),
			'valor_vivienda_actual' => round((double)$calculadora->getValorViviendaActual(), 2),
			'hipoteca_actual' => round((double)$calculadora->getHipotecaActual(), 2),
			'aportacion_tras_venta' => round((double)$calculadora->getAportacionTrasVenta(), 2),
			'escritura_compra_impuesto_transmisiones' => round(isset($resultado['escritura_compra_impuesto_transmisiones']) ? $resultado['escritura_compra_impuesto_transmisiones'] : 0, 2),
			'gastos_inmobiliaria' => round(isset($resultado['gastos_inmobiliaria']) ? $resultado['gastos_inmobiliaria'] : (isset($resultado['honorarios_inmobiliaria']) ? $resultado['honorarios_inmobiliaria'] : (double)$calculadora->getHonorariosInmobiliaria()), 2),
			'producto' => (int)$calculadora->getProducto()
		];
		
		return $datosRespuesta;
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
			'fecha_evaluacion' => (new \DateTime())->format('Y-m-d H:i:s')
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

	/**
	 * Endpoint API para buscar cliente por teléfono o DNI
	 * Permite GET o POST, recibe 'telefono' o 'dni' y devuelve datos básicos del cliente
	 */
	public function buscarClienteAction(Request $request)
	{
		// Permitir parámetros por JSON (application/json) o por GET/POST clásicos
		$telefono = null;
		$dni = null;
		$contentType = $request->headers->get('Content-Type');
		if ($contentType && strpos($contentType, 'application/json') !== false) {
			$data = json_decode($request->getContent(), true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
				$telefono = isset($data['telefono']) ? $data['telefono'] : null;
				$dni = isset($data['dni']) ? $data['dni'] : null;
			}
		} else {
			$telefono = $request->get('telefono');
			$dni = $request->get('dni');
		}
		$conn = $this->getDoctrine()->getConnection();

		if (!$telefono && !$dni) {
			return new JsonResponse([
				'success' => false,
				'error' => 'Debe proporcionar al menos un parámetro: telefono o dni.'
			], 400);
		}

		$where = [];
		$params = [];
		if ($telefono) {
			// Buscar por variantes del teléfono (sin prefijo, últimos 9 dígitos)
			$variants = array_unique(array_filter([
				$telefono,
				ltrim($telefono, '0'),
				(strlen($telefono) > 9 ? substr($telefono, -9) : null)
			]));
			$telPlaceholders = [];
			foreach ($variants as $i => $v) {
				$ph = ':tel' . $i;
				$telPlaceholders[] = $ph;
				$params[$ph] = $v;
			}
			$where[] = 'telefono_movil IN (' . implode(',', $telPlaceholders) . ')';
		}
		if ($dni) {
			$where[] = 'nif = :dni';
			$params[':dni'] = $dni;
		}

		$sql = 'SELECT id_usuario, nombre, apellidos, nif, email, telefono_movil, telefono_fijo, estado FROM usuario WHERE estado = 1';
		if (count($where) > 0) {
			$sql .= ' AND (' . implode(' OR ', $where) . ')';
		}
		$sql .= ' LIMIT 1';

		$stmt = $conn->prepare($sql);
		foreach ($params as $ph => $val) {
			$stmt->bindValue(trim($ph, ':'), $val);
		}
		$stmt->execute();
		$cliente = $stmt->fetch();

		if (!$cliente) {
			return new JsonResponse([
				'success' => false,
				'error' => 'No se encontró ningún cliente con los datos proporcionados.'
			], 404);
		}

		// Devolver datos básicos del cliente
		return new JsonResponse([
			'success' => true,
			'cliente' => [
				'id' => $cliente['id_usuario'],
				'nombre' => $cliente['nombre'],
				'apellidos' => $cliente['apellidos'],
				'dni' => $cliente['nif'],
				'email' => $cliente['email'],
				'telefono_movil' => $cliente['telefono_movil'],
				'telefono_fijo' => $cliente['telefono_fijo'],
				'estado' => $cliente['estado'],
			]
		]);
	}
}
