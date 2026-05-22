<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BotApiController extends Controller
{
	public function calcularSencillaAction(Request $request)
	{
		$respuesta = array(
			'errorlevel' => 0,
			'errores' => array()
		);
		
		$contentType = $request->headers->get('Content-Type');
		if ($contentType && strpos($contentType, 'application/json') !== false) {
			$jsonRecibido = json_decode($request->getContent(), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return new JsonResponse(array('mensaje' => 'JSON inválido.'), 400);
			}
		} else {
			$jsonRecibido = array(
				'precioTotal' => $request->request->get('precioTotal'),
				'aportacionInicial' => $request->request->get('aportacionInicial'),
				'tasaInteres' => $request->request->get('tasaInteres'),
				'plazoAmortizacion' => $request->request->get('plazoAmortizacion'),
			);
		}

		$requiredKeys = array('precioTotal', 'aportacionInicial', 'tasaInteres', 'plazoAmortizacion');
		$isValid = true;
		foreach ($requiredKeys as $key) {
			if (!isset($jsonRecibido[$key]) || $jsonRecibido[$key] === null || $jsonRecibido[$key] === '') {
				$isValid = false;
				break;
			}
		}

		if ($isValid) {
			$calculadora = new \AppBundle\Entity\CalculadoraSencilla();
			$calculadora->setPrecioTotal((double)$jsonRecibido['precioTotal']);
			$calculadora->setAportacionInicial((double)$jsonRecibido['aportacionInicial']);
			$calculadora->setTasaInteres((double)$jsonRecibido['tasaInteres']);
			$calculadora->setPlazoAmortizacion((int)$jsonRecibido['plazoAmortizacion']);

			$validador = $this->get('validator');
			$violaciones = $validador->validate($calculadora);

			if (count($violaciones) === 0) {
				$resultadoHipoteca = $calculadora->calcularHipoteca();
				$respuesta['datos'] = array(
					'hipoteca' => $resultadoHipoteca['capital_less_initial_amount'],
					'pago_mensual' => $resultadoHipoteca['fee'],
					'hipoteca_total_con_interes' => $resultadoHipoteca['capital_less_initial_amount'] + $resultadoHipoteca['interest_discharged_total'],
					'total_con_anticipo' => $calculadora->getPrecioTotal() + $resultadoHipoteca['interest_discharged_total']
				);
			} else {
				$respuesta['errorlevel'] = 1;
				foreach ($violaciones as $violacion) {
					$respuesta['errores'][] = array(
						'propiedad' => $violacion->getPropertyPath(),
						'mensaje' => $violacion->getMessage()
					);
				}
			}
		} else {
			$respuesta['errorlevel'] = 2;
			$respuesta['errores'][] = array('mensaje' => 'Faltan parámetros requeridos o tienen un valor vacío.');
		}

		return new JsonResponse($respuesta, JSON_UNESCAPED_UNICODE);
	}
}
