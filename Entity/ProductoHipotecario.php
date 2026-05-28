<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductoHipotecario
 *
 * @ORM\Table(name="producto_hipotecario")
 * @ORM\Entity
 */
class ProductoHipotecario
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
     * @ORM\Column(name="codigo_producto", type="string", length=255, unique=true)
     */
    private $codigoProducto;

    /**
     * @var float
     *
     * @ORM\Column(name="tipo_fijo", type="float", nullable=true)
     */
    private $tipoFijo;

    /**
     * @var float
     *
     * @ORM\Column(name="tipo_variable", type="float", nullable=true)
     */
    private $tipoVariable;

    /**
     * @var float
     *
     * @ORM\Column(name="tipo_mixto", type="float", nullable=true)
     */
    private $tipoMixto;

    /**
     * @var float
     *
     * @ORM\Column(name="comision_apertura", type="float", nullable=true)
     */
    private $comisionApertura;

    /**
     * @var bool
     *
     * @ORM\Column(name="permite_vinculaciones", type="boolean")
     */
    private $permiteVinculaciones;

    /**
     * @var float
     *
     * @ORM\Column(name="levantamiento_registral", type="float", nullable=true)
     */
    private $levantamientoRegistral;

    public function __construct()
    {
        $this->permiteVinculaciones = true;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setCodigoProducto($codigoProducto)
    {
        $this->codigoProducto = $codigoProducto;
        return $this;
    }

    public function getCodigoProducto()
    {
        return $this->codigoProducto;
    }

    public function setTipoFijo($tipoFijo)
    {
        $this->tipoFijo = $tipoFijo;
        return $this;
    }

    public function getTipoFijo()
    {
        return $this->tipoFijo;
    }

    public function setTipoVariable($tipoVariable)
    {
        $this->tipoVariable = $tipoVariable;
        return $this;
    }

    public function getTipoVariable()
    {
        return $this->tipoVariable;
    }

    public function setTipoMixto($tipoMixto)
    {
        $this->tipoMixto = $tipoMixto;
        return $this;
    }

    public function getTipoMixto()
    {
        return $this->tipoMixto;
    }

    public function setComisionApertura($comisionApertura)
    {
        $this->comisionApertura = $comisionApertura;
        return $this;
    }

    public function getComisionApertura()
    {
        return $this->comisionApertura;
    }

    public function setPermiteVinculaciones($permiteVinculaciones)
    {
        $this->permiteVinculaciones = $permiteVinculaciones;
        return $this;
    }

    public function getPermiteVinculaciones()
    {
        return $this->permiteVinculaciones;
    }

    public function setLevantamientoRegistral($levantamientoRegistral)
    {
        $this->levantamientoRegistral = $levantamientoRegistral;
        return $this;
    }

    public function getLevantamientoRegistral()
    {
        return $this->levantamientoRegistral;
    }
}
