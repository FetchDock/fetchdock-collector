<?php

namespace App\Command;

use App\Entity\Report;
use App\Service\ErrorCategorizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Re-applies the error category rules (collector.error_categories) to every report, after the rules changed.
 */
#[AsCommand(
    name: 'app:reports:recategorize',
    description: 'Re-derives every report\'s errorCategory from diagnostics.errorMessage with the current rules',
)]
final class RecategorizeReportsCommand extends Command
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ErrorCategorizer $errorCategorizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $changed = 0;
        $seen = 0;
        $reports = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Report::class, 'r')
            ->getQuery()
            ->toIterable();

        foreach ($reports as $report) {
            \assert($report instanceof Report);
            $category = $this->errorCategorizer->categorize($report->getDiagnostics()['errorMessage'] ?? null);
            if ($category !== $report->getErrorCategory()) {
                $report->setErrorCategory($category);
                ++$changed;
            }
            if (0 === ++$seen % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();

        (new SymfonyStyle($input, $output))->success(\sprintf('Recategorized %d of %d reports.', $changed, $seen));

        return Command::SUCCESS;
    }
}
