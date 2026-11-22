<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

final class WellKnownTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testVersionIsPublic(): void
    {
        $response = self::createClient()->request('GET', '/.well-known/fetchdock-collector');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('version', $response->toArray());
    }
}
