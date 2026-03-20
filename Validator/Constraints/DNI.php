<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DNI extends Constraint
{
	public $message = 'EL DNI "{{ string }}" no es válido.';
}
