<?php

namespace AppBundle\Entity;

class VistaRotacionComerciales
{
    private $idUsuario;
    private $role;
    private $nombre;
    private $apellidos;
    private $maxOperacionesVivas;
    private $ultimaAsignacion;
    private $operacionesDisponibles;

    public function getIdUsuario()
    {
        return $this->idUsuario;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function getApellidos()
    {
        return $this->apellidos;
    }

    public function getMaxOperacionesVivas()
    {
        return $this->maxOperacionesVivas;
    }

    public function getUltimaAsignacion()
    {
        return $this->ultimaAsignacion;
    }

    public function getOperacionesDisponibles()
    {
        return $this->operacionesDisponibles;
    }
}
