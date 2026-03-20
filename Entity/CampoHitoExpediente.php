<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * CampoHitoExpediente
 */
class CampoHitoExpediente
{
	/**
	 * @var integer
	 */
	private $idCampoHitoExpediente;

	/**
	 * @var string
	 */
	private $valor;

	/**
	 * @var DateTime
	 */
	private $fechaModificacion;

	/**
	 * @var boolean
	 */
	private $obligatorio = false;

	/**
	 * @var boolean
	 */
	private $solicitarAlColaborador = false;

	/**
	 * @var boolean
	 */
	private $avisarColaborador = false;

	/**
	 * @var boolean
	 */
	private $paraFirmar = false;

	/**
	 * @var boolean
	 */
	private $firmado = false;

	/**
	 * @var boolean
	 */
	private $enviarAlCliente = false;

	/**
	 * @var boolean
	 */
	private $enviarAlColaborador = false;

	/**
	 * @var CampoHito
	 */
	private $idCampoHito;

	/**
	 * @var GrupoHitoExpediente
	 */
	private $idGrupoHitoExpediente;

	/**
	 * @var HitoExpediente
	 */
	private $idHitoExpediente;

	/**
	 * @var Expediente
	 */
	private $idExpediente;

	/**
	 * @var OpcionesCampo
	 */
	private $idOpcionesCampo;

	/**
	 * @var AgenteColaborador
	 */
	private $idAgenteColaborador;


	/**
	 * Get idCampoHitoExpediente
	 *
	 * @return integer
	 */
	public function getIdCampoHitoExpediente()
	{
		return $this->idCampoHitoExpediente;
	}

	/**
	 * Set valor
	 *
	 * @param string $valor
	 *
	 * @return CampoHitoExpediente
	 */
	public function setValor($valor)
	{
		$this->valor = $valor;

		return $this;
	}

	/**
	 * Get valor
	 *
	 * @return string
	 */
	public function getValor()
	{
		return $this->valor;
	}

	/**
	 * Set fechaModificacion
	 *
	 * @param DateTime $fechaModificacion
	 *
	 * @return CampoHitoExpediente
	 */
	public function setFechaModificacion($fechaModificacion)
	{
		$this->fechaModificacion = $fechaModificacion;

		return $this;
	}

	/**
	 * Get fechaModificacion
	 *
	 * @return DateTime
	 */
	public function getFechaModificacion()
	{
		return $this->fechaModificacion;
	}

	/**
	 * Set obligatorio
	 *
	 * @param boolean $obligatorio
	 *
	 * @return CampoHitoExpediente
	 */
	public function setObligatorio($obligatorio)
	{
		$this->obligatorio = $obligatorio;

		return $this;
	}

	/**
	 * Get obligatorio
	 *
	 * @return boolean
	 */
	public function getObligatorio()
	{
		return $this->obligatorio;
	}

	/**
	 * Set solicitarAlColaborador
	 *
	 * @param boolean $solicitarAlColaborador
	 *
	 * @return CampoHitoExpediente
	 */
	public function setSolicitarAlColaborador($solicitarAlColaborador)
	{
		$this->solicitarAlColaborador = $solicitarAlColaborador;

		return $this;
	}

	/**
	 * Get solicitarAlColaborador
	 *
	 * @return boolean
	 */
	public function getSolicitarAlColaborador()
	{
		return $this->solicitarAlColaborador;
	}

	/**
	 * Set avisarColaborador
	 *
	 * @param boolean $avisarColaborador
	 *
	 * @return CampoHitoExpediente
	 */
	public function setAvisarColaborador($avisarColaborador)
	{
		$this->avisarColaborador = $avisarColaborador;

		return $this;
	}

	/**
	 * Get avisarColaborador
	 *
	 * @return boolean
	 */
	public function getAvisarColaborador()
	{
		return $this->avisarColaborador;
	}

	/**
	 * Set paraFirmar
	 *
	 * @param boolean $paraFirmar
	 *
	 * @return CampoHitoExpediente
	 */
	public function setParaFirmar($paraFirmar)
	{
		$this->paraFirmar = $paraFirmar;

		return $this;
	}

	/**
	 * Get paraFirmar
	 *
	 * @return boolean
	 */
	public function getParaFirmar()
	{
		return $this->paraFirmar;
	}

	/**
	 * Set firmado
	 *
	 * @param boolean $firmado
	 *
	 * @return CampoHitoExpediente
	 */
	public function setFirmado($firmado)
	{
		$this->firmado = $firmado;

		return $this;
	}

