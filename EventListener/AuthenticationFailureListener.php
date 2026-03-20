<?php

namespace AppBundle\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;

class AuthenticationFailureListener
{
	/**
	 * @param AuthenticationFailureEvent $event
	 */
	public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event)
	{
		$event->setResponse(new JWTAuthenticationFailureResponse('Usuario y/o contraseña incorrectos.'/*$event->getException()->getMessageKey()*/));
	}
}
