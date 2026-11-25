<?php

namespace App\State\Ingest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Policy\CollectorPolicy;
use App\Resource\Ingest\UpstreamPolicyText;

/**
 * GET /v1/policy/{version}: null (404) for a version that is neither current nor accepted.
 *
 * @implements ProviderInterface<UpstreamPolicyText>
 */
final readonly class PolicyTextProvider implements ProviderInterface
{
    public function __construct(
        private CollectorPolicy $policy,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UpstreamPolicyText
    {
        $version = (string) ($uriVariables['version'] ?? '');
        $markdown = $this->policy->text($version);
        if (null === $markdown) {
            return null;
        }

        $text = new UpstreamPolicyText();
        $text->version = $version;
        $text->current = $version === $this->policy->currentVersion;
        $text->markdown = $markdown;

        return $text;
    }
}