	/**
	 * Get firmado
	 *
	 * @return boolean
	 */
	public function getFirmado()
	{
		return $this->firmado;
	}

	/**
	 * Set enviarAlCliente
	 *
	 * @param boolean $enviarAlCliente
	 *
	 * @return CampoHitoExpediente
	 */
	public function setEnviarAlCliente($enviarAlCliente)
	{
		$this->enviarAlCliente = $enviarAlCliente;

		return $this;
	}

	/**
	 * Get enviarAlCliente
	 *
	 * @return boolean
	 */
	public function getEnviarAlCliente()
	{
		return $this->enviarAlCliente;
	}

	/**
	 * Set enviarAlColaborador
	 *
	 * @param boolean $enviarAlColaborador
	 *
	 * @return CampoHitoExpediente
	 */
	public function setEnviarAlColaborador($enviarAlColaborador)
	{
		$this->enviarAlColaborador = $enviarAlColaborador;

		return $this;
	}

	/**
	 * Get enviarAlColaborador
	 *
	 * @return boolean
	 */
	public function getEnviarAlColaborador()
	{
		return $this->enviarAlColaborador;
	}

	/**
	 * Set idCampoHito
	 *
	 * @param CampoHito $idCampoHito
	 *
	 * @return CampoHitoExpediente
	 */
	public function setIdCampoHito(CampoHito $idCampoHito = null)
	{
		$this->idCampoHito = $idCampoHito;

		return $this;
	}

	/**
	 * Get idCampoHito
	 *
	 * @return CampoHito
	 */
	public function getIdCampoHito()
	{
		return $this->idCampoHito;
	}

	/**
	 * Set idHitoExpediente
	 *
	 * @param HitoExpediente $idHitoExpediente
	 *
	 * @return CampoHitoExpediente
	 */
	public function setIdHitoExpediente(HitoExpediente $idHitoExpediente = null)
	{
		$this->idHitoExpediente = $idHitoExpediente;

		return $this;
	}

	/**
	 * Get idHitoExpediente
	 *
	 * @return HitoExpediente
	 */
	public function getIdHitoExpediente()
	{
		return $this->idHitoExpediente;
	}

	/**
	 * Set idExpediente
	 *
	 * @param Expediente $idExpediente
	 *
	 * @return CampoHitoExpediente
	 */
	public function setIdExpediente(Expediente $idExpediente = null)
	{
		$this->idExpediente = $idExpediente;

		return $this;
	}

	/**
	 * Get idExpediente
	 *
	 * @return Expediente
	 */
	public function getIdExpediente()
	{
		return $this->idExpediente;
	}

	/**
	 * Set idOpcionesCampo
	 *
	 * @param OpcionesCampo $idOpcionesCampo
	 *
	 * @return CampoHitoExpediente
	 */
	public function setIdOpcionesCampo(OpcionesCampo $idOpcionesCampo = null)
	{
		$this->idOpcionesCampo = $idOpcionesCampo;

		return $this;
	}

	/**
	 * Get idOpcionesCampo
	 *
	 * @return OpcionesCampo
	 */
	public function getIdOpcionesCampo()
	{
		return $this->idOpcionesCampo;
	}

	/**
	 * Set idAgenteColaborador
	 *
	 * @param AgenteColaborador $idAgenteColaborador
	 *
	 * @return CampoHitoExpediente
	 */
	public function setIdAgenteColaborador(AgenteColaborador $idAgenteColaborador = null)
	{
		$this->idAgenteColaborador = $idAgenteColaborador;

		return $this;
	}

	/**
	 * Get idAgenteColaborador
	 *
	 * @return AgenteColaborador
	 */
	public function getIdAgenteColaborador()
	{
		return $this->idAgenteColaborador;
	}

	public function __toString()
	{
		return $this->valor;
	}

	/**
	 * Get the value of idGrupoHitoExpediente
	 *
	 * @return  GrupoHitoExpediente
	 */ 
	public function getIdGrupoHitoExpediente()
	{
		return $this->idGrupoHitoExpediente;
	}

	/**
	 * Set the value of idGrupoHitoExpediente
	 *
	 * @param  GrupoHitoExpediente  $idGrupoHitoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdGrupoHitoExpediente(GrupoHitoExpediente $idGrupoHitoExpediente)
	{
		$this->idGrupoHitoExpediente = $idGrupoHitoExpediente;

		return $this;
	}
}
