<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ImpuestoCcaa
 *
 * @ORM\Table(name="impuesto_ccaa")
 * @ORM\Entity
 */
class ImpuestoCcaa
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
     * 1 = Andalucia, 2 = Aragon... según mapeo
     * @ORM\Column(name="comunidad_autonoma", type="integer")
     */
    private $comunidadAutonoma;

    /**
     * @var string
     * Ej: ITP, AJD, IVA, IGIC
     * @ORM\Column(name="tipo_impuesto", type="string", length=255)
     */
    private $tipoImpuesto;

    /**
     * @var float
     *
     * @ORM\Column(name="porcentaje_defecto", type="float")
     */
    private $porcentajeDefecto;

    /**
     * @var float
     *
     * @ORM\Column(name="porcentaje_bonificado_jovenes", type="float", nullable=true)
     */
    private $porcentajeBonificadoJovenes;

    /**
     * @var float
     *
     * @ORM\Column(name="porcentaje_bonificado_vpo", type="float", nullable=true)
     */
    private $porcentajeBonificadoVpo;

    public function getId()
    {
        return $this->id;
    }

    public function setComunidadAutonoma($comunidadAutonoma)
    {
        $this->comunidadAutonoma = $comunidadAutonoma;
        return $this;
    }

    public function getComunidadAutonoma()
    {
        return $this->comunidadAutonoma;
    }

    public function setTipoImpuesto($tipoImpuesto)
    {
        $this->tipoImpuesto = $tipoImpuesto;
        return $this;
    }

    public function getTipoImpuesto()
    {
        return $this->tipoImpuesto;
    }

    public function setPorcentajeDefecto($porcentajeDefecto)
    {
        $this->porcentajeDefecto = $porcentajeDefecto;
        return $this;
    }

    public function getPorcentajeDefecto()
    {
        return $this->porcentajeDefecto;
    }

    public function setPorcentajeBonificadoJovenes($porcentajeBonificadoJovenes)
    {
        $this->porcentajeBonificadoJovenes = $porcentajeBonificadoJovenes;
        return $this;
    }

    public function getPorcentajeBonificadoJovenes()
    {
        return $this->porcentajeBonificadoJovenes;
    }

    public function setPorcentajeBonificadoVpo($porcentajeBonificadoVpo)
    {
        $this->porcentajeBonificadoVpo = $porcentajeBonificadoVpo;
        return $this;
    }

    public function getPorcentajeBonificadoVpo()
    {
        return $this->porcentajeBonificadoVpo;
    }
}
