<?php

namespace AppBundle\Entity;

class VistaComercialesExpedientes
{
    private $idUsuario;
    private $role;
    private $maxOperacionesVivas;
    private $numExpedientes;
    private $numDisponibles;

    // Getters...
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
