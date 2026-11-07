<?php

namespace Jenssmit\AnalyticsBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Jenssmit\AnalyticsBundle\Entity\PageView;

/**
 * Mandantenbegrenzter Datenzugriff.
 *
 * Jede Abfragemethode verlangt einen Site-Key. Übergreifende Zugriffe
 * sind ausschließlich über die bewusst mit "All" endenden Methoden
 * möglich und müssen vom Aufrufer ausdrücklich gewählt werden.
 *
 * Aggregationen bewusst als SQL: Die Datenbank zählt und gruppiert,
 * PHP lädt niemals vollständige PageView-Mengen in den Speicher.
 */
class PageViewRepository
{
    private const GROUPABLE_COLUMNS = ['path', 'referrer', 'browser', 'device'];
    private const BATCH_SIZE = 1000;
    private const MAX_DELETE_ITERATIONS = 100000;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function save(PageView $pageView): void
    {
        $this->entityManager->persist($pageView);
        $this->entityManager->flush();
    }

    public function countViewsForSite(string $siteKey, \DateTimeImmutable $since): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_page_view WHERE site_key = :site AND created_at >= :since',
            ['site' => $siteKey, 'since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    public function countVisitorsForSite(string $siteKey, \DateTimeImmutable $since): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(DISTINCT ip_hash) FROM jenssmit_page_view WHERE site_key = :site AND created_at >= :since',
            ['site' => $siteKey, 'since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    /**
     * @return list<array{day: string, views: int, visitors: int}>
     */
    public function timeseriesForSite(string $siteKey, \DateTimeImmutable $since): array
    {
        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT %s AS day, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS visitors '
                .'FROM jenssmit_page_view WHERE site_key = :site AND created_at >= :since '
                .'GROUP BY 1 ORDER BY 1',
                $this->dateGroupExpression(),
            ),
            ['site' => $siteKey, 'since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE],
        )->fetchAllAssociative();

        $series = [];
        foreach ($rows as $row) {
            $series[] = [
                'day' => (string) $row['day'],
                'views' => (int) $row['views'],
                'visitors' => (int) $row['visitors'],
            ];
        }

        return $series;
    }

    /**
     * Gruppierte Zählung für eine freigegebene Spalte, mandantenbezogen.
     *
     * @return list<array{name: string, views: int}>
     */
    public function groupForSite(string $siteKey, \DateTimeImmutable $since, string $column, int $limit, string $nullLabel): array
    {
        if (!\in_array($column, self::GROUPABLE_COLUMNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Die Spalte "%s" ist nicht gruppierbar.', $column));
        }

        $rows = $this->connection()->executeQuery(
            \sprintf(
                'SELECT COALESCE(%s, :null_label) AS name, COUNT(*) AS views '
                .'FROM jenssmit_page_view WHERE site_key = :site AND created_at >= :since '
                .'GROUP BY 1 ORDER BY views DESC, name ASC LIMIT :limit',
                $column,
            ),
            [
                'site' => $siteKey,
                'since' => $since,
                'null_label' => $nullLabel,
                'limit' => \max(1, $limit),
            ],
            ['since' => Types::DATETIME_IMMUTABLE, 'limit' => Types::INTEGER],
        )->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $items[] = ['name' => (string) $row['name'], 'views' => (int) $row['views']];
        }

        return $items;
    }

    /**
     * Anzahl löschbarer Datensätze eines Mandanten, standardmäßig für
     * Dry-Run-Anzeigen des Purge-Befehls.
     */
    public function countBeforeForSite(string $siteKey, \DateTimeImmutable $before): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_page_view WHERE site_key = :site AND created_at < :before',
            ['site' => $siteKey, 'before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    /**
     * Explizit globaler Zugriff: zählt Datensätze aller Mandanten.
     * Bewusst getrennt benannt, damit ein versehentlicher
     * Mandanten-Übergriff auffällt.
     */
    public function countBeforeAll(\DateTimeImmutable $before): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_page_view WHERE created_at < :before',
            ['before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    /**
     * Löscht alte Datensätze eines Mandanten in begrenzten Batches.
     * Andere Mandanten bleiben unberührt.
     */
    public function deleteBeforeForSite(string $siteKey, \DateTimeImmutable $before): int
    {
        return $this->deleteInBatches(
            'site_key = :site AND created_at < :before',
            ['site' => $siteKey, 'before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * Explizit globaler Zugriff: löscht alte Datensätze aller Mandanten.
     */
    public function deleteBeforeAll(\DateTimeImmutable $before): int
    {
        return $this->deleteInBatches(
            'created_at < :before',
            ['before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        );
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * Plattformbewusster Datumsausdruck für Tagesgruppierungen. Die
     * unterstützten Datenbanken haben unterschiedliche Datumsfunktionen.
     */
    private function dateGroupExpression(): string
    {
        $platform = $this->connection()->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            return "DATE_FORMAT(created_at, '%Y-%m-%d')";
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return "TO_CHAR(created_at, 'YYYY-MM-DD')";
        }

        if ($platform instanceof SQLitePlatform) {
            return "strftime('%Y-%m-%d', created_at)";
        }

        throw new \RuntimeException(\sprintf('Die Datenbank-Plattform "%s" wird für Statistikabfragen nicht unterstützt.', $platform::class));
    }

    /**
     * Löscht in Batches, damit große Tabellen keine langen
     * Transaktions- oder Sperrzeiten erzeugen. MySQL benötigt einen
     * materialisierten Subselect (Fehler 1093), PostgreSQL und SQLite
     * erlauben den direkten Subselect.
     *
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $types
     */
    private function deleteInBatches(string $where, array $parameters, array $types): int
    {
        $connection = $this->connection();
        $platform = $connection->getDatabasePlatform();

        $inner = 'SELECT id FROM jenssmit_page_view WHERE '.$where.' LIMIT :limit';

        $sql = ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform)
            ? 'DELETE FROM jenssmit_page_view WHERE id IN (SELECT id FROM ('.$inner.') AS batch)'
            : 'DELETE FROM jenssmit_page_view WHERE id IN ('.$inner.')';

        $parameters['limit'] = self::BATCH_SIZE;
        $types['limit'] = Types::INTEGER;

        $total = 0;
        $iterations = 0;

        do {
            $deleted = $connection->executeStatement($sql, $parameters, $types);
            $total += $deleted;
            ++$iterations;
        } while ($deleted >= self::BATCH_SIZE && $iterations < self::MAX_DELETE_ITERATIONS);

        return $total;
    }
}
