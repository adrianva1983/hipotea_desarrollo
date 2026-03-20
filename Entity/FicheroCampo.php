<?php

namespace AppBundle\Entity;

/**
 * FicheroCampo
 */
class FicheroCampo
{
	/**
	 * @var integer
	 */
	private $idFicheroCampo;

	/**
	 * @var string
	 */
	private $nombreFichero;

	/**
	 * @var CampoHito
	 */
	private $idCampoHito;

	/**
	 * @var CampoHitoExpediente
	 */
	private $idCampoHitoExpediente;

	/**
	 * @var Expediente
	 */
	private $idExpediente;

	private $fichero;

	/**
	 * @var string
	 */
	private $contenidoJson;


	/**
	 * Get idFicheroCampo
	 *
	 * @return integer
	 */
	public function getIdFicheroCampo()
	{
		return $this->idFicheroCampo;
	}

	/**
	 * Set nombreFichero
	 *
	 * @param string $nombreFichero
	 *
	 * @return FicheroCampo
	 */
	public function setNombreFichero($nombreFichero)
	{
		$this->nombreFichero = $nombreFichero;

		return $this;
	}

	/**
	 * Get nombreFichero
	 *
	 * @return string
	 */
	public function getNombreFichero()
	{
		return $this->nombreFichero;
	}

	/**
	 * Set idCampoHito
	 *
	 * @param CampoHito $idCampoHito
	 *
	 * @return FicheroCampo
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
	 * Set idCampoHitoExpediente
	 *
	 * @param CampoHitoExpediente $idCampoHitoExpediente
	 *
	 * @return FicheroCampo
	 */
	public function setIdCampoHitoExpediente(CampoHitoExpediente $idCampoHitoExpediente = null)
	{
		$this->idCampoHitoExpediente = $idCampoHitoExpediente;

		return $this;
	}

	/**
	 * Get idCampoHitoExpediente
	 *
	 * @return CampoHitoExpediente
	 */
	public function getIdCampoHitoExpediente()
	{
		return $this->idCampoHitoExpediente;
	}

	/**
	 * Set idExpediente
	 *
	 * @param Expediente $idExpediente
	 *
	 * @return FicheroCampo
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

	public function getFichero()
	{
		return $this->fichero;
	}

	public function setFichero($fichero)
	{
		$this->fichero = $fichero;

		return $this;
	}

	/**
	 * Set contenidoJson
	 *
	 * @param string $contenidoJson
	 *
	 * @return FicheroCampo
	 */
	public function setContenidoJson($contenidoJson)
	{
		$this->contenidoJson = $contenidoJson;

		return $this;
	}

	/**
	 * Get contenidoJson
	 *
	 * @return string
	 */
	public function getContenidoJson()
	{
		return $this->contenidoJson;
	}
}
