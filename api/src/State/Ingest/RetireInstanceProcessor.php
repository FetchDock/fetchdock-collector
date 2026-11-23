<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Instance;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DELETE /v1/instances/me: retires the instance, so its secret no longer works (403). Its reports stay.
 *
 * @implements ProcessorInterface<Instance, null>
 */
final readonly class RetireInstanceProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof Instance);
        $data->setRetiredAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return null;
    }
}
