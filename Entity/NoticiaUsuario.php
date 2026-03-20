<?php

namespace AppBundle\Entity;

/**
 * Noticia
 */
class NoticiaUsuario
{
	/**
	 * @var integer
	 */
	private $idNoticiaUsuario;

	/**
	 * @var Noticia
	 */
	private $idNoticia;

	/**
	 * @var Usuario
	 */
	private $idUsuario;


	/**
	 * Get idNoticiaUsuario
	 *
	 * @return integer
	 */
	public function getIdNoticiaUsuario()
	{
		return $this->idNoticiaUsuario;
	}

	/**
	 * Set idNoticia
	 *
	 * @param Noticia $idNoticia
	 *
	 * @return NoticiaUsuario
	 */
	public function setIdNoticia(Noticia $idNoticia = null)
	{
		$this->idNoticia = $idNoticia;

		return $this;
	}

	/**
	 * Get idNoticia
	 *
	 * @return Noticia
	 */
	public function getIdNoticia()
	{
		return $this->idNoticia;
	}

	/**
	 * Set idUsuario
	 *
	 * @param Usuario $idUsuario
	 *
	 * @return NoticiaUsuario
	 */
	public function setIdUsuario(Usuario $idUsuario = null)
	{
		if (!is_null($idUsuario)) {
			$this->idUsuario = $idUsuario;
		}
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
