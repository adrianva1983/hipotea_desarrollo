<?php

namespace AppBundle\Entity;

/**
 * LineaFactura
 */
class ConceptoSeguimientoExpediente
{
	/**
	 * @var integer
	 */
	private $idConceptoSeguimientoExpediente;

	/**
	 * @var string
	 */
	private $fase;

    /**
	 * @var string
	 */
	private $concepto;

	/**
	 * @var integer
	 */
	private $orden = '1';

	

	/**
	 * Get the value of idConceptoSeguimientoExpediente
	 *
	 * @return  integer
	 */ 
	public function getIdConceptoSeguimientoExpediente()
	{
		return $this->idConceptoSeguimientoExpediente;
	}

	/**
	 * Set the value of idConceptoSeguimientoExpediente
	 *
	 * @param  integer  $idConceptoSeguimientoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdConceptoSeguimientoExpediente($idConceptoSeguimientoExpediente)
	{
		$this->idConceptoSeguimientoExpediente = $idConceptoSeguimientoExpediente;

		return $this;
	}

	/**
	 * Get the value of fase
	 *
	 * @return  string
	 */ 
	public function getFase()
	{
		return $this->fase;
	}

	/**
	 * Set the value of fase
	 *
	 * @param  string  $fase
	 *
	 * @return  self
	 */ 
	public function setFase(string $fase)
	{
		$this->fase = $fase;

		return $this;
	}

	/**
	 * Get the value of concepto
	 *
	 * @return  string
	 */ 
	public function getConcepto()
	{
		return $this->concepto;
	}

	/**
	 * Set the value of concepto
	 *
	 * @param  string  $concepto
	 *
	 * @return  self
	 */ 
	public function setConcepto(string $concepto)
	{
		$this->concepto = $concepto;

		return $this;
	}

	/**
	 * Get the value of orden
	 *
	 * @return  integer
	 */ 
	public function getOrden()
	{
		return $this->orden;
	}

	/**
	 * Set the value of orden
	 *
	 * @param  integer  $orden
	 *
	 * @return  self
	 */ 
	public function setOrden($orden)
	{
		$this->orden = $orden;

		return $this;
    }
    
    public function __toString()
	{
		return $this->concepto;
	}
}
