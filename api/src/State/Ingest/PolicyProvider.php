<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Policy\CollectorPolicy;
use App\Resource\Ingest\UpstreamPolicy;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GET /v1/policy.
 *
 * @implements ProviderInterface<UpstreamPolicy>
 */
final readonly class PolicyProvider implements ProviderInterface
{
    public function __construct(
        private CollectorPolicy $policy,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UpstreamPolicy
    {
        $origin = $this->requestStack->getMainRequest()?->getSchemeAndHttpHost() ?? '';

        $policy = new UpstreamPolicy();
        $policy->version = $this->policy->currentVersion;
        $policy->url = $origin.'/policy';
        $policy->acceptedVersions = array_values($this->policy->acceptedVersions);

        return $policy;
    }
}
