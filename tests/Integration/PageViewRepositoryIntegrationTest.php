<?php

namespace Jenssmit\AnalyticsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\SchemaProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integrationstests gegen eine echte Datenbank. Die Verbindung wird
 * über ANALYTICS_DATABASE_URL konfiguriert, zum Beispiel:
 *
 *   ANALYTICS_DATABASE_URL=mysql://root:root@127.0.0.1:3306/analytics_test?serverVersion=8.0
 *   ANALYTICS_DATABASE_URL=postgresql://postgres:postgres@127.0.0.1:5432/analytics_test?serverVersion=16
 *   ANALYTICS_DATABASE_URL=sqlite:///:memory:
 *
 * Ohne Umgebungsvariable werden die Tests übersprungen.
 */
final class PageViewRepositoryIntegrationTest extends TestCase
{
    private static ?Connection $connection = null;
    private static ?EntityManager $entityManager = null;
    private ?PageViewRepository $repository = null;

    public static function setUpBeforeClass(): void
    {
        $databaseUrl = getenv('ANALYTICS_DATABASE_URL');

        if (false === $databaseUrl || '' === $databaseUrl) {
            return;
        }

        $ormConfig = ORMSetup::createXMLMetadataConfiguration([], true);
        $ormConfig->setMetadataDriverImpl(new XmlDriver(
            new SymfonyFileLocator([__DIR__.'/../../config/doctrine' => 'Jenssmit\AnalyticsBundle\Entity'], '.orm.xml'),
            '.orm.xml',
        ));
        $connectionParams = (new DsnParser([
            'mysql' => 'pdo_mysql',
            'postgresql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
        ]))->parse($databaseUrl);
        $connection = DriverManager::getConnection($connectionParams, $ormConfig);

        $connection->executeStatement('DROP TABLE IF EXISTS jenssmit_page_view');
        foreach ((new SchemaProvider())->createTableStatements($connection) as $statement) {
            $connection->executeStatement($statement);
        }

        self::$connection = $connection;
        self::$entityManager = new EntityManager($connection, $ormConfig);
    }

    protected function setUp(): void
    {
        if (null === self::$entityManager || null === self::$connection) {
            $this->markTestSkipped('ANALYTICS_DATABASE_URL ist nicht gesetzt.');
        }

        self::$connection->executeStatement('DELETE FROM jenssmit_page_view');
        $this->repository = new PageViewRepository(self::$entityManager);
    }

    /**
     * Regressionstest für Mandantentrennung: Site A darf die Daten von
     * Site B in keiner Abfrage sehen. Der Test schlägt fehl, sobald
     * ein Site-Filter aus einer Abfrage entfernt wird.
     */
    public function testSiteIsolationInAllAggregations(): void
    {
        $this->track('site-a', '/blog', 1, 'hash-a');
        $this->track('site-a', '/kontakt', 1, 'hash-b');
        $this->track('site-b', '/blog', 1, 'hash-c');

        $since = new \DateTimeImmutable('today -1 day');

        self::assertSame(2, $this->repository->countViewsForSite('site-a', $since));
        self::assertSame(1, $this->repository->countViewsForSite('site-b', $since));

        self::assertSame(2, $this->repository->countVisitorsForSite('site-a', $since));
        self::assertSame(1, $this->repository->countVisitorsForSite('site-b', $since));

        $siteATopPaths = $this->repository->groupForSite('site-a', $since, 'path', 10, 'Unbekannt');
        self::assertCount(2, $siteATopPaths);

        foreach ($siteATopPaths as $entry) {
            self::assertNotSame('', (string) $entry['name']);
        }

        // Zeitreihe von Site A enthält ausschließlich Tage von Site A.
        $timeseries = $this->repository->timeseriesForSite('site-a', $since);
        $viewsSum = 0;
        foreach ($timeseries as $row) {
            $viewsSum += $row['views'];
        }
        self::assertSame(2, $viewsSum);
    }

