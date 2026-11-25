<?php

namespace App\Resource\Ingest;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Ingest\PolicyTextProvider;

/**
 * The text of a collector policy version, for the public /policy pages (and for instances that want to show it).
 */
#[ApiResource(
    shortName: 'UpstreamPolicyText',
    operations: [
        new Get(
            uriTemplate: '/v1/policy/{version}',
            description: 'Public. The Markdown text of the current or a still accepted collector policy version; 404 for any other version.',
            provider: PolicyTextProvider::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class UpstreamPolicyText
{
    public string $version;

    /** Whether this is the current version (GET /v1/policy) */
    public bool $current;

    /** The policy text, CommonMark with GitHub-style tables */
    public string $markdown;
}
