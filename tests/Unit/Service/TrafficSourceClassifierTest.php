<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\TrafficSourceClassifier;
use PHPUnit\Framework\TestCase;

final class TrafficSourceClassifierTest extends TestCase
{
    private TrafficSourceClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new TrafficSourceClassifier();
    }

    public function testWithoutReferrerTheSourceIsDirect(): void
    {
        self::assertSame('direct', $this->classifier->classify(null, 'www.example.com'));
        self::assertSame('direct', $this->classifier->classify('', 'www.example.com'));
    }

    public function testOwnHostIsInternal(): void
    {
        self::assertSame('internal', $this->classifier->classify('www.example.com', 'www.example.com'));
        self::assertSame('internal', $this->classifier->classify('WWW.EXAMPLE.COM', 'www.example.com'));
    }

    /**
     * @dataProvider searchProvider
     */
    public function testSearchEnginesAreClassifiedAsSearch(string $host): void
    {
        self::assertSame('search', $this->classifier->classify($host, 'www.example.com'));
    }

    public static function searchProvider(): \Generator
    {
        yield 'Google' => ['www.google.de'];
        yield 'Bing' => ['www.bing.com'];
        yield 'DuckDuckGo' => ['duckduckgo.com'];
        yield 'Ecosia' => ['www.ecosia.org'];
    }

    /**
     * @dataProvider socialProvider
     */
    public function testSocialNetworksAreClassifiedAsSocial(string $host): void
    {
        self::assertSame('social', $this->classifier->classify($host, 'www.example.com'));
    }

    public static function socialProvider(): \Generator
    {
        yield 'Facebook' => ['m.facebook.com'];
        yield 'X' => ['t.co'];
        yield 'LinkedIn' => ['www.linkedin.com'];
        yield 'YouTube' => ['www.youtube.com'];
    }

    public function testOtherHostsAreReferrals(): void
    {
        self::assertSame('referral', $this->classifier->classify('partner.example.org', 'www.example.com'));
    }

    public function testCampaignTakesPrecedence(): void
    {
        self::assertSame('campaign', $this->classifier->classify('www.google.de', 'www.example.com', true));
        self::assertSame('campaign', $this->classifier->classify(null, 'www.example.com', true));
    }
}
