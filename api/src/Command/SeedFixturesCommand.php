<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Replaces all data with mock data (src/DataFixtures). Staging runs it on every deploy (ADR 0014).
 */
#[AsCommand(
    name: 'app:fixtures:seed',
    description: 'Purges the database and loads mock data; refuses to run unless COLLECTOR_ALLOW_FIXTURES=1',
)]
final class SeedFixturesCommand extends Command
{
    public function __construct(
        #[Autowire(param: 'collector.allow_fixtures')]
        private readonly bool $allowFixtures,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->allowFixtures) {
            (new SymfonyStyle($input, $output))->error('Mock data is not allowed here: set COLLECTOR_ALLOW_FIXTURES=1 (dev and staging only, never production).');

            return Command::FAILURE;
        }

        $load = new ArrayInput([]);
        $load->setInteractive(false);

        return $this->getApplication()?->find('doctrine:fixtures:load')->run($load, $output)
            ?? throw new \LogicException('The command has no application.');
    }
}
