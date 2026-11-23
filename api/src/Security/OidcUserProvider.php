<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Builds the maintainer from the access token's claims, like fetchdock-server: "roles" values become ROLE_* (a ROLE_
 * prefix is added when missing) and "groups" names become ROLE_GROUP_NAME. So an Authentik group "maintainer" grants
 * ROLE_MAINTAINER and a group "admin" grants ROLE_ADMIN. OIDC_ADMIN_GROUPS and OIDC_MAINTAINER_GROUPS (comma-separated
 * group names, case-insensitive) grant those roles to other groups too, e.g. the IdP group that makes someone an admin of
 * fetchdock-server.
 *
 * @implements AttributesBasedUserProviderInterface<OidcUser>
 */
final class OidcUserProvider implements AttributesBasedUserProviderInterface
{
    /** @var array<string, list<string>> Lowercased group name => the roles it grants */
    private array $groupRoles = [];

    /**
     * @param list<string|null> $adminGroups
     * @param list<string|null> $maintainerGroups
     */
    public function __construct(
        #[Autowire('%oidc.admin_groups%')] array $adminGroups = [],
        #[Autowire('%oidc.maintainer_groups%')] array $maintainerGroups = [],
    ) {
        foreach (['ROLE_ADMIN' => $adminGroups, 'ROLE_MAINTAINER' => $maintainerGroups] as $role => $groups) {
            foreach ($groups as $group) {
                $key = mb_strtolower(trim((string) $group));
                if ('' !== $key) {
                    $this->groupRoles[$key][] = $role;
                }
            }
        }
    }

    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        $roles = array_map(
            static fn (string $role): string => self::toRole($role),
            array_filter((array) ($attributes['roles'] ?? []), 'is_string'),
        );
        foreach (array_filter((array) ($attributes['groups'] ?? []), 'is_string') as $group) {
            $roles[] = self::toRole($group);
            array_push($roles, ...$this->groupRoles[mb_strtolower(trim($group))] ?? []);
        }

        return new OidcUser(
            sub: (string) ($attributes['sub'] ?? $identifier),
            roles: array_values(array_unique($roles)),
            name: \is_string($attributes['name'] ?? null) ? $attributes['name'] : null,
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof OidcUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user "%s".', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return OidcUser::class === $class;
    }

    private static function toRole(string $value): string
    {
        $role = strtoupper(str_replace([' ', '-'], '_', trim($value)));

        return str_starts_with($role, 'ROLE_') ? $role : 'ROLE_'.$role;
    }
}
