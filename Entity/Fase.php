<?php

namespace AppBundle\Entity;

/**
 * Fase
 */
class Fase
{
	/**
	 * @var integer
	 */
	private $idFase;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var integer
	 */
	private $tipo;

	/**
	 * @var string
	 */
	private $color;

	/**
	 * @var integer
	 */
	private $orden;

	/**
	 * @var boolean
	 */
	private $final = false;


	/**
	 * Get idFase
	 *
	 * @return integer
	 */
	public function getIdFase()
	{
		return $this->idFase;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return Fase
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
	 * Set tipo
	 *
	 * @param integer $tipo
	 *
	 * @return Fase
	 */
	public function setTipo($tipo)
	{
		$this->tipo = $tipo;

		return $this;
	}

	/**
	 * Get tipo
	 *
	 * @return integer
	 */
	public function getTipo()
	{
		return $this->tipo;
	}

	/**
	 * Set color
	 *
	 * @param string $color
	 *
	 * @return Fase
	 */
	public function setColor($color)
	{
		$this->color = $color;

		return $this;
	}

	/**
	 * Get color
	 *
	 * @return string
	 */
	public function getColor()
	{
		return $this->color;
	}

	/**
	 * Set orden
	 *
	 * @param integer $orden
	 *
	 * @return Fase
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
	 * Set final
	 *
	 * @param boolean $final
	 *
	 * @return Fase
	 */
	public function setFinal($final)
	{
		$this->final = $final;

		return $this;
	}

	/**
	 * Get final
	 *
	 * @return boolean
	 */
	public function getFinal()
	{
		return $this->final;
	}

	public function __toString()
	{
		return $this->nombre;
	}
}
