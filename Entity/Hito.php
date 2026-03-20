<?php

namespace AppBundle\Entity;

/**
 * Hito
 */
class Hito
{
	/**
	 * @var integer
	 */
	private $idHito;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var integer
	 */
	private $orden;

	/**
	 * @var Fase
	 */
	private $idFase;

	/**
	 * @var boolean
	 */
	private $repetible = false;

	/**
	 * @var boolean
	 */
	private $hitoCondicional = false;


	/**
	 * Get idHito
	 *
	 * @return integer
	 */
	public function getIdHito()
	{
		return $this->idHito;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return Hito
	 */
	public function setNombre($nombre)
	{
		$this->nombre = $nombre;

		return $this;
	}

	/**
	 * Get nombre
	 *
	 * @return string
	 */
	public function getNombre()
	{
		return $this->nombre;
	}

	/**
	 * Set orden
	 *
	 * @param integer $orden
	 *
	 * @return Hito
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
	 * Set repetible
	 *
	 * @param boolean $repetible
	 *
	 * @return Hito
	 */
	public function setRepetible($repetible)
	{
		$this->repetible = $repetible;

		return $this;
	}

	/**
	 * Get repetible
	 *
	 * @return boolean
	 */
	public function getRepetible()
	{
		return $this->repetible;
	}

	/**
	 * Set hitoCondicional
	 *
	 * @param boolean $hitoCondicional
	 *
	 * @return Hito
	 */
	public function setHitoCondicional($hitoCondicional)
	{
		$this->hitoCondicional = $hitoCondicional;

		return $this;
	}

	/**
	 * Get hitoCondicional
	 *
	 * @return boolean
	 */
	public function getHitoCondicional()
	{
		return $this->hitoCondicional;
	}

	/**
	 * Set idFase
	 *
	 * @param Fase $idFase
	 *
	 * @return Hito
	 */
	public function setIdFase(Fase $idFase = null)
	{
		$this->idFase = $idFase;

		return $this;
	}

	/**
	 * Get idFase
	 *
	 * @return Fase
	 */
	public function getIdFase()
	{
		return $this->idFase;
	}

	public function __toString()
	{
		return $this->nombre;
	}
}
