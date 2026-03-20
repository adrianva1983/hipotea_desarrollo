<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class BelenderHitoExpedienteRepository extends EntityRepository
{

    /**
     * Guardar un registro de descarga de Belender
     */
    public function guardarDescarga(int $idHitoExpediente, string $dni, string $requestId, string $tipoPeticion = 'BELENDER'): BelenderHitoExpediente
    {
        $registro = new BelenderHitoExpediente();
        $registro->setIdHitoExpediente($idHitoExpediente);
        $registro->setDniBelender($dni);
        $registro->setRequestIdBelender($requestId);
        $registro->setTipoPeticion($tipoPeticion);
        $registro->setFecha(new \DateTime());

        $this->getEntityManager()->persist($registro);
        $this->getEntityManager()->flush();

        return $registro;
    }

    /**
     * Obtener registro de descarga por request_id, DNI y tipo
     */
    public function findByRequestIdAndDni(string $requestId, string $dni, string $tipoPeticion = 'BELENDER'): ?BelenderHitoExpediente
    {
        return $this->findOneBy([
            'requestIdBelender' => $requestId,
            'dniBelender' => $dni,
            'tipoPeticion' => $tipoPeticion,
        ]);
    }

    /**
     * Obtener registros de un hito expediente
     */
    public function findByIdHitoExpediente(int $idHitoExpediente): array
    {
        return $this->findBy(['idHitoExpediente' => $idHitoExpediente]);
    }

    /**
     * Obtener registros de un hito expediente por tipo de petición
     */
    public function findByIdHitoExpedienteAndTipo(int $idHitoExpediente, string $tipoPeticion): array
    {
        return $this->findBy([
            'idHitoExpediente' => $idHitoExpediente,
            'tipoPeticion' => $tipoPeticion,
        ]);
    }

    /**
     * Actualizar fecha de descarga
     */
    public function actualizarFechaDescarga(int $id): void
    {
        $registro = $this->find($id);
        if ($registro) {
            $registro->setFechaDescarga(new \DateTime());
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Actualizar fecha de notificación
     */
    public function actualizarFechaNotificacion(int $id): void
    {
        $registro = $this->find($id);
        if ($registro) {
            $registro->setFechaNotificacion(new \DateTime());
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Obtener último registro para un hito expediente
     */
    public function findLastByIdHitoExpediente(int $idHitoExpediente): ?BelenderHitoExpediente
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.idHitoExpediente = :id')
            ->setParameter('id', $idHitoExpediente)
            ->orderBy('b.fecha', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtener último registro para un hito expediente por tipo de petición
     */
    public function findLastByIdHitoExpedienteAndTipo(int $idHitoExpediente, string $tipoPeticion): ?BelenderHitoExpediente
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.idHitoExpediente = :id')
            ->andWhere('b.tipoPeticion = :tipo')
            ->setParameter('id', $idHitoExpediente)
            ->setParameter('tipo', $tipoPeticion)
            ->orderBy('b.fecha', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
