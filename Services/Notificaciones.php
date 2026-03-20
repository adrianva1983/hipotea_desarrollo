<?php

namespace AppBundle\Services;

use AppBundle\Entity\Notificacion;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Security;

class Notificaciones
{
	private $usuario;
	private $managerEntidad;
	private $notificaciones;

	/**
	 * Notificaciones constructor.
	 * @param $managerEntidad
	 * @param $seguridad
	 */
	public function __construct(EntityManager $managerEntidad, Security $seguridad)
	{
		$this->usuario = $seguridad->getUser();
		$this->managerEntidad = $managerEntidad;
		$this->setNotificaciones();
	}

	/**
	 * @return mixed
	 */
	public function getNotificaciones()
	{
		return $this->notificaciones;
	}

	public function setNotificaciones()
	{
		$this->notificaciones = $this->managerEntidad->getRepository(Notificacion::class)->findBy(array(
			'idUsuario' => $this->usuario,
			'estado' => 1
		), array(
			'fecha' => 'DESC'
		));
	}
}
