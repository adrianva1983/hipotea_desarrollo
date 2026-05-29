<?php

namespace AppBundle\Entity;

class ReglasNegocio
{
    private $id;
    private $edadMaximaAlVencimiento;
    private $porcentajeMaximoEndeudamiento;
    private $gastosFijosTasacion;
    private $gastosFijosNotario;
    private $gastosFijosGestoria;
    private $edadJovenFrontera;

    public function getId()
    {
        return $this->id;
    }

    public function getEdadMaximaAlVencimiento()
    {
        return $this->edadMaximaAlVencimiento;
    }

    public function setEdadMaximaAlVencimiento($edadMaximaAlVencimiento)
    {
        $this->edadMaximaAlVencimiento = $edadMaximaAlVencimiento;
        return $this;
    }

    public function getPorcentajeMaximoEndeudamiento()
    {
        return $this->porcentajeMaximoEndeudamiento;
    }

    public function setPorcentajeMaximoEndeudamiento($porcentajeMaximoEndeudamiento)
    {
        $this->porcentajeMaximoEndeudamiento = $porcentajeMaximoEndeudamiento;
        return $this;
    }

    public function getGastosFijosTasacion()
    {
        return $this->gastosFijosTasacion;
    }

    public function setGastosFijosTasacion($gastosFijosTasacion)
    {
        $this->gastosFijosTasacion = $gastosFijosTasacion;
        return $this;
    }

    public function getGastosFijosNotario()
    {
        return $this->gastosFijosNotario;
    }

    public function setGastosFijosNotario($gastosFijosNotario)
    {
        $this->gastosFijosNotario = $gastosFijosNotario;
        return $this;
    }

    public function getGastosFijosGestoria()
    {
        return $this->gastosFijosGestoria;
    }

    public function setGastosFijosGestoria($gastosFijosGestoria)
    {
        $this->gastosFijosGestoria = $gastosFijosGestoria;
        return $this;
    }

    public function getEdadJovenFrontera()
    {
        return $this->edadJovenFrontera;
    }

    public function setEdadJovenFrontera($edadJovenFrontera)
    {
        $this->edadJovenFrontera = $edadJovenFrontera;
        return $this;
    }
}
