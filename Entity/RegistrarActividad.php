<?php

namespace AppBundle\Entity;

use Symfony\Component\EventDispatcher\Event;

class RegistrarActividad extends Event
{
	private $usuario;
	private $accion;
	private $entidad;

	public function __construct()
	{
		if (method_exists($this, $method_name = '__construct' . func_num_args())) {
			call_user_func_array(array($this, $method_name), func_get_args());
		}
	}

	/**
	 * RegistrarActividad constructor.
	 * @param $usuario
	 * @param $accion
	 */
	public function __construct2($usuario, $accion)
	{
		$this->usuario = $usuario;
		$this->accion = $accion;
	}

	/**
	 * RegistrarActividad constructor.
	 * @param $usuario
	 * @param $accion
	 * @param $entidad
	 */
	public function __construct3($usuario, $accion, $entidad)
	{
		$this->usuario = $usuario;
		$this->accion = $accion;
		$this->entidad = $entidad;
	}

	/**
	 * @return mixed
	 */
	public function getUsuario()
	{
		return $this->usuario;
	}

	/**
	 * @param mixed $usuario
	 */
	public function setUsuario($usuario)
	{
		$this->usuario = $usuario;
	}

	/**
	 * @return mixed
	 */
	public function getAccion()
	{
		return $this->accion;
	}

	/**
	 * @param mixed $accion
	 */
	public function setAccion($accion)
	{
		$this->accion = $accion;
	}

	/**
	 * @return mixed
	 */
	public function getEntidad()
	{
		return $this->entidad;
	}

	/**
	 * @param mixed $entidad
	 */
	public function setEntidad($entidad)
	{
		$this->entidad = $entidad;
	}
}
