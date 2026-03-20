<?php

namespace AppBundle\Repository;

use AppBundle\Entity\WhatsappSender;
use Doctrine\ORM\EntityRepository;

class WhatsappSenderRepository extends EntityRepository
{
    /**
     * Encontrar por teléfono
     */
    public function findByTelefono(string $telefono): ?WhatsappSender
    {
        return $this->findOneBy(['telefono' => $telefono]);
    }

    /**
     * Encontrar por agencia
     */
    public function findByIdAgencia(int $idAgencia)
    {
        return $this->findBy(['idAgencia' => $idAgencia]);
    }

    /**
     * Encontrar por usuario
     */
    public function findByIdUsuario(int $idUsuario)
    {
        return $this->findBy(['idUsuario' => $idUsuario]);
    }

    /**
     * Encontrar usuarios que centralizan leads
     */
    public function findCentralizadoresLeads()
    {
        return $this->createQueryBuilder('ws')
            ->andWhere('ws.mensajeTrasLeadUsuarioUnico = :val')
            ->setParameter('val', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontrar senders con pilot automático activo
     */
    public function findWithPilotoAutomatico()
    {
        return $this->createQueryBuilder('ws')
            ->andWhere('ws.pilotoAutomatico = :val')
            ->setParameter('val', true)
            ->getQuery()
            ->getResult();
    }
}
