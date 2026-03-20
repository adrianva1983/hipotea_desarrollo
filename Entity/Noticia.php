<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Noticia
 */
class Noticia
{
	/**
	 * @var integer
	 */
	private $idNoticia;

	/**
	 * @var string
	 */
	private $titulo;

	/**
	 * @var string
	 */
	private $imagen;

	/**
	 * @var string
	 */
	private $descripcion;

	/**
	 * @var DateTime
	 */
	private $fecha;

	/**
	 * @var boolean
	 */
	private $estado = '1';

	/**
	 * @var string
	 */
	private $url;

	private $fichero;

	/**
	 * @var Usuario
	 */
	private $idUsuario;

	/**
	 * Get idNoticia
	 *
	 * @return integer
	 */
	public function getIdNoticia()
	{
		return $this->idNoticia;
	}

	/**
	 * Set titulo
	 *
	 * @param string $titulo
	 *
	 * @return Noticia
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
	 * Set imagen
	 *
	 * @param string $imagen
	 *
	 * @return Noticia
	 */
	public function setImagen($imagen)
	{
		$this->imagen = $imagen;

		return $this;
	}

	/**
	 * Get imagen
	 *
	 * @return string
	 */
	public function getImagen()
	{
		return $this->imagen;
	}

	/**
	 * Set descripcion
	 *
	 * @param string $descripcion
	 *
	 * @return Noticia
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
	 * Set fecha
	 *
	 * @param DateTime $fecha
	 *
	 * @return Noticia
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
	 * Set estado
	 *
	 * @param boolean $estado
	 *
	 * @return Noticia
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
	 * Set url
	 *
	 * @param string $url
	 *
	 * @return Noticia
	 */
	public function setUrl($url)
	{
		$this->url = $url;

		return $this;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
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

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return Noticia
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
