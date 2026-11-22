<?php

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use App\Entity\Report;
use App\Factory\InstanceFactory;
use App\Factory\ReportFactory;
use App\Security\InstanceSecret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The mock data and the factories the tests share.
 */
final class FixturesTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFixturesLoad(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        (new AppFixtures())->load($entityManager);

        self::assertSame(20, InstanceFactory::count());
        self::assertSame(500, ReportFactory::count());
    }

    public function testReportsGetAnErrorCategory(): void
    {
        $report = ReportFactory::createOne([
            'diagnostics' => ['errorMessage' => 'ERROR: HTTP Error 403: Forbidden'],
        ]);

        self::assertInstanceOf(Report::class, $report);
        self::assertSame('http_403', $report->getErrorCategory());
    }

    public function testInstanceWithSecret(): void
    {
        $instance = InstanceFactory::new()->withSecret('s3cret')->create();

        self::assertSame(InstanceSecret::hash('s3cret'), $instance->getSecretHash());
        self::assertNotSame(InstanceSecret::hash('s3cret'), InstanceSecret::hash(InstanceSecret::generate()));
    }
}
