<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ReglasNegocio
 *
 * @ORM\Table(name="reglas_negocio")
 * @ORM\Entity
 */
class ReglasNegocio
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
     * @var int
     *
     * @ORM\Column(name="edad_maxima_al_vencimiento", type="integer")
     */
    private $edadMaximaAlVencimiento;

    /**
     * @var float
     *
     * @ORM\Column(name="porcentaje_maximo_endeudamiento", type="float")
     */
    private $porcentajeMaximoEndeudamiento;

    /**
     * @var float
     *
     * @ORM\Column(name="gastos_fijos_tasacion", type="float")
     */
    private $gastosFijosTasacion;

    /**
     * @var float
     *
     * @ORM\Column(name="gastos_fijos_notario", type="float")
     */
    private $gastosFijosNotario;

    /**
     * @var float
     *
     * @ORM\Column(name="gastos_fijos_gestoria", type="float")
     */
    private $gastosFijosGestoria;

    /**
     * @var int
     *
     * @ORM\Column(name="edad_joven_frontera", type="integer", nullable=true)
     */
    private $edadJovenFrontera;

    public function __construct()
    {
        $this->edadMaximaAlVencimiento = 75;
        $this->porcentajeMaximoEndeudamiento = 0.35;
        $this->gastosFijosTasacion = 0;
        $this->gastosFijosNotario = 0;
        $this->gastosFijosGestoria = 0;
        $this->edadJovenFrontera = 35;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setEdadMaximaAlVencimiento($edadMaximaAlVencimiento)
    {
        $this->edadMaximaAlVencimiento = $edadMaximaAlVencimiento;
        return $this;
    }

    public function getEdadMaximaAlVencimiento()
    {
        return $this->edadMaximaAlVencimiento;
    }

    public function setPorcentajeMaximoEndeudamiento($porcentajeMaximoEndeudamiento)
    {
        $this->porcentajeMaximoEndeudamiento = $porcentajeMaximoEndeudamiento;
        return $this;
    }

    public function getPorcentajeMaximoEndeudamiento()
    {
        return $this->porcentajeMaximoEndeudamiento;
    }

    public function setGastosFijosTasacion($gastosFijosTasacion)
    {
        $this->gastosFijosTasacion = $gastosFijosTasacion;
        return $this;
    }

    public function getGastosFijosTasacion()
    {
        return $this->gastosFijosTasacion;
    }

    public function setGastosFijosNotario($gastosFijosNotario)
    {
        $this->gastosFijosNotario = $gastosFijosNotario;
        return $this;
    }

    public function getGastosFijosNotario()
    {
        return $this->gastosFijosNotario;
    }

    public function setGastosFijosGestoria($gastosFijosGestoria)
    {
        $this->gastosFijosGestoria = $gastosFijosGestoria;
        return $this;
    }

    public function getGastosFijosGestoria()
    {
        return $this->gastosFijosGestoria;
    }

    public function setEdadJovenFrontera($edadJovenFrontera)
    {
        $this->edadJovenFrontera = $edadJovenFrontera;
        return $this;
    }

    public function getEdadJovenFrontera()
    {
        return $this->edadJovenFrontera;
    }
}
