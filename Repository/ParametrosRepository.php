<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Parametros;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParametrosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametros::class);
    }

    public function findFirst(): ?Parametros
    {
        return $this->findOneBy([], ['id' => 'ASC']);  // Cambia 'id' por la columna que prefieras ordenar
    }
}
