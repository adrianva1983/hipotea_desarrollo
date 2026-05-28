<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Entity\CalculadoraParametros;
use AppBundle\Entity\CalculadoraSencilla;

class BotCalculadoraController extends Controller
{
    /**
     * Endpoint que la IA llama después de extraer los datos del texto.
     * Ejemplo de JSON esperado en el body:
     * {
     *   "perfil": "funcionario",
     *   "precioTotal": 200000,
     *   "aportacionInicial": 40000,
     *   "plazoAmortizacion": 30
     * }
     */
    public function calcularDesdeIaAction(Request $request)
    {
        // 1. Recibir los datos extraídos por la IA (NLU)
        $contenido = $request->getContent();
        $datosIA = json_decode($contenido, true);

        if (!$datosIA || !isset($datosIA['perfil']) || !isset($datosIA['precioTotal'])) {
            return new JsonResponse(['error' => 'Datos insuficientes. Se requiere perfil y precioTotal.'], 400);
        }

        $perfil = strtolower($datosIA['perfil']);
        $precioTotal = (float)$datosIA['precioTotal'];
        $aportacionInicial = isset($datosIA['aportacionInicial']) ? (float)$datosIA['aportacionInicial'] : 0;
        $plazo = isset($datosIA['plazoAmortizacion']) ? (int)$datosIA['plazoAmortizacion'] : 30;

        // 2. Consultar Base de Datos (Reglas de Negocio)
        $em = $this->getDoctrine()->getManager();
        $parametro = $em->getRepository(CalculadoraParametros::class)->findOneBy([
            'perfilLaboral' => $perfil,
            'activo' => true
        ]);

        // Si no existe el perfil en BBDD, usamos valores por defecto seguros (ej. 3.0%)
        if ($parametro) {
            $tasaInteresReal = $parametro->getTasaInteres();
            // Validar plazo máximo según base de datos
            if ($plazo > $parametro->getPlazoMaximo()) {
                $plazo = $parametro->getPlazoMaximo();
            }
        } else {
            // Perfil "general" por defecto
            $tasaInteresReal = 3.0; 
            if ($plazo > 30) $plazo = 30;
        }

        // 3. Ejecutar el cálculo usando el modelo matemático ya existente
        $calc = new CalculadoraSencilla();
        $calc->setPrecioTotal($precioTotal);
        $calc->setAportacionInicial($aportacionInicial);
        $calc->setTasaInteres($tasaInteresReal); // Tasa extraída de la BBDD
        $calc->setPlazoAmortizacion($plazo);

        // Retorna un array con 'fee', 'capital_less_initial_amount', etc.
        $resultado = $calc->calcularHipoteca();

        // 4. Devolver la respuesta a la IA para que se la muestre al usuario
        return new JsonResponse([
            'mensaje_para_ia' => "Dile al usuario que el cálculo se hizo exitosamente con una tasa del {$tasaInteresReal}% aplicada según su perfil de {$perfil}.",
            'parametros_aplicados' => [
                'perfil' => $perfil,
                'tasa_interes' => $tasaInteresReal,
                'plazo_anios' => $plazo
            ],
            'calculo_exacto' => [
                'hipoteca_solicitada' => $resultado['capital_less_initial_amount'],
                'cuota_mensual' => round($resultado['fee'], 2),
                'total_intereses' => round($resultado['interest_discharged_total'], 2)
            ]
        ]);
    }
}
