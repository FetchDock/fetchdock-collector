<?php

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\CurrentUserProvider;

/**
 * The signed-in maintainer and their effective roles, so the PWA shows only what they may do.
 */
#[ApiResource(
    shortName: 'CurrentUser',
    operations: [
        new Get(
            uriTemplate: '/users/me',
            description: 'The signed-in maintainer and their effective roles (including those granted through the role hierarchy).',
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_MAINTAINER')",
    provider: CurrentUserProvider::class,
)]
final class CurrentUser
{
    /** The maintainer's OIDC subject */
    #[ApiProperty(identifier: true)]
    public string $subject;

    /** Display name from the token, if any */
    public ?string $name = null;

    /**
     * Effective roles, sorted: ROLE_MAINTAINER (triage) and ROLE_ADMIN (also stats, instances, blocking, deleting).
     *
     * @var list<string>
     */
    public array $roles = [];
}
