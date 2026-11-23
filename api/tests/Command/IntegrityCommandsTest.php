<?php

namespace App\Tests\Command;

use App\Entity\IntegrityCheck;
use App\Enum\IntegrityRole;
use App\Enum\IntegrityStatus;
use App\Service\Integrity\IntegrityManifest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * app:integrity:manifest and app:integrity:check (ADR 0013, collector#5).
 */
final class IntegrityCommandsTest extends KernelTestCase
{
    use ResetDatabase;

    private static function tester(string $command): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find($command));
    }

    public function testManifestCommandWritesTheManifestAndPrintsItsDigest(): void
    {
        $output = sys_get_temp_dir().'/integrity-manifest-'.bin2hex(random_bytes(4)).'.json';
        $tester = self::tester('app:integrity:manifest');

        try {
            self::assertSame(Command::SUCCESS, $tester->execute(['--git-sha' => 'abc1234', '--app-version' => '0.1.0', '--output' => $output]));
            $document = json_decode((string) file_get_contents($output), true);
            self::assertSame('0.1.0', $document['version']);
            self::assertSame('abc1234', $document['gitSha']);
            self::assertArrayHasKey('src/Kernel.php', $document['files']);
            self::assertArrayHasKey('composer.lock', $document['files']);
            self::assertArrayNotHasKey('config/reference.php', $document['files']);
            self::assertSame(hash_file('sha256', $output).'  '.IntegrityManifest::MANIFEST_FILE, trim($tester->getDisplay()));
        } finally {
            @unlink($output);
        }
    }

    public function testCheckCommandRecordsTheResult(): void
    {
        // The test environment has no manifest (like dev images): unknown, without asking GitHub
        $tester = self::tester('app:integrity:check');

        self::assertSame(Command::SUCCESS, $tester->execute(['--role' => 'worker']));
        self::assertStringContainsString('[integrity] unknown', $tester->getDisplay());

        $check = self::getContainer()->get(EntityManagerInterface::class)->getRepository(IntegrityCheck::class)->findOneBy([], ['id' => 'DESC']);
        self::assertSame(IntegrityRole::WORKER, $check->getRole());
        self::assertSame(IntegrityStatus::UNKNOWN, $check->getStatus());
        self::assertSame(gethostname(), $check->getHost());
    }

    public function testCheckCommandRejectsUnknownRoles(): void
    {
        self::assertSame(Command::INVALID, self::tester('app:integrity:check')->execute(['--role' => 'pwa']));
    }
}
