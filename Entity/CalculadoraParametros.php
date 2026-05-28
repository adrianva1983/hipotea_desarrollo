<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * CalculadoraParametros
 *
 * @ORM\Table(name="calculadora_parametros")
 * @ORM\Entity
 */
class CalculadoraParametros
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
     * @ORM\Column(name="perfil_laboral", type="string", length=255, unique=true)
     */
    private $perfilLaboral;

    /**
     * @var float
     *
     * @ORM\Column(name="tasa_interes", type="float")
     */
    private $tasaInteres;

    /**
     * @var int
     *
     * @ORM\Column(name="plazo_maximo", type="integer")
     */
    private $plazoMaximo;

    /**
     * @var bool
     *
     * @ORM\Column(name="activo", type="boolean")
     */
    private $activo;

    public function __construct()
    {
        $this->activo = true;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set perfilLaboral.
     *
     * @param string $perfilLaboral
     *
     * @return CalculadoraParametros
     */
    public function setPerfilLaboral($perfilLaboral)
    {
        $this->perfilLaboral = $perfilLaboral;

        return $this;
    }

    /**
     * Get perfilLaboral.
     *
     * @return string
     */
    public function getPerfilLaboral()
    {
        return $this->perfilLaboral;
    }

    /**
     * Set tasaInteres.
     *
     * @param float $tasaInteres
     *
     * @return CalculadoraParametros
     */
    public function setTasaInteres($tasaInteres)
    {
        $this->tasaInteres = $tasaInteres;

        return $this;
    }

    /**
     * Get tasaInteres.
     *
     * @return float
     */
    public function getTasaInteres()
    {
        return $this->tasaInteres;
    }

    /**
     * Set plazoMaximo.
     *
     * @param int $plazoMaximo
     *
     * @return CalculadoraParametros
     */
    public function setPlazoMaximo($plazoMaximo)
    {
        $this->plazoMaximo = $plazoMaximo;

        return $this;
    }

    /**
     * Get plazoMaximo.
     *
     * @return int
     */
    public function getPlazoMaximo()
    {
        return $this->plazoMaximo;
    }

    /**
     * Set activo.
     *
     * @param bool $activo
     *
     * @return CalculadoraParametros
     */
    public function setActivo($activo)
    {
        $this->activo = $activo;

        return $this;
    }

    /**
     * Get activo.
     *
     * @return bool
     */
    public function getActivo()
    {
        return $this->activo;
    }
}
