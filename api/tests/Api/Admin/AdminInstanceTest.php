<?php

namespace App\Tests\Api\Admin;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\InstanceFactory;
use App\Factory\ReportFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The maintainer API for instances and stats: ROLE_ADMIN only.
 */
final class AdminInstanceTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = false;

    private const array MAINTAINER = ['maintainer', 'maintainerpass'];
    private const array ADMIN = ['admin', 'adminpass'];

    public function testAdminListsInstancesWithReportCounts(): void
    {
        $instance = InstanceFactory::createOne(['lastSeenOn' => new \DateTimeImmutable('2026-11-20 15:30')]);
        ReportFactory::createMany(3, ['instance' => $instance]);

        $response = self::createClient()->request('GET', '/admin/instances/'.$instance->getId(), ['auth_basic' => self::ADMIN]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(3, $body['reportCount']);
        self::assertSame('2026-11-20', $body['lastSeenOn']);
        self::assertFalse($body['blocked']);
        self::assertNull($body['retiredAt']);
        self::assertArrayNotHasKey('secretHash', $body);

        self::assertSame(1, self::createClient()->request('GET', '/admin/instances', ['auth_basic' => self::ADMIN])->toArray()['totalItems']);
    }

    public function testAdminBlocksAnInstanceAndItsCallsGet403(): void
    {
        $instance = InstanceFactory::new()->withSecret('instance-secret')->create();

        self::createClient()->request('PATCH', '/admin/instances/'.$instance->getId(), [
            'auth_basic' => self::ADMIN,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['blocked' => true, 'serverVersion' => '9.9.9'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertTrue($instance->isBlocked());
        self::assertNotSame('9.9.9', $instance->getServerVersion());

        self::createClient()->request('DELETE', '/v1/instances/me', ['headers' => ['Authorization' => 'Bearer instance-secret']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testMaintainersSeeNoInstancesOrStats(): void
    {
        $instance = InstanceFactory::createOne();

        self::createClient()->request('GET', '/admin/instances', ['auth_basic' => self::MAINTAINER]);
        self::assertResponseStatusCodeSame(403);
        self::createClient()->request('PATCH', '/admin/instances/'.$instance->getId(), [
            'auth_basic' => self::MAINTAINER,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['blocked' => true],
        ]);
        self::assertResponseStatusCodeSame(403);
        self::createClient()->request('GET', '/admin/report_stats', ['auth_basic' => self::MAINTAINER]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testStatsGroupedByHostPerDay(): void
    {
        $a = InstanceFactory::createOne();
        $b = InstanceFactory::createOne();
        ReportFactory::createMany(2, ['instance' => $a, 'host' => 'vimeo.com', 'receivedAt' => new \DateTimeImmutable('2026-11-20 10:00')]);
        ReportFactory::createOne(['instance' => $b, 'host' => 'vimeo.com', 'receivedAt' => new \DateTimeImmutable('2026-11-20 23:00')]);
        ReportFactory::createOne(['instance' => $b, 'host' => 'x.com', 'receivedAt' => new \DateTimeImmutable('2026-11-21 08:00')]);
        ReportFactory::createOne(['instance' => $b, 'host' => 'x.com', 'receivedAt' => new \DateTimeImmutable('2026-10-01 08:00')]);

        $response = self::createClient()->request('GET', '/admin/report_stats?groupBy=host&period=day&since=2026-11-01&until=2026-12-01', ['auth_basic' => self::ADMIN]);

        self::assertResponseIsSuccessful();
        $rows = array_map(static fn (array $row) => [$row['key'], $row['period'], $row['reports'], $row['instances']], $response->toArray()['member']);
        self::assertSame([
            ['vimeo.com', '2026-11-20', 3, 2],
            ['x.com', '2026-11-21', 1, 1],
        ], $rows);
    }

    public function testStatsTotalsPerMonthAndValidation(): void
    {
        ReportFactory::createMany(2, ['receivedAt' => new \DateTimeImmutable('2026-11-20 10:00')]);

        $response = self::createClient()->request('GET', '/admin/report_stats?period=month&since=2026-01-01&until=2027-01-01', ['auth_basic' => self::ADMIN]);
        self::assertSame([['key' => null, 'period' => '2026-11-01', 'reports' => 2, 'instances' => 2]], array_map(
            static fn (array $row) => array_intersect_key($row, array_flip(['key', 'period', 'reports', 'instances'])),
            $response->toArray()['member'],
        ));

        foreach (['groupBy=url', 'period=year', 'since=yesterday-ish'] as $query) {
            self::createClient()->request('GET', '/admin/report_stats?'.$query, ['auth_basic' => self::ADMIN]);
            self::assertResponseStatusCodeSame(422);
        }
    }
}
