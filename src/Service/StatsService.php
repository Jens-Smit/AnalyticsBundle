<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;

/**
 * Wertet die Seitenaufrufe genau eines Mandanten aus.
 *
 * Der Site-Key wird per Dependency Injection gesetzt. Jede Abfrage
 * läuft über Repository-Methoden, die den Site-Filter zwingend
 * voraussetzen; das Service kann keine anderen Mandanten sehen.
 */
final class StatsService
{
    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly string $siteKey,
    ) {
    }

    /**
     * Zusammenfassung des Zeitraums. "visits" zählt Besuche
     * (Besuchstage): Da das IP-Pseudonym tagesbezogen ist, zählt jeder
     * Kalendertag mit Pseudonym als ein Besuch, nicht jeder Besucher
     * über den gesamten Zeitraum.
     *
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $from = $this->startOfPeriod($days);
        $views = $this->repository->countViewsForSite($this->siteKey, $from);
        $visits = $this->repository->countVisitsForSite($this->siteKey, $from);

        return [
            'site' => $this->siteKey,
            'period' => [
                'from' => $from->format('Y-m-d'),
                'days' => $days,
            ],
            'views' => $views,
            'visits' => $visits,
            'views_per_day' => 0 === $views ? 0 : round($views / max($days, 1), 2),
        ];
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
        $from = $this->startOfPeriod($days);

        $rows = $this->repository->timeseriesForSite($this->siteKey, $from);

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $day = (new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', $i))->format('Y-m-d');
            $series[] = [
                'date' => $day,
                'views' => $byDay[$day]['views'] ?? 0,
                'visitors' => $byDay[$day]['visitors'] ?? 0,
            ];
        }

        return [
            'site' => $this->siteKey,
            'days' => $days,
            'series' => $series,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topPages(int $limit = 10, int $days = 30): array
    {
        return $this->group('path', 'Unbekannt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function referrers(int $limit = 10, int $days = 30): array
    {
        return $this->group('referrer', 'Direkt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function browsers(int $limit = 10, int $days = 30): array
    {
        return $this->group('browser', 'Unbekannt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function devices(int $limit = 10, int $days = 30): array
    {
        return $this->group('device', 'Unbekannt', $limit, $days);
    }

    /**
     * @return array<string, mixed>
     */
    private function group(string $column, string $nullLabel, int $limit, int $days): array
    {
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

        return [
            'site' => $this->siteKey,
            'total' => $total,
            'items' => $items,
        ];
    }

    private function startOfPeriod(int $days): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today'))->modify(\sprintf('-%d days', max($days, 1) - 1));
    }
}
