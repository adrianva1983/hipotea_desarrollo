<?php

namespace AppBundle\EventListener;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class Login
{
	private $entidad;

	public function __construct(EntityManager $em)
	{
		$this->entidad = $em;
	}

	public function alIniciarSesion(AuthenticationEvent $evento)
	{
		$usuario = $evento->getAuthenticationToken()->getUser();
		if ($usuario instanceof UserInterface) {
			$usuario->setFechaConexion(new DateTime());
			if (!is_null($usuario->getTokenActivacion())) {
				$usuario->setTokenActivacion(null);
			}
			if (!is_null($usuario->getTokenFecha())) {
				$usuario->setTokenFecha(null);
			}
			$this->entidad->persist($usuario);
			try {
				$this->entidad->flush();
			} catch (OptimisticLockException $e) {
			}
		}
	}
}
