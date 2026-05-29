<?php

namespace AppBundle\Entity;

class ProductoHipotecario
{
    private $id;
    private $codigoProducto;
    private $tipoFijo;
    private $tipoVariable;
    private $tipoMixto;
    private $comisionApertura;
    private $permiteVinculaciones;
    private $levantamientoRegistral;

    public function getId()
    {
        return $this->id;
    }

    public function getCodigoProducto()
    {
        return $this->codigoProducto;
    }

    public function setCodigoProducto($codigoProducto)
    {
        $this->codigoProducto = $codigoProducto;
        return $this;
    }

    public function getTipoFijo()
    {
        return $this->tipoFijo;
    }

    public function setTipoFijo($tipoFijo)
    {
        $this->tipoFijo = $tipoFijo;
        return $this;
    }

    public function getTipoVariable()
    {
        return $this->tipoVariable;
    }

    public function setTipoVariable($tipoVariable)
    {
        $this->tipoVariable = $tipoVariable;
        return $this;
    }

    public function getTipoMixto()
    {
        return $this->tipoMixto;
    }

    public function setTipoMixto($tipoMixto)
    {
        $this->tipoMixto = $tipoMixto;
        return $this;
    }

    public function getComisionApertura()
    {
        return $this->comisionApertura;
    }

    public function setComisionApertura($comisionApertura)
    {
        $this->comisionApertura = $comisionApertura;
        return $this;
    }

    public function getPermiteVinculaciones()
    {
        return $this->permiteVinculaciones;
    }

    public function setPermiteVinculaciones($permiteVinculaciones)
    {
        $this->permiteVinculaciones = $permiteVinculaciones;
        return $this;
    }

    public function getLevantamientoRegistral()
    {
        return $this->levantamientoRegistral;
    }

    public function setLevantamientoRegistral($levantamientoRegistral)
    {
        $this->levantamientoRegistral = $levantamientoRegistral;
        return $this;
    }
}
