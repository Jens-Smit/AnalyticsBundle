<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Wertet die Seitenaufrufe genau eines Mandanten aus.
 *
 * Der Site-Key wird per Dependency Injection gesetzt. Jede Abfrage
 * läuft über Repository-Methoden, die den Site-Filter zwingend
 * voraussetzen; das Service kann keine anderen Mandanten sehen.
 *
 * Alle Antworten folgen dem einheitlichen Vertrag aus site, period,
 * data und meta. "visits" sind Besuchstage: Da das IP-Pseudonym
 * tagesbezogen ist, zählt jeder Kalendertag mit Pseudonym als ein
 * Besuch. Besucher sind nur als Tagesmetrik eindeutig.
 *
 * Aggregate Ergebnisse können optional über einen PSR-6-Cache mit
 * kurzer TTL zwischengespeichert werden. Der Cache-Schlüssel enthält
 * Site-Key, Report und Parameter sowie eine Generationsnummer, die
 * bei einem Purge erhöht wird. Es werden niemals Site-übergreifende
 * Daten aus einem Cache geliefert.
 */
final class StatsService
{
    private const CACHE_PREFIX = 'jenssmit_analytics.report.';
    private const GENERATION_KEY = 'jenssmit_analytics.report.generation';

    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly AnalyticsEventRepository $eventRepository,
        private readonly string $siteKey,
        private readonly SystemClock $clock,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly int $cacheTtl = 0,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        return $this->cached('summary', $days, null, function () use ($days): array {
            $from = $this->startOfPeriod($days);
            $views = $this->repository->countViewsForSite($this->siteKey, $from);
            $visits = $this->repository->countVisitsForSite($this->siteKey, $from);

            return [
                'views' => $views,
                'visits' => $visits,
                'views_per_day' => 0 === $views ? 0 : round($views / max($days, 1), 2),
            ];
        });
    }

    /**
     * Zeitreihe mit lückenloser Tagesfolge. Fehlende Tage werden mit
     * Nullen aufgefüllt; die Gruppierung selbst macht die Datenbank.
     *
     * @return array<string, mixed>
     */
    public function timeseries(int $days = 30): array
    {
        $days = max($days, 1);

        return $this->cached('timeseries', $days, null, function () use ($days): array {
            $from = $this->startOfPeriod($days);
            $rows = $this->repository->timeseriesForSite($this->siteKey, $from);

            $byDay = [];
            foreach ($rows as $row) {
                $byDay[$row['day']] = $row;
            }

            $series = [];
            for ($i = $days - 1; $i >= 0; --$i) {
                $day = $this->clock->today()->modify(\sprintf('-%d days', $i))->format('Y-m-d');
                $series[] = [
                    'date' => $day,
                    'views' => $byDay[$day]['views'] ?? 0,
                    'visitors' => $byDay[$day]['visitors'] ?? 0,
                ];
            }

            return $series;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function topPages(int $limit = 10, int $days = 30): array
    {
        return $this->group('top-pages', 'path', 'Unbekannt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function referrers(int $limit = 10, int $days = 30): array
    {
        return $this->group('referrers', 'referrer', 'Direkt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function browsers(int $limit = 10, int $days = 30): array
    {
        return $this->group('browsers', 'browser', 'Unbekannt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function devices(int $limit = 10, int $days = 30): array
    {
        return $this->group('devices', 'device', 'Unbekannt', $limit, $days);
    }

    /**
     * Traffic-Quellen: direct, search, social, internal, referral,
     * campaign. Alte Datensätze ohne Quelle erscheinen als unknown.
     *
     * @return array<string, mixed>
     */
    public function sources(int $limit = 10, int $days = 30): array
    {
        $limit = max($limit, 1);

        return $this->cached('sources', $days, $limit, function () use ($limit, $days): array {
            $from = $this->startOfPeriod($days);
            $total = $this->repository->countViewsForSite($this->siteKey, $from);

            $items = [];
            foreach ($this->repository->sourcesForSite($this->siteKey, $from, $limit, 'unknown') as $row) {
                $items[] = [
                    'name' => $row['name'],
                    'views' => $row['views'],
                    'share' => 0 === $total ? 0 : round($row['views'] / $total * 100, 1),
                ];
            }

            return ['total' => $total, 'items' => $items];
        });
    }

    /**
     * Häufigste Custom Events eines Mandanten, SQL-aggregiert.
     *
     * @return array<string, mixed>
     */
    public function events(int $limit = 10, int $days = 30): array
    {
        $limit = max($limit, 1);

        return $this->cached('events', $days, $limit, function () use ($limit, $days): array {
            $from = $this->startOfPeriod($days);

            $items = [];
            foreach ($this->eventRepository->namesForSite($this->siteKey, $from, $limit) as $row) {
                $items[] = [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'events' => $row['events'],
                ];
            }

            return ['total' => $this->eventRepository->countForSite($this->siteKey, $from), 'items' => $items];
        });
    }

    /**
     * Erhöht die Report-Generation, damit nach einem Purge keine
     * veralteten Cache-Einträge mehr ausgeliefert werden. Alte
     * Einträge laufen über ihre TTL ab.
     */
    public function invalidateReportCache(): void
    {
        if (null === $this->cache || $this->cacheTtl <= 0) {
            return;
        }

        $generation = $this->cache->getItem(self::GENERATION_KEY);
        $generation->set(((int) $generation->get()) + 1);
        $this->cache->save($generation);
    }

    /**
     * @param callable():array<mixed> $callback
     *
     * @return array<string, mixed>
     */
    private function cached(string $report, int $days, ?int $limit, callable $callback): array
    {
        $days = max($days, 1);
        $data = null;
        $key = null;

        if (null !== $this->cache && $this->cacheTtl > 0) {
            $key = self::CACHE_PREFIX
                .$this->generation()
                .'.'
                .hash('sha256', (string) json_encode([$this->siteKey, $report, $days, $limit]));

            $item = $this->cache->getItem($key);

            if ($item->isHit()) {
                $cached = $item->get();

                if (\is_array($cached)) {
                    $data = $cached;
                }
            }
        }

        if (null === $data) {
            $data = $callback();

            if (null !== $key) {
                $item = $this->cache->getItem($key);
                $item->set($data);
                $item->expiresAfter($this->cacheTtl);
                $this->cache->save($item);
            }
        }

        return $this->envelope($report, $days, $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function envelope(string $report, int $days, array $data): array
    {
        return [
            'site' => $this->siteKey,
            'period' => [
                'from' => $this->startOfPeriod($days)->format('Y-m-d'),
                'days' => $days,
            ],
            'data' => $data,
            'meta' => [
                'report' => $report,
                'timezone' => 'UTC',
                'visits_definition' => 'Besuchstage: Da das IP-Pseudonym tagesbezogen ist, zählt jeder Kalendertag mit Pseudonym als ein Besuch.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function group(string $report, string $column, string $nullLabel, int $limit, int $days): array
    {
        $limit = max($limit, 1);

        return $this->cached($report, $days, $limit, function () use ($column, $nullLabel, $limit, $days): array {
            $from = $this->startOfPeriod($days);
            $total = $this->repository->countViewsForSite($this->siteKey, $from);

            $items = [];
            foreach ($this->repository->groupForSite($this->siteKey, $from, $column, $limit, $nullLabel) as $row) {
                $items[] = [
                    'name' => $row['name'],
                    'views' => $row['views'],
                    'share' => 0 === $total ? 0 : round($row['views'] / $total * 100, 1),
                ];
            }

            return ['total' => $total, 'items' => $items];
        });
    }

    private function startOfPeriod(int $days): \DateTimeImmutable
    {
        return $this->clock->today()->modify(\sprintf('-%d days', max($days, 1) - 1));
    }

    private function generation(): int
    {
        if (null === $this->cache) {
            return 0;
        }

        return (int) $this->cache->getItem(self::GENERATION_KEY)->get();
    }
}
