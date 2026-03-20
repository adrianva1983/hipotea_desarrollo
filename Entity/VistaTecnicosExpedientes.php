<?php

namespace AppBundle\Entity;

class VistaTecnicosExpedientes
{
    private $idUsuario;
    private $role;
    private $maxOperacionesVivas;
    private $numExpedientes;
    private $numDisponibles;

    public function getIdUsuario()
    {
        return $this->idUsuario;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getMaxOperacionesVivas()
    {
        return $this->maxOperacionesVivas;
    }

    public function getNumExpedientes()
    {
        return $this->numExpedientes;
    }

    public function getNumDisponibles()
    {
        return $this->numDisponibles;
    }
}
