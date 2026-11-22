<?php

namespace App\Tests\Command;

use App\Command\SeedFixturesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedFixturesCommandTest extends TestCase
{
    public function testRefusesWithoutCollectorAllowFixtures(): void
    {
        $application = new Application();
        $application->addCommand(new SeedFixturesCommand(allowFixtures: false));
        $tester = new CommandTester($application->find('app:fixtures:seed'));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('COLLECTOR_ALLOW_FIXTURES=1', $tester->getDisplay());
    }
}
