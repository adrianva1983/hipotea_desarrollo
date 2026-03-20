<?php

namespace AppBundle\Repository;

use AppBundle\Entity\KommoMensaje;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;

/**
 * @extends ServiceEntityRepository<KommoMensaje>
 */
class KommoMensajeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KommoMensaje::class);
    }

    /**
     * Busca mensajes por Lead ID de Kommo
     * 
     * @param int $kommoLeadId
     * @return KommoMensaje[]
     */
    public function findByKommoLeadId(int $kommoLeadId): array
    {
        return $this->findBy(['kommoLeadId' => $kommoLeadId], ['createdAt' => 'DESC']);
    }

    /**
     * Busca el último mensaje de un Lead
     * 
     * @param int $kommoLeadId
     * @return KommoMensaje|null
     */
    public function findLastByKommoLeadId(int $kommoLeadId): ?KommoMensaje
    {
        return $this->findOneBy(['kommoLeadId' => $kommoLeadId], ['createdAt' => 'DESC']);
    }

    /**
     * Busca mensajes por estado
     * 
     * @param string $status
     * @return KommoMensaje[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC']);
    }

    /**
     * Busca mensajes dentro de un rango de fechas
     * 
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return KommoMensaje[]
     */
    public function findByDateRange(DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder('km')
            ->where('km.createdAt >= :startDate')
            ->andWhere('km.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('km.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta mensajes por Lead
     * 
     * @param int $kommoLeadId
     * @return int
     */
    public function countByKommoLeadId(int $kommoLeadId): int
    {
        return $this->createQueryBuilder('km')
            ->select('COUNT(km.id)')
            ->where('km.kommoLeadId = :kommoLeadId')
            ->setParameter('kommoLeadId', $kommoLeadId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
