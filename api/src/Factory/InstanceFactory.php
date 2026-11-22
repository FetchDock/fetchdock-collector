<?php

namespace App\Factory;

use App\Entity\Instance;
use App\Security\InstanceSecret;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Registered instances, for mock data (app:fixtures:seed) and tests.
 *
 * @extends PersistentObjectFactory<Instance>
 */
final class InstanceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Instance::class;
    }

    /**
     * An instance that authenticates with the given secret.
     */
    public function withSecret(string $secret): self
    {
        return $this->with(['secretHash' => InstanceSecret::hash($secret)]);
    }

    public function blocked(): self
    {
        return $this->with(['blocked' => true]);
    }

    public function retired(): self
    {
        return $this->with(static fn () => ['retiredAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-2 months'))]);
    }

    protected function defaults(): array
    {
        $registeredAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-18 months', '-1 week'));

        return [
            'secretHash' => InstanceSecret::hash(InstanceSecret::generate()),
            'serverVersion' => self::faker()->randomElement(['0.1.0', '0.1.1', '0.1.2', '0.2.0']),
            'acceptedPolicyVersion' => '1',
            'registeredAt' => $registeredAt,
            'lastSeenOn' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween(\DateTime::createFromImmutable($registeredAt))),
        ];
    }
}
