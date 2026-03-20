<?php

namespace AppBundle\Entity;

class ExpedienteEmail
{
	private $idEntidadColaboradora;
	private $idAgenteColaborador;
	private $asunto;
	private $mensaje;
	private $documentos;
	private $complementar;

	/**
	 * @return mixed
	 */
	public function getIdEntidadColaboradora()
	{
		return $this->idEntidadColaboradora;
	}

	/**
	 * @param mixed $idEntidadColaboradora
	 */
	public function setIdEntidadColaboradora($idEntidadColaboradora)
	{
		$this->idEntidadColaboradora = $idEntidadColaboradora;
	}

	/**
	 * @return mixed
	 */
	public function getIdAgenteColaborador()
	{
		return $this->idAgenteColaborador;
	}

	/**
	 * @param mixed $idAgenteColaborador
	 */
	public function setIdAgenteColaborador($idAgenteColaborador)
	{
		$this->idAgenteColaborador = $idAgenteColaborador;
	}

	/**
	 * @return mixed
	 */
	public function getAsunto()
	{
		return $this->asunto;
	}

	/**
	 * @param mixed $asunto
	 */
	public function setAsunto($asunto)
	{
		$this->asunto = $asunto;
	}

	/**
	 * @return mixed
	 */
	public function getMensaje()
	{
		return $this->mensaje;
	}

	/**
	 * @param mixed $mensaje
	 */
	public function setMensaje($mensaje)
	{
		$this->mensaje = $mensaje;
	}

	/**
	 * @return mixed
	 */
	public function getDocumentos()
	{
		return $this->documentos;
	}

	/**
	 * @param mixed $documentos
	 */
	public function setDocumentos($documentos)
	{
		$this->documentos = $documentos;
	}

	/**
	 *  @return mixed
	 */ 
	public function getComplementar()
	{
		return $this->complementar;
	}

	/**
	 * @param mixed  $complementar
	 */ 
	public function setComplementar($complementar)
	{
		$this->complementar = $complementar;
	}
}
