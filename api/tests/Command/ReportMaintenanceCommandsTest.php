<?php

namespace App\Tests\Command;

use App\Enum\ReportStatus;
use App\Factory\InstanceFactory;
use App\Factory\ReportCommentFactory;
use App\Factory\ReportFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * app:reports:purge (retention) and app:reports:recategorize.
 */
final class ReportMaintenanceCommandsTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private static function execute(string $command): CommandTester
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find($command));
        $tester->execute([]);

        return $tester;
    }

    public function testPurgeDeletesOnlyOldTriagedReportsAndEmptyRetiredInstances(): void
    {
        $old = new \DateTimeImmutable('-25 months');
        $instance = InstanceFactory::createOne();
        $oldResolved = ReportFactory::createOne(['instance' => $instance, 'receivedAt' => $old, 'status' => ReportStatus::Resolved]);
        ReportCommentFactory::createOne(['report' => $oldResolved]);
        ReportFactory::createOne(['instance' => $instance, 'receivedAt' => $old, 'status' => ReportStatus::Duplicate]);
        // Kept: open, or within the retention period
        $oldOpen = ReportFactory::createOne(['instance' => $instance, 'receivedAt' => $old, 'status' => ReportStatus::Acknowledged]);
        $recent = ReportFactory::createOne(['instance' => $instance, 'receivedAt' => new \DateTimeImmutable('-23 months'), 'status' => ReportStatus::WontFix]);
        // Retired: deleted without reports, kept with reports
        InstanceFactory::new()->retired()->create();
        $retiredWithReports = InstanceFactory::new()->retired()->create();
        ReportFactory::createOne(['instance' => $retiredWithReports, 'status' => ReportStatus::New]);
        $oldId = $oldResolved->getId();

        $tester = self::execute('app:reports:purge');

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Deleted 2 reports and 1 retired instances', $tester->getDisplay());
        self::assertSame(3, ReportFactory::count());
        self::assertNull(ReportFactory::repository()->find($oldId));
        self::assertNotNull(ReportFactory::repository()->find($oldOpen->getId()));
        self::assertNotNull(ReportFactory::repository()->find($recent->getId()));
        self::assertSame(0, ReportCommentFactory::count());
        self::assertSame(2, InstanceFactory::count());
    }

    public function testRecategorizeAppliesTheCurrentRules(): void
    {
        $report = ReportFactory::createOne(['kind' => \App\Enum\ReportKind::JobFailed, 'diagnostics' => ['errorMessage' => 'HTTP Error 429: Too Many Requests']]);
        $report->setErrorCategory('stale');
        ReportFactory::repository()->getEntityManager()->flush();

        self::execute('app:reports:recategorize')->assertCommandIsSuccessful();

        self::assertSame('http_429', ReportFactory::repository()->find($report->getId())?->getErrorCategory());
    }
}
