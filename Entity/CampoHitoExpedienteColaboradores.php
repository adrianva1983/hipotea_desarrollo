<?php

namespace AppBundle\Entity;

/**
 * HitoExpediente
 */
class CampoHitoExpedienteColaboradores
{
	/**
	 * @var integer
	 */
	private $idCampoHitoExpedienteColaboradores;

	/**
	 * @var EntidadColaboradora
	 */
	private $idEntidadColaboradora;

	/**
	 * @var AgenteColaborador
	 */
	private $idAgenteColaborador;

	/**
	 * @var CampoHito
	 */
	private $idCampoHito;

	/**
	 * @var HitoExpediente
	 */
	private $idHitoExpediente;

	/**
	 * @var Expediente
	 */
	private $idExpediente;


	/**
	 * Get idCampoHitoExpedienteColaboradores
	 *
	 * @return integer
	 */
	public function getIdCampoHitoExpedienteColaboradores()
	{
		return $this->idCampoHitoExpedienteColaboradores;
	}

	/**
	 * Set idEntidadColaboradora
	 *
	 * @param EntidadColaboradora $idEntidadColaboradora
	 *
	 * @return CampoHitoExpedienteColaboradores
	 */
	public function setIdEntidadColaboradora(EntidadColaboradora $idEntidadColaboradora = null)
	{
		$this->idEntidadColaboradora = $idEntidadColaboradora;

		return $this;
	}

	/**
	 * Get idEntidadColaboradora
	 *
	 * @return EntidadColaboradora
	 */
	public function getIdEntidadColaboradora()
	{
		return $this->idEntidadColaboradora;
	}

	/**
	 * Set idAgenteColaborador
	 *
	 * @param AgenteColaborador $idAgenteColaborador
	 *
	 * @return CampoHitoExpedienteColaboradores
	 */
	public function setIdAgenteColaborador(AgenteColaborador $idAgenteColaborador = null)
	{
		$this->idAgenteColaborador = $idAgenteColaborador;

		return $this;
	}

	/**
	 * Get idAgenteColaborador
	 *
	 * @return AgenteColaborador
	 */
	public function getIdAgenteColaborador()
	{
		return $this->idAgenteColaborador;
	}

	/**
	 * Set idCampoHito
	 *
	 * @param CampoHito $idCampoHito
	 *
	 * @return CampoHitoExpedienteColaboradores
	 */
	public function setIdCampoHito(CampoHito $idCampoHito = null)
	{
		$this->idCampoHito = $idCampoHito;

		return $this;
	}

	/**
	 * Get idCampoHito
	 *
	 * @return CampoHito
	 */
	public function getIdCampoHito()
	{
		return $this->idCampoHito;
	}

	/**
	 * Set idHitoExpediente
	 *
	 * @param HitoExpediente $idHitoExpediente
	 *
	 * @return CampoHitoExpedienteColaboradores
	 */
	public function setIdHitoExpediente(HitoExpediente $idHitoExpediente = null)
	{
		$this->idHitoExpediente = $idHitoExpediente;

		return $this;
	}

	/**
	 * Get idHitoExpediente
	 *
	 * @return HitoExpediente
	 */
	public function getIdHitoExpediente()
	{
		return $this->idHitoExpediente;
	}

	/**
	 * Set idExpediente
	 *
	 * @param Expediente $idExpediente
	 *
	 * @return CampoHitoExpedienteColaboradores
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
}
