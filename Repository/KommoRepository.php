<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\KommoWebhook;

class KommoRepository extends EntityRepository
{
    /**
     * Obtiene los últimos webhooks recibidos
     * 
     * @param int $limit
     * @return array
     */
    public function obtenerUltimosWebhooks($limit = 10)
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene webhooks por tipo de evento
     * 
     * @param string $webhookType
     * @param int $limit
     * @return array
     */
    public function obtenerPorTipo($webhookType, $limit = 50)
    {
        return $this->createQueryBuilder('k')
            ->where('k.webhookType = :tipo')
            ->setParameter('tipo', $webhookType)
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene webhooks por estado
     * 
     * @param string $estado (procesado, error, null)
     * @param int $limit
     * @return array
     */
    public function obtenerPorEstado($estado = 'procesado', $limit = 50)
    {
        return $this->createQueryBuilder('k')
            ->where('k.estado = :estado')
            ->setParameter('estado', $estado)
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene webhooks con errores
     * 
     * @param int $limit
     * @return array
     */
    public function obtenerConErrores($limit = 50)
    {
        return $this->obtenerPorEstado('error', $limit);
    }

    /**
     * Obtiene webhooks por ID de Kommo
     * 
     * @param string $kommoId
     * @return array
     */
    public function obtenerPorKommoId($kommoId)
    {
        return $this->createQueryBuilder('k')
            ->where('k.kommoId = :kommoId')
            ->setParameter('kommoId', $kommoId)
            ->orderBy('k.fecha', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene webhooks en un rango de fechas
     * 
     * @param \DateTime $fechaInicio
     * @param \DateTime $fechaFin
     * @param int $limit
     * @return array
     */
    public function obtenerPorRangoFechas(\DateTime $fechaInicio, \DateTime $fechaFin, $limit = 100)
    {
        return $this->createQueryBuilder('k')
            ->where('k.fecha BETWEEN :inicio AND :fin')
            ->setParameter('inicio', $fechaInicio)
            ->setParameter('fin', $fechaFin)
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene webhooks del último día
     * 
     * @param int $limit
     * @return array
     */
    public function obtenerUltimoDia($limit = 100)
    {
        $hace24horas = new \DateTime('-1 day');
        return $this->obtenerPorRangoFechas($hace24horas, new \DateTime(), $limit);
    }

    /**
     * Cuenta webhooks por tipo
     * 
     * @param string $webhookType
     * @return int
     */
    public function contarPorTipo($webhookType)
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.webhookType = :tipo')
            ->setParameter('tipo', $webhookType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Cuenta webhooks totales
     * 
     * @return int
     */
    public function contarTotal()
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Cuenta webhooks con error
     * 
     * @return int
     */
    public function contarErrores()
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.estado = :estado')
            ->setParameter('estado', 'error')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Cuenta webhooks procesados
     * 
     * @return int
     */
    public function contarProcesados()
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->where('k.estado = :estado')
            ->setParameter('estado', 'procesado')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene estadísticas de webhooks
     * 
     * @return array
     */
    public function obtenerEstadisticas()
    {
        $total = $this->contarTotal();
        $procesados = $this->contarProcesados();
        $errores = $this->contarErrores();

        return [
            'total' => $total,
            'procesados' => $procesados,
            'errores' => $errores,
            'porcentajeExito' => $total > 0 ? round(($procesados / $total) * 100, 2) : 0
        ];
    }

    /**
     * Obtiene tipos de eventos únicos recibidos
     * 
     * @return array
     */
    public function obtenerTiposUnicos()
    {
        $resultado = $this->createQueryBuilder('k')
            ->select('DISTINCT k.webhookType')
            ->orderBy('k.webhookType', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($resultado, 'webhookType');
    }

    /**
     * Obtiene conteo de webhooks por tipo
     * 
     * @return array
     */
    public function obtenerConteosPorTipo()
    {
        return $this->createQueryBuilder('k')
            ->select('k.webhookType, COUNT(k.id) as cantidad')
            ->groupBy('k.webhookType')
            ->orderBy('cantidad', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks por contenido del JSON
     * 
     * @param string $busqueda
     * @param int $limit
     * @return array
     */
    public function buscarPorJSON($busqueda, $limit = 50)
    {
        return $this->createQueryBuilder('k')
            ->where('k.jsonRecibido LIKE :busqueda')
            ->setParameter('busqueda', '%' . $busqueda . '%')
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Elimina webhooks más antiguos de X días
     * 
     * @param int $dias
     * @return int Cantidad de registros eliminados
     */
    public function eliminarPorAntigüedad($dias = 90)
    {
        $fecha = new \DateTime('-' . $dias . ' days');
        
        return $this->createQueryBuilder('k')
            ->delete()
            ->where('k.fecha < :fecha')
            ->setParameter('fecha', $fecha)
            ->getQuery()
            ->execute();
    }

    /**
     * Obtiene último webhook recibido
     * 
     * @return KommoWebhook|null
     */
    public function obtenerUltimo()
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.fecha', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
