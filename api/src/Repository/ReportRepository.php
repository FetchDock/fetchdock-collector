<?php

namespace App\Repository;

use App\Entity\Instance;
use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * The report with this id if it belongs to the instance; null for unknown ids, invalid ids and other instances'
     * reports alike, so instances can't probe for ids.
     */
    public function findOwned(string $id, Instance $instance): ?Report
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->findOneBy(['id' => Uuid::fromString($id), 'instance' => $instance]);
    }
}
