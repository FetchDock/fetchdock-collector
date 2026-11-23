<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Enum\IntegrityStatus;
use App\State\CollectorIntegrityProvider;

/**
 * Whether the collector runs the code of its public repository (ADR 0013, collector#5): a signal that catches honest
 * drift, not proof. Public, shown on the index page. Mismatched paths are in AdminIntegrity only.
 */
#[ApiResource(
    shortName: 'CollectorIntegrity',
    operations: [
        new Get(
            uriTemplate: '/v1/integrity',
            description: 'Public. The effective integrity status (the worst check of the last 48 hours across the api and worker containers), the version, commit and manifest digest of the running api image, and where CI published the official digest.',
            provider: CollectorIntegrityProvider::class,
        ),
    ],
    formats: ['json' => ['application/json']],
    normalizationContext: ['skip_null_values' => false],
)]
final class CollectorIntegrity
{
    /** verified: files and manifest match the official build; modified: they don't; unknown: can't be verified */
    public IntegrityStatus $status = IntegrityStatus::UNKNOWN;

    /** The fetchdock-collector version the api runs */
    public string $appVersion = '';

    /** The commit the image was built from, null without a manifest (development builds) */
    public ?string $gitSha = null;

    /** sha256 of the image's integrity-manifest.json; compare it with digestUrl */
    public ?string $manifestDigest = null;

    /** The official digest CI published for appVersion, null for versions without one */
    public ?string $digestUrl = null;

    /** The latest check, null without one in the last 48 hours */
    public ?\DateTimeImmutable $checkedAt = null;
}
