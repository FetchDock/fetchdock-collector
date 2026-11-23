<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DELETE /v1/reports/{id}: hard delete with comments, after OwnedReportProvider checked ownership.
 *
 * @implements ProcessorInterface<Report, null>
 */
final readonly class DeleteReportProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof Report);
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
