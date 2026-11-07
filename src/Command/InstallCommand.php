<?php

namespace Jenssmit\AnalyticsBundle\Command;

use Doctrine\DBAL\Connection;
use Jenssmit\AnalyticsBundle\Service\SchemaProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Legt die Analytics-Tabellen an (PageViews und Events). Die
 * Anweisungen sind idempotent: Bestehende Tabellen und Daten bleiben
 * unangetastet, es wird nichts gelöscht und keine Spalte verändert.
 */
#[AsCommand(
    name: 'analytics:install',
    description: 'Legt die Analytics-Tabellen an (idempotent, ohne Datenverlust).',
)]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaProvider $schemaProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Beispiel: php bin/console analytics:install');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $statements = [
                ...$this->schemaProvider->createTableStatements($this->connection),
                ...$this->schemaProvider->createEventTableStatements($this->connection),
            ];
        } catch (\RuntimeException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }

        $output->writeln('<info>Analytics-Tabellen wurden angelegt oder existierten bereits.</info>');

        return Command::SUCCESS;
    }
}
