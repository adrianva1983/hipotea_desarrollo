<?php

namespace AppBundle\Entity;

class VistaExpedientesRelacionados
{
    private $idExpediente;
    private $idsExpedientesRelacionados;

    public function getIdExpediente()
    {
        return $this->idExpediente;
    }

    public function getIdsExpedientesRelacionados()
    {
        return $this->idsExpedientesRelacionados;
    }
}
