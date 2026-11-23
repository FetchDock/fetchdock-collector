<?php

namespace App\Resource\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Enum\IntegrityStatus;
use App\State\Admin\AdminIntegrityProvider;

/**
 * The integrity check of the collector's code (ADR 0013, collector#5) with the paths that differ: a signal that
 * catches honest drift, not proof.
 */
#[ApiResource(
    shortName: 'AdminIntegrity',
    operations: [
        new Get(
            uriTemplate: '/admin/integrity',
            description: 'ROLE_ADMIN. The effective status (the worst check of the last 48 hours) and the latest check of each api and worker container in that period, with the paths that differ from the manifest.',
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_ADMIN')",
    provider: AdminIntegrityProvider::class,
)]
final class AdminIntegrity
{
    public IntegrityStatus $status = IntegrityStatus::UNKNOWN;

    /** The fetchdock-collector version this container runs */
    public string $appVersion = '';

    /** sha256 of this container's integrity manifest, null without one (development builds) */
    public ?string $manifestDigest = null;

    /** The commit the image was built from */
    public ?string $gitSha = null;

    /** Whether the check compares the manifest with the digest published on GitHub (INTEGRITY_CHECK_ONLINE) */
    public bool $checkOnline = true;

    /** @var list<AdminIntegrityCheck> newest first */
    public array $checks = [];
}
