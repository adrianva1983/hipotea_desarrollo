<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Log as Log;
use DateTime;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\Event;

class RegistrarActividad
{
	private $managerEntidad;

	function __construct(EntityManager $managerEntidad)
	{
		$this->managerEntidad = $managerEntidad;
	}

	public function onRegistrarActividadSinEntidad(Event $event)
	{
		$log = new Log();
		$log->setIdUsuario($event->getUsuario())
			->setFecha(new DateTime())
			->setAccion($event->getAccion());
		$this->managerEntidad->persist($log);
	}

	public function onRegistrarActividadConEntidad(Event $event)
	{
		$metadatosClase = $this->managerEntidad->getClassMetadata(get_class($event->getEntidad()));
		if (array_key_exists($metadatosClase->getSingleIdentifierFieldName(), $metadatosClase->getIdentifierValues($event->getEntidad()))) {
			$log = new Log();
			$log->setIdUsuario($event->getUsuario())
				->setFecha(new DateTime())
				->setAccion($event->getAccion() . ' ' . $metadatosClase->getTableName() . '.')
				->setIdElemento($metadatosClase->getIdentifierValues($event->getEntidad())[$metadatosClase->getSingleIdentifierFieldName()]);
			$this->managerEntidad->persist($log);
		}
	}
}
