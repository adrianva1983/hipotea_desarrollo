<?php

namespace AppBundle\Entity;

use DateTime;

/**
 * LineaFactura
 */
class SeguimientoExpediente
{
	/**
	 * @var integer
	 */
	private $idSeguimientoExpediente;

	/**
	 * @var DateTime
	 */
	private $fecha;

    /**
	 * @var string
	 */
	private $comentario;

	/**
	 * @var integer
	 */
    private $cliente = 0;
    
    /**
	 * @var integer
	 */
    private $colaborador = 0;
    
    /**
	 * @var Expediente
	 */
	private $idExpediente;

	/**
	 * @var ConceptoSeguimientoExpediente
	 */
	private $idConceptoSeguimientoExpediente;

	/**
	 * Get the value of idSeguimientoExpediente
	 *
	 * @return  integer
	 */ 
	public function getIdSeguimientoExpediente()
	{
		return $this->idSeguimientoExpediente;
	}

	/**
	 * Set the value of idSeguimientoExpediente
	 *
	 * @param  integer  $idSeguimientoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdSeguimientoExpediente($idSeguimientoExpediente)
	{
		$this->idSeguimientoExpediente = $idSeguimientoExpediente;

		return $this;
	}

	/**
	 * Get the value of fecha
	 *
	 * @return  DateTime
	 */ 
	public function getFecha()
	{
		return $this->fecha;
	}

	/**
	 * Set the value of fecha
	 *
	 * @param  DateTime  $fecha
	 *
	 * @return  self
	 */ 
	public function setFecha(DateTime $fecha)
	{
		$this->fecha = $fecha;

		return $this;
	}

	/**
	 * Get the value of comentario
	 *
	 * @return  string
	 */ 
	public function getComentario()
	{
		return $this->comentario;
	}

	/**
	 * Set the value of comentario
	 *
	 * @param  string  $comentario
	 *
	 * @return  self
	 */ 
	public function setComentario(string $comentario)
	{
		$this->comentario = $comentario;

		return $this;
	}

    

	/**
	 * Get the value of idExpediente
	 *
	 * @return  Expediente
	 */ 
	public function getIdExpediente()
	{
		return $this->idExpediente;
	}

	/**
	 * Set the value of idExpediente
	 *
	 * @param  Expediente  $idExpediente
	 *
	 * @return  self
	 */ 
	public function setIdExpediente(Expediente $idExpediente)
	{
		$this->idExpediente = $idExpediente;

		return $this;
	}

	/**
	 * Get the value of idConceptoSeguimientoExpediente
	 *
	 * @return  ConceptoSeguimientoExpediente
	 */ 
	public function getIdConceptoSeguimientoExpediente()
	{
		return $this->idConceptoSeguimientoExpediente;
	}

	/**
	 * Set the value of idConceptoSeguimientoExpediente
	 *
	 * @param  ConceptoSeguimientoExpediente  $idConceptoSeguimientoExpediente
	 *
	 * @return  self
	 */ 
	public function setIdConceptoSeguimientoExpediente(ConceptoSeguimientoExpediente $idConceptoSeguimientoExpediente)
	{
		$this->idConceptoSeguimientoExpediente = $idConceptoSeguimientoExpediente;

		return $this;
    }

    /**
     * Get the value of cliente
     *
     * @return  integer
     */ 
    public function getCliente()
    {
        return $this->cliente;
    }

    /**
     * Set the value of cliente
     *
     * @param  integer  $cliente
     *
     * @return  self
     */ 
    public function setCliente($cliente)
    {
        $this->cliente = $cliente;

        return $this;
    }

    /**
     * Get the value of colaborador
     *
     * @return  integer
     */ 
    public function getColaborador()
    {
        return $this->colaborador;
    }

    /**
     * Set the value of colaborador
     *
     * @param  integer  $colaborador
     *
     * @return  self
     */ 
    public function setColaborador($colaborador)
    {
        $this->colaborador = $colaborador;

        return $this;
    }

    public function __toString()
	{
		return $this->comentario;
	}
}
