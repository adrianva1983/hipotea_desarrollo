<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WhatsappServidor
 *
 * @ORM\Table(name="WhatsappServidores", indexes={
 *     @ORM\Index(name="IDX_IP", columns={"IP"}),
 *     @ORM\Index(name="IDX_Estado", columns={"Estado"})
 * })
 * @ORM\Entity(repositoryClass="AppBundle\Repository\WhatsappServidorRepository")
 */
class WhatsappServidor
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
     * @var string
     *
     * @ORM\Column(name="IP", type="string", length=20, nullable=false)
     */
    private $ip;

    /**
     * @var int
     *
     * @ORM\Column(name="MaxConectados", type="integer", nullable=false, options={"default"=10})
     */
    private $maxConectados = 10;

    /**
     * @var bool
     *
     * @ORM\Column(name="Estado", type="boolean", nullable=false, options={"default"=true})
     */
    private $estado = true;

    // Getters y Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getMaxConectados(): ?int
    {
        return $this->maxConectados;
    }

    public function setMaxConectados(int $maxConectados): self
    {
        $this->maxConectados = $maxConectados;
        return $this;
    }

    public function getEstado(): ?bool
    {
        return $this->estado;
    }

    public function setEstado(bool $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    /**
     * Obtener estado como texto
     */
    public function getEstadoTexto(): string
    {
        return $this->estado ? 'Activo' : 'Inactivo';
    }
}
