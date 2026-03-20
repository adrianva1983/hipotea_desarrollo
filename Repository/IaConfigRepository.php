<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class IaConfigRepository extends EntityRepository
{
	/**
	 * Devuelve proveedores activos ordenados por si son por defecto.
	 * @return array
	 */
	public function findActive()
	{
		return $this->createQueryBuilder('i')
			->where('i.activo = :activo')
			->setParameter('activo', true)
			->orderBy('i.esProveedorPorDefecto', 'DESC')
			->getQuery()
			->getResult();
	}
}
