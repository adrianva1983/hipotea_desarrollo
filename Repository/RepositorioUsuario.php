<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RepositorioUsuario extends EntityRepository implements UserLoaderInterface
{
	/**
	 * @param string $nombre
	 * @return mixed|null|UserInterface
	 * @throws NonUniqueResultException
	 */
	public function loadUserByUsername($nombre)
	{
		$u = $this->createQueryBuilder('u')
			->where('u.email = :email')
			->setMaxResults(1)
			->setParameter('email', $nombre)
			->getQuery();
		return $u->getOneOrNullResult();
	}
}
