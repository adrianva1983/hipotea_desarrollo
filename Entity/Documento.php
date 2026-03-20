<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Documento
 */
class Documento
{
	/**
	 * @var integer
	 */
	private $idDocumento;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var string
	 */
	private $nombreFichero;

	/**
	 * @var string
	 */
	private $descripcion;

	/**
	 * @var DateTime
	 */
	private $fechaSubida;

	/**
	 * @var boolean
	 */
	private $estado = true;

	/**
	 * @var integer
	 */
	private $visiblePara = 0;

	/**
	 * @var Usuario
	 */
	private $idUsuario;

	private $fichero;


	/**
	 * Get idDocumento
	 *
	 * @return integer
	 */
	public function getIdDocumento()
	{
		return $this->idDocumento;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return Documento
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
	 * Set nombreFichero
	 *
	 * @param string $nombreFichero
	 *
	 * @return Documento
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
	 * Set descripcion
	 *
	 * @param string $descripcion
	 *
	 * @return Documento
	 */
	public function setDescripcion($descripcion)
	{
		$this->descripcion = $descripcion;

		return $this;
	}

	/**
	 * Get descripcion
	 *
	 * @return string
	 */
	public function getDescripcion()
	{
		return $this->descripcion;
	}

	/**
	 * Set fechaSubida
	 *
	 * @param DateTime $fechaSubida
	 *
	 * @return Documento
	 */
	public function setFechaSubida($fechaSubida)
	{
		$this->fechaSubida = $fechaSubida;

		return $this;
	}

	/**
	 * Get fechaSubida
	 *
	 * @return DateTime
	 */
	public function getFechaSubida()
	{
		return $this->fechaSubida;
	}

	/**
	 * Set estado
	 *
	 * @param boolean $estado
	 *
	 * @return Documento
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
	 * Set visiblePara
	 *
	 * @param integer $visiblePara
	 *
	 * @return Documento
	 */
	public function setVisiblePara($visiblePara)
	{
		$this->visiblePara = $visiblePara;

		return $this;
	}

	/**
	 * Get visiblePara
	 *
	 * @return integer
	 */
	public function getVisiblePara()
	{
		return $this->visiblePara;
	}

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return Documento
	 */
	public function setIdUsuario(Usuario $idUsuario = null)
	{
		$this->idUsuario = $idUsuario;

		return $this;
	}

	/**
	 * Get idUsuario
	 *
	 * @return Usuario
	 */
	public function getIdUsuario()
	{
		return $this->idUsuario;
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
}
