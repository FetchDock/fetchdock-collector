<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Instance;
use App\RateLimit\IngestRateLimiter;
use App\Resource\Ingest\InstanceRegistration;
use App\Resource\Ingest\RegisteredInstance;
use App\Security\InstanceSecret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * POST /v1/instances: a new instance with a random id and secret; only the secret's hash is stored.
 *
 * @implements ProcessorInterface<InstanceRegistration, RegisteredInstance>
 */
final readonly class RegisterInstanceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IngestRateLimiter $rateLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegisteredInstance
    {
        \assert($data instanceof InstanceRegistration);
        $this->rateLimiter->consumeRegistration($this->requestStack->getMainRequest()?->getClientIp());

        $secret = InstanceSecret::generate();
        $instance = new Instance(InstanceSecret::hash($secret), $data->serverVersion, $data->acceptedPolicyVersion);
        $this->entityManager->persist($instance);
        $this->entityManager->flush();

        return new RegisteredInstance($instance->getId(), $secret);
    }
}
