<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Parametros as ParametrosEntidad;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use AppBundle\Form\Parametros as ParametrosFormulario;

class ParametrosController extends Controller
{
    public function modificarParametrosAction(Request $request, RouterInterface $router, $id)
	{
		return $this->formularioParametros(array(
			'id' => $id,
			'entidad' => ParametrosEntidad::class,
			'idEntidad' => 'id',
			'elementoNoExiste' => 'La configuración con id="' . $id . '" no existe.',
			'ruta' => 'lista_expedientes',
			'formularioModificar' => ParametrosFormulario::class,
			'request' => $request,
			'formularioMensaje' => 'La configuración ha sido modificada correctamente.',
			'renderizar' => '@App/Backoffice/AgregarModificar/Parametros.html.twig',
			'titulo' => 'Modificar parámetros',
			'migasPan' => array(
				// array(
				// 	'vista' => 'Lista de usuarios',
				// 	'url' => $router->generate('lista_usuarios')
				// )
			),
			'formularioNombre' => 'modificarParametros',
			'router' => $router
		));
	}

    private	function formularioParametros($parametros)
	{
		$doctrine = $this->getDoctrine();

		if (isset($parametros['id'])) {
			
            $configuracion = $doctrine->getRepository($parametros['entidad'])->findOneBy(array(
                $parametros['idEntidad'] => $parametros['id']
            ));
		
			if (!$configuracion) {
				$this->addFlash('warning', $parametros['elementoNoExiste']);
				return $this->redirectToRoute($parametros['ruta']);
			}
			$formulario = $this->createForm($parametros['formularioModificar'], $configuracion);
		}
		$formulario->handleRequest($parametros['request']);
		if ($formulario->isSubmitted() && $formulario->isValid()) {
			$managerEntidad = $doctrine->getManager();
			$managerEntidad->persist($configuracion);
			$managerEntidad->flush();
			$this->addFlash('success', $parametros['formularioMensaje']);
			return $this->redirectToRoute($parametros['ruta']);
		}
		$titulo = "Modificar parámetros";

		return $this->render($parametros['renderizar'], array(
			'titulo' => $titulo,
			'migasPan' => $parametros['migasPan'],
			$parametros['formularioNombre'] => $formulario->createView()
		));
	}
}