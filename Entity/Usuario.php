<?php

namespace AppBundle\Entity;

use DateTime;
use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Usuario
 */
class Usuario implements UserInterface, Serializable
{
	function __construct()
	{
		$this->setFechaRegistro(new DateTime());
		$this->setFechaConexion(new DateTime());
	}

	/**
	 * @var integer
	 */
	private $idUsuario;

	/**
	 * @var string
	 */
	private $nif;

	/**
	 * @var string
	 */
	private $email;

	/**
	 * @var string
	 */
	private $plainPassword;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string
	 */
	private $role;

	/**
	 * @var string
	 */
	private $nombre;

	/**
	 * @var string
	 */
	private $apellidos;

	/**
	 * @var string
	 */
	private $empresa;

	/**
	 * @var string
	 */
	private $telefonoMovil;

	/**
	 * @var string
	 */
	private $telefonoFijo;

	/**
	 * @var string
	 */
	private $direccion;

	/**
	 * @var string
	 */
	private $cp;

	/**
	 * @var string
	 */
	private $provincia;

	/**
	 * @var string
	 */
	private $municipio;

	/**
	 * @var string
	 */
	private $pais;

	/**
	 * @var DateTime
	 */
	private $fechaRegistro;

	/**
	 * @var DateTime
	 */
	private $fechaConexion;

	/**
	 * @var DateTime
	 */
	private $fechaCaducidad;

	/**
	 * @var boolean
	 */
	private $politicaPrivacidad = false;

	/**
	 * @var boolean
	 */
	private $contratoFipre = false;

	/**
	 * @var boolean
	 */
	private $estado;

	/**
	 * @var string
	 */
	private $tokenActivacion;

	/**
	 * @var DateTime
	 */
	private $tokenFecha;

	/**
	 * @var string
	 */
	private $tag;

	/**
	 * @var integer
	 */
	private $zona;

	/**
	 * @var Inmobiliaria
	 */
	private $idInmobiliaria;

	/**
	 * @var Usuario
	 */
	private $idColaborador;

	/**
	 * @var string
	 */
	private $mailerTransport;

	/**
	 * @var string
	 */
	private $mailerHost;

	/**
	 * @var string
	 */
	private $mailerUser;

	/**
	 * @var string
	 */
	private $mailerPassword;

	/**
	 * @var string
	 */
	private $mailerEncryption;

	/**
	 * @var string
	 */
	private $mailerAuthMode;

	/**
	 * @var string
	 */
	private $mailerPort;


	/**
	 * @var string
	 */
	private $firmaCorreo;

	/**
	 * @var integer
	 */
	private $maxOperacionesVivas;

	/**
	 * @var string
	 */
	private $fotoPerfil;

	/**
	 * @var Oficina
	 */
	private $idOficina;

	/**
	 * @var Usuario
	 */
	private $idDireccionComercialAsignado;

	/**
	 * @var Usuario
	 */
	private $idDireccionExpansionAsignado;

	/**
	 * Get idUsuario
	 *
	 * @return integer
	 */
	public function getIdUsuario()
	{
		return $this->idUsuario;
	}

	/**
	 * Set nif
	 *
	 * @param string $nif
	 *
	 * @return Usuario
	 */
	public function setNif($nif)
	{
		$this->nif = $nif;

		return $this;
	}

	/**
	 * Get nif
	 *
	 * @return string
	 */
	public function getNif()
	{
		return $this->nif;
	}

	/**
	 * Set email
	 *
	 * @param string $email
	 *
	 * @return Usuario
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
	 * Set plainPassword
	 *
	 * @param string $password
	 *
	 * @return Usuario
	 */
	public function setPlainPassword($password)
	{
		$this->plainPassword = $password;

		return $this;
	}

	/**
	 * Get plainPassword
	 *
	 * @return string
	 */
	public function getPlainPassword()
	{
		return $this->plainPassword;
	}

