<?php

namespace App\Security;

use App\Repository\InstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates instances on the ingest API by their secret (Authorization: Bearer <instanceSecret>). The secret's
 * SHA-256 hash is looked up; unknown secrets get 401. Records the day the instance was last seen.
 */
final readonly class InstanceAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private InstanceRepository $instances,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $hash = InstanceSecret::hash($accessToken);
        $instance = $this->instances->findOneBy(['secretHash' => $hash]);
        if (null === $instance || !hash_equals($instance->getSecretHash(), $hash)) {
            throw new BadCredentialsException('Invalid instance secret.');
        }

        $today = new \DateTimeImmutable('today');
        if ($instance->getLastSeenOn() < $today) {
            $instance->setLastSeenOn($today);
            $this->entityManager->flush();
        }

        $user = new InstanceUser($instance);

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
