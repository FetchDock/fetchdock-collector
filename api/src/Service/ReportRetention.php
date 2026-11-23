<?php

namespace App\Service;

use App\Entity\Instance;
use App\Entity\Report;
use App\Enum\ReportStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The retention rule (collector design): reports received more than collector.report_retention_months ago with a
 * terminal status are deleted with their comments, and so are retired instances without reports. Open reports stay
 * until they are triaged.
 */
final readonly class ReportRetention
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'collector.report_retention_months')]
        private int $retentionMonths,
    ) {
    }

    /**
     * @return array{reports: int, instances: int} the number of deleted reports and instances
     */
    public function purge(?\DateTimeImmutable $now = null): array
    {
        $cutoff = ($now ?? new \DateTimeImmutable())->modify(\sprintf('-%d months', max(0, $this->retentionMonths)));
        $terminal = array_values(array_filter(ReportStatus::cases(), static fn (ReportStatus $status): bool => $status->isTerminal()));

        // Comments go with their report (ON DELETE CASCADE)
        $reports = (int) $this->entityManager->createQueryBuilder()
            ->delete(Report::class, 'r')
            ->where('r.receivedAt < :cutoff')
            ->andWhere('r.status IN (:terminal)')
            ->setParameter('cutoff', $cutoff)
            ->setParameter('terminal', $terminal)
            ->getQuery()
            ->execute();

        $instances = (int) $this->entityManager->createQueryBuilder()
            ->delete(Instance::class, 'i')
            ->where('i.retiredAt IS NOT NULL')
            ->andWhere(\sprintf('NOT EXISTS (SELECT 1 FROM %s r WHERE r.instance = i)', Report::class))
            ->getQuery()
            ->execute();

        return ['reports' => $reports, 'instances' => $instances];
    }
}
