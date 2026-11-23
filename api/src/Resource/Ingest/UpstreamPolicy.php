<?php

namespace App\Resource\Ingest;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Ingest\PolicyProvider;

/**
 * The collector's current privacy policy. Instances show it to their admin and users before they consent.
 */
#[ApiResource(
    shortName: 'UpstreamPolicy',
    operations: [
        new Get(
            uriTemplate: '/v1/policy',
            description: 'Public. The current collector policy version, its human-readable page and the versions under which consent is still honoured.',
            provider: PolicyProvider::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class UpstreamPolicy
{
    public string $version;

    /** Human-readable policy page */
    public string $url;

    /**
     * Consent under these versions is still honoured
     *
     * @var list<string>
     */
    public array $acceptedVersions = [];
}
