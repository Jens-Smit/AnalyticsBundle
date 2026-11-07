<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\BotDetector;
use PHPUnit\Framework\TestCase;

final class BotDetectorTest extends TestCase
{
    /**
     * @dataProvider botProvider
     */
    public function testDetectsKnownBots(string $userAgent): void
    {
        self::assertTrue((new BotDetector())->isBot($userAgent));
    }

    public static function botProvider(): \Generator
    {
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
        yield 'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'];
        yield 'cURL' => ['curl/8.5.0'];
        yield 'Wget' => ['Wget/1.21.4 (linux-gnu)'];
        yield 'Headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36'];
        yield 'Monitoring' => ['Mozilla/5.0 (compatible; Uptimebot/1.0)'];
    }

    /**
     * @dataProvider regularBrowserProvider
     */
    public function testDoesNotFlagRegularBrowsers(string $userAgent): void
    {
        self::assertFalse((new BotDetector())->isBot($userAgent));
    }

    public static function regularBrowserProvider(): \Generator
    {
        yield 'Chrome auf Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'];
        yield 'Firefox auf Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0'];
        yield 'Safari auf iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'];
        yield 'Edge auf Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0'];
        yield 'Samsung Browser' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36'];
    }

    public function testEmptyUserAgentIsNotABot(): void
    {
        self::assertFalse((new BotDetector())->isBot(null));
        self::assertFalse((new BotDetector())->isBot(''));
        self::assertFalse((new BotDetector())->isBot('   '));
    }

    public function testAdditionalPatternsExtendTheDefaults(): void
    {
        $detector = new BotDetector(['mein-eigener-crawler']);

        self::assertTrue($detector->isBot('Mein-Eigener-Crawler/1.0'));
        self::assertTrue($detector->isBot('Googlebot/2.1'));
    }

    public function testExceptionsOverrideBotDetection(): void
    {
        $detector = new BotDetector([], ['HeadlessChrome']);

        self::assertFalse($detector->isBot('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36'));
        self::assertTrue($detector->isBot('curl/8.5.0'));
    }
}
