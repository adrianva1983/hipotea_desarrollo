<?php

namespace AppBundle\Entity;

class CalculadoraSencilla
{
	private $precioTotal;
	private $aportacionInicial;
	private $tasaInteres;
	private $plazoAmortizacion;
	private $hipoteca;

	/**
	 * @return double
	 */
	public function getPrecioTotal()
	{
		return $this->precioTotal;
	}

	/**
	 * @param double $precioTotal
	 */
	public function setPrecioTotal($precioTotal)
	{
		$this->precioTotal = $precioTotal;
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
	public function getTasaInteres()
	{
		return $this->tasaInteres;
	}

	/**
	 * @param double $tasaInteres
	 */
	public function setTasaInteres($tasaInteres)
	{
		$this->tasaInteres = $tasaInteres;
	}

	/**
	 * @return integer
	 */
	public function getPlazoAmortizacion()
	{
		return $this->plazoAmortizacion;
	}

	/**
	 * @param integer $plazoAmortizacion
	 */
	public function setPlazoAmortizacion($plazoAmortizacion)
	{
		$this->plazoAmortizacion = $plazoAmortizacion;
	}

	/**
	 * @return mixed
	 */
	public function getHipoteca()
	{
		return $this->hipoteca;
	}

	public function calcularHipoteca()
	{
		$this->hipoteca['interest'] = $this->getTasaInteres() / 100;
		$this->hipoteca['capital_less_initial_amount'] = $this->getPrecioTotal() - $this->getAportacionInicial();
		$this->hipoteca['interest_payment'][] = ($this->hipoteca['capital_less_initial_amount'] * $this->hipoteca['interest']) / 12;
		$this->hipoteca['total_timelines'] = $this->getPlazoAmortizacion() * 12;
		$this->hipoteca['apr'] = 1 - (1 / (pow(1 + ($this->hipoteca['interest'] / 12), $this->hipoteca['total_timelines'])));
		$this->hipoteca['fee'] = $this->hipoteca['interest_payment'][0] / $this->hipoteca['apr'];
		$this->hipoteca['interest_discharged'] = $this->hipoteca['interest_payment'][0];
		$this->hipoteca['interest_discharged_total'] = $this->hipoteca['interest_payment'][0];
		$this->hipoteca['capital_payment'][] = $this->hipoteca['fee'] - $this->hipoteca['interest_payment'][0];
		$this->hipoteca['capital_discharged'] = $this->hipoteca['capital_payment'][0];
		$this->hipoteca['capital_discharged_total'] = $this->hipoteca['capital_payment'][0];
		$this->hipoteca['capital_pending'][] = $this->hipoteca['capital_less_initial_amount'] - $this->hipoteca['capital_discharged_total'];
		for ($i = 1, $j = 2; $i < $this->hipoteca['total_timelines']; $i += 1) {
			$this->hipoteca['interest_payment'][] = ($this->hipoteca['capital_pending'][$i - 1] * $this->hipoteca['interest']) / 12;
			$this->hipoteca['interest_discharged'] += $this->hipoteca['interest_payment'][$i];
			$this->hipoteca['interest_discharged_total'] += $this->hipoteca['interest_payment'][$i];
			$this->hipoteca['capital_payment'][] = $this->hipoteca['fee'] - $this->hipoteca['interest_payment'][$i];
			$this->hipoteca['capital_discharged'] += $this->hipoteca['capital_payment'][$i];
			$this->hipoteca['capital_discharged_total'] += $this->hipoteca['capital_payment'][$i];
			$this->hipoteca['capital_pending'][] = $this->hipoteca['capital_less_initial_amount'] - $this->hipoteca['capital_discharged_total'];
			if ($j < 12) {
				$j += 1;
			} else {
				$j = 1;
				$this->hipoteca['interest_discharged_deadline'][] = $this->hipoteca['interest_discharged'];
				$this->hipoteca['interest_discharged'] = 0;
				$this->hipoteca['interest_discharged_total_deadline'][] = $this->hipoteca['interest_discharged_total'];
				$this->hipoteca['capital_discharged_deadline'][] = $this->hipoteca['capital_discharged'];
				$this->hipoteca['capital_discharged'] = 0;
				$this->hipoteca['capital_discharged_total_deadline'][] = $this->hipoteca['capital_discharged_total'];
			}
		}
		return $this->hipoteca;
	}
}
