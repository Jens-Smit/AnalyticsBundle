<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Stellt die Schema-Anweisungen der Analytics-Tabelle bereit. Die
 * Anweisungen sind idempotent (IF NOT EXISTS) und plattformabhängig.
 *
 * Diese Klasse wird vom Installationsbefehl und den Tests genutzt,
 * damit Schema-Definition und Tests dieselbe Quelle verwenden.
 */
final class SchemaProvider
{
    public const TABLE = 'jenssmit_page_view';
    public const EVENT_TABLE = 'jenssmit_analytics_event';

    /**
     * @return list<string>
     */
    public function createTableStatements(Connection $connection): array
    {
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            return [<<<'SQL'
                CREATE TABLE IF NOT EXISTS jenssmit_page_view (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    site_key VARCHAR(32) NOT NULL,
                    path VARCHAR(500) NOT NULL,
                    route VARCHAR(190) NULL,
                    referrer VARCHAR(255) NULL,
                    browser VARCHAR(32) NULL,
                    operating_system VARCHAR(32) NULL,
                    device VARCHAR(16) NULL,
                    ip_hash VARCHAR(64) NULL,
                    user_identifier VARCHAR(191) NULL,
                    source VARCHAR(16) NULL,
                    utm_source VARCHAR(100) NULL,
                    utm_medium VARCHAR(50) NULL,
                    utm_campaign VARCHAR(100) NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_jenssmit_page_view_created_at (created_at),
                    INDEX idx_jenssmit_page_view_site_created (site_key, created_at)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
                SQL];
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_page_view (
                        id BIGSERIAL PRIMARY KEY,
                        site_key VARCHAR(32) NOT NULL,
                        path VARCHAR(500) NOT NULL,
                        route VARCHAR(190) NULL,
                        referrer VARCHAR(255) NULL,
                        browser VARCHAR(32) NULL,
                        operating_system VARCHAR(32) NULL,
                        device VARCHAR(16) NULL,
                        ip_hash VARCHAR(64) NULL,
                        user_identifier VARCHAR(191) NULL,
                        source VARCHAR(16) NULL,
                        utm_source VARCHAR(100) NULL,
                        utm_medium VARCHAR(50) NULL,
                        utm_campaign VARCHAR(100) NULL,
                        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
                    )
                    SQL,
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_created_at ON jenssmit_page_view (created_at)',
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_site_created ON jenssmit_page_view (site_key, created_at)',
            ];
        }

        if ($platform instanceof SQLitePlatform) {
            return [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_page_view (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        site_key VARCHAR(32) NOT NULL,
                        path VARCHAR(500) NOT NULL,
                        route VARCHAR(190) NULL,
                        referrer VARCHAR(255) NULL,
                        browser VARCHAR(32) NULL,
                        operating_system VARCHAR(32) NULL,
                        device VARCHAR(16) NULL,
                        ip_hash VARCHAR(64) NULL,
                        user_identifier VARCHAR(191) NULL,
                        source VARCHAR(16) NULL,
                        utm_source VARCHAR(100) NULL,
                        utm_medium VARCHAR(50) NULL,
                        utm_campaign VARCHAR(100) NULL,
                        created_at DATETIME NOT NULL
                    )
                    SQL,
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_created_at ON jenssmit_page_view (created_at)',
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_page_view_site_created ON jenssmit_page_view (site_key, created_at)',
            ];
        }

        throw new \RuntimeException(\sprintf('Die Datenbank-Plattform "%s" wird nicht unterstützt.', $platform::class));
    }


    /**
     * Schema-Anweisungen für die Event-Tabelle, ebenfalls idempotent
     * und plattformabhängig.
     *
     * @return list<string>
     */
    public function createEventTableStatements(Connection $connection): array
    {
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            return [<<<'SQL'
                CREATE TABLE IF NOT EXISTS jenssmit_analytics_event (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    site_key VARCHAR(32) NOT NULL,
                    event_name VARCHAR(80) NOT NULL,
                    event_category VARCHAR(64) NULL,
                    path VARCHAR(500) NOT NULL,
                    event_value VARCHAR(100) NULL,
                    visitor_hash VARCHAR(64) NULL,
                    occurred_at DATETIME NOT NULL,
                    INDEX idx_jenssmit_event_site_occurred (site_key, occurred_at)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
                SQL];
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_analytics_event (
                        id BIGSERIAL PRIMARY KEY,
                        site_key VARCHAR(32) NOT NULL,
                        event_name VARCHAR(80) NOT NULL,
                        event_category VARCHAR(64) NULL,
                        path VARCHAR(500) NOT NULL,
                        event_value VARCHAR(100) NULL,
                        visitor_hash VARCHAR(64) NULL,
                        occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
                    )
                    SQL,
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_event_site_occurred ON jenssmit_analytics_event (site_key, occurred_at)',
            ];
        }

        if ($platform instanceof SQLitePlatform) {
            return [
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS jenssmit_analytics_event (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        site_key VARCHAR(32) NOT NULL,
                        event_name VARCHAR(80) NOT NULL,
                        event_category VARCHAR(64) NULL,
                        path VARCHAR(500) NOT NULL,
                        event_value VARCHAR(100) NULL,
                        visitor_hash VARCHAR(64) NULL,
                        occurred_at DATETIME NOT NULL
                    )
                    SQL,
                'CREATE INDEX IF NOT EXISTS idx_jenssmit_event_site_occurred ON jenssmit_analytics_event (site_key, occurred_at)',
            ];
        }


        throw new \RuntimeException(\sprintf('Die Datenbank-Plattform "%s" wird nicht unterstützt.', $platform::class));
    }
}
