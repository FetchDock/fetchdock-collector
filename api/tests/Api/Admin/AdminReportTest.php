<?php

namespace App\Tests\Api\Admin;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Enum\ReportStatus;
use App\Factory\InstanceFactory;
use App\Factory\ReportCommentFactory;
use App\Factory\ReportFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The maintainer API for reports: triage by ROLE_MAINTAINER, instance ids and deletion for ROLE_ADMIN only.
 */
final class AdminReportTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = false;

    private const array MAINTAINER = ['maintainer', 'maintainerpass'];
    private const array ADMIN = ['admin', 'adminpass'];

    public function testMaintainerListsAndFiltersReports(): void
    {
        ReportFactory::createMany(3, ['host' => 'vimeo.com', 'status' => ReportStatus::New]);
        ReportFactory::createMany(2, ['host' => 'x.com', 'status' => ReportStatus::Resolved]);

        $all = self::createClient()->request('GET', '/admin/reports', ['auth_basic' => self::MAINTAINER])->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame(5, $all['totalItems']);

        $filtered = self::createClient()->request('GET', '/admin/reports?host=x.com&status=resolved', ['auth_basic' => self::MAINTAINER])->toArray();
        self::assertSame(2, $filtered['totalItems']);
        self::assertSame(0, self::createClient()->request('GET', '/admin/reports?host=x.com&status=new', ['auth_basic' => self::MAINTAINER])->toArray()['totalItems']);
    }

    public function testFiltersByDownloaderAndErrorCategory(): void
    {
        ReportFactory::createOne(['kind' => \App\Enum\ReportKind::JobFailed, 'diagnostics' => ['downloader' => 'gallery-dl-cli', 'errorMessage' => 'HTTP Error 429: Too Many Requests']]);
        ReportFactory::createOne(['kind' => \App\Enum\ReportKind::JobFailed, 'diagnostics' => ['downloader' => 'yt-dlp-cli', 'errorMessage' => 'HTTP Error 403: Forbidden']]);

        $byDownloader = self::createClient()->request('GET', '/admin/reports?downloader=gallery-dl-cli', ['auth_basic' => self::MAINTAINER])->toArray();
        self::assertSame(1, $byDownloader['totalItems']);
        self::assertSame('http_429', $byDownloader['member'][0]['errorCategory']);

        self::assertSame(1, self::createClient()->request('GET', '/admin/reports?errorCategory=http_403', ['auth_basic' => self::MAINTAINER])->toArray()['totalItems']);
    }

    public function testInstanceIdIsForAdminsOnly(): void
    {
        $instance = InstanceFactory::createOne();
        $report = ReportFactory::createOne(['instance' => $instance]);
        ReportCommentFactory::createOne(['report' => $report, 'comment' => 'More context']);

        $asMaintainer = self::createClient()->request('GET', '/admin/reports/'.$report->getId(), ['auth_basic' => self::MAINTAINER])->toArray();
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('instance', $asMaintainer);
        self::assertNull($asMaintainer['instance']);
        self::assertSame('More context', $asMaintainer['comments'][0]['comment']);
        self::assertSame(['id', 'comment', 'createdAt', 'receivedAt'], array_values(array_filter(array_keys($asMaintainer['comments'][0]), static fn (string $key) => !str_starts_with($key, '@'))));

        $asAdmin = self::createClient()->request('GET', '/admin/reports/'.$report->getId(), ['auth_basic' => self::ADMIN])->toArray();
        self::assertSame($instance->getId()->toRfc4122(), $asAdmin['instance']);

        // Admins can filter by instance
        ReportFactory::createOne();
        self::assertSame(1, self::createClient()->request('GET', '/admin/reports?instance='.$instance->getId(), ['auth_basic' => self::ADMIN])->toArray()['totalItems']);
    }

    public function testMaintainerTriagesAReport(): void
    {
        $report = ReportFactory::createOne(['status' => ReportStatus::New, 'updatedAt' => new \DateTimeImmutable('-1 day')]);

        $response = self::createClient()->request('PATCH', '/admin/reports/'.$report->getId(), [
            'auth_basic' => self::MAINTAINER,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['status' => 'acknowledged', 'maintainerNote' => 'Known yt-dlp issue', 'url' => 'https://changed.example/'],
        ]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame('acknowledged', $body['status']);
        self::assertSame('Known yt-dlp issue', $body['maintainerNote']);
        // Only triage fields are writable
        self::assertSame($report->getUrl(), $body['url']);
        self::assertGreaterThan(new \DateTimeImmutable('-1 minute'), new \DateTimeImmutable($body['updatedAt']));
    }

    public function testAdminWithoutMaintainerRoleListsShowsAndTriagesReports(): void
    {
        // The in-memory "admin" has ROLE_ADMIN only, like an IdP user in the "admin" group but not in "maintainer":
        // the role hierarchy grants everything ROLE_MAINTAINER can do
        $report = ReportFactory::createOne(['status' => ReportStatus::New]);
        ReportFactory::createOne();

        self::assertSame(2, self::createClient()->request('GET', '/admin/reports', ['auth_basic' => self::ADMIN])->toArray()['totalItems']);
        self::assertResponseIsSuccessful();

        self::createClient()->request('GET', '/admin/reports/'.$report->getId(), ['auth_basic' => self::ADMIN]);
        self::assertResponseIsSuccessful();

        $response = self::createClient()->request('PATCH', '/admin/reports/'.$report->getId(), [
            'auth_basic' => self::ADMIN,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['status' => 'acknowledged'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('acknowledged', $response->toArray()['status']);
    }

    public function testTriageValidation(): void
    {
        $report = ReportFactory::createOne();
        $options = ['auth_basic' => self::MAINTAINER, 'headers' => ['Content-Type' => 'application/merge-patch+json']];

        self::createClient()->request('PATCH', '/admin/reports/'.$report->getId(), $options + ['json' => ['maintainerNote' => str_repeat('a', 4001)]]);
        self::assertResponseStatusCodeSame(422);

        self::createClient()->request('PATCH', '/admin/reports/'.$report->getId(), $options + ['json' => ['status' => 'unknown']]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testOnlyAdminsDeleteReports(): void
    {
        $report = ReportFactory::createOne();
        ReportCommentFactory::createOne(['report' => $report]);

        self::createClient()->request('DELETE', '/admin/reports/'.$report->getId(), ['auth_basic' => self::MAINTAINER]);
        self::assertResponseStatusCodeSame(403);

        self::createClient()->request('DELETE', '/admin/reports/'.$report->getId(), ['auth_basic' => self::ADMIN]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, ReportFactory::count());
        self::assertSame(0, ReportCommentFactory::count());
    }

    public function testUsersWithoutCollectorRoleAndInstancesAreRejected(): void
    {
        self::createClient()->request('GET', '/admin/reports', ['auth_basic' => ['user', 'userpass']]);
        self::assertResponseStatusCodeSame(403);

        // An instance secret is no maintainer token
        InstanceFactory::new()->withSecret('instance-secret')->create();
        self::createClient()->request('GET', '/admin/reports', ['headers' => ['Authorization' => 'Bearer instance-secret']]);
        self::assertResponseStatusCodeSame(401);
    }
}
