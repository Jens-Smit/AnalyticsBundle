<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class StatsServiceTest extends TestCase
{
    private PageViewRepository&MockObject $repository;
    private AnalyticsEventRepository&MockObject $eventRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PageViewRepository::class);
        $this->eventRepository = $this->createMock(AnalyticsEventRepository::class);
    }

    private function createService(string $siteKey = 'site-a'): StatsService
    {
        return new StatsService($this->repository, $this->eventRepository, $siteKey, new SystemClock());
    }

    public function testSummaryUsesTheUnifiedResponseContract(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countViewsForSite')
            ->with('site-a', self::callback(static fn (\DateTimeImmutable $since): bool => $since instanceof \DateTimeImmutable))
            ->willReturn(10);
        $this->repository
            ->expects(self::once())
            ->method('countVisitsForSite')
            ->with('site-a', self::anything())
            ->willReturn(6);

        $summary = $this->createService()->summary(30);

        self::assertSame('site-a', $summary['site']);
        self::assertArrayHasKey('from', $summary['period']);
        self::assertSame(30, $summary['period']['days']);
        self::assertSame(10, $summary['data']['views']);
        self::assertSame(6, $summary['data']['visits']);
        self::assertSame(0.33, $summary['data']['views_per_day']);
        self::assertSame('UTC', $summary['meta']['timezone']);
        self::assertSame('summary', $summary['meta']['report']);
    }

    public function testTimeseriesFillsMissingDaysWithZeros(): void
    {
        $today = (new SystemClock())->today();

        $this->repository
            ->method('timeseriesForSite')
            ->with('site-a', self::anything())
            ->willReturn([
                ['day' => $today->format('Y-m-d'), 'views' => 10, 'visitors' => 6],
            ]);

        $timeseries = $this->createService()->timeseries(7);

        self::assertCount(7, $timeseries['data']);

        foreach ($timeseries['data'] as $index => $entry) {
            $expectedDate = $today->modify(\sprintf('-%d days', 6 - $index))->format('Y-m-d');

            self::assertSame($expectedDate, $entry['date']);
        }

        self::assertSame(0, $timeseries['data'][0]['views']);
        self::assertSame(0, $timeseries['data'][0]['visitors']);
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
        self::assertSame(10, $topPages['data']['total']);
        self::assertSame(80.0, $topPages['data']['items'][0]['share']);
        self::assertSame(20.0, $topPages['data']['items'][1]['share']);
    }

    public function testReferrersUseDirektLabelForNullReferrer(): void
    {
        $this->repository->method('countViewsForSite')->willReturn(5);
        $this->repository
            ->method('groupForSite')
            ->with('site-a', self::anything(), 'referrer', 10, 'Direkt')
            ->willReturn([['name' => 'Direkt', 'views' => 5]]);

        $referrers = $this->createService()->referrers(10, 30);

        self::assertSame('Direkt', $referrers['data']['items'][0]['name']);
        self::assertSame(100.0, $referrers['data']['items'][0]['share']);
    }

    public function testSourcesAreSiteScoped(): void
    {
        $this->repository->method('countViewsForSite')->willReturn(10);
        $this->repository
            ->method('sourcesForSite')
            ->with('site-a', self::anything(), 10, 'unknown')
            ->willReturn([
                ['name' => 'search', 'views' => 8],
                ['name' => 'direct', 'views' => 2],
            ]);

        $sources = $this->createService()->sources(10, 30);

        self::assertSame('sources', $sources['meta']['report']);
        self::assertSame('search', $sources['data']['items'][0]['name']);
        self::assertSame(80.0, $sources['data']['items'][0]['share']);
    }

    public function testEventsAreSiteScoped(): void
    {
        $this->eventRepository->method('countForSite')->willReturn(4);
        $this->eventRepository
            ->method('namesForSite')
            ->with('site-a', self::anything(), 10)
            ->willReturn([
                ['name' => 'newsletter_signup', 'category' => 'engagement', 'events' => 3],
            ]);

        $events = $this->createService()->events(10, 30);

        self::assertSame('events', $events['meta']['report']);
        self::assertSame(4, $events['data']['total']);
        self::assertSame('newsletter_signup', $events['data']['items'][0]['name']);
    }

    public function testReportCacheServesRepeatedCallsFromCache(): void
    {
        $this->repository->expects(self::once())->method('countViewsForSite')->willReturn(10);
        $this->repository->expects(self::once())->method('countVisitsForSite')->willReturn(6);

        $service = new StatsService(
            $this->repository,
            $this->eventRepository,
            'site-a',
            new SystemClock(),
            new ArrayAdapter(),
            60,
        );

        $first = $service->summary(30);
        $second = $service->summary(30);

        self::assertSame($first, $second);
    }

    public function testCacheInvalidationDropsStaleEntries(): void
    {
        $this->repository->expects(self::exactly(2))->method('countViewsForSite')->willReturn(10);
        $this->repository->expects(self::exactly(2))->method('countVisitsForSite')->willReturn(6);

        $service = new StatsService(
            $this->repository,
            $this->eventRepository,
            'site-a',
            new SystemClock(),
            new ArrayAdapter(),
            60,
        );

        $service->summary(30);
        $service->invalidateReportCache();
        $service->summary(30);
    }

    public function testCacheKeysAreSiteScoped(): void
    {
        $this->repository->method('countViewsForSite')->willReturn(10);
        $this->repository->method('countVisitsForSite')->willReturn(6);

        $cache = new ArrayAdapter();

        $serviceA = new StatsService($this->repository, $this->eventRepository, 'site-a', new SystemClock(), $cache, 60);
        $serviceB = new StatsService($this->repository, $this->eventRepository, 'site-b', new SystemClock(), $cache, 60);

        $summaryA = $serviceA->summary(30);
        $summaryB = $serviceB->summary(30);

        self::assertSame('site-a', $summaryA['site']);
        self::assertSame('site-b', $summaryB['site']);
    }
}
