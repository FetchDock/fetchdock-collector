<?php

namespace App\Tests\Unit;

use App\Security\OidcUserProvider;
use PHPUnit\Framework\TestCase;

final class OidcUserProviderTest extends TestCase
{
    public function testRolesAndGroupsClaimsBecomeRoles(): void
    {
        $user = (new OidcUserProvider())->loadUserByIdentifier('abc', [
            'sub' => 'abc',
            'roles' => ['maintainer', 'ROLE_ADMIN'],
            'groups' => ['Some Group', 'admin'],
            'name' => 'Maintainer',
        ]);

        self::assertSame('abc', $user->getUserIdentifier());
        self::assertSame(['ROLE_MAINTAINER', 'ROLE_ADMIN', 'ROLE_SOME_GROUP'], $user->getRoles());
    }

    public function testNoClaimsMeansNoRoles(): void
    {
        self::assertSame([], (new OidcUserProvider())->loadUserByIdentifier('abc', ['sub' => 'abc'])->getRoles());
    }
}