	/**
	 * Set password
	 *
	 * @param string $password
	 *
	 * @return Usuario
	 */
	public function setPassword($password)
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * Get password
	 *
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * Set roles
	 *
	 * @param string $roles
	 *
	 * @return Usuario
	 */
	public function setRoles($roles)
	{
		$this->role = $roles;

		return $this;
	}

	/**
	 * Get roles
	 *
	 * @return array
	 */
	public function getRoles()
	{
		if (is_null($this->getRole())) {
			return array('ROLE_CLIENTE');
		}
		return array($this->getRole());
	}

	/**
	 * Set role
	 *
	 * @param string $role
	 *
	 * @return Usuario
	 */
	public function setRole($role)
	{
		$this->role = $role;

		return $this;
	}

	/**
	 * Get role
	 *
	 * @return string
	 */
	public function getRole()
	{
		return $this->role;
	}

	/**
	 * Set nombre
	 *
	 * @param string $nombre
	 *
	 * @return Usuario
	 */
	public function setUsername($nombre)
	{
		$this->nombre = $nombre;

		return $this;
	}

	/**
	 * Get nombre
	 *
	 * @return string
	 */
	public function getUsername()
	{
		return $this->nombre;
	}

	/**
	 * Set apellidos
	 *
	 * @param string $apellidos
	 *
	 * @return Usuario
	 */
	public function setApellidos($apellidos)
	{
		$this->apellidos = $apellidos;

		return $this;
	}

	/**
	 * Get apellidos
	 *
	 * @return string
	 */
	public function getApellidos()
	{
		return $this->apellidos;
	}

	/**
	 * Set empresa
	 *
	 * @param string $empresa
	 *
	 * @return Usuario
	 */
	public function setEmpresa($empresa)
	{
		$this->empresa = $empresa;

		return $this;
	}

	/**
	 * Get empresa
	 *
	 * @return string
	 */
	public function getEmpresa()
	{
		return $this->empresa;
	}

	/**
	 * Set telefonoMovil
	 *
	 * @param string $telefonoMovil
	 *
	 * @return Usuario
	 */
	public function setTelefonoMovil($telefonoMovil)
	{
		$this->telefonoMovil = $telefonoMovil;

		return $this;
	}

	/**
	 * Get telefonoMovil
	 *
	 * @return string
	 */
	public function getTelefonoMovil()
	{
		return $this->telefonoMovil;
	}

	/**
	 * Set telefonoFijo
	 *
	 * @param string $telefonoFijo
	 *
	 * @return Usuario
	 */
	public function setTelefonoFijo($telefonoFijo)
	{
		$this->telefonoFijo = $telefonoFijo;

		return $this;
	}

	/**
	 * Get telefonoFijo
	 *
	 * @return string
	 */
	public function getTelefonoFijo()
	{
		return $this->telefonoFijo;
	}

	/**
	 * Set direccion
	 *
	 * @param string $direccion
	 *
	 * @return Usuario
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
	 * Set cp
	 *
	 * @param string $cp
	 *
	 * @return Usuario
	 */
	public function setCp($cp)
	{
		$this->cp = $cp;

		return $this;
	}

	/**
	 * Get cp
	 *
	 * @return string
	 */
	public function getCp()
	{
		return $this->cp;
	}

	/**
	 * Set provincia
	 *
	 * @param string $provincia
	 *
	 * @return Usuario
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
	 * Set municipio
	 *
	 * @param string $municipio
	 *
	 * @return Usuario
	 */
	public function setMunicipio($municipio)
	{
		$this->municipio = $municipio;

		return $this;
	}

	/**
	 * Get municipio
	 *
	 * @return string
	 */
	public function getMunicipio()
	{
		return $this->municipio;
	}

	/**
	 * Set pais
	 *
	 * @param string $pais
	 *
	 * @return Usuario
	 */
	public function setPais($pais)
	{
		$this->pais = $pais;

		return $this;
	}

