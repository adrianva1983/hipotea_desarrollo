<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class DocumentoAnalisisRepository extends EntityRepository
{
    /**
     * Obtiene análisis de un expediente
     */
    public function findByExpediente($idExpediente)
    {
        return $this->createQueryBuilder('da')
            ->where('da.idExpediente = :idExpediente')
            ->setParameter('idExpediente', $idExpediente)
            ->orderBy('da.fechaAnalisis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene análisis exitosos de un expediente
     */
    public function findExitosos($idExpediente)
    {
        return $this->createQueryBuilder('da')
            ->where('da.idExpediente = :idExpediente')
            ->andWhere('da.estado = :estado')
            ->setParameter('idExpediente', $idExpediente)
            ->setParameter('estado', 'procesado')
            ->orderBy('da.fechaAnalisis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene análisis con errores
     */
    public function findConErrores($idExpediente)
    {
        return $this->createQueryBuilder('da')
            ->where('da.idExpediente = :idExpediente')
            ->andWhere('da.estado = :estado')
            ->setParameter('idExpediente', $idExpediente)
            ->setParameter('estado', 'error')
            ->orderBy('da.fechaAnalisis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene análisis por fichero
     */
    public function findByFichero($idFicheroCampo)
    {
        return $this->findOneBy(['idFicheroCampo' => $idFicheroCampo]);
    }
}
