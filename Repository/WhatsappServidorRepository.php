<?php

namespace AppBundle\Repository;

use AppBundle\Entity\WhatsappServidor;
use Doctrine\ORM\EntityRepository;

class WhatsappServidorRepository extends EntityRepository
{
    /**
     * Encontrar servidor por IP
     */
    public function findByIp(string $ip): ?WhatsappServidor
    {
        return $this->findOneBy(['ip' => $ip]);
    }

    /**
     * Encontrar servidores activos
     */
    public function findActivos()
    {
        return $this->findBy(['estado' => true], ['ip' => 'ASC']);
    }

    /**
     * Encontrar servidores inactivos
     */
    public function findInactivos()
    {
        return $this->findBy(['estado' => false]);
    }

    /**
     * Obtener servidor con menos conexiones activas
     */
    public function findServidorConMenosConexiones()
    {
        return $this->createQueryBuilder('ws')
            ->andWhere('ws.estado = :estado')
            ->setParameter('estado', true)
            ->orderBy('ws.maxConectados', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Contar servidores activos
     */
    public function countActivos(): int
    {
        return $this->createQueryBuilder('ws')
            ->select('COUNT(ws.id)')
            ->andWhere('ws.estado = :estado')
            ->setParameter('estado', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