	/**
	 * Get pais
	 *
	 * @return string
	 */
	public function getPais()
	{
		return $this->pais;
	}

	/**
	 * Set fechaRegistro
	 *
	 * @param DateTime $fechaRegistro
	 *
	 * @return Usuario
	 */
	public function setFechaRegistro($fechaRegistro)
	{
		$this->fechaRegistro = $fechaRegistro;

		return $this;
	}

	/**
	 * Get fechaRegistro
	 *
	 * @return DateTime
	 */
	public function getFechaRegistro()
	{
		return $this->fechaRegistro;
	}

	/**
	 * Set fechaConexion
	 *
	 * @param DateTime $fechaConexion
	 *
	 * @return Usuario
	 */
	public function setFechaConexion($fechaConexion)
	{
		$this->fechaConexion = $fechaConexion;

		return $this;
	}

	/**
	 * Get fechaConexion
	 *
	 * @return DateTime
	 */
	public function getFechaConexion()
	{
		return $this->fechaConexion;
	}

	/**
	 * Set fechaCaducidad
	 *
	 * @param DateTime $fechaCaducidad
	 *
	 * @return Usuario
	 */
	public function setFechaCaducidad($fechaCaducidad)
	{
		$this->fechaCaducidad = $fechaCaducidad;

		return $this;
	}

	/**
	 * Get fechaCaducidad
	 *
	 * @return DateTime
	 */
	public function getFechaCaducidad()
	{
		return $this->fechaCaducidad;
	}

	/**
	 * Set politicaPrivacidad
	 *
	 * @param boolean $politicaPrivacidad
	 *
	 * @return Usuario
	 */
	public function setPoliticaPrivacidad($politicaPrivacidad)
	{
		$this->politicaPrivacidad = $politicaPrivacidad;

		return $this;
	}

	/**
	 * Get politicaPrivacidad
	 *
	 * @return boolean
	 */
	public function getPoliticaPrivacidad()
	{
		return $this->politicaPrivacidad;
	}

	/**
	 * Set contratoFipre
	 *
	 * @param boolean $contratoFipre
	 *
	 * @return Usuario
	 */
	public function setContratoFipre($contratoFipre)
	{
		$this->contratoFipre = $contratoFipre;

		return $this;
	}

	/**
	 * Get contratoFipre
	 *
	 * @return boolean
	 */
	public function getContratoFipre()
	{
		return $this->contratoFipre;
	}

	/**
	 * Set estado
	 *
	 * @param boolean $estado
	 *
	 * @return Usuario
	 */
	public function setEstado($estado)
	{
		$this->estado = $estado;

		return $this;
	}

	/**
	 * Get estado
	 *
	 * @return boolean
	 */
	public function getEstado()
	{
		return $this->estado;
	}

	/**
	 * Set tokenActivacion
	 *
	 * @param string $tokenActivacion
	 *
	 * @return Usuario
	 */
	public function setTokenActivacion($tokenActivacion)
	{
		$this->tokenActivacion = $tokenActivacion;

		return $this;
	}

	/**
	 * Get tokenActivacion
	 *
	 * @return string
	 */
	public function getTokenActivacion()
	{
		return $this->tokenActivacion;
	}

	/**
	 * Set tokenFecha
	 *
	 * @param DateTime $tokenFecha
	 *
	 * @return Usuario
	 */
	public function setTokenFecha($tokenFecha)
	{
		$this->tokenFecha = $tokenFecha;

		return $this;
	}

	/**
	 * Get tokenFecha
	 *
	 * @return DateTime
	 */
	public function getTokenFecha()
	{
		return $this->tokenFecha;
	}

	/**
	 * Set tag
	 *
	 * @param string $tag
	 *
	 * @return Usuario
	 */
	public function setTag($tag)
	{
		$this->tag = $tag;

		return $this;
	}

	/**
	 * Get tag
	 *
	 * @return string
	 */
	public function getTag()
	{
		return $this->tag;
	}

