<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Log
 */
class Log
{
	/**
	 * @var integer
	 */
	private $idLog;

	/**
	 * @var DateTime
	 */
	private $fecha;

	/**
	 * @var string
	 */
	private $accion;

	/**
	 * @var integer
	 */
	private $idElemento;

	/**
	 * @var Usuario
	 */
	private $idUsuario;


	/**
	 * Get idLog
	 *
	 * @return integer
	 */
	public function getIdLog()
	{
		return $this->idLog;
	}

	/**
	 * Set fecha
	 *
	 * @param DateTime $fecha
	 *
	 * @return Log
	 */
	public function setFecha($fecha)
	{
		$this->fecha = $fecha;

		return $this;
	}

	/**
	 * Get fecha
	 *
	 * @return DateTime
	 */
	public function getFecha()
	{
		return $this->fecha;
	}

	/**
	 * Set accion
	 *
	 * @param string $accion
	 *
	 * @return Log
	 */
	public function setAccion($accion)
	{
		$this->accion = $accion;

		return $this;
	}

	/**
	 * Get accion
	 *
	 * @return string
	 */
	public function getAccion()
	{
		return $this->accion;
	}

	/**
	 * Set idElemento
	 *
	 * @param integer $idElemento
	 *
	 * @return Log
	 */
	public function setIdElemento($idElemento)
	{
		$this->idElemento = $idElemento;

		return $this;
	}

	/**
	 * Get idElemento
	 *
	 * @return integer
	 */
	public function getIdElemento()
	{
		return $this->idElemento;
	}

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return Log
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
}
