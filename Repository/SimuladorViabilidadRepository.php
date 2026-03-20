<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\SimuladorViabilidad;

class SimuladorViabilidadRepository extends EntityRepository
{
    /**
     * Encuentra un simulador por UUID y Usuario
     *
     * @param string $uuid
     * @param mixed $usuario
     *
     * @return SimuladorViabilidad|null
     */
    public function findOneByUuidAndUsuario($uuid, $usuario)
    {
        return $this->createQueryBuilder('sv')
            ->andWhere('sv.uuid = :uuid')
            ->andWhere('sv.usuario = :usuario')
            ->setParameter('uuid', $uuid)
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra todos los borradores de un usuario
     *
     * @param mixed $usuario
     *
     * @return array
     */
    public function findBorradoresByUsuario($usuario)
    {
        return $this->createQueryBuilder('sv')
            ->andWhere('sv.usuario = :usuario')
            ->andWhere('sv.estado = :estado')
            ->setParameter('usuario', $usuario)
            ->setParameter('estado', 'borrador')
            ->orderBy('sv.fechaActualizacion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra todos los simuladores enviados de una inmobiliaria
     *
     * @param mixed $inmobiliaria
     *
     * @return array
     */
    public function findEnviadosByInmobiliaria($inmobiliaria)
    {
        return $this->createQueryBuilder('sv')
            ->andWhere('sv.inmobiliaria = :inmobiliaria')
            ->andWhere('sv.estado = :estado')
            ->setParameter('inmobiliaria', $inmobiliaria)
            ->setParameter('estado', 'enviado')
            ->orderBy('sv.fechaActualizacion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
