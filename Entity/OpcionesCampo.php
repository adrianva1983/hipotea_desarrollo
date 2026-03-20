<?php

namespace AppBundle\Entity;

/**
 * OpcionesCampo
 */
class OpcionesCampo
{
	/**
	 * @var integer
	 */
	private $idOpcionesCampo;

	/**
	 * @var integer
	 */
	private $orden;

	/**
	 * @var string
	 */
	private $valor;

	/**
	 * @var CampoHito
	 */
	private $idCampoHito;

	/**
	 * @var string
	 */
	private $idHitoCondicional;

	/**
	 * @var string
	 */
	private $idCampoCondicional;


	/**
	 * Get idOpcionesCampo
	 *
	 * @return integer
	 */
	public function getIdOpcionesCampo()
	{
		return $this->idOpcionesCampo;
	}

	/**
	 * Set orden
	 *
	 * @param integer $orden
	 *
	 * @return OpcionesCampo
	 */
	public function setOrden($orden)
	{
		$this->orden = $orden;

		return $this;
	}

	/**
	 * Get orden
	 *
	 * @return integer
	 */
	public function getOrden()
	{
		return $this->orden;
	}

	/**
	 * Set valor
	 *
	 * @param string $valor
	 *
	 * @return OpcionesCampo
	 */
	public function setValor($valor)
	{
		$this->valor = $valor;

		return $this;
	}

	/**
	 * Get valor
	 *
	 * @return string
	 */
	public function getValor()
	{
		return $this->valor;
	}

	/**
	 * Set idCampoHito
	 *
	 * @param CampoHito $idCampoHito
	 *
	 * @return OpcionesCampo
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
	 * Set idHitoCondicional
	 *
	 * @param string $idHitoCondicional
	 *
	 * @return OpcionesCampo
	 */
	public function setIdhitoCondicional($idHitoCondicional)
	{
		$this->idHitoCondicional = $idHitoCondicional;

		return $this;
	}

	/**
	 * Get idHitoCondicional
	 *
	 * @return string
	 */
	public function getIdHitoCondicional()
	{
		return $this->idHitoCondicional;
	}

	/**
	 * Set idCampoCondicional
	 *
	 * @param string $idCampoCondicional
	 *
	 * @return OpcionesCampo
	 */
	public function setIdCampoCondicional($idCampoCondicional)
	{
		$this->idCampoCondicional = $idCampoCondicional;

		return $this;
	}

	/**
	 * Get idCampoCondicional
	 *
	 * @return string
	 */
	public function getIdCampoCondicional()
	{
		return $this->idCampoCondicional;
	}

	public function __toString()
	{
		return $this->valor;
	}
}
