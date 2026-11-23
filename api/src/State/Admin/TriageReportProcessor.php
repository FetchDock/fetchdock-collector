<?php

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Report;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PATCH /admin/reports/{id}: records the time of the triage change, then persists.
 *
 * @implements ProcessorInterface<Report, Report>
 */
final readonly class TriageReportProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Report, Report> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Report
    {
        \assert($data instanceof Report);
        $data->setUpdatedAt(new \DateTimeImmutable());

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
