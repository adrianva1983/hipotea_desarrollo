<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityManagerInterface;
use DateTime;

/**
 * @ORM\Entity(repositoryClass=BelenderHitoExpedienteRepository::class)
 * @ORM\Table(name="belender_hito_expediente")
 */
class BelenderHitoExpediente
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="id_hito_expediente")
     */
    private $idHitoExpediente;

    /**
     * @ORM\Column(type="string", length=255, name="dni_belender")
     */
    private $dniBelender;

    /**
     * @ORM\Column(type="string", length=255, name="requestid_belender")
     */
    private $requestIdBelender;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $fecha;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="fecha_notificacion")
     */
    private $fechaNotificacion;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="fecha_descarga")
     */
    private $fechaDescarga;

    /**
     * @ORM\Column(type="string", length=50, name="tipo_peticion", options={"default"="BELENDER"})
     */
    private $tipoPeticion = 'BELENDER';

    /**
     * @ORM\Column(type="string", length=100, nullable=true, name="status_code")
     */
    private $statusCode;

    /**
     * @ORM\Column(type="datetime", nullable=true, name="fecha_status")
     */
    private $fechaStatus;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdHitoExpediente(): ?int
    {
        return $this->idHitoExpediente;
    }

    public function setIdHitoExpediente(int $idHitoExpediente): self
    {
        $this->idHitoExpediente = $idHitoExpediente;

        return $this;
    }

    public function getDniBelender(): ?string
    {
        return $this->dniBelender;
    }

    public function setDniBelender(string $dniBelender): self
    {
        $this->dniBelender = $dniBelender;

        return $this;
    }

    public function getRequestIdBelender(): ?string
    {
        return $this->requestIdBelender;
    }

    public function setRequestIdBelender(string $requestIdBelender): self
    {
        $this->requestIdBelender = $requestIdBelender;

        return $this;
    }

    public function getFecha(): ?DateTime
    {
        return $this->fecha;
    }

    public function setFecha(?DateTime $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getFechaNotificacion(): ?DateTime
    {
        return $this->fechaNotificacion;
    }

    public function setFechaNotificacion(?DateTime $fechaNotificacion): self
    {
        $this->fechaNotificacion = $fechaNotificacion;

        return $this;
    }

    public function getFechaDescarga(): ?DateTime
    {
        return $this->fechaDescarga;
    }

    public function setFechaDescarga(?DateTime $fechaDescarga): self
    {
        $this->fechaDescarga = $fechaDescarga;

        return $this;
    }

    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function setStatusCode(?string $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getFechaStatus(): ?DateTime
    {
        return $this->fechaStatus;
    }

    public function setFechaStatus(?DateTime $fechaStatus): self
    {
        $this->fechaStatus = $fechaStatus;

        return $this;
    }

    public function getTipoPeticion(): ?string
    {
        return $this->tipoPeticion;
    }

    public function setTipoPeticion(string $tipoPeticion): self
    {
        $this->tipoPeticion = $tipoPeticion;

        return $this;
    }
}
