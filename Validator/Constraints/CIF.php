<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class CIF extends Constraint
{
	public $message = 'EL CIF "{{ string }}" no es válido.';
}
