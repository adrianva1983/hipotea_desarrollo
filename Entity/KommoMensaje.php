<?php

namespace AppBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\KommoMensajeRepository")
 * @ORM\Table(name="kommo_mensaje")
 */
class KommoMensaje
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="kommo_lead_id")
     */
    private $kommoLeadId;

    /**
     * @ORM\Column(type="text")
     */
    private $messageText;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $messageType;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $rawPayload;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->status = 'received';
    }

    /**
     * Procesa webhook recibido de Kommo
     * 
     * @param array $payload Datos del webhook
     * @return bool
     */
    public function procesarWebhook(array $payload): bool
    {
        try {
            if (!isset($payload['leads'][0]['id']) || !isset($payload['leads'][0]['custom_fields_values'])) {
                error_log("? Kommo: Payload inválido - estructura inesperada");
                return false;
            }

            $lead = $payload['leads'][0];
            $this->kommoLeadId = $lead['id'];
            $this->rawPayload = $payload;

            // Extraer mensaje de campos personalizados si existe
            if (isset($lead['custom_fields_values']['message'])) {
                $this->messageText = $lead['custom_fields_values']['message'];
            }

            // Extraer tipo de evento/mensaje
            if (isset($payload['event']['type'])) {
                $this->messageType = $payload['event']['type'];
            }

            $this->updatedAt = new DateTime();
            error_log("? Kommo: Webhook procesado - Lead ID: {$this->kommoLeadId}");
            return true;
        } catch (\Exception $e) {
            error_log("? Kommo: Error procesando webhook - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valida que el mensaje tenga datos mínimos requeridos
     * 
     * @return bool
     */
    public function esValido(): bool
    {
        return $this->kommoLeadId !== null && !empty($this->messageText);
    }

    /**
     * Getters y Setters
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKommoLeadId(): ?int
    {
        return $this->kommoLeadId;
    }

    public function setKommoLeadId(int $kommoLeadId): self
    {
        $this->kommoLeadId = $kommoLeadId;
        return $this;
    }

    public function getMessageText(): ?string
    {
        return $this->messageText;
    }

    public function setMessageText(string $messageText): self
    {
        $this->messageText = $messageText;
        return $this;
    }

    public function getMessageType(): ?string
    {
        return $this->messageType;
    }

    public function setMessageType(?string $messageType): self
    {
        $this->messageType = $messageType;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(?array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;
        return $this;
    }
}
