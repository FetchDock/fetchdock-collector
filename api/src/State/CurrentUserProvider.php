<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Resource\CurrentUser;
use App\Security\OidcUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * GET /users/me.
 *
 * @implements ProviderInterface<CurrentUser>
 */
final readonly class CurrentUserProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUser
    {
        // The operation requires ROLE_MAINTAINER, so there is a user
        $user = $this->security->getUser() ?? throw new \LogicException('No signed-in user.');

        $roles = array_values(array_unique($this->roleHierarchy->getReachableRoleNames($user->getRoles())));
        sort($roles);

        $currentUser = new CurrentUser();
        $currentUser->subject = $user->getUserIdentifier();
        $currentUser->name = $user instanceof OidcUser ? $user->getName() : null;
        $currentUser->roles = $roles;

        return $currentUser;
    }
}