    public function testTimeseriesGroupsByDayInSql(): void
    {
        $yesterday = new \DateTimeImmutable('yesterday 12:00');
        $today = new \DateTimeImmutable('today 12:00');

        $this->track('site-a', '/a', 1, 'hash-1', $yesterday);
        $this->track('site-a', '/b', 1, 'hash-1', $today);
        $this->track('site-a', '/b', 1, 'hash-2', $today);

        $series = $this->repository->timeseriesForSite('site-a', new \DateTimeImmutable('yesterday'));

        self::assertCount(2, $series);
        self::assertSame($yesterday->format('Y-m-d'), $series[0]['day']);
        self::assertSame(1, $series[0]['views']);
        self::assertSame(1, $series[0]['visitors']);
        self::assertSame($today->format('Y-m-d'), $series[1]['day']);
        self::assertSame(2, $series[1]['views']);
        self::assertSame(2, $series[1]['visitors']);
    }

    public function testGroupForSiteOrdersByViewsAndAppliesLimit(): void
    {
        $this->track('site-a', '/beliebt', 1, 'hash-a');
        $this->track('site-a', '/beliebt', 1, 'hash-b');
        $this->track('site-a', '/seltener', 1, 'hash-c');

        $since = new \DateTimeImmutable('today -1 day');
        $top = $this->repository->groupForSite('site-a', $since, 'path', 1, 'Unbekannt');

        self::assertCount(1, $top);
        self::assertSame('/beliebt', $top[0]['name']);
        self::assertSame(2, $top[0]['views']);
    }

    public function testReferrerGroupingLabelsNullAsDirekt(): void
    {
        $this->track('site-a', '/a', 1, 'hash-a', null, 'www.google.com');
        $this->track('site-a', '/b', 1, 'hash-b', null, null);

        $referrers = $this->repository->groupForSite(
            'site-a',
            new \DateTimeImmutable('today -1 day'),
            'referrer',
            10,
            'Direkt',
        );

        self::assertCount(2, $referrers);
        $names = array_column($referrers, 'name');

        self::assertContains('www.google.com', $names);
        self::assertContains('Direkt', $names);
    }

    public function testInvalidGroupColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->groupForSite('site-a', new \DateTimeImmutable('today'), 'user_identifier', 10, 'X');
    }

    public function testPurgeDeletesOnlyExpiredRowsOfTheGivenSite(): void
    {
        $old = new \DateTimeImmutable('-30 days');
        $recent = new \DateTimeImmutable('-1 day');

        $this->track('site-a', '/alt', 1, 'hash-a', $old);
        $this->track('site-a', '/alt', 1, 'hash-b', $old);
        $this->track('site-b', '/alt', 1, 'hash-c', $old);
        $this->track('site-a', '/neu', 1, 'hash-d', $recent);

        $before = new \DateTimeImmutable('-5 days');

        self::assertSame(2, $this->repository->countBeforeForSite('site-a', $before));
        self::assertSame(3, $this->repository->countBeforeAll($before));

        $deleted = $this->repository->deleteBeforeForSite('site-a', $before);

        self::assertSame(2, $deleted);

        $since = new \DateTimeImmutable('-60 days');
        self::assertSame(1, $this->repository->countViewsForSite('site-a', $since));
        self::assertSame(1, $this->repository->countViewsForSite('site-b', $since));
    }

    public function testGlobalPurgeRespectsBatching(): void
    {
        // Mehr Datensätze als eine Batch-Größe (1000), damit der
        // Batch-Löschpfad tatsächlich mehrfach durchlaufen wird.
        $old = new \DateTimeImmutable('-30 days');

        $count = 1001;
        for ($i = 0; $i < $count; ++$i) {
            self::$entityManager->persist(PageView::create(
                'batch-site',
                '/p'.$i,
                $old,
                null,
                null,
                null,
                null,
                null,
                'hash-'.$i,
                null,
            ));
        }
        self::$entityManager->flush();
        self::$entityManager->clear();

        $deleted = $this->repository->deleteBeforeAll(new \DateTimeImmutable('-5 days'));

        self::assertSame($count, $deleted);
        self::assertSame(0, $this->repository->countBeforeAll(new \DateTimeImmutable('-5 days')));
    }

    private function track(
        string $siteKey,
        string $path,
        int $hoursAgo,
        string $ipHash,
        ?\DateTimeImmutable $at = null,
        ?string $referrer = null,
    ): void {
        self::$entityManager->persist(PageView::create(
            $siteKey,
            $path,
            $at ?? new \DateTimeImmutable(\sprintf('-%d hours', $hoursAgo)),
            null,
            $referrer,
            null,
            null,
            null,
            $ipHash,
            null,
        ));
        self::$entityManager->flush();
    }
}
