<?php

namespace AppBundle\Entity;

/**
 * ImagenFichero
 */
class ImagenFichero
{
	/**
	 * @var integer
	 */
	private $idImagenFichero;

	/**
	 * @var string
	 */
	private $nombreImagen;

	/**
	 * @var FicheroCampo
	 */
	private $idFicheroCampo;


	/**
	 * Get idImagenFichero
	 *
	 * @return integer
	 */
	public function getIdImagenFichero()
	{
		return $this->idImagenFichero;
	}

	/**
	 * Set nombreImagen
	 *
	 * @param string $nombreImagen
	 *
	 * @return ImagenFichero
	 */
	public function setNombreImagen($nombreImagen)
	{
		$this->nombreImagen = $nombreImagen;

		return $this;
	}

	/**
	 * Get nombreImagen
	 *
	 * @return string
	 */
	public function getNombreImagen()
	{
		return $this->nombreImagen;
	}

	/**
	 * Set idFicheroCampo
	 *
	 * @param FicheroCampo $idFicheroCampo
	 *
	 * @return ImagenFichero
	 */
	public function setIdFicheroCampo(FicheroCampo $idFicheroCampo = null)
	{
		$this->idFicheroCampo = $idFicheroCampo;

		return $this;
	}

	/**
	 * Get idFicheroCampo
	 *
	 * @return FicheroCampo
	 */
	public function getIdFicheroCampo()
	{
		return $this->idFicheroCampo;
	}
}
