<?php

namespace App\Security;

use App\Repository\InstanceRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Loads instances by id for the ingest firewall. The firewall is stateless, so users are never refreshed.
 *
 * @implements UserProviderInterface<InstanceUser>
 */
final readonly class InstanceUserProvider implements UserProviderInterface
{
    public function __construct(private InstanceRepository $instances)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $instance = Uuid::isValid($identifier) ? $this->instances->find(Uuid::fromString($identifier)) : null;

        return null !== $instance ? new InstanceUser($instance) : throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('Instances authenticate on every request.');
    }

    public function supportsClass(string $class): bool
    {
        return InstanceUser::class === $class;
    }
}
