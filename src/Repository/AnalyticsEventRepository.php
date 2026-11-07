<?php

namespace Jenssmit\AnalyticsBundle\Repository;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Jenssmit\AnalyticsBundle\Entity\AnalyticsEvent;

/**
 * Mandantengetrennter Zugriff auf Custom Events. Jede reguläre Abfrage
 * verlangt zwingend einen Site-Key; übergreifende Zugriffe existieren
 * nur als ausdrücklich benannte *All-Methoden für den dokumentierten
 * Betriebsmodus mit analytics:purge --all-sites.
 */
class AnalyticsEventRepository
{
    private const BATCH_SIZE = 1000;
    private const MAX_DELETE_ITERATIONS = 1000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AnalyticsEvent $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function countForSite(string $siteKey, \DateTimeImmutable $since): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_analytics_event WHERE site_key = :site AND occurred_at >= :since',
            ['site' => $siteKey, 'since' => $since],
            ['since' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    /**
     * Häufigste Event-Namen eines Mandanten, SQL-aggregiert.
     *
     * @return list<array{name: string, category: ?string, events: int}>
     */
    public function namesForSite(string $siteKey, \DateTimeImmutable $since, int $limit): array
    {
        $rows = $this->connection()->executeQuery(
            'SELECT event_name AS name, MAX(event_category) AS category, COUNT(*) AS events '
            .'FROM jenssmit_analytics_event WHERE site_key = :site AND occurred_at >= :since '
            .'GROUP BY event_name ORDER BY events DESC, name ASC LIMIT :limit',
            ['site' => $siteKey, 'since' => $since, 'limit' => \max(1, $limit)],
            ['since' => Types::DATETIME_IMMUTABLE, 'limit' => Types::INTEGER],
        )->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'name' => (string) $row['name'],
                'category' => null === $row['category'] ? null : (string) $row['category'],
                'events' => (int) $row['events'],
            ];
        }

        return $items;
    }

    public function countBeforeForSite(string $siteKey, \DateTimeImmutable $before): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_analytics_event WHERE site_key = :site AND occurred_at < :before',
            ['site' => $siteKey, 'before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    public function countBeforeAll(\DateTimeImmutable $before): int
    {
        return (int) $this->connection()->executeQuery(
            'SELECT COUNT(*) FROM jenssmit_analytics_event WHERE occurred_at < :before',
            ['before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        )->fetchOne();
    }

    public function deleteBeforeForSite(string $siteKey, \DateTimeImmutable $before): int
    {
        return $this->deleteInBatches(
            'site_key = :site AND occurred_at < :before',
            ['site' => $siteKey, 'before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        );
    }

    public function deleteBeforeAll(\DateTimeImmutable $before): int
    {
        return $this->deleteInBatches(
            'occurred_at < :before',
            ['before' => $before],
            ['before' => Types::DATETIME_IMMUTABLE],
        );
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $types
     */
    private function deleteInBatches(string $where, array $parameters, array $types): int
    {
        $connection = $this->connection();
        $platform = $connection->getDatabasePlatform();

        $inner = 'SELECT id FROM jenssmit_analytics_event WHERE '.$where.' LIMIT :limit';

        $sql = ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform)
            ? 'DELETE FROM jenssmit_analytics_event WHERE id IN (SELECT id FROM ('.$inner.') AS batch)'
            : 'DELETE FROM jenssmit_analytics_event WHERE id IN ('.$inner.')';

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
