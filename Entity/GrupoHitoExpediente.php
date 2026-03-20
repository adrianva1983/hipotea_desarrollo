<?php

namespace AppBundle\Entity;


/**
 * GrupoHitoExpediente
 */
class GrupoHitoExpediente
{
	/**
	 * @var integer
	 */
	private $idGrupoHitoExpediente;


	/**
	 * @var HitoExpediente
	 */
	private $idHitoExpediente;

	/**
	 * @var GrupoCamposHito
	 */
	private $idGrupoCamposHito;


	

	/**
	 * Get the value of idGrupoHitoExpediente
	 *
	 * @return  integer
	 */ 
	public function getIdGrupoHitoExpediente()
	{
		return $this->idGrupoHitoExpediente;
	}

	/**
	 * Set the value of idGrupoHitoExpediente
	 *
	 * @param  integer  $idGrupoHitoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdGrupoHitoExpediente($idGrupoHitoExpediente)
	{
		$this->idGrupoHitoExpediente = $idGrupoHitoExpediente;

		return $this;
	}

	/**
	 * Get the value of idHitoExpediente
	 *
	 * @return  HitoExpediente
	 */ 
	public function getIdHitoExpediente()
	{
		return $this->idHitoExpediente;
	}

	/**
	 * Set the value of idHitoExpediente
	 *
	 * @param  HitoExpediente  $idHitoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdHitoExpediente(HitoExpediente $idHitoExpediente)
	{
		$this->idHitoExpediente = $idHitoExpediente;

		return $this;
	}

	/**
	 * Get the value of idGrupoCamposHito
	 *
	 * @return  GrupoCamposHito
	 */ 
	public function getIdGrupoCamposHito()
	{
		return $this->idGrupoCamposHito;
	}

	/**
	 * Set the value of idGrupoCamposHito
	 *
	 * @param  GrupoCamposHito  $idGrupoCamposHito
	 *
	 * @return  self
	 */ 
	public function setIdGrupoCamposHito(GrupoCamposHito $idGrupoCamposHito)
	{
		$this->idGrupoCamposHito = $idGrupoCamposHito;

		return $this;
	}
}
