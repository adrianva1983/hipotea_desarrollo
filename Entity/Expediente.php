<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * Expediente
 */
class Expediente
{
	function __construct()
	{
		$this->setFechaCreacion(new DateTime());
		$this->setFechaModificacion(new DateTime());
	}

	/**
	 * @var integer
	 */
	private $idExpediente;

	/**
	 * @var Fase
	 */
	private $idFaseActual;

	/**
	 * @var Usuario
	 */
	private $idCliente;

	/**
	 * @var Usuario
	 */
	private $idComercial;

	/**
	 * @var Usuario
	 */
	private $idTecnico;

	/**
	 * @var Usuario
	 */
	private $idColaborador;

	/**
	 * @var string
	 */
	private $vivienda;

	/**
	 * @var integer
	 */
	private $estado = 1;

	/**
	 * @var DateTime
	 */
	private $fechaCreacion;

	/**
	 * @var DateTime
	 */
	private $fechaModificacion;

	/**
	 * @var integer
	 */
	private $motivoCancelacion;

	/**
	 * @var string
	 */
	private $texto;

	/**
	 * @var Usuario
	 */
	private $idResponsableZona;

	/**
	 * @var boolean
	 */
	private $whatsappAutomatico = false;

	/**
	 * @var boolean
	 */
	private $whatsappAutomaticoEnviado = false;


	/**
	 * Get idExpediente
	 *
	 * @return integer
	 */
	public function getIdExpediente()
	{
		return $this->idExpediente;
	}

	/**
	 * Set idFaseActual
	 *
	 * @param Fase $idFaseActual
	 *
	 * @return Expediente
	 */
	public function setIdFaseActual(Fase $idFaseActual = null)
	{
		$this->idFaseActual = $idFaseActual;

		return $this;
	}

	/**
	 * Get idFaseActual
	 *
	 * @return Fase
	 */
	public function getIdFaseActual()
	{
		return $this->idFaseActual;
	}

	/**
	 * Set idCliente
	 *
	 * @param Usuario $idCliente
	 *
	 * @return Expediente
	 */
	public function setIdCliente(Usuario $idCliente = null)
	{
		$this->idCliente = $idCliente;

		return $this;
	}

	/**
	 * Get idCliente
	 *
	 * @return Usuario
	 */
	public function getIdCliente()
	{
		return $this->idCliente;
	}

	/**
	 * Set idComercial
	 *
	 * @param Usuario $idComercial
	 *
	 * @return Expediente
	 */
	public function setIdComercial(Usuario $idComercial = null)
	{
		$this->idComercial = $idComercial;

		return $this;
	}

	/**
	 * Get idComercial
	 *
	 * @return Usuario
	 */
	public function getIdComercial()
	{
		return $this->idComercial;
	}

	/**
	 * Set idTecnico
	 *
	 * @param Usuario $idTecnico
	 *
	 * @return Expediente
	 */
	public function setIdTecnico(Usuario $idTecnico = null)
	{
		$this->idTecnico = $idTecnico;

		return $this;
	}

	/**
	 * Get idTecnico
	 *
	 * @return Usuario
	 */
	public function getIdTecnico()
	{
		return $this->idTecnico;
	}

	/**
	 * Set idColaborador
	 *
	 * @param Usuario $idColaborador
	 *
	 * @return Expediente
	 */
	public function setIdColaborador(Usuario $idColaborador = null)
	{
		$this->idColaborador = $idColaborador;

		return $this;
	}

	/**
	 * Get idColaborador
	 *
	 * @return Usuario
	 */
	public function getIdColaborador()
	{
		return $this->idColaborador;
	}

	/**
	 * Set vivienda
	 *
	 * @param string $vivienda
	 *
	 * @return Expediente
	 */
	public function setVivienda($vivienda)
	{
		$this->vivienda = $vivienda;

		return $this;
	}

	/**
	 * Get vivienda
	 *
	 * @return string
	 */
	public function getVivienda()
	{
		return $this->vivienda;
	}

	/**
	 * Set estado
	 *
	 * @param integer $estado
	 *
	 * @return Expediente
	 */
	public function setEstado($estado)
	{
		$this->estado = $estado;

		return $this;
	}

	/**
	 * Get estado
	 *
	 * @return integer
	 */
	public function getEstado()
	{
		return $this->estado;
	}

	/**
	 * Set fechaCreacion
	 *
	 * @param DateTime $fechaCreacion
	 *
	 * @return Expediente
	 */
	public function setFechaCreacion($fechaCreacion)
	{
		$this->fechaCreacion = $fechaCreacion;

		return $this;
	}

	/**
	 * Get fechaCreacion
	 *
	 * @return DateTime
	 */
	public function getFechaCreacion()
	{
		return $this->fechaCreacion;
	}

	/**
	 * Set fechaModificacion
	 *
	 * @param DateTime $fechaModificacion
	 *
	 * @return Expediente
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
	 * Set motivoCancelacion
	 *
	 * @param integer $motivoCancelacion
	 *
	 * @return Expediente
	 */
	public function setMotivoCancelacion($motivoCancelacion)
	{
		$this->motivoCancelacion = $motivoCancelacion;

		return $this;
	}

	/**
	 * Get motivoCancelacion
	 *
	 * @return integer
	 */
	public function getMotivoCancelacion()
	{
		return $this->motivoCancelacion;
	}

	/**
	 * Set texto
	 *
	 * @param string $texto
	 *
	 * @return Expediente
	 */
	public function setTexto($texto)
	{
		$this->texto = $texto;

		return $this;
	}

	/**
	 * Get texto
	 *
	 * @return string
	 */
	public function getTexto()
	{
		return $this->texto;
	}

	public function __toString()
	{
		return strval($this->idExpediente);
	}

	/**
	 * Set idResponsableZona
	 *
	 * @param Usuario|null $idResponsableZona
	 * @return Expediente
	 */
	public function setIdResponsableZona(Usuario $idResponsableZona = null)
	{
		$this->idResponsableZona = $idResponsableZona;
		return $this;
	}

	/**
	 * Get idResponsableZona
	 *
	 * @return Usuario|null
	 */
	public function getIdResponsableZona()
	{
		return $this->idResponsableZona;
	}

	/**
	 * Set whatsappAutomatico
	 *
	 * @param boolean $whatsappAutomatico
	 * @return Expediente
	 */
	public function setWhatsappAutomatico($whatsappAutomatico)
	{
		$this->whatsappAutomatico = (bool) $whatsappAutomatico;
		return $this;
	}

	/**
	 * Get whatsappAutomatico
	 *
	 * @return boolean
	 */
	public function getWhatsappAutomatico()
	{
		return $this->whatsappAutomatico;
	}

	/**
	 * Set whatsappAutomaticoEnviado
	 *
	 * @param boolean $whatsappAutomaticoEnviado
	 * @return Expediente
	 */
	public function setWhatsappAutomaticoEnviado($whatsappAutomaticoEnviado)
	{
		$this->whatsappAutomaticoEnviado = (bool) $whatsappAutomaticoEnviado;
		return $this;
	}

	/**
	 * Get whatsappAutomaticoEnviado
	 *
	 * @return boolean
	 */
	public function getWhatsappAutomaticoEnviado()
	{
		return $this->whatsappAutomaticoEnviado;
	}

	/**
	 * Doctrine lifecycle callback.
	 * Actualiza `fechaModificacion` antes de un update en BD.
	 */
	public function updateFechaModificacion()
	{
		$this->setFechaModificacion(new DateTime());
	}
}
