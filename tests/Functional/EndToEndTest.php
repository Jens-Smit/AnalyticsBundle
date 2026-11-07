<?php

namespace Jenssmit\AnalyticsBundle\Tests\Functional;

use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Tests\Fixtures\Kernel\AnalyticsTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Release-Candidate-Validierung: Erfassung und Auswertung laufen
 * vollständig über den kompilierten Container der Testanwendung,
 * wie es eine frische Symfony-Installation tun würde – inklusive
 * PrivacyPolicy, Doctrine-Mapping und synchronem Fallback ohne
 * Messenger.
 */
final class EndToEndTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AnalyticsTestKernel::class;
    }

    public function testRecordAndReportThroughTheContainer(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $metadata = $entityManager->getClassMetadata(\Jenssmit\AnalyticsBundle\Entity\PageView::class);

        (new \Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema([$metadata]);

        $recorder = $container->get(PageViewRecorder::class);

        $request = Request::create('/blog/ueber-uns?utm_source=newsletter&utm_medium=email', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'HTTP_REFERER' => 'https://www.google.com/search?q=beispiel',
            'REMOTE_ADDR' => '203.0.113.42',
        ]);

        self::assertTrue($recorder->record($request));

        $stats = $container->get(StatsService::class);
        $summary = $stats->summary(1);

        self::assertSame('test_site', $summary['site']);
        self::assertSame(1, $summary['data']['views']);
        self::assertSame(1, $summary['data']['visits']);

        $topPages = $stats->topPages(10, 1);
        self::assertSame('/blog/ueber-uns', $topPages['data']['items'][0]['name']);

        $sources = $stats->sources(10, 1);
        self::assertSame('campaign', $sources['data']['items'][0]['name']);
    }

    public function testPrivacyPolicyRejectsBotsThroughTheContainer(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $metadata = $entityManager->getClassMetadata(\Jenssmit\AnalyticsBundle\Entity\PageView::class);

        (new \Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema([$metadata]);

        $recorder = $container->get(PageViewRecorder::class);

        $botRequest = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'REMOTE_ADDR' => '203.0.113.42',
        ]);
        $dntRequest = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'HTTP_DNT' => '1',
            'REMOTE_ADDR' => '203.0.113.42',
        ]);

        self::assertFalse($recorder->record($botRequest));
        self::assertFalse($recorder->record($dntRequest));
        self::assertSame(0, $container->get(StatsService::class)->summary(1)['data']['views']);
    }
}
