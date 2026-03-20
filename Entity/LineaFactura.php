<?php

namespace AppBundle\Entity;

/**
 * LineaFactura
 */
class LineaFactura
{
	/**
	 * @var integer
	 */
	private $idLineaFactura;

	/**
	 * @var integer
	 */
	private $lineaFactura;

	/**
	 * @var string
	 */
	private $concepto;

	/**
	 * @var integer
	 */
	private $unidades = '1';

	/**
	 * @var float
	 */
	private $importe = '0';

	/**
	 * @var integer
	 */
	private $tipoIva = '21';

	/**
	 * @var float
	 */
	private $impuestos = '0';

	/**
	 * @var float
	 */
	private $tipoRetencion = '0';

	/**
	 * @var float
	 */
	private $retencion = '0';

	/**
	 * @var float
	 */
	private $totalBaseImponible = '0';

	/**
	 * @var float
	 */
	private $total = '0';

	/**
	 * @var Factura
	 */
	private $idFactura;


	/**
	 * Get idLineaFactura
	 *
	 * @return integer
	 */
	public function getIdLineaFactura()
	{
		return $this->idLineaFactura;
	}

	/**
	 * Set lineaFactura
	 *
	 * @param integer $lineaFactura
	 *
	 * @return LineaFactura
	 */
	public function setLineaFactura($lineaFactura)
	{
		$this->lineaFactura = $lineaFactura;

		return $this;
	}

	/**
	 * Get lineaFactura
	 *
	 * @return integer
	 */
	public function getLineaFactura()
	{
		return $this->lineaFactura;
	}

	/**
	 * Set concepto
	 *
	 * @param string $concepto
	 *
	 * @return LineaFactura
	 */
	public function setConcepto($concepto)
	{
		$this->concepto = $concepto;

		return $this;
	}

	/**
	 * Get concepto
	 *
	 * @return string
	 */
	public function getConcepto()
	{
		return $this->concepto;
	}

	/**
	 * Set unidades
	 *
	 * @param integer $unidades
	 *
	 * @return LineaFactura
	 */
	public function setUnidades($unidades)
	{
		$this->unidades = $unidades;

		return $this;
	}

	/**
	 * Get unidades
	 *
	 * @return integer
	 */
	public function getUnidades()
	{
		return $this->unidades;
	}

	/**
	 * Set importe
	 *
	 * @param float $importe
	 *
	 * @return LineaFactura
	 */
	public function setImporte($importe)
	{
		$this->importe = $importe;

		return $this;
	}

	/**
	 * Get importe
	 *
	 * @return float
	 */
	public function getImporte()
	{
		return $this->importe;
	}

	/**
	 * Set tipoIva
	 *
	 * @param integer $tipoIva
	 *
	 * @return LineaFactura
	 */
	public function setTipoIva($tipoIva)
	{
		$this->tipoIva = $tipoIva;

		return $this;
	}

	/**
	 * Get tipoIva
	 *
	 * @return integer
	 */
	public function getTipoIva()
	{
		return $this->tipoIva;
	}

	/**
	 * Set impuestos
	 *
	 * @param float $impuestos
	 *
	 * @return LineaFactura
	 */
	public function setImpuestos($impuestos)
	{
		$this->impuestos = $impuestos;

		return $this;
	}

	/**
	 * Get impuestos
	 *
	 * @return float
	 */
	public function getImpuestos()
	{
		return $this->impuestos;
	}

	/**
	 * Set tipoRetencion
	 *
	 * @param float $tipoRetencion
	 *
	 * @return LineaFactura
	 */
	public function setTipoRetencion($tipoRetencion)
	{
		$this->tipoRetencion = $tipoRetencion;

		return $this;
	}

	/**
	 * Get tipoRetencion
	 *
	 * @return float
	 */
	public function getTipoRetencion()
	{
		return $this->tipoRetencion;
	}

	/**
	 * Set retencion
	 *
	 * @param float $retencion
	 *
	 * @return LineaFactura
	 */
	public function setRetencion($retencion)
	{
		$this->retencion = $retencion;

		return $this;
	}

	/**
	 * Get retencion
	 *
	 * @return float
	 */
	public function getRetencion()
	{
		return $this->retencion;
	}

	/**
	 * Set totalBaseImponible
	 *
	 * @param float $totalBaseImponible
	 *
	 * @return LineaFactura
	 */
	public function setTotalBaseImponible($totalBaseImponible)
	{
		$this->totalBaseImponible = $totalBaseImponible;

		return $this;
	}

	/**
	 * Get totalBaseImponible
	 *
	 * @return float
	 */
	public function getTotalBaseImponible()
	{
		return $this->totalBaseImponible;
	}

	/**
	 * Set total
	 *
	 * @param float $total
	 *
	 * @return LineaFactura
	 */
	public function setTotal($total)
	{
		$this->total = $total;

		return $this;
	}

	/**
	 * Get total
	 *
	 * @return float
	 */
	public function getTotal()
	{
		return $this->total;
	}

	/**
	 * Set idFactura
	 *
	 * @param Factura $idFactura
	 *
	 * @return LineaFactura
	 */
	public function setIdFactura(Factura $idFactura = null)
	{
		$this->idFactura = $idFactura;

		return $this;
	}

	/**
	 * Get idFactura
	 *
	 * @return Factura
	 */
	public function getIdFactura()
	{
		return $this->idFactura;
	}
}