	/**
	 * Set zona
	 *
	 * @param integer $zona
	 *
	 * @return Usuario
	 */
	public function setZona($zona)
	{
		$this->zona = $zona;

		return $this;
	}

	/**
	 * Get zona
	 *
	 * @return integer
	 */
	public function getZona()
	{
		return $this->zona;
	}

	/**
	 * Set idInmobiliaria
	 *
	 * @param Inmobiliaria $idInmobiliaria
	 *
	 * @return Usuario
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

	public function getSalt()
	{
		return null;
	}

	public function eraseCredentials()
	{
	}

	/**
	 * @see \Serializable::serialize()
	 */
	public function serialize()
	{
		return serialize(array(
			$this->idUsuario,
			$this->nombre,
			$this->password
		));
	}

	/**
	 * @param $serialized
	 * @see \Serializable::unserialize()
	 *
	 */
	public function unserialize($serialized)
	{
		list ($this->idUsuario, $this->nombre, $this->password) = unserialize($serialized, array(
			'allowed_classes' => false
		));
	}

	public function __toString()
	{
		return $this->nombre;
	}

	/**
	 * Get the value of idColaborador
	 *
	 * @return  Usuario
	 */ 
	public function getIdColaborador()
	{
		return $this->idColaborador;
	}

	/**
	 * Set the value of idColaborador
	 *
	 * @param  Usuario  $idColaborador
	 *
	 * @return  self
	 */ 
	public function setIdColaborador(Usuario $idColaborador)
	{
		$this->idColaborador = $idColaborador;

		return $this;
	}

	/**
	 * Get the value of mailerTransport
	 *
	 * @return  string
	 */ 
	public function getMailerTransport()
	{
		return $this->mailerTransport;
	}

	/**
	 * Set the value of mailerTransport
	 *
	 * @param  string  $mailerTransport
	 *
	 * @return  self
	 */ 
	public function setMailerTransport($mailerTransport)
	{
		$this->mailerTransport = $mailerTransport;

		return $this;
	}

	/**
	 * Get the value of mailerHost
	 *
	 * @return  string
	 */ 
	public function getMailerHost()
	{
		return $this->mailerHost;
	}

	/**
	 * Set the value of mailerHost
	 *
	 * @param  string  $mailerHost
	 *
	 * @return  self
	 */ 
	public function setMailerHost($mailerHost)
	{
		$this->mailerHost = $mailerHost;

		return $this;
	}

	/**
	 * Get the value of mailerUser
	 *
	 * @return  string
	 */ 
	public function getMailerUser()
	{
		return $this->mailerUser;
	}

	/**
	 * Set the value of mailerUser
	 *
	 * @param  string  $mailerUser
	 *
	 * @return  self
	 */ 
	public function setMailerUser($mailerUser)
	{
		$this->mailerUser = $mailerUser;

		return $this;
	}

	/**
	 * Get the value of mailerPassword
	 *
	 * @return  string
	 */ 
	public function getMailerPassword()
	{
		return $this->mailerPassword;
	}

	/**
	 * Set the value of mailerPassword
	 *
	 * @param  string  $mailerPassword
	 *
	 * @return  self
	 */ 
	public function setMailerPassword($mailerPassword)
	{
		$this->mailerPassword = $mailerPassword;

		return $this;
	}

	/**
	 * Get the value of mailerEncryption
	 *
	 * @return  string
	 */ 
	public function getMailerEncryption()
	{
		return $this->mailerEncryption;
	}

	/**
	 * Set the value of mailerEncryption
	 *
	 * @param  string  $mailerEncryption
	 *
	 * @return  self
	 */ 
	public function setMailerEncryption($mailerEncryption)
	{
		$this->mailerEncryption = $mailerEncryption;

		return $this;
	}

	/**
	 * Get the value of mailerAuthMode
	 *
	 * @return  string
	 */ 
	public function getMailerAuthMode()
	{
		return $this->mailerAuthMode;
	}

