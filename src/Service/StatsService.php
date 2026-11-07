<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;

final class StatsService
{
    public function __construct(private readonly PageViewRepository $repository)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $from = $this->startOfPeriod($days);
        $rows = $this->repository->findSince($from);

        $views = 0;
        $visitors = [];
        foreach ($rows as $row) {
            ++$views;
            $hash = $row->getIpHash();
            if (null !== $hash && '' !== $hash) {
                $visitors[$hash] = true;
            }
        }

        return [
            'period' => [
                'days' => $days,
                'from' => $from->format('Y-m-d'),
                'until' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ],
            'views' => $views,
            'unique_visitors' => \count($visitors),
            'views_per_day' => 0 === $views ? 0 : round($views / max($days, 1), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function timeseries(int $days = 30): array
    {
        $from = $this->startOfPeriod($days);
        $rows = $this->repository->findSince($from);

        $series = [];
        for ($i = max($days, 1) - 1; $i >= 0; --$i) {
            $date = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $i));
            $series[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'views' => 0,
                'visitors' => 0,
            ];
        }

        $visitorsByDay = [];
        foreach ($rows as $row) {
            $day = $row->getCreatedAt()->format('Y-m-d');
            if (!isset($series[$day])) {
                continue;
            }

            ++$series[$day]['views'];

            $hash = $row->getIpHash();
            if (null !== $hash && '' !== $hash && !isset($visitorsByDay[$day][$hash])) {
                $visitorsByDay[$day][$hash] = true;
                ++$series[$day]['visitors'];
            }
        }

        return ['days' => $days, 'series' => array_values($series)];
    }

    /**
     * @return array<string, mixed>
     */
    public function topPages(int $limit = 10, int $days = 30): array
    {
        return $this->aggregateBy($this->startOfPeriod($days), $limit, static fn ($row) => $row->getPath());
    }

    /**
     * @return array<string, mixed>
     */
    public function referrers(int $limit = 10, int $days = 30): array
    {
        return $this->aggregateBy($this->startOfPeriod($days), $limit, static fn ($row) => self::referrerHost($row->getReferrer()));
    }

    /**
     * @return array<string, mixed>
     */
    public function browsers(int $limit = 10, int $days = 30): array
    {
        return $this->aggregateBy($this->startOfPeriod($days), $limit, static fn ($row) => $row->getBrowser() ?? 'Unbekannt');
    }

    /**
     * @return array<string, mixed>
     */
    public function devices(int $limit = 10, int $days = 30): array
    {
        return $this->aggregateBy($this->startOfPeriod($days), $limit, static fn ($row) => $row->getDevice() ?? 'Unbekannt');
    }

    /**
     * @param callable $key
     *
     * @return array<string, mixed>
     */
    private function aggregateBy(\DateTimeImmutable $since, int $limit, callable $key): array
    {
        $rows = $this->repository->findSince($since);

        $counts = [];
        $total = 0;
        foreach ($rows as $row) {
            $name = $key($row);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            ++$total;
        }

        arsort($counts);

        $items = [];
        foreach (\array_slice($counts, 0, max($limit, 1), true) as $name => $views) {
            $items[] = [
                'name' => $name,
                'views' => $views,
                'share' => 0 === $total ? 0 : round($views / $total * 100, 1),
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    private static function referrerHost(?string $referrer): string
    {
        if (null === $referrer || '' === $referrer) {
            return 'Direkt';
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : 'Direkt';
    }

    private function startOfPeriod(int $days): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', max($days, 1) - 1));
    }
}
