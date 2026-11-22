<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A maintainer signed in through OpenID Connect. Stateless: the collector stores no users (ADR 0014).
 */
final readonly class OidcUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $sub,
        private array $roles = [],
        private ?string $name = null,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        // Per the OpenID spec the subject is unique and never reassigned within the issuer
        return $this->sub;
    }
}