	/**
	 * Set the value of mailerAuthMode
	 *
	 * @param  string  $mailerAuthMode
	 *
	 * @return  self
	 */ 
	public function setMailerAuthMode($mailerAuthMode)
	{
		$this->mailerAuthMode = $mailerAuthMode;

		return $this;
	}

	/**
	 * Get the value of mailerPort
	 *
	 * @return  string
	 */ 
	public function getMailerPort()
	{
		return $this->mailerPort;
	}

	/**
	 * Set the value of mailerPort
	 *
	 * @param  string  $mailerPort
	 *
	 * @return  self
	 */ 
	public function setMailerPort($mailerPort)
	{
		$this->mailerPort = $mailerPort;

		return $this;
	}

	/**
	 * Get the value of firmaCorreo
	 *
	 * @return  string
	 */ 
	public function getFirmaCorreo()
	{
		return $this->firmaCorreo;
	}

	/**
	 * Set the value of firmaCorreo
	 *
	 * @param  string  $firmaCorreo
	 *
	 * @return  self
	 */ 
	public function setFirmaCorreo($firmaCorreo)
	{
		$this->firmaCorreo = $firmaCorreo;

		return $this;
	}


	/**
	 * Get the value of maxOperacionesVivas
	 *
	 * @return integer
	 */ 
	public function getMaxOperacionesVivas()
	{
		return $this->maxOperacionesVivas;
	}

	/**
	 * Set the value of maxOperacionesVivas
	 *
	 * @param  integer  $maxOperacionesVivas
	 *
	 * @return  self
	 */ 
	public function setMaxOperacionesVivas($maxOperacionesVivas)
	{
		$this->maxOperacionesVivas = $maxOperacionesVivas;

		return $this;
	}

	/**
	 * Get the value of fotoPerfil
	 *
	 * @return  string
	 */ 
	public function getFotoPerfil()
	{
		return $this->fotoPerfil;
	}

	/**
	 * Set the value of fotoPerfil
	 *
	 * @param  string  $fotoPerfil
	 *
	 * @return  self
	 */ 
	public function setFotoPerfil($fotoPerfil)
	{
		$this->fotoPerfil = $fotoPerfil;

		return $this;
	}
	/**
	 * Get idOficina
	 *
	 * @return Oficina
	 */
	public function getIdOficina()
	{
		return $this->idOficina;
	}

	/**
	 * Set idOficina
	 *
	 * @param Oficina $idOficina
	 *
	 * @return Usuario
	 */
	public function setIdOficina(\AppBundle\Entity\Oficina $idOficina = null)
	{
		$this->idOficina = $idOficina;

		return $this;
	}

	/**
	 * Get idDireccionComercialAsignado
	 *
	 * @return Usuario
	 */
	public function getIdDireccionComercialAsignado()
	{
		return $this->idDireccionComercialAsignado;
	}

	/**
	 * Set idDireccionComercialAsignado
	 *
	 * @param Usuario $idDireccionComercialAsignado
	 *
	 * @return Usuario
	 */
	public function setIdDireccionComercialAsignado(\AppBundle\Entity\Usuario $idDireccionComercialAsignado = null)
	{
		$this->idDireccionComercialAsignado = $idDireccionComercialAsignado;

		return $this;
	}

	/**
	 * Get idDireccionExpansionAsignado
	 *
	 * @return Usuario
	 */
	public function getIdDireccionExpansionAsignado()
	{
		return $this->idDireccionExpansionAsignado;
	}

	/**
	 * Set idDireccionExpansionAsignado
	 *
	 * @param Usuario $idDireccionExpansionAsignado
	 *
	 * @return Usuario
	 */
	public function setIdDireccionExpansionAsignado(\AppBundle\Entity\Usuario $idDireccionExpansionAsignado = null)
	{
		$this->idDireccionExpansionAsignado = $idDireccionExpansionAsignado;

		return $this;
	}
}
