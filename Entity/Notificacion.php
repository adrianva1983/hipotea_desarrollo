<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Notificacion
 */
class Notificacion
{
	/**
	 * @var integer
	 */
	private $idNotificacion;

	/**
	 * @var string
	 */
	private $titulo = 'Información';

	/**
	 * @var string
	 */
	private $texto;

	/**
	 * @var DateTime
	 */
	private $fecha;

	/**
	 * @var DateTime
	 */
	private $fechaLeida;

	/**
	 * @var integer
	 */
	private $estado = '1';

	/**
	 * @var Usuario
	 */
	private $idUsuario;

	/**
	 * @var Expediente
	 */
	private $idExpediente;


	/**
	 * Get idNotificacion
	 *
	 * @return integer
	 */
	public function getIdNotificacion()
	{
		return $this->idNotificacion;
	}

	/**
	 * Set titulo
	 *
	 * @param string $titulo
	 *
	 * @return Notificacion
	 */
	public function setTitulo($titulo)
	{
		$this->titulo = $titulo;

		return $this;
	}

	/**
	 * Get titulo
	 *
	 * @return string
	 */
	public function getTitulo()
	{
		return $this->titulo;
	}

	/**
	 * Set texto
	 *
	 * @param string $texto
	 *
	 * @return Notificacion
	 */
	public function setTexto($texto)
	{
		$this->texto = $texto;

		return $this;
	}

	/**
	 * Get texto
	 *
	 * @return string
	 */
	public function getTexto()
	{
		return $this->texto;
	}

	/**
	 * Set fecha
	 *
	 * @param DateTime $fecha
	 *
	 * @return Notificacion
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
	 * Set fechaLeida
	 *
	 * @param DateTime $fechaLeida
	 *
	 * @return Notificacion
	 */
	public function setFechaLeida($fechaLeida)
	{
		$this->fechaLeida = $fechaLeida;

		return $this;
	}

	/**
	 * Get fechaLeida
	 *
	 * @return DateTime
	 */
	public function getFechaLeida()
	{
		return $this->fechaLeida;
	}

	/**
	 * Set estado
	 *
	 * @param integer $estado
	 *
	 * @return Notificacion
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
	 * @return Notificacion
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
	 * Set idExpediente
	 *
	 * @param Expediente $idExpediente
	 *
	 * @return Notificacion
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
}
