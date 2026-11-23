<?php

namespace App\Security;

use App\Entity\Instance;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A registered instance, authenticated on the ingest API (/v1) with its secret as bearer token. Only active instances
 * get ROLE_INSTANCE; blocked and retired ones are authenticated but denied (403).
 */
final readonly class InstanceUser implements UserInterface
{
    public const string ROLE = 'ROLE_INSTANCE';

    public function __construct(public Instance $instance)
    {
    }

    public function getRoles(): array
    {
        return $this->isActive() ? [self::ROLE] : [];
    }

    public function isActive(): bool
    {
        return !$this->instance->isBlocked() && null === $this->instance->getRetiredAt();
    }

    public function getUserIdentifier(): string
    {
        return $this->instance->getId()->toRfc4122();
    }

    public function eraseCredentials(): void
    {
    }
}
