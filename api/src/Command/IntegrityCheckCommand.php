<?php

namespace App\Command;

use App\Enum\IntegrityRole;
use App\Enum\IntegrityStatus;
use App\Service\Integrity\IntegrityChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks the container's files against the image's integrity manifest and records the result (ADR 0013,
 * collector#5). Runs in the api and worker entrypoints and daily in the worker (App\Schedule). A modified collector
 * still runs; the status is shown on the public index page. Exits 0 unless the check itself fails.
 */
#[AsCommand(name: 'app:integrity:check', description: 'Check the application files against the integrity manifest')]
final readonly class IntegrityCheckCommand
{
    public function __construct(
        private IntegrityChecker $checker,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Container role (api or worker), defaults to NODE_MODE (worker, else api)')]
        ?string $role = null,
    ): int {
        $integrityRole = null === $role ? IntegrityRole::fromNodeMode(getenv('NODE_MODE') ?: null) : IntegrityRole::tryFrom($role);
        if (null === $integrityRole) {
            $io->error('--role must be api or worker');

            return Command::INVALID;
        }

        $check = $this->checker->check($integrityRole);
        $line = \sprintf('[integrity] %s (fetchdock-collector %s, manifest %s)', $check->getStatus()->value, $check->getAppVersion(), $check->getManifestDigest() ?? 'none');
        if (IntegrityStatus::MODIFIED === $check->getStatus()) {
            $io->warning($line);
            $io->listing($check->getMismatches());
        } else {
            $io->writeln($line);
        }

        return Command::SUCCESS;
    }
}
