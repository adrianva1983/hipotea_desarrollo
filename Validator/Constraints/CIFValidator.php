<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CIFValidator extends ConstraintValidator
{
	public function validate($valor, Constraint $restriccion)
	{
		if (!$restriccion instanceof CIF) {
			throw new UnexpectedTypeException($restriccion, CIF::class);
		}
		if (is_null($valor) || empty($valor)) {
			return;
		}
		if (!is_string($valor)) {
			throw new UnexpectedTypeException($valor, 'string');
		}
		$valor = trim($valor);
		if (preg_match('/^\d{8}[A-Za-z]$/', $valor)) {
			$valorNumero = substr($valor, 0, 8);
			$valorLongitud = strlen($valor);
			$valorLetra = substr($valor, $valorLongitud - 1, $valorLongitud);
			$arrayLetras = array('T', 'R', 'W', 'A', 'G', 'M', 'Y', 'F', 'P', 'D', 'X', 'B', 'N', 'J', 'Z', 'S', 'Q', 'V', 'H', 'L', 'C', 'K', 'E');
			if (stristr($valorLetra, $arrayLetras[$valorNumero % 23])) {
				$valido = true;
			} else {
				$valido = false;
			}
		} elseif (preg_match('/^[A-Ha-hJjNnP-Sp-sU-Wu-w]\d{7}(\d|[A-Ja-j])$/', $valor)) {
			$digitos = substr($valor, 1, 7);
			$sumaDigitosPares = 0;
			$sumaPosicionesImpares = 0;
			for ($i = 0; $i < 7; $i += 1) {
				if ($i % 2 === 0) {
					$digitoImparPorDos = $digitos[$i] * 2;
					$sumaPosicionesImpares += (int)($digitoImparPorDos / 10) + $digitoImparPorDos % 10;
				} else {
					$sumaDigitosPares += $digitos[$i];
				}
			}
			$digitoUnidades = ($sumaDigitosPares + $sumaPosicionesImpares) % 10;
			if ($digitoUnidades !== 0) {
				$digitoControl = 10 - $digitoUnidades;
			} else {
				$digitoControl = 0;
			}
			if (substr($digitos, 0, 2) === '00' || in_array(strtoupper($valor[0]), array('P', 'Q', 'R', 'S', 'W'))) {
				$valido = $this->digitoControlLetra($digitoControl, $valor);
			} elseif (in_array(strtoupper($valor[0]), array('A', 'B', 'E', 'H'))) {
				$valido = $this->digitoControlNumero($digitoControl, $valor);
			} else {
				if (is_numeric($valor[8])) {
					$valido = $this->digitoControlNumero($digitoControl, $valor);
				} else {
					$valido = $this->digitoControlLetra($digitoControl, $valor);
				}
			}
		} else {
			$valido = false;
		}
		if (!$valido) {
			$this->context->buildViolation($restriccion->message)
				->setParameter('{{ string }}', $valor)
				->addViolation();
		}
	}

	private function digitoControlLetra($digitoControl, $valor)
	{
		$arrayLetras = array(
			0 => 'J',
			1 => 'A',
			2 => 'B',
			3 => 'C',
			4 => 'D',
			5 => 'E',
			6 => 'F',
			7 => 'G',
			8 => 'H',
			9 => 'I'
		);
		if ($arrayLetras[$digitoControl] === strtoupper($valor[8])) {
			return true;
		} else {
			return false;
		}
	}

	private function digitoControlNumero($digitoControl, $valor)
	{
		if ($digitoControl === (int)$valor[8]) {
			return true;
		} else {
			return false;
		}
	}
}
