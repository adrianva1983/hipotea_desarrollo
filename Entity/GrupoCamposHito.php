<?php

namespace AppBundle\Entity;

/**
 * GrupoCamposHito
 */
class GrupoCamposHito
{
	/**
	 * @var integer
	 */
	private $idGrupoCamposHito;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var integer
	 */
	private $orden;

	/**
	 * @var Hito
	 */
	private $idHito;


	/**
	 * Get idCampoHito
	 *
	 * @return integer
	 */
	public function getIdGrupoCamposHito()
	{
		return $this->idGrupoCamposHito;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return GrupoCamposHito
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
	 * @return GrupoCamposHito
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
	 * @var boolean
	 */
	private $repetible = false;

	/**
	 * Set idHito
	 *
	 * @param Hito $idHito
	 *
	 * @return GrupoCamposHito
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

	public function __toString()
	{
		if (is_null($this->nombre)) {
			return '';
		}
		return $this->nombre;
	}

	/**
	 * Set repetible
	 *
	 * @param boolean $repetible
	 *
	 * @return GrupoCamposHito
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
}
