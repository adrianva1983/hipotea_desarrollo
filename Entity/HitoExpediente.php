<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * HitoExpediente
 */
class HitoExpediente
{
	/**
	 * @var integer
	 */
	private $idHitoExpediente;

	/**
	 * @var boolean
	 */
	private $estado = '0';

	/**
	 * @var DateTime
	 */
	private $fechaModificacion;

	/**
	 * @var Expediente
	 */
	private $idExpediente;

	/**
	 * @var Hito
	 */
	private $idHito;


	/**
	 * Get idHitoExpediente
	 *
	 * @return integer
	 */
	public function getIdHitoExpediente()
	{
		return $this->idHitoExpediente;
	}

	/**
	 * Set estado
	 *
	 * @param boolean $estado
	 *
	 * @return HitoExpediente
	 */
	public function setEstado($estado)
	{
		$this->estado = $estado;

		return $this;
	}

	/**
	 * Get estado
	 *
	 * @return boolean
	 */
	public function getEstado()
	{
		return $this->estado;
	}

	/**
	 * Set fechaModificacion
	 *
	 * @param DateTime $fechaModificacion
	 *
	 * @return HitoExpediente
	 */
	public function setFechaModificacion($fechaModificacion)
	{
		$this->fechaModificacion = $fechaModificacion;

		return $this;
	}

	/**
	 * Get fechaModificacion
	 *
	 * @return DateTime
	 */
	public function getFechaModificacion()
	{
		return $this->fechaModificacion;
	}

	/**
	 * Set idExpediente
	 *
	 * @param Expediente $idExpediente
	 *
	 * @return HitoExpediente
	 */
	public function setIdExpediente(Expediente $idExpediente = null)
	{
		$this->idExpediente = $idExpediente;

		return $this;
	}

	/**
	 * Get idExpediente
	 *
	 * @return Expediente
	 */
	public function getIdExpediente()
	{
		return $this->idExpediente;
	}

	/**
	 * Set idHito
	 *
	 * @param Hito $idHito
	 *
	 * @return HitoExpediente
	 */
	public function setIdHito(Hito $idHito = null)
	{
		$this->idHito = $idHito;

		return $this;
	}

	/**
	 * Get idHito
	 *
	 * @return Hito
	 */
	public function getIdHito()
	{
		return $this->idHito;
	}
}
