<?php

namespace AppBundle\Utils;

use AppBundle\Entity\AgenteColaborador;
use AppBundle\Entity\Usuario;

class UsuariosNombreCompleto
{
	public function obtener($usuario, $mostrarInmobiliaria = false)
	{
		if (is_null($usuario)) {
			$nombreCompleto = '';
		} else {
			if ($usuario instanceof Usuario) {
				$nombre = $usuario->getUsername();
			} elseif ($usuario instanceof AgenteColaborador) {
				$nombre = $usuario->getNombre();
			}
			if (empty($usuario->getApellidos())) {
				if (empty($nombre)) {
					$nombreCompleto = null;
				} else {
					$nombreCompleto = $nombre;
				}
			} else {
				if (empty($nombre)) {
					$nombreCompleto = $usuario->getApellidos();
				} else {
					$nombreCompleto = $usuario->getApellidos() . ', ' . $nombre;
				}
			}
			if ($mostrarInmobiliaria && !is_null($nombreCompleto) && !is_null($usuario->getIdInmobiliaria())) {
				$nombreCompleto .= ' (' . $usuario->getIdInmobiliaria() . ')';
			}
		}
		return $nombreCompleto;
	}
}
