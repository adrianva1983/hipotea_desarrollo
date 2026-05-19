<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\WhatsappSender;

/**
 * Repositorio para WhatsappSender
 * 
 * Maneja operaciones de lectura/escritura para sesiones de WhatsApp
 */
class WhatsappSenderRepository extends EntityRepository
{
    /**
     * Busca un sender por sessionId
     * 
     * @param string $sessionId
     * @return WhatsappSender|null
     */
    public function findBySessionId($sessionId)
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    /**
     * Busca un sender por teléfono
     * 
     * @param string $telefono
     * @return WhatsappSender|null
     */
    public function findByTelefono($telefono)
    {
        return $this->findOneBy(['telefono' => $telefono]);
    }

    /**
     * Busca un sender por usuario
     * 
     * @param int $idUsuario
     * @return WhatsappSender[]
     */
    public function findByIdUsuario($idUsuario)
    {
        return $this->findBy(
            ['idUsuario' => $idUsuario],
            ['fechaUltimaInteraccion' => 'DESC']
        );
    }

    /**
     * Obtiene todas las sesiones activas
     * 
     * @return WhatsappSender[]
     */
    public function obtenerSesionesActivas()
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.sessionId IS NOT NULL')
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene sesiones activas de un usuario específico
     * 
     * @param int $idUsuario
     * @return WhatsappSender[]
     */
    public function obtenerSesionesActivasPorUsuario($idUsuario)
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.idUsuario = :idUsuario')
            ->andWhere('ws.sessionId IS NOT NULL')
            ->setParameter('idUsuario', $idUsuario)
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene senders inactivos (sin sesión activa)
     * 
     * @param int $horasInactividad Número de horas sin interacción
     * @return WhatsappSender[]
     */
    public function obtenerSesionesInactivas($horasInactividad = 24)
    {
        $desde = new \DateTime("-{$horasInactividad} hours");
        
        return $this->createQueryBuilder('ws')
            ->where('ws.sessionId IS NULL')
            ->andWhere('ws.fechaUltimaInteraccion < :desde')
            ->setParameter('desde', $desde)
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta sesiones activas totales
     * 
     * @return int
     */
    public function contarSesionesActivas()
    {
        return $this->createQueryBuilder('ws')
            ->select('COUNT(ws.id)')
            ->where('ws.sessionId IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca senders por agencia
     * 
     * @param int $idAgencia
     * @return WhatsappSender[]
     */
    public function findByIdAgencia($idAgencia)
    {
        return $this->findBy(
            ['idAgencia' => $idAgencia],
            ['fechaUltimaInteraccion' => 'DESC']
        );
    }

    /**
     * Obtiene los últimos contactos activos
     * 
     * @param int $limit
     * @return WhatsappSender[]
     */
    public function obtenerUltimosContactos($limit = 10)
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.sessionId IS NOT NULL')
            ->orderBy('ws.fechaUltimaInteraccion', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Limpia sesiones expiradas (sin actividad en X días)
     * 
     * @param int $diasExpiracion
     * @return int Número de registros actualizados
     */
    public function limpiarSesionesExpiradas($diasExpiracion = 7)
    {
        $hasta = new \DateTime("-{$diasExpiracion} days");
        
        return $this->createQueryBuilder('ws')
            ->update()
            ->set('ws.sessionId', 'NULL')
            ->where('ws.fechaUltimaInteraccion < :hasta')
            ->andWhere('ws.sessionId IS NOT NULL')
            ->setParameter('hasta', $hasta)
            ->getQuery()
            ->execute();
    }
}
