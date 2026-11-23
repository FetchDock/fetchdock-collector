<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Instance;

/**
 * DELETE /v1/instances/me: the calling instance.
 *
 * @implements ProviderInterface<Instance>
 */
final readonly class CurrentInstanceProvider implements ProviderInterface
{
    public function __construct(private CurrentInstance $currentInstance)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Instance
    {
        return $this->currentInstance->get();
    }
}
