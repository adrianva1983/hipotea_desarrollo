<?php

namespace AppBundle\Entity;

/**
 * CampoHito
 */
class CampoHito
{
	/**
	 * @var integer
	 */
	private $idCampoHito;

	/**
	 * @var integer
	 */
	private $tipo = '1';

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var integer
	 */
	private $orden;

	/**
	 * @var boolean
	 */
	private $campoCondicional = false;

	/**
	 * @var boolean
	 */
	private $mostrarCliente= true;

	/**
	 * @var boolean
	 */
	private $mostrarColaborador= true;

	/**
	 * @var GrupoCamposHito
	 */
	private $idGrupoCamposHito;


	/**
	 * Get idCampoHito
	 *
	 * @return integer
	 */
	public function getIdCampoHito()
	{
		return $this->idCampoHito;
	}

	/**
	 * Set tipo
	 *
	 * @param integer $tipo
	 *
	 * @return CampoHito
	 */
	public function setTipo($tipo)
	{
		$this->tipo = $tipo;

		return $this;
	}

	/**
	 * Get tipo
	 *
	 * @return integer
	 */
	public function getTipo()
	{
		return $this->tipo;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return CampoHito
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
	 * Set orden
	 *
	 * @param integer $orden
	 *
	 * @return CampoHito
	 */
	public function setOrden($orden)
	{
		$this->orden = $orden;

		return $this;
	}

	/**
	 * Get orden
	 *
	 * @return integer
	 */
	public function getOrden()
	{
		return $this->orden;
	}

	/**
	 * Set campoCondicional
	 *
	 * @param boolean $campoCondicional
	 *
	 * @return CampoHito
	 */
	public function setCampoCondicional($campoCondicional)
	{
		$this->campoCondicional = $campoCondicional;

		return $this;
	}

	/**
	 * Get campoCondicional
	 *
	 * @return boolean
	 */
	public function getCampoCondicional()
	{
		return $this->campoCondicional;
	}

	/**
	 * Set idGrupoCamposHito
	 *
	 * @param GrupoCamposHito $idGrupoCamposHito
	 *
	 * @return CampoHito
	 */
	public function setIdGrupoCamposHito(GrupoCamposHito $idGrupoCamposHito = null)
	{
		$this->idGrupoCamposHito = $idGrupoCamposHito;

		return $this;
	}

	/**
	 * Get idGrupoCamposHito
	 *
	 * @return GrupoCamposHito
	 */
	public function getIdGrupoCamposHito()
	{
		return $this->idGrupoCamposHito;
	}

	public function __toString()
	{
		return $this->nombre;
	}

	/**
	 * Get the value of mostrarCliente
	 *
	 * @return  boolean
	 */ 
	public function getMostrarCliente()
	{
		return $this->mostrarCliente;
	}

	/**
	 * Set the value of mostrarCliente
	 *
	 * @param  boolean  $mostrarCliente
	 *
	 * @return  CampoHito
	 */ 
	public function setMostrarCliente($mostrarCliente)
	{
		$this->mostrarCliente = $mostrarCliente;

		return $this;
	}

	/**
	 * Get the value of mostrarColaborador
	 *
	 * @return  boolean
	 */ 
	public function getMostrarColaborador()
	{
		return $this->mostrarColaborador;
	}

	/**
	 * Set the value of mostrarColaborador
	 *
	 * @param  boolean  $mostrarColaborador
	 *
	 * @return  CampoHito
	 */ 
	public function setMostrarColaborador($mostrarColaborador)
	{
		$this->mostrarColaborador = $mostrarColaborador;

		return $this;
	}
}
