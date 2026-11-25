<?php

namespace App\Tests\Api\Ingest;

use App\Entity\Instance;
use App\Entity\Report;
use App\Factory\InstanceFactory;
use App\Factory\ReportCommentFactory;
use App\Factory\ReportFactory;
use App\Security\InstanceSecret;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The ingest API (/v1): auth, ownership, validation and size limits.
 */
final class IngestApiTest extends IngestTestCase
{
    private const string SECRET = 'test-secret-of-instance-a';

    public function testPolicyIsPublic(): void
    {
        $response = self::ingest('GET', '/v1/policy');

        self::assertResponseIsSuccessful();
        self::assertSame(['version' => '1', 'url' => 'http://localhost/policy', 'acceptedVersions' => ['1']], $response->toArray());
    }

    public function testPolicyTextIsPublic(): void
    {
        $response = self::ingest('GET', '/v1/policy/1', 'not-a-secret');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(['version', 'current', 'markdown'], array_keys($body));
        self::assertSame('1', $body['version']);
        self::assertTrue($body['current']);
        self::assertStringStartsWith('# FetchDock report collector privacy policy', $body['markdown']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unpublishedPolicyVersions(): iterable
    {
        yield 'unknown version' => ['0'];
        yield 'path traversal' => ['..%2F..%2Fcomposer'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unpublishedPolicyVersions')]
    public function testPolicyTextIsOnlyServedForPublishedVersions(string $version): void
    {
        self::ingest('GET', '/v1/policy/'.$version);

        self::assertResponseStatusCodeSame(404);
    }

    public function testRegisterReturnsTheSecretOnceAndStoresOnlyItsHash(): void
    {
        $response = self::ingest('POST', '/v1/instances', json: ['serverVersion' => '0.8.0', 'acceptedPolicyVersion' => '1']);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame(['instanceId', 'instanceSecret'], array_keys($body));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $body['instanceSecret']);

        $instance = InstanceFactory::repository()->find($body['instanceId']);
        self::assertInstanceOf(Instance::class, $instance);
        self::assertSame(InstanceSecret::hash($body['instanceSecret']), $instance->getSecretHash());
        self::assertSame('0.8.0', $instance->getServerVersion());

        // The new secret works right away
        self::ingest('POST', '/v1/reports', $body['instanceSecret'], self::reportPayload());
        self::assertResponseStatusCodeSame(201);
    }

    public function testRegisterRejectsAnUnacceptedPolicyVersion(): void
    {
        $response = self::ingest('POST', '/v1/instances', json: ['serverVersion' => '0.8.0', 'acceptedPolicyVersion' => '0']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('acceptedPolicyVersion', $response->toArray(false)['violations'][0]['propertyPath']);
        self::assertSame(0, InstanceFactory::count());
    }

    public function testRegisterIgnoresABearerToken(): void
    {
        self::ingest('POST', '/v1/instances', 'not-a-secret', ['serverVersion' => '0.8.0', 'acceptedPolicyVersion' => '1']);

        self::assertResponseStatusCodeSame(201);
    }

    public function testReportIsStoredVerbatimWithItsErrorCategory(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create(['serverVersion' => '0.7.0', 'lastSeenOn' => new \DateTimeImmutable('-3 days')]);

        $response = self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame(['id'], array_keys($body));

        $report = ReportFactory::repository()->find($body['id']);
        self::assertInstanceOf(Report::class, $report);
        self::assertTrue($report->getInstance()->getId()->equals($instance->getId()));
        self::assertSame('https://www.youtube.com/watch?v=abcdefghijk', $report->getUrl());
        self::assertSame('youtube', $report->getSite());
        self::assertSame('http_403', $report->getErrorCategory());
        self::assertSame('yt-dlp-cli', $report->getDownloader());
        self::assertSame('Fails every time', $report->getComment());
        self::assertSame(['policyVersion' => '1', 'consentedAt' => '2026-11-20T09:59:00+00:00'], $report->getConsent());
        self::assertEquals(new \DateTimeImmutable('2026-11-20T10:00:00+00:00'), $report->getReportedAt());
        self::assertSame('new', $report->getStatus()->value);
        // The instance's version and the day it was last seen are updated
        self::assertSame('0.8.0', $instance->getServerVersion());
        self::assertEquals(new \DateTimeImmutable('today'), $instance->getLastSeenOn());
    }

    public function testUnsupportedUrlReportWithoutDiagnostics(): void
    {
        InstanceFactory::new()->withSecret(self::SECRET)->create();

        $response = self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload([
            'kind' => 'unsupported_url', 'diagnostics' => null, 'site' => null, 'userAgent' => null, 'comment' => null,
        ]));

        self::assertResponseStatusCodeSame(201);
        $report = ReportFactory::repository()->find($response->toArray()['id']);
        self::assertNull($report?->getErrorCategory());
        self::assertNull($report?->getDiagnostics());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidReports(): iterable
    {
        yield 'consent version not accepted' => [['consent' => ['policyVersion' => '0', 'consentedAt' => '2026-11-20T09:59:00+00:00']], 'consent.policyVersion'];
        yield 'operator version not accepted' => [['operatorPolicyVersion' => '2'], 'operatorPolicyVersion'];
        yield 'unknown schema version' => [['schemaVersion' => 2], 'schemaVersion'];
        yield 'non-http url' => [['url' => 'ftp://example.com/file'], 'url'];
        yield 'url too long' => [['url' => 'https://example.com/'.str_repeat('a', 2048)], 'url'];
        yield 'user agent too long' => [['userAgent' => str_repeat('a', 513)], 'userAgent'];
        yield 'comment too long' => [['comment' => str_repeat('a', 2001)], 'comment'];
        yield 'admin comment too long' => [['adminComment' => str_repeat('a', 4001)], 'adminComment'];
        yield 'error message too long' => [['diagnostics' => ['errorMessage' => str_repeat('a', 4001)]], 'diagnostics.errorMessage'];
        yield 'diagnostics on unsupported_url' => [['kind' => 'unsupported_url'], 'diagnostics'];
        yield 'missing consent' => [['consent' => self::MISSING], 'consent'];
        yield 'missing url' => [['url' => self::MISSING], 'url'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidReports')]
    public function testInvalidReportsAreRejected(array $overrides, string $propertyPath): void
    {
        InstanceFactory::new()->withSecret(self::SECRET)->create();

        $response = self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload($overrides));

        self::assertResponseStatusCodeSame(422);
        self::assertContains($propertyPath, array_column($response->toArray(false)['violations'], 'propertyPath'));
        self::assertSame(0, ReportFactory::count());
    }

    public function testBodyAbove32KiBIsRejected(): void
    {
        InstanceFactory::new()->withSecret(self::SECRET)->create();

        self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload(['comment' => str_repeat('a', 33 * 1024)]));

        self::assertResponseStatusCodeSame(413);
        self::assertSame(0, ReportFactory::count());
    }

    public function testMissingOrUnknownSecretIs401(): void
    {
        InstanceFactory::new()->withSecret(self::SECRET)->create();

        self::ingest('POST', '/v1/reports', null, self::reportPayload());
        self::assertResponseStatusCodeSame(401);

        self::ingest('POST', '/v1/reports', 'unknown-secret', self::reportPayload());
        self::assertResponseStatusCodeSame(401);

        self::ingest('DELETE', '/v1/instances/me', 'unknown-secret');
        self::assertResponseStatusCodeSame(401);

        self::assertSame(0, ReportFactory::count());
    }

    public function testBlockedInstanceIs403(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->blocked()->create();
        $report = ReportFactory::createOne(['instance' => $instance]);

        $response = self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('blocked', $response->toArray(false)['detail']);

        self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, ['comment' => 'x', 'createdAt' => '2026-11-20T10:00:00+00:00']);
        self::assertResponseStatusCodeSame(403);

        self::ingest('DELETE', '/v1/reports/'.$report->getId(), self::SECRET);
        self::assertResponseStatusCodeSame(403);

        self::assertSame(1, ReportFactory::count());
    }

    public function testRetiringAnInstanceDisablesItsSecretButKeepsItsReports(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        ReportFactory::createOne(['instance' => $instance]);

        self::ingest('DELETE', '/v1/instances/me', self::SECRET);
        self::assertResponseStatusCodeSame(204);
        self::assertNotNull($instance->getRetiredAt());

        $response = self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());
        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('retired', $response->toArray(false)['detail']);

        self::ingest('DELETE', '/v1/instances/me', self::SECRET);
        self::assertResponseStatusCodeSame(403);

        self::assertSame(1, ReportFactory::count());
    }

    public function testCommentOnOwnReport(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        $report = ReportFactory::createOne(['instance' => $instance]);

        $response = self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, [
            'comment' => 'Happens on every video of this channel',
            'createdAt' => '2026-11-21T08:00:00+00:00',
        ]);

        self::assertResponseStatusCodeSame(201);
        $comment = ReportCommentFactory::repository()->find($response->toArray()['id']);
        self::assertSame('Happens on every video of this channel', $comment?->getComment());
        self::assertTrue($comment->getReport()->getId()->equals($report->getId()));
    }

    public function testCommentValidation(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        $report = ReportFactory::createOne(['instance' => $instance]);

        self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, ['comment' => str_repeat('a', 4001), 'createdAt' => '2026-11-21T08:00:00+00:00']);
        self::assertResponseStatusCodeSame(422);

        self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, ['comment' => '', 'createdAt' => '2026-11-21T08:00:00+00:00']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testOtherInstancesReportsAre404(): void
    {
        InstanceFactory::new()->withSecret(self::SECRET)->create();
        $foreign = ReportFactory::createOne(['instance' => InstanceFactory::new()]);

        self::ingest('POST', '/v1/reports/'.$foreign->getId().'/comments', self::SECRET, ['comment' => 'x', 'createdAt' => '2026-11-21T08:00:00+00:00']);
        self::assertResponseStatusCodeSame(404);

        self::ingest('DELETE', '/v1/reports/'.$foreign->getId(), self::SECRET);
        self::assertResponseStatusCodeSame(404);

        foreach (['00000000-0000-4000-8000-000000000000', 'not-a-uuid'] as $id) {
            self::ingest('DELETE', '/v1/reports/'.$id, self::SECRET);
            self::assertResponseStatusCodeSame(404);
            self::ingest('POST', '/v1/reports/'.$id.'/comments', self::SECRET, ['comment' => 'x', 'createdAt' => '2026-11-21T08:00:00+00:00']);
            self::assertResponseStatusCodeSame(404);
        }

        self::assertSame(1, ReportFactory::count());
        self::assertSame(0, ReportCommentFactory::count());
    }

    public function testDeleteOwnReportRemovesItsComments(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        $report = ReportFactory::createOne(['instance' => $instance]);
        ReportCommentFactory::createMany(2, ['report' => $report]);
        $id = $report->getId();

        self::ingest('DELETE', '/v1/reports/'.$id, self::SECRET);

        self::assertResponseStatusCodeSame(204);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(0, ReportFactory::count());
        self::assertSame(0, ReportCommentFactory::count());

        // A repeated delete is 404, which the server treats as confirmed too
        self::ingest('DELETE', '/v1/reports/'.$id, self::SECRET);
        self::assertResponseStatusCodeSame(404);
    }
}
