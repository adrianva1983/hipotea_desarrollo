<?php

namespace AppBundle\Entity;

class ImpuestoCcaa
{
    private $id;
    private $comunidadAutonoma;
    private $tipoImpuesto;
    private $porcentajeDefecto;
    private $porcentajeBonificadoJovenes;
    private $porcentajeBonificadoVpo;

    public function getId()
    {
        return $this->id;
    }

    public function getComunidadAutonoma()
    {
        return $this->comunidadAutonoma;
    }

    public function setComunidadAutonoma($comunidadAutonoma)
    {
        $this->comunidadAutonoma = $comunidadAutonoma;
        return $this;
    }

    public function getTipoImpuesto()
    {
        return $this->tipoImpuesto;
    }

    public function setTipoImpuesto($tipoImpuesto)
    {
        $this->tipoImpuesto = $tipoImpuesto;
        return $this;
    }

    public function getPorcentajeDefecto()
    {
        return $this->porcentajeDefecto;
    }

    public function setPorcentajeDefecto($porcentajeDefecto)
    {
        $this->porcentajeDefecto = $porcentajeDefecto;
        return $this;
    }

    public function getPorcentajeBonificadoJovenes()
    {
        return $this->porcentajeBonificadoJovenes;
    }

    public function setPorcentajeBonificadoJovenes($porcentajeBonificadoJovenes)
    {
        $this->porcentajeBonificadoJovenes = $porcentajeBonificadoJovenes;
        return $this;
    }

    public function getPorcentajeBonificadoVpo()
    {
        return $this->porcentajeBonificadoVpo;
    }

    public function setPorcentajeBonificadoVpo($porcentajeBonificadoVpo)
    {
        $this->porcentajeBonificadoVpo = $porcentajeBonificadoVpo;
        return $this;
    }
}
