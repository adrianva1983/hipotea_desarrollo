<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Notificacion;
use DateTime;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\Event;

class RecibirDocumentoAPI
{
	private $managerEntidad;

	function __construct(EntityManager $managerEntidad)
	{
		$this->managerEntidad = $managerEntidad;
	}

	public function onDocumentoSubido(Event $evento)
	{
		$notificacion = new Notificacion();
		$notificacion->setFecha(new DateTime());
		$notificacion->setIdUsuario($evento->getIdUsuario());
		switch ($evento->getRespuesta()) {
			case 0:
				$notificacion->setTexto('El documento "' . $evento->getNombreDocumento() . '" se ha subido correctamente.');
				break;
			case 1:
				$notificacion->setTexto('No has seleccionado ningún documento.');
				break;
			case 2:
				$notificacion->setTexto('El documento "' . $evento->getNombreDocumento() . '" no es un PDF.');
				break;
			case 3:
				$notificacion->setTexto('El documento "' . $evento->getNombreDocumento() . '" no esta firmado.');
				break;
			case 4:
				$notificacion->setTexto('Error al guardar el documento "' . $evento->getNombreDocumento() . '" en el servidor.');
				break;
			case 6:
				$notificacion->setTexto('Campo hito no encontrado.');
				break;
			default:
				$notificacion->setTexto('Error desconocido al subir el documento "' . $evento->getNombreDocumento() . '".');
		}
		$managerEntidad = $this->managerEntidad;
		$managerEntidad->persist($notificacion);
		$managerEntidad->flush();
		if ($evento->getEnviarNotificacionPush()) {
			//TODO: Enviar notificacion PUSH.
		}
	}
}
