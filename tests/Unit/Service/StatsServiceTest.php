<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StatsServiceTest extends TestCase
{
    private PageViewRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PageViewRepository::class);
    }

    private function createService(string $siteKey = 'site-a'): StatsService
    {
        return new StatsService($this->repository, $siteKey);
    }

    public function testSummaryUsesOnlyTheConfiguredSite(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countViewsForSite')
            ->with('site-a', self::callback(static fn (\DateTimeImmutable $since): bool => $since instanceof \DateTimeImmutable))
            ->willReturn(10);
        $this->repository
            ->expects(self::once())
            ->method('countVisitorsForSite')
            ->with('site-a', self::anything())
            ->willReturn(6);

        $summary = $this->createService()->summary(30);

        self::assertSame('site-a', $summary['site']);
        self::assertSame(10, $summary['views']);
        self::assertSame(6, $summary['unique_visitors']);
        self::assertSame(0.33, $summary['views_per_day']);
    }

    public function testTimeseriesFillsMissingDaysWithZeros(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->repository
            ->method('timeseriesForSite')
            ->with('site-a', self::anything())
            ->willReturn([
                ['day' => $today->format('Y-m-d'), 'views' => 10, 'visitors' => 6],
            ]);

        $timeseries = $this->createService()->timeseries(7);

        self::assertCount(7, $timeseries['series']);

        foreach ($timeseries['series'] as $index => $entry) {
            $expectedDate = $today->modify(\sprintf('-%d days', 6 - $index))->format('Y-m-d');

            self::assertSame($expectedDate, $entry['date']);
        }

        self::assertSame(10, $timeseries['series'][6]['views']);
        self::assertSame(6, $timeseries['series'][6]['visitors']);
        self::assertSame(0, $timeseries['series'][0]['views']);
        self::assertSame(0, $timeseries['series'][0]['visitors']);
    }

    public function testTopPagesComputeShareFromSiteTotal(): void
    {
        $this->repository->method('countViewsForSite')->willReturn(10);
        $this->repository
            ->method('groupForSite')
            ->with('site-a', self::anything(), 'path', 10, 'Unbekannt')
            ->willReturn([
                ['name' => '/blog', 'views' => 8],
                ['name' => '/ueber', 'views' => 2],
            ]);

        $topPages = $this->createService()->topPages(10, 30);

        self::assertSame('site-a', $topPages['site']);
        self::assertSame(10, $topPages['total']);
        self::assertSame(80.0, $topPages['items'][0]['share']);
        self::assertSame(20.0, $topPages['items'][1]['share']);
    }

    public function testReferrersUseDirektLabelForNullReferrer(): void
    {
        $this->repository->method('countViewsForSite')->willReturn(5);
        $this->repository
            ->method('groupForSite')
            ->with('site-a', self::anything(), 'referrer', 10, 'Direkt')
            ->willReturn([['name' => 'Direkt', 'views' => 5]]);

        $referrers = $this->createService()->referrers(10, 30);

        self::assertSame('Direkt', $referrers['items'][0]['name']);
        self::assertSame(100.0, $referrers['items'][0]['share']);
    }
}
