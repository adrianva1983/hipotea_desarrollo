<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DocumentoAnalisisRepository")
 * @ORM\Table(name="documento_analisis")
 */
class DocumentoAnalisis
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $idAnalisis;

    /**
     * @ORM\Column(type="integer")
     */
    private $idExpediente;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idFicheroCampo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nombreDocumento;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $tipoDocumento;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $contenidoExtraido;

    /**
     * @ORM\Column(type="decimal", precision=3, scale=2, nullable=true)
     */
    private $confianza;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $proveedorIa;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $modeloIa;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $tokensUsados = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $tokensEntrada = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $tokensSalida = 0;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fechaAnalisis;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $estado = 'procesado';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $mensajeError;

    public function __construct()
    {
        $this->fechaAnalisis = new \DateTime();
    }

    // GETTERS Y SETTERS

    public function getIdAnalisis()
    {
        return $this->idAnalisis;
    }

    public function setIdExpediente($idExpediente)
    {
        $this->idExpediente = $idExpediente;
        return $this;
    }

    public function getIdExpediente()
    {
        return $this->idExpediente;
    }

    public function setIdFicheroCampo($idFicheroCampo)
    {
        $this->idFicheroCampo = $idFicheroCampo;
        return $this;
    }

    public function getIdFicheroCampo()
    {
        return $this->idFicheroCampo;
    }

    public function setNombreDocumento($nombreDocumento)
    {
        $this->nombreDocumento = $nombreDocumento;
        return $this;
    }

    public function getNombreDocumento()
    {
        return $this->nombreDocumento;
    }

    public function setTipoDocumento($tipoDocumento)
    {
        $this->tipoDocumento = $tipoDocumento;
        return $this;
    }

    public function getTipoDocumento()
    {
        return $this->tipoDocumento;
    }

    public function setContenidoExtraido($contenidoExtraido)
    {
        $this->contenidoExtraido = $contenidoExtraido;
        return $this;
    }

    public function getContenidoExtraido()
    {
        return $this->contenidoExtraido;
    }

    public function setConfianza($confianza)
    {
        $this->confianza = $confianza;
        return $this;
    }

    public function getConfianza()
    {
        return $this->confianza;
    }

    public function setProveedorIa($proveedorIa)
    {
        $this->proveedorIa = $proveedorIa;
        return $this;
    }

    public function getProveedorIa()
    {
        return $this->proveedorIa;
    }

    public function setModeloIa($modeloIa)
    {
        $this->modeloIa = $modeloIa;
        return $this;
    }

    public function getModeloIa()
    {
        return $this->modeloIa;
    }

    public function setTokensUsados($tokensUsados)
    {
        $this->tokensUsados = $tokensUsados;
        return $this;
    }

    public function getTokensUsados()
    {
        return $this->tokensUsados;
    }

    public function setTokensEntrada($tokensEntrada)
    {
        $this->tokensEntrada = $tokensEntrada;
        return $this;
    }

    public function getTokensEntrada()
    {
        return $this->tokensEntrada;
    }

    public function setTokensSalida($tokensSalida)
    {
        $this->tokensSalida = $tokensSalida;
        return $this;
    }

    public function getTokensSalida()
    {
        return $this->tokensSalida;
    }

    public function setFechaAnalisis(\DateTime $fechaAnalisis)
    {
        $this->fechaAnalisis = $fechaAnalisis;
        return $this;
    }

    public function getFechaAnalisis()
    {
        return $this->fechaAnalisis;
    }

    public function setEstado($estado)
    {
        $this->estado = $estado;
        return $this;
    }

    public function getEstado()
    {
        return $this->estado;
    }

    public function setMensajeError($mensajeError)
    {
        $this->mensajeError = $mensajeError;
        return $this;
    }

    public function getMensajeError()
    {
        return $this->mensajeError;
    }
}
