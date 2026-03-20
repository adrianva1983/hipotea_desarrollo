<?php

namespace AppBundle\Entity;

use Symfony\Component\EventDispatcher\Event;

class DocumentoNotificacion extends Event
{
	private $usuario;
	private $nombreDocumento;
	private $respuesta;
	private $enviarNotificacionPush;

	public function __construct()
	{
		if (method_exists($this, $method_name = '__construct' . func_num_args())) {
			call_user_func_array(array($this, $method_name), func_get_args());
		}
	}

	/**
	 * DocumentoNotificacion constructor.
	 * @param $usuario
	 * @param $nombreDocumento
	 * @param $respuesta
	 */
	public function __construct3($usuario, $nombreDocumento, $respuesta)
	{
		$this->usuario = $usuario;
		$this->nombreDocumento = $nombreDocumento;
		$this->respuesta = $respuesta;
		$this->enviarNotificacionPush = true;
	}

	/**
	 * DocumentoNotificacion constructor.
	 * @param $usuario
	 * @param $nombreDocumento
	 * @param $respuesta
	 * @param $enviarNotificacionPush
	 */
	public function __construct4($usuario, $nombreDocumento, $respuesta, $enviarNotificacionPush)
	{
		$this->usuario = $usuario;
		$this->nombreDocumento = $nombreDocumento;
		$this->respuesta = $respuesta;
		$this->enviarNotificacionPush = $enviarNotificacionPush;
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
	public function getNombreDocumento()
	{
		return $this->nombreDocumento;
	}

	/**
	 * @param mixed $nombreDocumento
	 */
	public function setNombreDocumento($nombreDocumento)
	{
		$this->nombreDocumento = $nombreDocumento;
	}

	/**
	 * @return mixed
	 */
	public function getRespuesta()
	{
		return $this->respuesta;
	}

	/**
	 * @param mixed $respuesta
	 */
	public function setRespuesta($respuesta)
	{
		$this->respuesta = $respuesta;
	}

	/**
	 * @return mixed
	 */
	public function getEnviarNotificacionPush()
	{
		return $this->enviarNotificacionPush;
	}

	/**
	 * @param mixed $enviarNotificacionPush
	 */
	public function setEnviarNotificacionPush($enviarNotificacionPush)
	{
		$this->enviarNotificacionPush = $enviarNotificacionPush;
	}
}
