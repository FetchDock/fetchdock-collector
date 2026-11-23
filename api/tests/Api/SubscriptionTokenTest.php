<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Mercure\AdminTopics;

/**
 * POST /users/me/subscription-token: subscribe-only Mercure tokens for the admin topics.
 */
final class SubscriptionTokenTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testMaintainerGetsASubscribeOnlyTokenForAdminTopics(): void
    {
        $response = self::createClient()->request('POST', '/users/me/subscription-token', ['auth_basic' => ['maintainer', 'maintainerpass']]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        $selector = self::getContainer()->get(AdminTopics::class)->subscribeSelector();
        self::assertStringEndsWith('/admin/{+path}', $selector);
        self::assertSame([$selector], $body['topics']);

        $claims = json_decode(base64_decode(strtr(explode('.', $body['token'])[1], '-_', '+/')), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([$selector], $claims['mercure']['subscribe']);
        self::assertArrayNotHasKey('publish', $claims['mercure']);
    }

    public function testAdminWithoutMaintainerRoleGetsAToken(): void
    {
        self::createClient()->request('POST', '/users/me/subscription-token', ['auth_basic' => ['admin', 'adminpass']]);

        self::assertResponseIsSuccessful();
    }

    public function testUserWithoutCollectorRoleGetsNoToken(): void
    {
        self::createClient()->request('POST', '/users/me/subscription-token', ['auth_basic' => ['user', 'userpass']]);

        self::assertResponseStatusCodeSame(403);
    }
}
