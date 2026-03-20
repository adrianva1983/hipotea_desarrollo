<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Factura
 */
class Factura
{
	/**
	 * @var integer
	 */
	private $idFactura;

	/**
	 * @var DateTime
	 */
	private $fecha;

	/**
	 * @var string
	 */
	private $serie;

	/**
	 * @var integer
	 */
	private $numero;

	/**
	 * @var float
	 */
	private $baseImponible = '0';

	/**
	 * @var float
	 */
	private $impuestos = '0';

	/**
	 * @var float
	 */
	private $retenciones = '0';

	/**
	 * @var float
	 */
	private $total = '0';

	/**
	 * @var string
	 */
	private $descripcion;

	/**
	 * @var ClienteFactura
	 */
	private $idClienteFactura;

	/**
	 * @var EmisorFactura
	 */
	private $idEmisorFactura;


	/**
	 * Get idFactura
	 *
	 * @return integer
	 */
	public function getIdFactura()
	{
		return $this->idFactura;
	}

	/**
	 * Set fecha
	 *
	 * @param DateTime $fecha
	 *
	 * @return Factura
	 */
	public function setFecha($fecha)
	{
		$this->fecha = $fecha;

		return $this;
	}

	/**
	 * Get fecha
	 *
	 * @return DateTime
	 */
	public function getFecha()
	{
		return $this->fecha;
	}

	/**
	 * Set serie
	 *
	 * @param string $serie
	 *
	 * @return Factura
	 */
	public function setSerie($serie)
	{
		$this->serie = $serie;

		return $this;
	}

	/**
	 * Get serie
	 *
	 * @return string
	 */
	public function getSerie()
	{
		return $this->serie;
	}

	/**
	 * Set numero
	 *
	 * @param integer $numero
	 *
	 * @return Factura
	 */
	public function setNumero($numero)
	{
		$this->numero = $numero;

		return $this;
	}

	/**
	 * Get numero
	 *
	 * @return integer
	 */
	public function getNumero()
	{
		return $this->numero;
	}

	/**
	 * Set baseImponible
	 *
	 * @param float $baseImponible
	 *
	 * @return Factura
	 */
	public function setBaseImponible($baseImponible)
	{
		$this->baseImponible = $baseImponible;

		return $this;
	}

	/**
	 * Get baseImponible
	 *
	 * @return float
	 */
	public function getBaseImponible()
	{
		return $this->baseImponible;
	}

	/**
	 * Set impuestos
	 *
	 * @param float $impuestos
	 *
	 * @return Factura
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
	 * Set retenciones
	 *
	 * @param float $retenciones
	 *
	 * @return Factura
	 */
	public function setRetenciones($retenciones)
	{
		$this->retenciones = $retenciones;

		return $this;
	}

	/**
	 * Get retenciones
	 *
	 * @return float
	 */
	public function getRetenciones()
	{
		return $this->retenciones;
	}

	/**
	 * Set total
	 *
	 * @param float $total
	 *
	 * @return Factura
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
	 * Set descripcion
	 *
	 * @param string $descripcion
	 *
	 * @return Factura
	 */
	public function setDescripcion($descripcion)
	{
		$this->descripcion = $descripcion;

		return $this;
	}

	/**
	 * Get descripcion
	 *
	 * @return string
	 */
	public function getDescripcion()
	{
		return $this->descripcion;
	}

	/**
	 * Set idClienteFactura
	 *
	 * @param ClienteFactura $idClienteFactura
	 *
	 * @return Factura
	 */
	public function setIdClienteFactura(ClienteFactura $idClienteFactura = null)
	{
		$this->idClienteFactura = $idClienteFactura;

		return $this;
	}

	/**
	 * Get idClienteFactura
	 *
	 * @return ClienteFactura
	 */
	public function getIdClienteFactura()
	{
		return $this->idClienteFactura;
	}

	/**
	 * Set idEmisorFactura
	 *
	 * @param EmisorFactura $idEmisorFactura
	 *
	 * @return Factura
	 */
	public function setIdEmisorFactura(EmisorFactura $idEmisorFactura = null)
	{
		$this->idEmisorFactura = $idEmisorFactura;

		return $this;
	}

	/**
	 * Get idEmisorFactura
	 *
	 * @return EmisorFactura
	 */
	public function getIdEmisorFactura()
	{
		return $this->idEmisorFactura;
	}
}
