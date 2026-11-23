<?php

namespace App\Tests\Api\Ingest;

use App\Factory\InstanceFactory;
use App\Factory\ReportFactory;

/**
 * The optional abuse limits (RATE_LIMIT_*): off by default, 429 with Retry-After when set.
 */
final class RateLimitTest extends IngestTestCase
{
    private const string SECRET = 'test-secret-of-instance-a';
    private const array VARIABLES = ['RATE_LIMIT_REGISTRATION_PER_IP', 'RATE_LIMIT_REPORTS_PER_INSTANCE', 'RATE_LIMIT_COMMENTS_PER_INSTANCE'];

    protected function setUp(): void
    {
        parent::setUp();
        // The limiter state is cached on the filesystem across kernels and test runs
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (self::VARIABLES as $variable) {
            unset($_SERVER[$variable], $_ENV[$variable]);
        }
    }

    /** Sets a limit before the kernel boots (env variables are resolved per container) */
    private static function limit(string $variable, int $limit): void
    {
        self::ensureKernelShutdown();
        $_SERVER[$variable] = $_ENV[$variable] = (string) $limit;
    }

    public function testLimitsAreOffByDefault(): void
    {
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        $report = ReportFactory::createOne(['instance' => $instance]);

        for ($i = 0; $i < 5; ++$i) {
            self::ingest('POST', '/v1/instances', json: ['serverVersion' => '0.8.0', 'acceptedPolicyVersion' => '1']);
            self::assertResponseStatusCodeSame(201);
            self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());
            self::assertResponseStatusCodeSame(201);
            self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, ['comment' => 'x', 'createdAt' => '2026-11-21T08:00:00+00:00']);
            self::assertResponseStatusCodeSame(201);
        }
    }

    public function testRegistrationLimitPerIp(): void
    {
        self::limit('RATE_LIMIT_REGISTRATION_PER_IP', 2);
        $registration = ['serverVersion' => '0.8.0', 'acceptedPolicyVersion' => '1'];

        self::ingest('POST', '/v1/instances', json: $registration);
        self::ingest('POST', '/v1/instances', json: $registration);
        self::assertResponseStatusCodeSame(201);

        $response = self::ingest('POST', '/v1/instances', json: $registration);
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $response->getHeaders(false)['retry-after'][0]);
        self::assertSame(2, InstanceFactory::count());
    }

    public function testReportLimitPerInstance(): void
    {
        self::limit('RATE_LIMIT_REPORTS_PER_INSTANCE', 1);
        InstanceFactory::new()->withSecret(self::SECRET)->create();
        InstanceFactory::new()->withSecret('other-secret')->create();

        self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());
        self::assertResponseStatusCodeSame(201);
        self::ingest('POST', '/v1/reports', self::SECRET, self::reportPayload());
        self::assertResponseStatusCodeSame(429);

        // Per instance: another instance is not affected
        self::ingest('POST', '/v1/reports', 'other-secret', self::reportPayload());
        self::assertResponseStatusCodeSame(201);
    }

    public function testCommentLimitPerInstance(): void
    {
        self::limit('RATE_LIMIT_COMMENTS_PER_INSTANCE', 1);
        $instance = InstanceFactory::new()->withSecret(self::SECRET)->create();
        $report = ReportFactory::createOne(['instance' => $instance]);
        $comment = ['comment' => 'x', 'createdAt' => '2026-11-21T08:00:00+00:00'];

        self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, $comment);
        self::assertResponseStatusCodeSame(201);
        self::ingest('POST', '/v1/reports/'.$report->getId().'/comments', self::SECRET, $comment);
        self::assertResponseStatusCodeSame(429);
    }
}
