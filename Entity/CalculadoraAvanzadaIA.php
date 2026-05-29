<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityManagerInterface;

/**
 * CalculadoraAvanzadaIA
 */
class CalculadoraAvanzadaIA
{
	private $tipo;
	private $numTitulares;
	private $aportacionInicial;
	private $edad;
	private $edadTitularUno;
	private $edadTitularDos;
	private $valorInmueble;
	private $producto;
	private $hipotecaActual;
	private $valorViviendaActual;
	// private $ventaCasaActual;
	private $aportacionTrasVenta;
	private $ingresosMensuales;
	private $numPagasExtra;
	private $importePagaExtra;
	private $prestamosMensuales;

	private $ingresosMensualesDos;
	private $numPagasExtraDos;
	private $importePagaExtraDos;
	private $prestamosMensualesDos;

	private $tipologiaOperacion;
	private $comunidadAutonoma;
	private $obraNueva;
	private $tieneMenosEdadMaxima;
	private $minusvaliaFamiliaNumerosa;
	private $familiaNumerosa;
	private $monoparental;
	private $vpo;
	private $honorariosInmobiliaria;
	private $destinoCompra;

	private $plazoAmortizacion;

	/**
	 * @return double
	 */
	public function getTipo()
	{
		return $this->tipo;
	}

	/**
	 * @param double $tipo
	 */
	public function setTipo($tipo)
	{
		$this->tipo = $tipo;
	}

	/**
	 * @return double
	 */
	public function getAportacionInicial()
	{
		return $this->aportacionInicial;
	}

	/**
	 * @param double $aportacionInicial
	 */
	public function setAportacionInicial($aportacionInicial)
	{
		$this->aportacionInicial = $aportacionInicial;
	}

	/**
	 * @return double
	 */
	public function getEdad()
	{
		return $this->edad;
	}

	/**
	 * @param double $edad
	 */
	public function setEdad($edad)
	{
		$this->edad = $edad;
	}

	/**
	 * @return integer
	 */
	public function getValorInmueble()
	{
		return $this->valorInmueble;
	}

	/**
	 * @param integer $valorInmueble
	 */
	public function setValorInmueble($valorInmueble)
	{
		$this->valorInmueble = $valorInmueble;
	}

	/**
	 * @return integer
	 */
	public function getProducto()
	{
		return $this->producto;
	}

	/**
	 * @param integer $producto
	 */
	public function setProducto($producto)
	{
		$this->producto = $producto;
	}

	/**
	 * Get the value of hipotecaActual
	 */
	public function getHipotecaActual()
	{
		return $this->hipotecaActual;
	}

	/**
	 * Set the value of hipotecaActual
	 *
	 * @param $hipotecaActual
	 * @return  self
	 */
	public function setHipotecaActual($hipotecaActual)
	{
		$this->hipotecaActual = $hipotecaActual;
		return $this;
	}

	/**
	 * Get the value of aportacionTrasVenta
	 */
	public function getAportacionTrasVenta()
	{
		return $this->aportacionTrasVenta;
	}

	/**
	 * Set the value of aportacionTrasVenta
	 *
	 * @param $aportacionTrasVenta
	 * @return  self
	 */
	public function setAportacionTrasVenta($aportacionTrasVenta)
	{
		$this->aportacionTrasVenta = $aportacionTrasVenta;
		return $this;
	}

	/**
	 * Get the value of ingresosMensuales
	 */
	public function getIngresosMensuales()
	{
		return $this->ingresosMensuales;
	}

	/**
	 * Set the value of ingresosMensuales
	 *
	 * @param $ingresosMensuales
	 * @return  self
	 */
	public function setIngresosMensuales($ingresosMensuales)
	{
		$this->ingresosMensuales = $ingresosMensuales;
		return $this;
	}

	/**
	 * Get the value of numPagasExtra
	 */
	public function getNumPagasExtra()
	{
		return $this->numPagasExtra;
	}

	/**
	 * Set the value of numPagasExtra
	 *
	 * @param $numPagasExtra
	 * @return  self
	 */
	public function setNumPagasExtra($numPagasExtra)
	{
		$this->numPagasExtra = $numPagasExtra;
		return $this;
	}

	/**
	 * Get the value of importePagaExtra
	 */
	public function getImportePagaExtra()
	{
		return $this->importePagaExtra;
	}

	/**
	 * Set the value of importePagaExtra
	 *
	 * @param $importePagaExtra
	 * @return  self
	 */
	public function setImportePagaExtra($importePagaExtra)
	{
		$this->importePagaExtra = $importePagaExtra;
		return $this;
	}

	/**
	 * Get the value of prestamosMensuales
	 */
	public function getPrestamosMensuales()
	{
		return $this->prestamosMensuales;
	}

	/**
	 * Set the value of prestamosMensuales
	 *
	 * @param $prestamosMensuales
	 * @return  self
	 */
	public function setPrestamosMensuales($prestamosMensuales)
	{
		$this->prestamosMensuales = $prestamosMensuales;
		return $this;
	}

	/**
	 * Get the value of ingresosMensualesDos
	 */
	public function getIngresosMensualesDos()
	{
		return $this->ingresosMensualesDos;
	}

	/**
	 * Set the value of ingresosMensualesDos
	 *
	 * @param $ingresosMensualesDos
	 * @return  self
	 */
	public function setIngresosMensualesDos($ingresosMensualesDos)
	{
		$this->ingresosMensualesDos = $ingresosMensualesDos;
		return $this;
	}

	/**
	 * Get the value of numPagasExtraDos
	 */
	public function getNumPagasExtraDos()
	{
		return $this->numPagasExtraDos;
	}

	/**
	 * Set the value of numPagasExtraDos
	 *
	 * @param $numPagasExtraDos
	 * @return  self
	 */
	public function setNumPagasExtraDos($numPagasExtraDos)
	{
		$this->numPagasExtraDos = $numPagasExtraDos;
		return $this;
	}

	/**
	 * Get the value of importePagaExtraDos
	 */
	public function getImportePagaExtraDos()
	{
		return $this->importePagaExtraDos;
	}

	/**
	 * Set the value of importePagaExtraDos
	 *
	 * @param $importePagaExtraDos
	 * @return  self
	 */
	public function setImportePagaExtraDos($importePagaExtraDos)
	{
		$this->importePagaExtraDos = $importePagaExtraDos;
		return $this;
	}

	/**
	 * Get the value of prestamosMensualesDos
	 */
	public function getPrestamosMensualesDos()
	{
		return $this->prestamosMensualesDos;
	}

	/**
	 * Set the value of prestamosMensualesDos
	 *
	 * @param $prestamosMensualesDos
	 * @return  self
	 */
	public function setPrestamosMensualesDos($prestamosMensualesDos)
	{
		$this->prestamosMensualesDos = $prestamosMensualesDos;
		return $this;
	}

