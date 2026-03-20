<?php

namespace AppBundle\Entity;

/**
 * EntidadColaboradora
 */
class EntidadColaboradora
{
	/**
	 * @var integer
	 */
	private $idEntidadColaboradora;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var integer
	 */
	private $tipoEntidad;

	/**
	 * @var boolean
	 */
	private $estado = '1';


	/**
	 * Get idEntidadColaboradora
	 *
	 * @return integer
	 */
	public function getIdEntidadColaboradora()
	{
		return $this->idEntidadColaboradora;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return EntidadColaboradora
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
	 * Set tipoEntidad
	 *
	 * @param integer $tipoEntidad
	 *
	 * @return EntidadColaboradora
	 */
	public function setTipoEntidad($tipoEntidad)
	{
		$this->tipoEntidad = $tipoEntidad;

		return $this;
	}

	/**
	 * Get tipoEntidad
	 *
	 * @return integer
	 */
	public function getTipoEntidad()
	{
		return $this->tipoEntidad;
	}

	/**
	 * Set estado
	 *
	 * @param boolean $estado
	 *
	 * @return EntidadColaboradora
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

	public function __toString()
	{
		return $this->nombre;
	}
}
