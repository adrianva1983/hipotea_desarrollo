<?php

namespace AppBundle\Entity;

/**
 * AgenteColaborador
 */
class AgenteColaborador
{
	/**
	 * @var integer
	 */
	private $idAgenteColaborador;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var string
	 */
	private $apellidos;

	/**
	 * @var string
	 */
	private $email;

	/**
	 * @var string
	 */
	private $telefono;

	/**
	 * @var string
	 */
	private $direccion;

	/**
	 * @var EntidadColaboradora
	 */
	private $idEntidadColaboradora;


	/**
	 * Get idAgenteColaborador
	 *
	 * @return integer
	 */
	public function getIdAgenteColaborador()
	{
		return $this->idAgenteColaborador;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return AgenteColaborador
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
	 * Set apellidos
	 *
	 * @param string $apellidos
	 *
	 * @return AgenteColaborador
	 */
	public function setApellidos($apellidos)
	{
		$this->apellidos = $apellidos;

		return $this;
	}

	/**
	 * Get apellidos
	 *
	 * @return string
	 */
	public function getApellidos()
	{
		return $this->apellidos;
	}

	/**
	 * Set email
	 *
	 * @param string $email
	 *
	 * @return AgenteColaborador
	 */
	public function setEmail($email)
	{
		$this->email = $email;

		return $this;
	}

	/**
	 * Get email
	 *
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * Set telefono
	 *
	 * @param string $telefono
	 *
	 * @return AgenteColaborador
	 */
	public function setTelefono($telefono)
	{
		$this->telefono = $telefono;

		return $this;
	}

	/**
	 * Get telefono
	 *
	 * @return string
	 */
	public function getTelefono()
	{
		return $this->telefono;
	}

	/**
	 * Set direccion
	 *
	 * @param string $direccion
	 *
	 * @return AgenteColaborador
	 */
	public function setDireccion($direccion)
	{
		$this->direccion = $direccion;

		return $this;
	}

	/**
	 * Get direccion
	 *
	 * @return string
	 */
	public function getDireccion()
	{
		return $this->direccion;
	}

	/**
	 * Set idEntidadColaboradora
	 *
	 * @param EntidadColaboradora $idEntidadColaboradora
	 *
	 * @return AgenteColaborador
	 */
	public function setIdEntidadColaboradora(EntidadColaboradora $idEntidadColaboradora = null)
	{
		$this->idEntidadColaboradora = $idEntidadColaboradora;

		return $this;
	}

	/**
	 * Get idEntidadColaboradora
	 *
	 * @return EntidadColaboradora
	 */
	public function getIdEntidadColaboradora()
	{
		return $this->idEntidadColaboradora;
	}

	public function __toString()
	{
		return $this->nombre;
	}
}
