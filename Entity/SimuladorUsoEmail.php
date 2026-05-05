<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SimuladorUsoEmail
 *
 * @ORM\Table(name="simulador_uso_email", indexes={@ORM\Index(name="idx_email", columns={"email"})})
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SimuladorUsoEmailRepository")
 */
class SimuladorUsoEmail
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     */
    private $email;

    /**
     * @var int
     *
     * @ORM\Column(name="usos", type="integer", options={"default" : 1})
     */
    private $usos = 1;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="primer_uso", type="datetime")
     */
    private $primerUso;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="ultimo_uso", type="datetime")
     */
    private $ultimoUso;

    public function getId()
    {
        return $this->id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function getUsos()
    {
        return $this->usos;
    }

    public function setUsos($usos)
    {
        $this->usos = $usos;
        return $this;
    }

    public function incrementarUsos()
    {
        $this->usos++;
        $this->ultimoUso = new \DateTime();
        return $this;
    }

    public function getPrimerUso()
    {
        return $this->primerUso;
    }

    public function setPrimerUso(\DateTime $primerUso)
    {
        $this->primerUso = $primerUso;
        return $this;
    }

    public function getUltimoUso()
    {
        return $this->ultimoUso;
    }

    public function setUltimoUso(\DateTime $ultimoUso)
    {
        $this->ultimoUso = $ultimoUso;
        return $this;
    }
}