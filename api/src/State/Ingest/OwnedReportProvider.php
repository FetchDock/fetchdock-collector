<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Report;
use App\Repository\ReportRepository;

/**
 * A report of the calling instance by id; null (404) when it doesn't exist or belongs to another instance.
 *
 * @implements ProviderInterface<Report>
 */
final readonly class OwnedReportProvider implements ProviderInterface
{
    public function __construct(
        private CurrentInstance $currentInstance,
        private ReportRepository $reports,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Report
    {
        return $this->reports->findOwned((string) ($uriVariables['id'] ?? ''), $this->currentInstance->get());
    }
}
