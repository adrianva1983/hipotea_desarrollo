<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Dispositivo
 */
class Dispositivo
{
	/**
	 * @var integer
	 */
	private $idDispositivo;

	/**
	 * @var string
	 */
	private $tipo;

	/**
	 * @var string
	 */
	private $identificador;

	/**
	 * @var DateTime
	 */
	private $fechaRegistro;

	/**
	 * @var DateTime
	 */
	private $fechaAcceso;

	/**
	 * @var Usuario
	 */
	private $idUsuario;


	/**
	 * Get idDispositivo
	 *
	 * @return integer
	 */
	public function getIdDispositivo()
	{
		return $this->idDispositivo;
	}

	/**
	 * Set tipo
	 *
	 * @param string $tipo
	 *
	 * @return Dispositivo
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
	 * Set identificador
	 *
	 * @param string $identificador
	 *
	 * @return Dispositivo
	 */
	public function setIdentificador($identificador)
	{
		$this->identificador = $identificador;

		return $this;
	}

	/**
	 * Get identificador
	 *
	 * @return string
	 */
	public function getIdentificador()
	{
		return $this->identificador;
	}

	/**
	 * Set fechaRegistro
	 *
	 * @param DateTime $fechaRegistro
	 *
	 * @return Dispositivo
	 */
	public function setFechaRegistro($fechaRegistro)
	{
		$this->fechaRegistro = $fechaRegistro;

		return $this;
	}

	/**
	 * Get fechaRegistro
	 *
	 * @return DateTime
	 */
	public function getFechaRegistro()
	{
		return $this->fechaRegistro;
	}

	/**
	 * Set fechaAcceso
	 *
	 * @param DateTime $fechaAcceso
	 *
	 * @return Dispositivo
	 */
	public function setFechaAcceso($fechaAcceso)
	{
		$this->fechaAcceso = $fechaAcceso;

		return $this;
	}

	/**
	 * Get fechaAcceso
	 *
	 * @return DateTime
	 */
	public function getFechaAcceso()
	{
		return $this->fechaAcceso;
	}

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return Dispositivo
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
