<?php

namespace AppBundle\Entity;

/**
 * Inmobiliaria
 */
class Inmobiliaria
{
	/**
	 * @var integer
	 */
	private $idInmobiliaria;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var Usuario
	 */
	private $idComercial;

	/**
	 * @var Usuario
	 */
	private $idResponsableZona;

	/**
	 * Set idInmobiliaria
	 *
	 * @param string $idInmobiliaria
	 *
	 * @return Inmobiliaria
	 */
	public function setIdInmobiliaria($idInmobiliaria)
	{
		$this->idInmobiliaria = $idInmobiliaria;

		return $this;
	}

	/**
	 * Get idInmobiliaria
	 *
	 * @return integer
	 */
	public function getIdInmobiliaria()
	{
		return $this->idInmobiliaria;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return Inmobiliaria
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
	 * Set idComercial
	 *
	 * @param Usuario $idComercial
	 *
	 * @return Inmobiliaria
	 */
	public function setIdComercial(Usuario $idComercial = null)
	{
		$this->idComercial = $idComercial;

		return $this;
	}

	/**
	 * Get idComercial
	 *
	 * @return Usuario
	 */
	public function getIdComercial()
	{
		return $this->idComercial;
	}

	/**
	 * Set idResponsableZona
	 *
	 * @param Usuario $idResponsableZona
	 *
	 * @return Inmobiliaria
	 */
	public function setIdResponsableZona(Usuario $idResponsableZona = null)
	{
		$this->idResponsableZona = $idResponsableZona;

		return $this;
	}

	/**
	 * Get idResponsableZona
	 *
	 * @return Usuario
	 */
	public function getIdResponsableZona()
	{
		return $this->idResponsableZona;
	}

	public function __toString()
	{
		return $this->nombre;
	}
}
