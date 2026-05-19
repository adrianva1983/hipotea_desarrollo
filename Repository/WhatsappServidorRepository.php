<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\WhatsappServidor;

/**
 * Repositorio para WhatsappServidor
 * 
 * Maneja operaciones de lectura/escritura para servidores WhatsApp
 */
class WhatsappServidorRepository extends EntityRepository
{
    /**
     * Obtiene servidores activos
     * 
     * @return WhatsappServidor[]
     */
    public function obtenerServidoresActivos()
    {
        return $this->findBy(
            ['estado' => true],
            ['ip' => 'ASC']
        );
    }

    /**
     * Busca un servidor por IP
     * 
     * @param string $ip
     * @return WhatsappServidor|null
     */
    public function findByIp($ip)
    {
        return $this->findOneBy(['ip' => $ip]);
    }

    /**
     * Obtiene el servidor con menor carga (menos conexiones)
     * 
     * @return WhatsappServidor|null
     */
    public function obtenerServidorDisponible()
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.estado = true')
            ->orderBy('ws.maxConectados', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtiene estadísticas de servidores
     * 
     * @return array
     */
    public function obtenerEstadisticas()
    {
        return $this->createQueryBuilder('ws')
            ->select('COUNT(ws.id) as total, SUM(ws.maxConectados) as capacidadTotal')
            ->where('ws.estado = true')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Desactiva un servidor
     * 
     * @param string $ip
     * @return int
     */
    public function desactivarServidor($ip)
    {
        return $this->createQueryBuilder('ws')
            ->update()
            ->set('ws.estado', 'false')
            ->where('ws.ip = :ip')
            ->setParameter('ip', $ip)
            ->getQuery()
            ->execute();
    }

    /**
     * Activa un servidor
     * 
     * @param string $ip
     * @return int
     */
    public function activarServidor($ip)
    {
        return $this->createQueryBuilder('ws')
            ->update()
            ->set('ws.estado', 'true')
            ->where('ws.ip = :ip')
            ->setParameter('ip', $ip)
            ->getQuery()
            ->execute();
    }
}
