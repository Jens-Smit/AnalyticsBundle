<?php

namespace Jenssmit\AnalyticsBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'analytics:install',
    description: 'Legt die Tabelle für die Seitenaufruf-Statistik an (MySQL/MariaDB und PostgreSQL).'
)]
final class InstallCommand extends Command
{
    private const TABLE = 'jenssmit_page_view';

    public function __construct(private readonly ManagerRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            $statements = [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_page_view (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        site_key VARCHAR(32) NOT NULL,
                        path VARCHAR(500) NOT NULL,
                        route VARCHAR(190) NULL,
                        referrer VARCHAR(500) NULL,
                        user_agent VARCHAR(255) NULL,
                        browser VARCHAR(32) NULL,
                        operating_system VARCHAR(32) NULL,
                        device VARCHAR(16) NULL,
                        ip_hash VARCHAR(64) NULL,
                        user_identifier VARCHAR(191) NULL,
                        created_at DATETIME NOT NULL,
                        INDEX idx_jenssmit_page_view_created_at (created_at),
                        INDEX idx_jenssmit_page_view_site_created (site_key, created_at)
                    ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
                    SQL,
            ];
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $statements = [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_page_view (
                        id BIGSERIAL PRIMARY KEY,
                        site_key VARCHAR(32) NOT NULL,
                        path VARCHAR(500) NOT NULL,
                        route VARCHAR(190) NULL,
                        referrer VARCHAR(500) NULL,
                        user_agent VARCHAR(255) NULL,
                        browser VARCHAR(32) NULL,
                        operating_system VARCHAR(32) NULL,
                        device VARCHAR(16) NULL,
                        ip_hash VARCHAR(64) NULL,
                        user_identifier VARCHAR(191) NULL,
                        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
                    )
                    SQL,
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_created_at ON jenssmit_page_view (created_at)',
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_site_created ON jenssmit_page_view (site_key, created_at)',
            ];
        } else {
            $output->writeln(sprintf(
                '<error>Die Datenbank-Plattform "%s" wird nicht unterstützt. Bitte die Tabelle "%s" analog zur README manuell anlegen.</error>',
                $platform::class,
                self::TABLE
            ));

            return Command::FAILURE;
        }

        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        $output->writeln(sprintf('<info>Die Tabelle "%s" wurde angelegt oder existierte bereits.</info>', self::TABLE));

        return Command::SUCCESS;
    }
}
