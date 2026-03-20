<?php

namespace AppBundle\Security;

use AppBundle\Entity\Usuario as Usuario;
use DateTime;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ComprobarUsuario implements UserCheckerInterface
{
	public function checkPreAuth(UserInterface $usuario)
	{
		if (!$usuario instanceof Usuario) {
			return;
		}
	}

	public function checkPostAuth(UserInterface $usuario)
	{
		if (!$usuario instanceof Usuario) {
			return;
		}
		if (!$usuario->getEstado()) {
			throw new DisabledException();
		}
		if (!is_null($usuario->getFechaCaducidad()) && new DateTime() > $usuario->getFechaCaducidad()) {
			throw new AccountExpiredException();
		}
	}
}
