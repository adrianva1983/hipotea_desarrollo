<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class SimuladorUsoEmailRepository extends EntityRepository
{
    // Repositorio con utilidades para SimuladorUsoEmail

    public function findOneByEmailAndTipo(string $email, string $tipo)
    {
        return $this->findOneBy(['email' => $email, 'tipo' => $tipo]);
    }

    public function resetUsosByEmailAndTipo(string $email, string $tipo): int
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->update('AppBundle:SimuladorUsoEmail', 's')
           ->set('s.usos', 0)
           ->set('s.primerUso', ':null')
           ->set('s.ultimoUso', ':null')
           ->where('s.email = :email')
           ->andWhere('s.tipo = :tipo')
           ->setParameter('email', $email)
           ->setParameter('tipo', $tipo)
           ->setParameter('null', null);

        return $qb->getQuery()->execute();
    }

    public function resetAllUsos(): int
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->update('AppBundle:SimuladorUsoEmail', 's')
           ->set('s.usos', 0)
           ->set('s.primerUso', ':null')
           ->set('s.ultimoUso', ':null')
           ->setParameter('null', null);

        return $qb->getQuery()->execute();
    }
}
