<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\IntegrityCheck;
use App\Enum\IntegrityRole;
use App\Enum\IntegrityStatus;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * GET /v1/integrity (public) and GET /admin/integrity (ROLE_ADMIN), ADR 0013, collector#5.
 */
final class IntegrityTest extends ApiTestCase
{
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = false;

    private const array MAINTAINER = ['maintainer', 'maintainerpass'];
    private const array ADMIN = ['admin', 'adminpass'];
    private const string SECRET_PATH = 'src/Patched/SecretPath.php';

    private function record(IntegrityCheck ...$checks): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($checks as $check) {
            $entityManager->persist($check);
        }
        $entityManager->flush();
    }

    public function testPublicStatusIsTheWorstRecentCheckWithoutPaths(): void
    {
        $this->record(
            new IntegrityCheck('api-1', IntegrityRole::API, IntegrityStatus::VERIFIED, str_repeat('a', 64), 'dev', [], new \DateTimeImmutable('-1 hour')),
            new IntegrityCheck('worker-1', IntegrityRole::WORKER, IntegrityStatus::MODIFIED, str_repeat('a', 64), 'dev', [self::SECRET_PATH], new \DateTimeImmutable('-2 hours')),
            // Outside the 48 hour window
            new IntegrityCheck('api-0', IntegrityRole::API, IntegrityStatus::UNKNOWN, null, 'dev', [], new \DateTimeImmutable('-3 days')),
        );

        // Public, a stray bearer token doesn't matter
        $response = self::createClient()->request('GET', '/v1/integrity', ['headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer not-an-instance']]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame('modified', $body['status']);
        self::assertSame(['status', 'appVersion', 'gitSha', 'manifestDigest', 'digestUrl', 'checkedAt'], array_keys($body));
        // The test environment has no manifest and runs version "dev"
        self::assertNull($body['manifestDigest']);
        self::assertNull($body['digestUrl']);
        self::assertSame((new \DateTimeImmutable('-1 hour'))->format('Y-m-d\TH'), substr($body['checkedAt'], 0, 13));
        self::assertStringNotContainsString(self::SECRET_PATH, $response->getContent());
        self::assertStringNotContainsString('worker-1', $response->getContent());
    }

    public function testPublicStatusIsUnknownWithoutRecentChecks(): void
    {
        $body = self::createClient()->request('GET', '/v1/integrity', ['headers' => ['Accept' => 'application/json']])->toArray();

        self::assertSame('unknown', $body['status']);
        self::assertNull($body['checkedAt']);
    }

    public function testAdminSeesTheLatestCheckPerContainerWithPaths(): void
    {
        $this->record(
            new IntegrityCheck('api-1', IntegrityRole::API, IntegrityStatus::VERIFIED, null, 'dev', [], new \DateTimeImmutable('-3 hours')),
            new IntegrityCheck('api-1', IntegrityRole::API, IntegrityStatus::MODIFIED, null, 'dev', [self::SECRET_PATH], new \DateTimeImmutable('-1 hour')),
            new IntegrityCheck('worker-1', IntegrityRole::WORKER, IntegrityStatus::UNKNOWN, null, 'dev', [], new \DateTimeImmutable('-2 hours')),
        );

        $response = self::createClient()->request('GET', '/admin/integrity', ['auth_basic' => self::ADMIN]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame('modified', $body['status']);
        self::assertTrue($body['checkOnline']);
        self::assertSame([['api-1', 'api', 'modified', [self::SECRET_PATH]], ['worker-1', 'worker', 'unknown', []]], array_map(
            static fn (array $check): array => [$check['host'], $check['role'], $check['status'], $check['mismatches']],
            $body['checks'],
        ));
    }

    public function testMaintainersSeeNoAdminIntegrity(): void
    {
        self::createClient()->request('GET', '/admin/integrity', ['auth_basic' => self::MAINTAINER]);
        self::assertResponseStatusCodeSame(403);
    }
}
