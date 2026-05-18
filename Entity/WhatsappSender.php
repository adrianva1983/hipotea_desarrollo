<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * WhatsappSender
 *
 * @ORM\Table(name="WhatsappSenders", indexes={
 *     @ORM\Index(name="IDX_IdAgencia", columns={"IdAgencia"}),
 *     @ORM\Index(name="IDX_IdUsuario", columns={"IdUsuario"})
 * })
 * @ORM\Entity(repositoryClass="AppBundle\Repository\WhatsappSenderRepository")
 */
class WhatsappSender
{
    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="IdAgencia", type="integer", nullable=true)
     */
    private $idAgencia;

    /**
     * @var int|null
     *
     * @ORM\Column(name="IdUsuario", type="integer", nullable=true)
     */
    private $idUsuario;

    /**
     * @var string
     *
     * @ORM\Column(name="Telefono", type="string", length=20, nullable=false)
     */
    private $telefono;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="FechaUltimaInteraccion", type="datetime", nullable=true)
     */
    private $fechaUltimaInteraccion;

    /**
     * @var int
     *
     * @ORM\Column(name="Version", type="integer", nullable=false)
     */
    private $version;

    /**
     * @var string|null
     *
     * @ORM\Column(name="ImagenQR", type="text", nullable=true)
     */
    private $imagenQR;

    /**
     * @var string|null
     *
     * @ORM\Column(name="PathEjecutable", type="string", length=255, nullable=true)
     */
    private $pathEjecutable;

    /**
     * @var bool
     *
     * @ORM\Column(name="CrucesAutomaticos", type="boolean", nullable=false, options={"default"=true})
     */
    private $crucesAutomaticos = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="CrucesAutomaticosRGPDExterna", type="boolean", nullable=false, options={"default"=false})
     */
    private $crucesAutomaticosRGPDExterna = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="AutomatizacionesWhatsapp", type="boolean", nullable=false, options={"default"=false})
     */
    private $automatizacionesWhatsapp = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="SyncConversaciones", type="boolean", nullable=false, options={"default"=true})
     */
    private $syncConversaciones = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="RecordatoriosVisitas", type="boolean", nullable=false, options={"default"=true})
     */
    private $recordatoriosVisitas = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="PilotoAutomatico", type="boolean", nullable=false, options={"default"=false})
     */
    private $pilotoAutomatico = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="PilotoAutomaticoSystemPrompt", type="text", nullable=true)
     */
    private $pilotoAutomaticoSystemPrompt;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="MensajeTrasLead", type="boolean", nullable=true, options={"default"=false})
     */
    private $mensajeTrasLead = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="MensajeTrasLeadUsuarioUnico", type="boolean", nullable=false, options={"default"=false})
     */
    private $mensajeTrasLeadUsuarioUnico = false;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="FechaUltimoMensajeTrasLead", type="datetime", nullable=true)
     */
    private $fechaUltimoMensajeTrasLead;

    /**
     * @var bool
     *
     * @ORM\Column(name="AgendaVisitaTrasLead", type="boolean", nullable=false, options={"default"=false})
     */
    private $agendaVisitaTrasLead = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="LeadsAComercial", type="boolean", nullable=false, options={"default"=false})
     */
    private $leadsAComercial = false;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="FechaUltimoMensajeLeadComercial", type="datetime", nullable=true)
     */
    private $fechaUltimoMensajeLeadComercial;

    /**
     * @var string|null
     *
     * @ORM\Column(name="ObservacionesError", type="text", nullable=true)
     */
    private $observacionesError;

    /**
     * @var string|null
     *
     * @ORM\Column(name="Servidor", type="string", length=255, nullable=true)
     */
    private $servidor;

    /**
     * @var string|null
     *
     * @ORM\Column(name="SessionId", type="string", length=255, nullable=true)
     */
    private $sessionId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="Configuracion", type="text", nullable=true)
     */
    private $configuracion;

    // Getters y Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdAgencia(): ?int
    {
        return $this->idAgencia;
    }

    public function setIdAgencia(?int $idAgencia): self
    {
        $this->idAgencia = $idAgencia;
        return $this;
    }

    public function getIdUsuario(): ?int
    {
        return $this->idUsuario;
    }

    public function setIdUsuario(?int $idUsuario): self
    {
        $this->idUsuario = $idUsuario;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(string $telefono): self
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getFechaUltimaInteraccion(): ?DateTime
    {
        return $this->fechaUltimaInteraccion;
    }

    public function setFechaUltimaInteraccion(?DateTime $fechaUltimaInteraccion): self
    {
        $this->fechaUltimaInteraccion = $fechaUltimaInteraccion;
        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getImagenQR(): ?string
    {
        return $this->imagenQR;
    }

    public function setImagenQR(?string $imagenQR): self
    {
        $this->imagenQR = $imagenQR;
        return $this;
    }

    public function getPathEjecutable(): ?string
    {
        return $this->pathEjecutable;
    }

    public function setPathEjecutable(?string $pathEjecutable): self
    {
        $this->pathEjecutable = $pathEjecutable;
        return $this;
    }

    public function getCrucesAutomaticos(): ?bool
    {
        return $this->crucesAutomaticos;
    }

    public function setCrucesAutomaticos(bool $crucesAutomaticos): self
    {
        $this->crucesAutomaticos = $crucesAutomaticos;
        return $this;
    }

    public function getCrucesAutomaticosRGPDExterna(): ?bool
    {
        return $this->crucesAutomaticosRGPDExterna;
    }

    public function setCrucesAutomaticosRGPDExterna(bool $crucesAutomaticosRGPDExterna): self
    {
        $this->crucesAutomaticosRGPDExterna = $crucesAutomaticosRGPDExterna;
        return $this;
    }

    public function getAutomatizacionesWhatsapp(): ?bool
    {
        return $this->automatizacionesWhatsapp;
    }

    public function setAutomatizacionesWhatsapp(bool $automatizacionesWhatsapp): self
    {
        $this->automatizacionesWhatsapp = $automatizacionesWhatsapp;
        return $this;
    }

    public function getSyncConversaciones(): ?bool
    {
        return $this->syncConversaciones;
    }

    public function setSyncConversaciones(bool $syncConversaciones): self
    {
        $this->syncConversaciones = $syncConversaciones;
        return $this;
    }

    public function getRecordatoriosVisitas(): ?bool
    {
        return $this->recordatoriosVisitas;
    }

    public function setRecordatoriosVisitas(bool $recordatoriosVisitas): self
    {
        $this->recordatoriosVisitas = $recordatoriosVisitas;
        return $this;
    }

    public function getPilotoAutomatico(): ?bool
    {
        return $this->pilotoAutomatico;
    }

    public function setPilotoAutomatico(bool $pilotoAutomatico): self
    {
        $this->pilotoAutomatico = $pilotoAutomatico;
        return $this;
    }

    public function getPilotoAutomaticoSystemPrompt(): ?string
    {
        return $this->pilotoAutomaticoSystemPrompt;
    }

    public function setPilotoAutomaticoSystemPrompt(?string $pilotoAutomaticoSystemPrompt): self
    {
        $this->pilotoAutomaticoSystemPrompt = $pilotoAutomaticoSystemPrompt;
        return $this;
    }

    public function getMensajeTrasLead(): ?bool
    {
        return $this->mensajeTrasLead;
    }

    public function setMensajeTrasLead(?bool $mensajeTrasLead): self
    {
        $this->mensajeTrasLead = $mensajeTrasLead;
        return $this;
    }

    public function getMensajeTrasLeadUsuarioUnico(): ?bool
    {
        return $this->mensajeTrasLeadUsuarioUnico;
    }

    public function setMensajeTrasLeadUsuarioUnico(bool $mensajeTrasLeadUsuarioUnico): self
    {
        $this->mensajeTrasLeadUsuarioUnico = $mensajeTrasLeadUsuarioUnico;
        return $this;
    }

    public function getFechaUltimoMensajeTrasLead(): ?DateTime
    {
        return $this->fechaUltimoMensajeTrasLead;
    }

    public function setFechaUltimoMensajeTrasLead(?DateTime $fechaUltimoMensajeTrasLead): self
    {
        $this->fechaUltimoMensajeTrasLead = $fechaUltimoMensajeTrasLead;
        return $this;
    }

    public function getAgendaVisitaTrasLead(): ?bool
    {
        return $this->agendaVisitaTrasLead;
    }

    public function setAgendaVisitaTrasLead(bool $agendaVisitaTrasLead): self
    {
        $this->agendaVisitaTrasLead = $agendaVisitaTrasLead;
        return $this;
    }

    public function getLeadsAComercial(): ?bool
    {
        return $this->leadsAComercial;
    }

    public function setLeadsAComercial(bool $leadsAComercial): self
    {
        $this->leadsAComercial = $leadsAComercial;
        return $this;
    }

    public function getFechaUltimoMensajeLeadComercial(): ?DateTime
    {
        return $this->fechaUltimoMensajeLeadComercial;
    }

    public function setFechaUltimoMensajeLeadComercial(?DateTime $fechaUltimoMensajeLeadComercial): self
    {
        $this->fechaUltimoMensajeLeadComercial = $fechaUltimoMensajeLeadComercial;
        return $this;
    }

    public function getObservacionesError(): ?string
    {
        return $this->observacionesError;
    }

    public function setObservacionesError(?string $observacionesError): self
    {
        $this->observacionesError = $observacionesError;
        return $this;
    }

    public function getServidor(): ?string
    {
        return $this->servidor;
    }

    public function setServidor(?string $servidor): self
    {
        $this->servidor = $servidor;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getConfiguracion(): ?string
    {
        return $this->configuracion;
    }

    public function setConfiguracion(?string $configuracion): self
    {
        $this->configuracion = $configuracion;
        return $this;
    }
}