	/**
	 * @return double
	 */
	public function getPlazoAmortizacion()
	{
		return $this->plazoAmortizacion;
	}

	/**
	 * @param double $plazoAmortizacion
	 */
	public function setPlazoAmortizacion($plazoAmortizacion)
	{
		$this->plazoAmortizacion = $plazoAmortizacion;
	}

	/*public function calcularProducto()
	{
		$this->producto['interest'] = $this->getEdad() / 100;
		$this->producto['capital_less_initial_amount'] = $this->getTipo() - $this->getAportacionInicial();
		$this->producto['interest_payment'][] = ($this->producto['capital_less_initial_amount'] * $this->producto['interest']) / 12;
		$this->producto['total_timelines'] = $this->getValorInmueble() * 12;
		$this->producto['apr'] = 1 - (1 / (pow(1 + ($this->producto['interest'] / 12), $this->producto['total_timelines'])));
		$this->producto['fee'] = $this->producto['interest_payment'][0] / $this->producto['apr'];
		$this->producto['interest_discharged'] = $this->producto['interest_payment'][0];
		$this->producto['interest_discharged_total'] = $this->producto['interest_payment'][0];
		$this->producto['capital_payment'][] = $this->producto['fee'] - $this->producto['interest_payment'][0];
		$this->producto['capital_discharged'] = $this->producto['capital_payment'][0];
		$this->producto['capital_discharged_total'] = $this->producto['capital_payment'][0];
		$this->producto['capital_pending'][] = $this->producto['capital_less_initial_amount'] - $this->producto['capital_discharged_total'];
		for ($i = 1, $j = 2; $i < $this->producto['total_timelines']; $i += 1) {
			$this->producto['interest_payment'][] = ($this->producto['capital_pending'][$i - 1] * $this->producto['interest']) / 12;
			$this->producto['interest_discharged'] += $this->producto['interest_payment'][$i];
			$this->producto['interest_discharged_total'] += $this->producto['interest_payment'][$i];
			$this->producto['capital_payment'][] = $this->producto['fee'] - $this->producto['interest_payment'][$i];
			$this->producto['capital_discharged'] += $this->producto['capital_payment'][$i];
			$this->producto['capital_discharged_total'] += $this->producto['capital_payment'][$i];
			$this->producto['capital_pending'][] = $this->producto['capital_less_initial_amount'] - $this->producto['capital_discharged_total'];
			if ($j < 12) {
				$j += 1;
			} else {
				$j = 1;
				$this->producto['interest_discharged_deadline'][] = $this->producto['interest_discharged'];
				$this->producto['interest_discharged'] = 0;
				$this->producto['interest_discharged_total_deadline'][] = $this->producto['interest_discharged_total'];
				$this->producto['capital_discharged_deadline'][] = $this->producto['capital_discharged'];
				$this->producto['capital_discharged'] = 0;
				$this->producto['capital_discharged_total_deadline'][] = $this->producto['capital_discharged_total'];
			}
		}
		return $this->producto;
	}*/
	public function calcularAvanzadaIA(EntityManagerInterface $entityManager)
	{
		$respuesta = array(
			'cuota_fija' => 0,
			'cuota_variable' => 0,
			'importe_maximo' => 0,
			'mensaje' => ''
		);

		// Leer parámetros desde Base de Datos
		$repoReglas = $entityManager->getRepository('AppBundle:ReglasNegocio');
		$reglasNegocio = $repoReglas->find(1);
		$repoProdHipotecario = $entityManager->getRepository('AppBundle:ProductoHipotecario');
		
		// Mantener importe maximo de la tabla antigua por ahora
		$repoCalcIA = $entityManager->getRepository('AppBundle:CalculadoraParametros');
		$paramImporteMaximo = $repoCalcIA->findOneBy(['perfilLaboral' => 'Importe Maximo', 'activo' => true]);

		// $datos = json_decode($request->getContent())->datos;
		$edad = $this->getEdad();
		$edadMaxima = $reglasNegocio ? $reglasNegocio->getEdadMaximaAlVencimiento() : 75;
		$amortizacion = $edadMaxima - $edad;
		if ($this->getTipo() == 2) {
			$tipo_calculo = 'importe-maximo';
			$precio_maximo = true;
		} else {
			$tipo_calculo = 'cuota';
			$precio_maximo = false;
		}
		$valor_inmueble = (is_null($this->getValorInmueble())) ? 0 : $this->getValorInmueble();
		$aportacion = (is_null($this->getAportacionInicial())) ? 0 : $this->getAportacionInicial();
		$tipo_hipoteca1 = $this->getProducto();
		switch ($tipo_hipoteca1) {
			case '1':
				$tipo_hipoteca = 'cien';
				break;
			case '2':
				$tipo_hipoteca = 'premium';
				break;
			case '3':
				$tipo_hipoteca = 'sin_compromiso';
				break;
			case '4':
				$tipo_hipoteca = 'cambio_casa';
				break;
		}
		// Vamos a ver segun la CCAA y si es nueva o usada los gastos e impuestos
		$ccaa = $this->getComunidadAutonoma();
		// $entityManager = $this->getDoctrine()->getManager();
		$parametrosObj = new Parametros();
		$parametros = $parametrosObj->obtenerParametros($entityManager);
		// var_dump($parametros);
		// die;
		$nombreCCAA = $parametrosObj->obtenerNombreCCAA($ccaa);
		// var_dump($nombreCCAA);
		// die;
		$nueva = $this->getObraNueva();
		$minusvaliaFamiliaNumerosa = $this->getMinusvaliaFamiliaNumerosa();
		$familiaNumerosa = $this->getFamiliaNumerosa();
		$vpo = $this->getVpo();
		$monoparental = $this->getMonoparental();
		$habitual = $this->getDestinoCompra()===1?true:false;

		$resTipoInteres = $this->obtenerInteres($entityManager, $reglasNegocio, $edad, $ccaa, $nueva, $vpo);
		$tipo_interes_ccaa = $resTipoInteres['tipo'];
		$respuesta['mensaje'] = $resTipoInteres['mensaje'];
		$bonificacion = $resTipoInteres['bonificacion'];
		// var_dump($tipo_interes_ccaa);
		// die;

		$gasto_inmobiliaria = (is_null($this->getHonorariosInmobiliaria())) ? 0 : $this->getHonorariosInmobiliaria();// * 1.21;
		$comision_apertura = $valor_inmueble * (is_null($parametros->getPorComisionApertura()) ? 0 : $parametros->getPorComisionApertura());
		$honorarios_financiacion = (is_null($parametros->getHonorariosFinanciacion())) ? 0 : $parametros->getHonorariosFinanciacion();
		$tasacion = (is_null($parametros->getTasacion())) ? 0 : $parametros->getTasacion();
		$vinculaciones = (is_null($parametros->getVinculaciones())) ? 0 : $parametros->getVinculaciones();
		$escritura_compra_notario = (is_null($parametros->getEscrituraCompraNotario())) ? 0 : $parametros->getEscrituraCompraNotario();
		$escritura_compra_registro = (is_null($parametros->getEscrituraCompraRegistro())) ? 0 : $parametros->getEscrituraCompraRegistro();
		$escritura_compra_gestoria = (is_null($parametros->getEscrituraCompraGestoria())) ? 0 : $parametros->getEscrituraCompraGestoria();
		$tipo_importe_maximo = (is_null($parametros->getInteresImporteMaximo())) ? 0 : $parametros->getInteresImporteMaximo();

		$valor_vivienda_actual = (is_null($this->getValorViviendaActual())) ? 0 : $this->getValorViviendaActual();
		$hipoteca_actual = (is_null($this->getHipotecaActual())) ? 0 : $this->getHipotecaActual();
		// $venta_casa_actual = (is_null($this->getVentaCasaActual())) ? 0 : $this->getVentaCasaActual();
		// $numero_pagas = 12;
		$aportacion_tras_venta = (is_null($this->getAportacionTrasVenta())) ? 0 : $this->getAportacionTrasVenta();
		$ingresos_mensuales = (is_null($this->getIngresosMensuales())) ? 0 : $this->getIngresosMensuales();
		$numero_pagas_extra = (is_null($this->getNumPagasExtra())) ? 0 : $this->getNumPagasExtra();
		$importe_paga_extra = (is_null($this->getImportePagaExtra())) ? 0 : $this->getImportePagaExtra();
		$prestamos_mensuales = (is_null($this->getPrestamosMensuales())) ? 0 : $this->getPrestamosMensuales();

		$ingresos_mensuales_dos = (is_null($this->getIngresosMensualesDos())) ? 0 : $this->getIngresosMensualesDos();
		$numero_pagas_extra_dos = (is_null($this->getNumPagasExtraDos())) ? 0 : $this->getNumPagasExtraDos();
		$importe_paga_extra_dos = (is_null($this->getImportePagaExtraDos())) ? 0 : $this->getImportePagaExtraDos();
		$prestamos_mensuales_dos = (is_null($this->getPrestamosMensualesDos())) ? 0 : $this->getPrestamosMensualesDos();

		$numTitulares = (is_null($this->getNumTitulares())) ? 0 : $this->getNumTitulares();
		$edadTitularUno = (is_null($this->getEdadTitularUno())) ? 0 : $this->getEdadTitularUno();
		$edadTitularDos = (is_null($this->getEdadTitularDos())) ? 0 : $this->getEdadTitularDos();

		$levantamiento_registral = 0;
		if ($amortizacion > 30) {
			$amortizacion = 30;
		} elseif ($amortizacion < 15) {
			$respuesta['mensaje'] = 'No es posible realizar la operación debido a la edad del cliente.';
			$respuesta['importe_fijo'] = 0;
			$respuesta['importe_variable'] = 0;
			$respuesta['amortizacion'] = 0;
			$respuesta['entrada'] = 0;
			$respuesta['con_interes_fijo'] = 0;
			$respuesta['con_interes_variable'] = 0;
			$respuesta['con_entrada_fijo'] = 0;
			$respuesta['con_entrada_variable'] = 0;
			$respuesta['tipo_calculo'] = 0;
			$respuesta['cuota_mixta'] = 0;
			$respuesta['gastos'] = 0;
			$respuesta['tipo_fijo'] = 0;
			$respuesta['tipo_variable'] = 0;
			$respuesta['tipo_mixto'] = 0;
			$respuesta['tipo_luego_mixto'] = 0;
			$respuesta['intereses'] = 0;
			$respuesta['importe_total'] = 0;
			$respuesta['tasacion'] = 0;
			$respuesta['vinculaciones'] = 0;
			$respuesta['notario'] = 0;
			$respuesta['registro'] = 0;
			$respuesta['gestoria'] = 0;
			$respuesta['obraNueva'] = 0;
			$respuesta['escritura_compra_impuesto_transmisiones'] = 0;
			$respuesta['importe_iva'] = 0;
			return $respuesta;
		}
		
		// if ($edad >= 35) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.08;
		// } elseif ($edad < 35 && $valor_inmueble <= 130000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.035;
		// } else {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.08;
		// }

		// Cambio a nueva calculadora
		// if ($edad >= 35 && $valor_inmueble <= 150000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		// } elseif ($edad >= 35 && $valor_inmueble > 150000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		// } elseif ($edad < 35 && $valor_inmueble <= 150000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.035;
		// } elseif ($edad < 35 && $valor_inmueble > 150000) {
		// 	$escritura_compra_impuesto_transmisiones = $valor_inmueble * 0.07;
		// }

		$valor_bonificacion = $valor_inmueble * $tipo_interes_ccaa * $bonificacion;
		$escritura_compra_impuesto_transmisiones = ($valor_inmueble * $tipo_interes_ccaa) - $valor_bonificacion;
		// echo "habitual: ".$habitual."<br>";
		// echo "edad: ".$habitual."<br>";
		// echo "minus: ".$minusvaliaFamiliaNumerosa."<br>";
		// echo "valor inmueble: ".$valor_inmueble."<br>";
		// echo "tipo_interes_ccaa: ".$tipo_interes_ccaa."<br>";
		// echo "valor_inmueble * tipo_interes_ccaa: ".$valor_inmueble * $tipo_interes_ccaa."<br>";

		// echo "bonificacion: ".$bonificacion."<br>";
		// echo "escritura_compra_impuesto_transmisiones: ".$escritura_compra_impuesto_transmisiones."<br>";
		// die;
		if ($nueva) {
			if (isset($nombreCCAA) && $nombreCCAA === 'Canarias') {
				// IGIC aplicable en Canarias
				$tipo_iva = 0.07;
			} elseif ($habitual && $vpo) {
				$tipo_iva = 0.04;
			} else {
				$tipo_iva = 0.1;
			}
			$importe_iva = $valor_inmueble * $tipo_iva;
		} else {
			$importe_iva = 0;
		}

		$escritura_prestamo_hipotecario_notario = 0; //1100; Nueva Ley
		$escritura_prestamo_hipotecario_registro = 0; // 300; Nueva Ley
		$escritura_prestamo_hipotecario_gestoria = 0; //300; Nueva Ley
		if ($edad < 35 && $valor_inmueble <= 150000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 16000 - $aportacion) * 1.3) * 0.003;
		} elseif ($valor_inmueble > 150000 && $valor_inmueble <= 200000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 25000 - $aportacion) * 1.3) * 0.015;
		} elseif ($valor_inmueble > 200000) {
			$escritura_prestamo_hipotecario_impuesto_ajd = (($valor_inmueble + 40000 - $aportacion) * 1.3) * 0.015;
		}
		$escritura_prestamo_hipotecario_impuesto_ajd = 0; // Nueva Ley
		if ($tipo_calculo === 'importe-maximo') {
			// $amortizacion = 75 - max($edadTitularUno, $edadTitularDos);
			// if ($amortizacion > 30) {
			// 	$amortizacion = 30;
			// }
			if($edadTitularDos > 0){
				$edad = min($edadTitularUno, $edadTitularDos);
			}else{
				$edad = $edadTitularUno;
			}
			$amortizacion = $this->getPlazoAmortizacion();
			$respuesta['mensaje'] = '';
			if ($numTitulares == 1){
				$cuota = (((($ingresos_mensuales * 12) + ($importe_paga_extra * $numero_pagas_extra)) / 12) * 0.35) - $prestamos_mensuales;
			}else{
				$cuota = ((($ingresos_mensuales * 12) + ($ingresos_mensuales_dos * 12) + ($importe_paga_extra * $numero_pagas_extra) + ($importe_paga_extra_dos * $numero_pagas_extra_dos)) /12 * 0.35) - $prestamos_mensuales - $prestamos_mensuales_dos;
			}
			// dump($cuota);
			// die;
			// $gastos = 15000;
			
			$interes_fijo = $tipo_importe_maximo; // Interes para este calculo sera 2% siempre, ni fijo ni variable
			if ($paramImporteMaximo && $paramImporteMaximo->getTasaInteres() > 0) {
				$interes_fijo = $paramImporteMaximo->getTasaInteres() / 100;
			}
			$interes_variable = 0.025;
			$interes_fijo_l = "1,5%";
			$interes_variable_l = "2,5%";
			$aportacion_inicial = $aportacion;
			
			$datos_calculo = array(
				'cuota' => $cuota,
				'entrada' => $aportacion_inicial,
				'intereses' => $interes_fijo,
				'plazo' => $amortizacion,
				'gastos' => 0,
				'edad' => $edad
			);
			$resultado_f = $this->calculoImporteMaximo($datos_calculo);
			// dump($resultado_f);
			// die;
			// $datos_calculo = array(
			// 	'cuota' => $cuota,
			// 	'entrada' => $aportacion_inicial,
			// 	'intereses' => $interes_variable,
			// 	'plazo' => $amortizacion,
			// 	'gastos' => $gastos
			// );
			// $resultado_v = $this->calculoImporteMaximo($datos_calculo);

			$valor_inmueble = $resultado_f['importe'];
			$gastosDiez = $resultado_f['gastos'];
			
			
			$resTipoInteres = $this->obtenerInteres($entityManager, $reglasNegocio, $edad, $ccaa, $nueva, $vpo);
			// dump($edad);
			// dump($valor_inmueble);
			// dump($resTipoInteres);
			// die();
			$tipo_interes_ccaa = $resTipoInteres['tipo'];
			$respuesta['mensaje'] = $resTipoInteres['mensaje'];
			$bonificacion = $resTipoInteres['bonificacion'];

			// $valor_inmueble_sin_entrada = $resultado_f['importe'] - $aportacion_inicial;
			$valor_bonificacion = $valor_inmueble * $tipo_interes_ccaa * $bonificacion;
			$escritura_compra_impuesto_transmisiones = ($valor_inmueble * $tipo_interes_ccaa) - $valor_bonificacion;

			
			
			if($nueva){
				$importe_iva = $valor_inmueble * $tipo_iva;
			}else{
				$importe_iva = 0;
			}

			$gastos = $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura + $importe_iva;

			$escritura_compra_impuesto_transmisiones_anteriores = $escritura_compra_impuesto_transmisiones;
			$importe_iva_anterior = $importe_iva;


			// Prueba
			$datos_calculo = array(
				'cuota' => $cuota,
				'entrada' => $aportacion_inicial,
				'intereses' => $interes_fijo,
				'plazo' => $amortizacion,
				'gastos' => $gastos,
				'edad' => $edad
			);
			$resultado_f = $this->calculoImporteMaximo($datos_calculo);
			// dump($resultado_f);
			// die();
			$valor_inmueble = $this->redondear500($resultado_f['importe']);
			$resTipoInteres = $this->obtenerInteres($entityManager, $reglasNegocio, $edad, $ccaa, $nueva, $vpo);
			$tipo_interes_ccaa = $resTipoInteres['tipo'];
			$respuesta['mensaje'] = $resTipoInteres['mensaje'];
			$bonificacion = $resTipoInteres['bonificacion'];

			// $valor_inmueble_sin_entrada = $resultado_f['importe'] - $aportacion_inicial;
			$valor_bonificacion = $valor_inmueble * $tipo_interes_ccaa * $bonificacion;
			$escritura_compra_impuesto_transmisiones = ($valor_inmueble * $tipo_interes_ccaa) - $valor_bonificacion;

			
			
			if($nueva){
				$importe_iva = $valor_inmueble * $tipo_iva;
			}else{
				$importe_iva = 0;
			}

			$gastos = $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura + $importe_iva;

			$escritura_compra_impuesto_transmisiones_anteriores = $escritura_compra_impuesto_transmisiones;
			$importe_iva_anterior = $importe_iva;
			// Fin prueba

			// $respuesta['importe_fijo'] = $this->redondear500($resultado_f['importe'] - $gastosDiez + $gastos);
			$respuesta['importe_fijo'] = $this->redondear500($resultado_f['importe']);
			$escritura_compra_impuesto_transmisiones = ($respuesta['importe_fijo'] * $tipo_interes_ccaa) - $valor_bonificacion;
			if($nueva){
				$importe_iva = $respuesta['importe_fijo'] * $tipo_iva;
			}else{
				$importe_iva = 0;
			}
			$gastos = $gastos - $escritura_compra_impuesto_transmisiones_anteriores + $escritura_compra_impuesto_transmisiones - $importe_iva_anterior + $importe_iva;
			// $respuesta['importe_fijo'] = $resultado_f['importe'];
			if ($edad < 35 && $resultado_f['importe'] <= 150000) {
				// $respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
			}
			// $respuesta['importe_variable'] = $resultado_v['importe'];
			// $respuesta['con_entrada_fijo'] = $resultado_f['conEntrada'];
			// $respuesta['con_entrada_variable'] = $resultado_v['conEntrada'];
			$respuesta['gastos'] = $gastos;

			$datos_calculo = array(
				'precio' => $respuesta['importe_fijo'] + $gastos,
				'entrada' => $aportacion_inicial,
				'intereses' => $interes_fijo,
				'plazo' => $amortizacion
			);
			
			$resultado = $this->calculoSencillo($datos_calculo);

			$respuesta['cuota'] = $resultado['cuota'];
			// $respuesta['con_interes_fijo'] = $resultado_f['interes'];
			// $respuesta['con_interes_variable'] = $resultado_v['interes'];
			$respuesta['entrada'] = $aportacion_inicial;
			$respuesta['tipo_calculo'] = $tipo_calculo;
			$respuesta['amortizacion'] = $amortizacion;

			$respuesta['tasacion'] = $tasacion;
			$respuesta['vinculaciones'] = $vinculaciones;
			$respuesta['notario'] = $escritura_compra_notario;
			$respuesta['registro'] = $escritura_compra_registro;
			$respuesta['gestoria'] = $escritura_compra_gestoria;
			$respuesta['obraNueva'] = $nueva;
			$respuesta['escritura_compra_impuesto_transmisiones'] = $escritura_compra_impuesto_transmisiones;
			$respuesta['importe_iva'] = $importe_iva;
			$respuesta['tipo_importe_maximo'] = $tipo_importe_maximo * 100;
			$respuesta['tipo_interes_ccaa'] = $tipo_interes_ccaa;
			return $respuesta;
		}
		if (isset($tipo_hipoteca)) {
			$productoDB = $repoProdHipotecario->findOneBy(['codigoProducto' => $tipo_hipoteca]);
			if ($productoDB) {
				$tipo_fijo = $productoDB->getTipoFijo() / 100;
				$tipo_variable = $productoDB->getTipoVariable() / 100;
				$tipo_mixto = $productoDB->getTipoMixto() / 100;
				
				$comision_apertura = ($productoDB->getComisionApertura() / 100) * $valor_inmueble;
				$vinculaciones = $productoDB->getPermiteVinculaciones() ? ($reglasNegocio ? 1 : 0) : 0; // Se podria coger de parametros si hubiese

				switch ($tipo_hipoteca) {
					case 'cien':
						$tipo_luego_mixto = 0.5;
						break;
					case 'premium':
						$tipo_luego_mixto = 0.3;
						$vinculaciones = 0;
						break;
					case 'sin_compromiso':
						$tipo_luego_mixto = 0.75;
						$vinculaciones = 0;
						break;
					case 'cambio_casa':
						$tipo_luego_mixto = 0.5;
						$levantamiento_registral = $productoDB->getLevantamientoRegistral();
						$tasacion = $tasacion * 2;
						$escritura_prestamo_hipotecario_notario = $escritura_prestamo_hipotecario_notario * 2;
						$escritura_prestamo_hipotecario_registro = $escritura_prestamo_hipotecario_registro * 2;
						$escritura_prestamo_hipotecario_gestoria = $escritura_prestamo_hipotecario_gestoria * 2;
						$escritura_prestamo_hipotecario_impuesto_ajd = 0; // Nueva Ley
						break;
				}
			}
		}
		if ($tipo_calculo === 'cuota' && isset($tipo_fijo) && isset($tipo_variable)) {
			if ($tipo_hipoteca != 'cambio_casa') {
				$gastos_totales = $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura + $importe_iva;

				// echo "Inmobiliaria: " . $gasto_inmobiliaria . "<br>";
				// echo "Honorarios financiacion: " . $honorarios_financiacion . "<br>";
				// echo "Tasacion: " . $tasacion . "<br>";
				// echo "Vinculaciones: " . $vinculaciones . "<br>";
				// echo "Notario: " . $escritura_compra_notario . "<br>";
				// echo "Registro: " . $escritura_compra_registro . "<br>";
				// echo "Gestoria: " . $escritura_compra_gestoria . "<br>";
				// echo "AJD/ITP: " . $escritura_compra_impuesto_transmisiones . "<br>";
				// echo "Comision apertura: " . $comision_apertura . "<br>";
				// echo "IVA: " . $importe_iva . "<br>";
				// echo "TOTAL: " . $gastos_totales . "<br>";
				// die;
				$valor_inmueble += $gastos_totales;
				$datos_calculo = array(
					'precio' => $valor_inmueble,
					'entrada' => $aportacion,
					'intereses' => $tipo_fijo,
					'plazo' => $amortizacion
				);
				$resultado = $this->calculoSencillo($datos_calculo);
				$respuesta['importe_fijo'] = $resultado['cuota'];
				if ($edad < 35 && $valor_inmueble <= 150000) {
					// $respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
				}
				// $respuesta['mensaje'] .= 'Esta calculadora sólo esta destinada para vivienda habitual (descartando locales, naves, segunda residencia, etc.), y no contempla excepciones (minusvalía, etc.).';
				$respuesta['cuota_fija'] = $resultado['cuota'];
				$respuesta['con_entrada_fijo'] = $resultado['conEntrada'];
				$respuesta['con_interes_fijo'] = $resultado['interes'];
				$respuesta['entrada'] = $aportacion;
				$datos_calculo = array(
					'precio' => $valor_inmueble,
					'entrada' => $aportacion,
					'intereses' => $tipo_variable,
					'plazo' => $amortizacion
				);
				$resultado = $this->calculoSencillo($datos_calculo);
				$respuesta['con_entrada_variable'] = $resultado['conEntrada'];
				$respuesta['con_interes_variable'] = $resultado['interes'];
				$respuesta['cuota_variable'] = $resultado['cuota'];
				$respuesta['tipo_calculo'] = $tipo_calculo;
				$respuesta['amortizacion'] = $amortizacion;
				$respuesta['importe_variable'] = $resultado['cuota'];
				$respuesta['importe_total'] = $valor_inmueble - $aportacion;
				$respuesta['gastos'] = $gastos_totales;
				$respuesta['intereses'] = $resultado['interes']-$respuesta['gastos']-$valor_inmueble;

				// Ahora calculamos el tipo mixto
				$datos_calculo = array(
					'precio' => $valor_inmueble,
					'entrada' => $aportacion,
					'intereses' => $tipo_mixto,
					'plazo' => $amortizacion
				);
				$resultado = $this->calculoSencillo($datos_calculo);
				$respuesta['cuota_mixta'] = $resultado['cuota'];
				$respuesta['tipo_luego_mixto'] = $tipo_luego_mixto;

				$respuesta['tipo_fijo'] = $tipo_fijo*100;
				$respuesta['tipo_variable'] = $tipo_variable*100;
				$respuesta['tipo_mixto'] = $tipo_mixto*100;
				$respuesta['tasacion'] = $tasacion;
				$respuesta['vinculaciones'] = $vinculaciones;
				$respuesta['notario'] = $escritura_compra_notario;
				$respuesta['registro'] = $escritura_compra_registro;
				$respuesta['gestoria'] = $escritura_compra_gestoria;
				$respuesta['obraNueva'] = $nueva;
				$respuesta['escritura_compra_impuesto_transmisiones'] = $escritura_compra_impuesto_transmisiones;
				$respuesta['importe_iva'] = $importe_iva;
				$respuesta['tipo_interes_ccaa'] = $tipo_interes_ccaa;
				return $respuesta;
			} else {
				// CAMBIO DE CASA:
				// Una persona de 30 años tiene una casa que vale 100000€ y le quedan 40000€ de hipoteca. Se quiere comprar una casa que vale 150000€
				// El precio serían los 150000 + 40000 + 15000(Gastos) - 10000 (aportación) = 195000€
				// Tenemos que calcular la cuota hasta la venta y la cuota después de la venta.
				// Si el importe total necesario (195000) supera el 80% del valor de las dos casas, mostrar mensaje de que se tiene que estudiar y se pongan en contacto.
				// Para el cálculo de la cuota antes de la venta tomamos como cantidad a financiar el 80% del valor de la casa a vender. Ahora calculamos con el interés fijo y variable con la carencia y con el importe restante.
				// La normal sería  con un interés fijo del 2,75% y un variable de 1,59%, el cálculo de la calculadora normal para 115000€ (195000 -80000) de importe, con entrada 0, el interés correspondiente y el plazo será de 30 años. Por lo que nos sale un importe de 401€ para variable y de 469€ para fijo. Ahora hay que sumarle la cuota de los 80000 de carencia con los interes correspondientes y la calculadora normal.
				// 80000  x 1.59% = 1272 /12 = 106€
				// 80000  x 2.75% = 2200 /12 = 183€
				// Sumando la carencia con la normal, la cuota a pagar hasta la venta sería de :
				// 507€ variable y 652€ fijo
				// // NOOOOOO Sin carencia serían 280€ para variable y 327€ de fijo por lo que al finalizar la carencia quedarían unas cuotas de 681€ (variable) y 796€ (fijo)
				// Tras la venta, si se vende por 100000 y se entregan los 100000, serían= 195000 - 100000 = 95000 Saldría 331€ variable y 387€ fijo
				$importe_total_inmuebles = $valor_inmueble + $valor_vivienda_actual;
				$ochenta_por_ciento_total = 0.8 * $importe_total_inmuebles;
				// echo "<br>Valor Inmueble: ".$valor_inmueble;
				// echo "<br>Hipoteca pendiente actual: ".$hipoteca_actual;
				// echo "<br>Gasto Inmobiliaria: ".$gasto_inmobiliaria;
				// echo "<br>Honorarios financiacion: ".$honorarios_financiacion;
				// echo "<br>Tasacion: ".$tasacion;
				// echo "<br>Escritura compra notario: ".$escritura_compra_notario;
				// echo "<br>Escritura compra registro: ".$escritura_compra_registro;
				// echo "<br>Escritura compra gestoria: ".$escritura_compra_gestoria;
				// echo "<br>Escritura compra impuesto transmisiones: ".$escritura_compra_impuesto_transmisiones;
				// echo "<br>Comision apertura: ".$comision_apertura;
				$gastos_totales = $gasto_inmobiliaria + $honorarios_financiacion + $tasacion + $vinculaciones + $escritura_compra_notario + $escritura_compra_registro + $escritura_compra_gestoria + $escritura_compra_impuesto_transmisiones + $comision_apertura +$levantamiento_registral + $importe_iva;
				$valor_inmueble += $gastos_totales+$hipoteca_actual-$aportacion;


				$respuesta['cuota_mixta_final'] = 0;
				$respuesta['gastos'] = $gastos_totales;
				$respuesta['hipoteca_actual'] = $hipoteca_actual;


				$respuesta['tipo_fijo'] = $tipo_fijo*100;
				$respuesta['tipo_variable'] = $tipo_variable*100;
				$respuesta['tipo_mixto'] = $tipo_mixto*100;
				$respuesta['tasacion'] = $tasacion;
				$respuesta['vinculaciones'] = $vinculaciones;
				$respuesta['notario'] = $escritura_compra_notario;
				$respuesta['registro'] = $escritura_compra_registro;
				$respuesta['gestoria'] = $escritura_compra_gestoria;
				$respuesta['obraNueva'] = $nueva;
				$respuesta['escritura_compra_impuesto_transmisiones'] = $escritura_compra_impuesto_transmisiones;
				$respuesta['importe_iva'] = $importe_iva;
				$respuesta['tipo_luego_mixto'] = $tipo_luego_mixto;
				$respuesta['intereses'] = 0;
				$respuesta['importe_total'] = $valor_inmueble;
				$respuesta['cuota_mixta'] = 0;
				$respuesta['tipo_interes_ccaa'] = $tipo_interes_ccaa;

				if ($valor_inmueble > $ochenta_por_ciento_total) {
					// echo "<br><br>Suma Valor Inmueble Total: ".$valor_inmueble;
					// echo "<br>Precio vivienda actual ".$valor_vivienda_actual;
					// echo "<br>80% total dos viviendas ".$ochenta_por_ciento_total;
					// die();
					$respuesta['mensaje'] = 'Con los datos facilitados no es posible ofrecerte un resultado, debe ser estudiado con más detalle por nuestros asesores. Por favor ponte en contacto con nosotros.';
					$respuesta['importe_fijo'] = 0;
					$respuesta['importe_variable'] = 0;
					$respuesta['amortizacion'] = 0;
					$respuesta['entrada'] = 0;
					$respuesta['con_interes_fijo'] = 0;
					$respuesta['con_interes_variable'] = 0;
					$respuesta['con_entrada_fijo'] = 0;
					$respuesta['con_entrada_variable'] = 0;
					$respuesta['tipo_calculo'] = 0;
					$respuesta['cuota_fija'] = 0;
					$respuesta['cuota_variable'] = 0;
					$respuesta['cuota_variable_final'] = 0;
					$respuesta['cuota_fija_final'] = 0;
					

					return $respuesta;
				}
				// Primero calculamos el importe antes de la venta
				// Y primero la cuota para el 80% del valor de la vivienda a vender
				$nuevo_interes_fijo = 0.0225;
				$nuevo_interes_variable = 0.0159;

				$valor_80_actual = 0.8 * $valor_vivienda_actual;
				$datos_calculo = array(
					'precio' => $valor_80_actual,
					'entrada' => 0,
					'intereses' => $tipo_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_80_casa_vender= $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_80_actual,
					'entrada' => 0,
					'intereses' => $tipo_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);

				$datos_calculo = array(
					'precio' => $valor_80_actual,
					'entrada' => 0,
					'intereses' => $tipo_mixto,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);

				$cuota_variable_80_casa_vender= $resultado['cuota'];
				$valor_resto = $valor_inmueble - $valor_80_actual;
				$datos_calculo = array(
					'precio' => $valor_resto,
					'entrada' => 0,
					'intereses' => $tipo_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_resto = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_resto,
					'entrada' => 0,
					'intereses' => $tipo_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_variable_resto = $resultado['cuota'];

				$datos_calculo = array(
					'precio' => $valor_resto,
					'entrada' => 0,
					'intereses' => $tipo_mixto,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_mixta_resto = $resultado['cuota'];


				$intereses_fijo_80_vender = $valor_80_actual * $tipo_fijo / 12;
				$intereses_variable_80_vender = $valor_80_actual * $tipo_variable / 12;
				$intereses_mixto_80_vender = $valor_80_actual * $tipo_mixto / 12;
				$cuota_fija_antes_venta = $cuota_fija_resto + $intereses_fijo_80_vender;
				$cuota_variable_antes_venta = $cuota_variable_resto + $intereses_variable_80_vender;
				$cuota_mixta_antes_venta = $cuota_mixta_resto + $intereses_mixto_80_vender;
				$valor_final = $valor_inmueble - $aportacion_tras_venta;
				$datos_calculo = array(
					'precio' => $valor_final,
					'entrada' => 0,
					'intereses' => $tipo_fijo,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_fija_final = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_final,
					'entrada' => 0,
					'intereses' => $tipo_variable,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_variable_final = $resultado['cuota'];
				$datos_calculo = array(
					'precio' => $valor_final,
					'entrada' => 0,
					'intereses' => $tipo_mixto,
					'plazo' => $amortizacion
				);
				
				$resultado = $this->calculoSencillo($datos_calculo);
				$cuota_mixta_final = $resultado['cuota'];
				if ($edad < 35 && $valor_inmueble <= 150000) {
					// $respuesta['mensaje'] = 'Tiene bonificación por ser menor de 35 años.';
				}
				// $respuesta['mensaje'] .= 'Esta calculadora sólo esta destinada para vivienda habitual (descartando locales, naves, segunda residencia, etc.), y no contempla excepciones (minusvalía, etc.).';
				$respuesta['importe_fijo'] = $cuota_fija_antes_venta;
				$respuesta['importe_variable'] = $cuota_variable_antes_venta;
				$respuesta['cuota_fija'] = $cuota_fija_antes_venta;
				$respuesta['cuota_variable'] = $cuota_variable_antes_venta;
				$respuesta['cuota_mixta'] = $cuota_mixta_antes_venta;
				$respuesta['cuota_fija_final'] = $cuota_fija_final;
				$respuesta['cuota_variable_final'] = $cuota_variable_final;
				$respuesta['cuota_mixta_final'] = $cuota_mixta_final;
				$respuesta['con_entrada_fijo'] = 0;
				$respuesta['con_interes_fijo'] = 0;
				$respuesta['entrada'] = $aportacion;
				$respuesta['con_entrada_variable'] = 0;
				$respuesta['con_interes_variable'] = 0;
				$respuesta['tipo_calculo'] = $tipo_calculo;
				$respuesta['amortizacion'] = $amortizacion;
				return $respuesta;
			}
		} else {
			return null;
		}
	}

		private function obtenerInteres($entityManager, $reglasNegocio, $edad, $ccaa_id, $nueva, $vpo)
	{
		$respuesta = ['tipo' => 0, 'bonificacion' => 0, 'mensaje' => ''];
		
		$repoImpuesto = $entityManager->getRepository('AppBundle:ImpuestoCcaa');
		$tipo_impuesto = $nueva ? 'AJD' : 'ITP';
		
		$impuesto = $repoImpuesto->findOneBy([
			'comunidadAutonoma' => $ccaa_id,
			'tipoImpuesto' => $tipo_impuesto
		]);
		
		if (!$impuesto) {
			$respuesta['tipo'] = $nueva ? 0.015 : 0.10; // Fallback por defecto si no existe en BD
			return $respuesta;
		}
		
		$respuesta['tipo'] = $impuesto->getPorcentajeDefecto();
		
		// Aplicar bonificaciones
		if ($reglasNegocio && $reglasNegocio->getEdadJovenFrontera()) {
			if ($edad < $reglasNegocio->getEdadJovenFrontera() && $impuesto->getPorcentajeBonificadoJovenes() !== null) {
				$respuesta['tipo'] = $impuesto->getPorcentajeBonificadoJovenes();
				$respuesta['mensaje'] = "Aplicado impuesto bonificado para menores de " . $reglasNegocio->getEdadJovenFrontera() . " años.";
			}
		}
		
		if ($vpo && $impuesto->getPorcentajeBonificadoVpo() !== null) {
			$respuesta['tipo'] = $impuesto->getPorcentajeBonificadoVpo();
			$respuesta['mensaje'] = "Aplicado impuesto bonificado para Vivienda de Protección Oficial.";
		}
		
		return $respuesta;
	}





		public function getValorViviendaActual()
	{
		return $this->valorViviendaActual;
	}

	/**
	 * Set the value of valorViviendaActual
	 *
	 * @return  self
	 */
	public function setValorViviendaActual($valorViviendaActual)
	{
		$this->valorViviendaActual = $valorViviendaActual;
		return $this;
	}

	/**
	 * Get the value of tipologiaOperacion
	 */ 
	public function getTipologiaOperacion()
	{
		return $this->tipologiaOperacion;
	}

	/**
	 * Set the value of tipologiaOperacion
	 *
	 * @return  self
	 */ 
	public function setTipologiaOperacion($tipologiaOperacion)
	{
		$this->tipologiaOperacion = $tipologiaOperacion;

		return $this;
	}

	/**
	 * Get the value of comunidadAutonoma
	 */ 
	public function getComunidadAutonoma()
	{
		return $this->comunidadAutonoma;
	}

	/**
	 * Set the value of comunidadAutonoma
	 *
	 * @return  self
	 */ 
	public function setComunidadAutonoma($comunidadAutonoma)
	{
		$this->comunidadAutonoma = $comunidadAutonoma;

		return $this;
	}

	/**
	 * Get the value of obraNueva
	 */ 
	public function getObraNueva()
	{
		return $this->obraNueva;
	}

	/**
	 * Set the value of obraNueva
	 *
	 * @return  self
	 */ 
	public function setObraNueva($obraNueva)
	{
		$this->obraNueva = $obraNueva;

		return $this;
	}

	/**
	 * Get the value of tieneMenosEdadMaxima
	 */ 
	public function getTieneMenosEdadMaxima()
	{
		return $this->tieneMenosEdadMaxima;
	}

	/**
	 * Set the value of tieneMenosEdadMaxima
	 *
	 * @return  self
	 */ 
	public function setTieneMenosEdadMaxima($tieneMenosEdadMaxima)
	{
		$this->tieneMenosEdadMaxima = $tieneMenosEdadMaxima;

		return $this;
	}

	/**
	 * Get the value of minusvaliaFamiliaNumerosa
	 */ 
	public function getMinusvaliaFamiliaNumerosa()
	{
		return $this->minusvaliaFamiliaNumerosa;
	}

	/**
	 * Set the value of minusvaliaFamiliaNumerosa
	 *
	 * @return  self
	 */ 
	public function setMinusvaliaFamiliaNumerosa($minusvaliaFamiliaNumerosa)
	{
		$this->minusvaliaFamiliaNumerosa = $minusvaliaFamiliaNumerosa;

		return $this;
	}

	/**
	 * Get the value of familiaNumerosa
	 */ 
	public function getFamiliaNumerosa()
	{
		return $this->familiaNumerosa;
	}

	/**
	 * Set the value of familiaNumerosa
	 *
	 * @return  self
	 */ 
	public function setFamiliaNumerosa($familiaNumerosa)
	{
		$this->familiaNumerosa = $familiaNumerosa;

		return $this;
	}

	/**
	 * Get the value of vpo
	 */ 
	public function getVpo()
	{
		return $this->vpo;
	}

	/**
	 * Set the value of vpo
	 *
	 * @return  self
	 */ 
	public function setVpo($vpo)
	{
		$this->vpo = $vpo;

		return $this;
	}

	/**
	 * Get the value of monoparental
	 */ 
	public function getMonoparental()
	{
		return $this->monoparental;
	}

	/**
	 * Set the value of monoparental
	 *
	 * @return  self
	 */ 
	public function setMonoparental($monoparental)
	{
		$this->monoparental = $monoparental;

		return $this;
	}

	/**
	 * Get the value of honorariosInmobiliaria
	 */ 
	public function getHonorariosInmobiliaria()
	{
		return $this->honorariosInmobiliaria;
	}

	/**
	 * Set the value of honorariosInmobiliaria
	 *
	 * @return  self
	 */ 
	public function setHonorariosInmobiliaria($honorariosInmobiliaria)
	{
		$this->honorariosInmobiliaria = $honorariosInmobiliaria;

		return $this;
	}

	/**
	 * Get the value of destinoCompra
	 */ 
	public function getDestinoCompra()
	{
		return $this->destinoCompra;
	}

	/**
	 * Set the value of destinoCompra
	 *
	 * @return  self
	 */ 
	public function setDestinoCompra($destinoCompra)
	{
		$this->destinoCompra = $destinoCompra;

		return $this;
	}

	/**
	 * @return double
	 */
	public function getEdadTitularUno()
	{
		return $this->edadTitularUno;
	}

	/**
	 * @param double $edadTitularUno
	 */
	public function setEdadTitularUno($edadTitularUno)
	{
		$this->edadTitularUno = $edadTitularUno;
	}

	/**
	 * @return double
	 */
	public function getEdadTitularDos()
	{
		return $this->edadTitularDos;
	}

	/**
	 * @param double $edadTitularDos
	 */
	public function setEdadTitularDos($edadTitularDos)
	{
		$this->edadTitularDos = $edadTitularDos;
	}

	/**
	 * Get the value of numTitulares
	 */ 
	public function getNumTitulares()
	{
		return $this->numTitulares;
	}

	/**
	 * Set the value of numTitulares
	 *
	 * @return  self
	 */ 
	public function setNumTitulares($numTitulares)
	{
		$this->numTitulares = $numTitulares;

		return $this;
	}

	/**
	 * Get the text value of destinoCompra
	 */ 
	public function getTextDestinoCompra()
	{
		switch ($this->destinoCompra) {
			case '1':
				return 'Vivienda habitual';
				break;
			case '2':
				return 'Segunda residencia';
				break;
			case '3':
				return 'Inversión';
				break;
			case '4':
				return 'Otros';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of obraNueva
	 */ 
	public function getTextObraNueva()
	{
		switch ($this->obraNueva) {
			case '1':
				return 'Sí';
				break;
			case '0':
				return 'No';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of comunidadAutonoma
	 */ 
	public function getTextComunidadAutonoma()
	{
		switch ($this->comunidadAutonoma) {
			case '1':
				return 'Andalucía';
				break;
			case '2':
				return 'Aragón';
				break;
			case '3':
				return 'Asturias';
				break;
			case '4':
				return 'Baleares';
				break;
			case '5':
				return 'Canarias';
				break;
			case '6':
				return 'Cantabria';
				break;
			case '7':
				return 'Castilla-La Mancha';
				break;
			case '8':
				return 'Castilla y León';
				break;
			case '9':
				return 'Cataluña';
				break;
			case '11':
				return 'Comunidad Valenciana';
				break;
			case '12':
				return 'Extremadura';
				break;
			case '13':
				return 'Galicia';
				break;
			case '14':
				return 'La Rioja';
				break;
			case '15':
				return 'Madrid';
				break;
			case '17':
				return 'Murcia';
				break;
			case '18':
				return 'Navarra';
				break;
			case '19':
				return 'País Vasco';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of minusvaliaFamiliaNumerosa
	 */ 
	public function getTextMinusvaliaFamiliaNumerosa()
	{
		switch ($this->minusvaliaFamiliaNumerosa) {
			case '1':
				return 'Sí';
				break;
			case '0':
				return 'No';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of familiaNumerosa
	 */ 
	public function getTextFamiliaNumerosa()
	{
		switch ($this->familiaNumerosa) {
			case '1':
				return 'Sí';
				break;
			case '0':
				return 'No';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of monoparental
	 */ 
	public function getTextMonoparental()
	{
		switch ($this->monoparental) {
			case '1':
				return 'Sí';
				break;
			case '0':
				return 'No';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of vpo
	 */ 
	public function getTextVpo()
	{
		switch ($this->vpo) {
			case '1':
				return 'Sí';
				break;
			case '0':
				return 'No';
				break;
			default:
				return '';
				break;
		}
	}

	/**
	 * Get the text value of producto
	 */ 
	public function getTextProducto()
	{
		switch ($this->producto) {
			case '1':
				return 'Hipoteca + 80%';
				break;
			case '2':
				return 'Premium';
				break;
			case '3':
				return 'Sin Compromiso';
				break;
			case '4':
				return 'Cambio de casa';
				break;
			default:
				return '';
				break;
		}
	}
	
}



