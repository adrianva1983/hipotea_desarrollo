<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * SeguimientoHorario
 */
class SeguimientoHorario
{
	/**
	 * @var integer
	 */
	private $idSeguimientoHorario;

	/**
	 * @var string
	 */
	private $datosCliente;

	/**
	 * @var DateTime
	 */
	private $fechaInicio;

	/**
	 * @var DateTime
	 */
	private $fechaFin;

	/**
	 * @var string
	 */
	private $tipo = 'reunion';

	/**
	 * @var string
	 */
	private $observaciones;

	/**
	 * @var integer
	 */
	private $estado = '1';

	/**
	 * @var Usuario
	 */
	private $idUsuario;

	/**
	 * @var Usuario
	 */
	private $idCliente;

	/**
	 * @var Inmobiliaria
	 */
	private $idInmobiliaria;


	/**
	 * Get idSeguimientoHorario
	 *
	 * @return integer
	 */
	public function getIdSeguimientoHorario()
	{
		return $this->idSeguimientoHorario;
	}

	/**
	 * Set datosCliente
	 *
	 * @param string $datosCliente
	 *
	 * @return SeguimientoHorario
	 */
	public function setDatosCliente($datosCliente)
	{
		$this->datosCliente = $datosCliente;

		return $this;
	}

	/**
	 * Get datosCliente
	 *
	 * @return string
	 */
	public function getDatosCliente()
	{
		return $this->datosCliente;
	}

	/**
	 * Set fecha
	 *
	 * @param DateTime $fechaInicio
	 *
	 * @return SeguimientoHorario
	 */
	public function setFechaInicio($fechaInicio)
	{
		$this->fechaInicio = $fechaInicio;

		return $this;
	}

	/**
	 * Get fecha
	 *
	 * @return DateTime
	 */
	public function getFechaInicio()
	{
		return $this->fechaInicio;
	}

	/**
	 * Set hora
	 *
	 * @param DateTime $fechaFin
	 *
	 * @return SeguimientoHorario
	 */
	public function setFechaFin($fechaFin)
	{
		$this->fechaFin = $fechaFin;

		return $this;
	}

	/**
	 * Get hora
	 *
	 * @return DateTime
	 */
	public function getFechaFin()
	{
		return $this->fechaFin;
	}

	/**
	 * Set tipo
	 *
	 * @param string $tipo
	 *
	 * @return SeguimientoHorario
	 */
	public function setTipo($tipo)
	{
		$this->tipo = $tipo;

		return $this;
	}

	/**
	 * Get tipo
	 *
	 * @return string
	 */
	public function getTipo()
	{
		return $this->tipo;
	}

	/**
	 * Set observaciones
	 *
	 * @param string $observaciones
	 *
	 * @return SeguimientoHorario
	 */
	public function setObservaciones($observaciones)
	{
		$this->observaciones = $observaciones;

		return $this;
	}

	/**
	 * Get observaciones
	 *
	 * @return string
	 */
	public function getObservaciones()
	{
		return $this->observaciones;
	}

	/**
	 * Set estado
	 *
	 * @param integer $estado
	 *
	 * @return SeguimientoHorario
	 */
	public function setEstado($estado)
	{
		$this->estado = $estado;

		return $this;
	}

	/**
	 * Get estado
	 *
	 * @return integer
	 */
	public function getEstado()
	{
		return $this->estado;
	}

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return SeguimientoHorario
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

	/**
	 * Set idCliente
	 *
	 * @param Usuario $idCliente
	 *
	 * @return SeguimientoHorario
	 */
	public function setIdCliente(Usuario $idCliente = null)
	{
		$this->idCliente = $idCliente;

		return $this;
	}

	/**
	 * Get idCliente
	 *
	 * @return Usuario
	 */
	public function getIdCliente()
	{
		return $this->idCliente;
	}

	/**
	 * Get the value of idInmobiliaria
	 *
	 * @return  Inmobiliaria
	 */ 
	public function getIdInmobiliaria()
	{
		return $this->idInmobiliaria;
	}

	/**
	 * Set the value of idInmobiliaria
	 *
	 * @param  Inmobiliaria  $idInmobiliaria
	 *
	 * @return  SeguimientoHorario
	 */ 
	public function setIdInmobiliaria(Inmobiliaria $idInmobiliaria = null)
	{
		$this->idInmobiliaria = $idInmobiliaria;

		return $this;
	}
}
