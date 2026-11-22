<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * GET /users/me and the maintainer-only access rule (test users: config/packages/security.yaml, when@test).
 */
final class CurrentUserTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testMaintainerSeesTheirRoles(): void
    {
        $response = self::createClient()->request('GET', '/users/me', ['auth_basic' => ['maintainer', 'maintainerpass']]);

        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_MAINTAINER'], $response->toArray()['roles']);
        self::assertSame('maintainer', $response->toArray()['subject']);
    }

    public function testAdminIsAlsoMaintainer(): void
    {
        $response = self::createClient()->request('GET', '/users/me', ['auth_basic' => ['admin', 'adminpass']]);

        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_ADMIN', 'ROLE_MAINTAINER'], $response->toArray()['roles']);
    }

    public function testUserWithoutCollectorRoleIsRejected(): void
    {
        self::createClient()->request('GET', '/users/me', ['auth_basic' => ['user', 'userpass']]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousIsRejected(): void
    {
        self::createClient()->request('GET', '/users/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDocsArePublic(): void
    {
        self::createClient()->request('GET', '/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']]);

        self::assertResponseIsSuccessful();
    }
}
