<?php

namespace App\Repository;

use App\Entity\IntegrityCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IntegrityCheck>
 */
class IntegrityCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegrityCheck::class);
    }

    /**
     * Checks since the given time, newest first.
     *
     * @return list<IntegrityCheck>
     */
    public function findSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.checkedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('c.checkedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.checkedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * The latest check of each container (host and role) since the given time, newest first.
     *
     * @return list<IntegrityCheck>
     */
    public function findLatestPerContainerSince(\DateTimeImmutable $since): array
    {
        $latest = [];
        foreach ($this->findSince($since) as $check) {
            $latest[$check->getHost()."\0".$check->getRole()->value] ??= $check;
        }

        return array_values($latest);
    }
}
