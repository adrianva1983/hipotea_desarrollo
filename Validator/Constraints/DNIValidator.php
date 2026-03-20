<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DNIValidator extends ConstraintValidator
{
	public function validate($valor, Constraint $restriccion)
	{
		if (!$restriccion instanceof DNI) {
			throw new UnexpectedTypeException($restriccion, DNI::class);
		}
		if (is_null($valor) || empty($valor)) {
			return;
		}
		if (!is_string($valor)) {
			throw new UnexpectedTypeException($valor, 'string');
		}
		$valor = trim($valor);
		$valido = true;
		if (preg_match('/^\d{8}[\s-]?[A-Za-z]$/', $valor)) {
			$valorNumero = substr($valor, 0, 8);
			$valorLongitud = strlen($valor);
			$valorLetra = substr($valor, $valorLongitud - 1, $valorLongitud);
			$arrayLetras = array('T', 'R', 'W', 'A', 'G', 'M', 'Y', 'F', 'P', 'D', 'X', 'B', 'N', 'J', 'Z', 'S', 'Q', 'V', 'H', 'L', 'C', 'K', 'E');
			if (!stristr($valorLetra, $arrayLetras[$valorNumero % 23])) {
				$valido = false;
			}
		}
		if (!$valido) {
			$this->context->buildViolation($restriccion->message)
				->setParameter('{{ string }}', $valor)
				->addViolation();
		}
	}
}
