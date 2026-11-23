<?php

namespace App\Command;

use App\Service\ReportRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Applies the retention rule (ReportRetention). Scheduled daily (App\Schedule).
 */
#[AsCommand(
    name: 'app:reports:purge',
    description: 'Deletes triaged reports (resolved, wont_fix, duplicate) older than REPORT_RETENTION_MONTHS and retired instances without reports',
)]
final class PurgeReportsCommand extends Command
{
    public function __construct(private readonly ReportRetention $retention)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purged = $this->retention->purge();
        (new SymfonyStyle($input, $output))->success(\sprintf('Deleted %d reports and %d retired instances.', $purged['reports'], $purged['instances']));

        return Command::SUCCESS;
    }
}
