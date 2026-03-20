<?php

namespace AppBundle\Entity;

/**
 * ClienteFactura
 */
class ClienteFactura
{
	/**
	 * @var integer
	 */
	private $idClienteFactura;

	/**
	 * @var string
	 */
	private $razonSocial;

	/**
	 * @var string
	 */
	private $cif;

	/**
	 * @var string
	 */
	private $direccion;

	/**
	 * @var string
	 */
	private $provincia;

	/**
	 * @var string
	 */
	private $municipio;

	/**
	 * @var string
	 */
	private $cp;

	/**
	 * @var string
	 */
	private $telefono;


	/**
	 * Get idClienteFactura
	 *
	 * @return integer
	 */
	public function getIdClienteFactura()
	{
		return $this->idClienteFactura;
	}

	/**
	 * Set razonSocial
	 *
	 * @param string $razonSocial
	 *
	 * @return ClienteFactura
	 */
	public function setRazonSocial($razonSocial)
	{
		$this->razonSocial = $razonSocial;

		return $this;
	}

	/**
	 * Get razonSocial
	 *
	 * @return string
	 */
	public function getRazonSocial()
	{
		return $this->razonSocial;
	}

	/**
	 * Set cif
	 *
	 * @param string $cif
	 *
	 * @return ClienteFactura
	 */
	public function setCif($cif)
	{
		$this->cif = $cif;

		return $this;
	}

	/**
	 * Get cif
	 *
	 * @return string
	 */
	public function getCif()
	{
		return $this->cif;
	}

	/**
	 * Set direccion
	 *
	 * @param string $direccion
	 *
	 * @return ClienteFactura
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
	 * Set provincia
	 *
	 * @param string $provincia
	 *
	 * @return ClienteFactura
	 */
	public function setProvincia($provincia)
	{
		$this->provincia = $provincia;

		return $this;
	}

	/**
	 * Get provincia
	 *
	 * @return string
	 */
	public function getProvincia()
	{
		return $this->provincia;
	}

	/**
	 * Set municipio
	 *
	 * @param string $municipio
	 *
	 * @return ClienteFactura
	 */
	public function setMunicipio($municipio)
	{
		$this->municipio = $municipio;

		return $this;
	}

	/**
	 * Get municipio
	 *
	 * @return string
	 */
	public function getMunicipio()
	{
		return $this->municipio;
	}

	/**
	 * Set cp
	 *
	 * @param string $cp
	 *
	 * @return ClienteFactura
	 */
	public function setCp($cp)
	{
		$this->cp = $cp;

		return $this;
	}

	/**
	 * Get cp
	 *
	 * @return string
	 */
	public function getCp()
	{
		return $this->cp;
	}

	/**
	 * Set telefono
	 *
	 * @param string $telefono
	 *
	 * @return ClienteFactura
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

	public function __toString()
	{
		return $this->razonSocial;
	}
}
