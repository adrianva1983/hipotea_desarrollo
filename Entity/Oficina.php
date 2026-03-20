<?php

namespace AppBundle\Entity;

/**
 * Oficina
 */
class Oficina
{
    public function __construct()
    {
        $this->setFechaCreacion(new \DateTime());
        $this->setFechaModificacion(new \DateTime());
        $this->setActiva(true);
    }
    /**
     * @var integer
     */
    private $idOficina;

    /**
     * @var string
     */
    private $nombre;

    /**
     * @var Inmobiliaria
     */
    private $idInmobiliaria;

    /**
     * @var Usuario
     */
    private $idUsuario;

    /**
     * @var string
     */
    private $direccion;

    /**
     * @var string
     */
    private $telefono;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $codigoPostal;

    /**
     * @var string
     */
    private $ciudad;

    /**
     * @var string
     */
    private $provincia;

    /**
     * @var boolean
     */
    private $activa;

    /**
     * @var \DateTime
     */
    private $fechaCreacion;

    /**
     * @var \DateTime
     */
    private $fechaModificacion;

    /**
     * Get idOficina
     *
     * @return integer
     */
    public function getIdOficina()
    {
        return $this->idOficina;
    }

    /**
     * Set nombre
     *
     * @param string $nombre
     *
     * @return Oficina
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * Get nombre
     *
     * @return string
     */
    public function getNombre()
    {
        return $this->nombre;
    }

    /**
     * Set idInmobiliaria
     *
     * @param Inmobiliaria $idInmobiliaria
     *
     * @return Oficina
     */
    public function setIdInmobiliaria(Inmobiliaria $idInmobiliaria = null)
    {
        $this->idInmobiliaria = $idInmobiliaria;

        return $this;
    }

    /**
     * Get idInmobiliaria
     *
     * @return Inmobiliaria
     */
    public function getIdInmobiliaria()
    {
        return $this->idInmobiliaria;
    }

    /**
     * Set idUsuario
     *
     * @param Usuario $idUsuario
     *
     * @return Oficina
     */
    public function setIdUsuario(Usuario $idUsuario = null)
    {
        $this->idUsuario = $idUsuario;

        return $this;
    }

    /**
     * Get idUsuario
     *
     * @return Usuario
     */
    public function getIdUsuario()
    {
        return $this->idUsuario;
    }

    /**
     * Get idUsuario as integer for forms
     *
     * @return integer
     */
    public function getIdUsuarioId()
    {
        return $this->idUsuario ? $this->idUsuario->getIdUsuario() : null;
    }

    /**
     * Set idUsuario from integer ID
     *
     * @param integer $idUsuario
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return Oficina
     */
    public function setIdUsuarioFromId($idUsuario, $em = null)
    {
        if ($idUsuario && $em) {
            $usuario = $em->getRepository('AppBundle:Usuario')->find($idUsuario);
            $this->setIdUsuario($usuario);
        }
        return $this;
    }

    /**
     * Set direccion
     *
     * @param string $direccion
     *
     * @return Oficina
     */
    public function setDireccion($direccion)
    {
        $this->direccion = $direccion;

        return $this;
    }

    /**
     * Get direccion
     *
     * @return string
     */
    public function getDireccion()
    {
        return $this->direccion;
    }

    /**
     * Set telefono
     *
     * @param string $telefono
     *
     * @return Oficina
     */
    public function setTelefono($telefono)
    {
        $this->telefono = $telefono;

        return $this;
    }

    /**
     * Get telefono
     *
     * @return string
     */
    public function getTelefono()
    {
        return $this->telefono;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return Oficina
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set codigoPostal
     *
     * @param string $codigoPostal
     *
     * @return Oficina
     */
    public function setCodigoPostal($codigoPostal)
    {
        $this->codigoPostal = $codigoPostal;

        return $this;
    }

    /**
     * Get codigoPostal
     *
     * @return string
     */
    public function getCodigoPostal()
    {
        return $this->codigoPostal;
    }

    /**
     * Set ciudad
     *
     * @param string $ciudad
     *
     * @return Oficina
     */
    public function setCiudad($ciudad)
    {
        $this->ciudad = $ciudad;

        return $this;
    }

    /**
     * Get ciudad
     *
     * @return string
     */
    public function getCiudad()
    {
        return $this->ciudad;
    }

    /**
     * Set provincia
     *
     * @param string $provincia
     *
     * @return Oficina
     */
    public function setProvincia($provincia)
    {
        $this->provincia = $provincia;

        return $this;
    }

    /**
     * Get provincia
     *
     * @return string
     */
    public function getProvincia()
    {
        return $this->provincia;
    }

    /**
     * Set activa
     *
     * @param boolean $activa
     *
     * @return Oficina
     */
    public function setActiva($activa)
    {
        $this->activa = $activa;

        return $this;
    }

    /**
     * Get activa
     *
     * @return boolean
     */
    public function getActiva()
    {
        return $this->activa;
    }

    /**
     * Set fechaCreacion
     *
     * @param \DateTime $fechaCreacion
     *
     * @return Oficina
     */
    public function setFechaCreacion($fechaCreacion)
    {
        $this->fechaCreacion = $fechaCreacion;

        return $this;
    }

    /**
     * Get fechaCreacion
     *
     * @return \DateTime
     */
    public function getFechaCreacion()
    {
        return $this->fechaCreacion;
    }

    /**
     * Set fechaModificacion
     *
     * @param \DateTime $fechaModificacion
     *
     * @return Oficina
     */
    public function setFechaModificacion($fechaModificacion)
    {
        $this->fechaModificacion = $fechaModificacion;

        return $this;
    }

    /**
     * Get fechaModificacion
     *
     * @return \DateTime
     */
    public function getFechaModificacion()
    {
        return $this->fechaModificacion;
    }

    /**
     * String representation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->nombre ?: '';
    }
}