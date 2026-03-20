<?php

namespace AppBundle\Entity;

/**
 * EnvioCalculadora
 */
class EnvioCalculadora
{
	/**
	 * @var integer
	 */
	private $idEnvioCalculadora;

	/**
	 * @var string
	 */
	private $nombre;

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
	private $privacidad;

	


	/**
	 * Get idEnvioCalculadora
	 *
	 * @return integer
	 */
	public function getIdEnvioCalculadora()
	{
		return $this->idEnvioCalculadora;
	}


	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return EnvioCalculadora
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
	 * Set email
	 *
	 * @param string $email
	 *
	 * @return EnvioCalculadora
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
	 * @return EnvioCalculadora
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
	 * Set privacidad
	 *
	 * @param boolean $privacidad
	 *
	 * @return EnvioCalculadora
	 */
	public function setPrivacidad($privacidad)
	{
		$this->privacidad = $privacidad;

		return $this;
	}

	/**
	 * Get privacidad
	 *
	 * @return boolean
	 */
	public function getPrivacidad()
	{
		return $this->privacidad;
	}


	public function __toString()
	{
		return $this->nombre;
	}
}
