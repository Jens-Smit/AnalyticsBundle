<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\UserAgentParser;
use PHPUnit\Framework\TestCase;

final class UserAgentParserTest extends TestCase
{
    private UserAgentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new UserAgentParser();
    }

    /**
     * @dataProvider userAgentProvider
     */
    public function testParsesUserAgent(string $userAgent, ?string $browser, ?string $operatingSystem, ?string $device): void
    {
        $result = $this->parser->parse($userAgent);

        self::assertSame($browser, $result['browser']);
        self::assertSame($operatingSystem, $result['operating_system']);
        self::assertSame($device, $result['device']);
    }

    public static function userAgentProvider(): \Generator
    {
        yield 'Chrome auf Windows' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Chrome',
            'Windows',
            'desktop',
        ];

        yield 'Safari auf iPhone' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            'Safari',
            'iOS',
            'mobile',
        ];

        yield 'Firefox auf Linux' => [
            'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0',
            'Firefox',
            'Linux',
            'desktop',
        ];

        yield 'Edge auf Windows' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
            'Edge',
            'Windows',
            'desktop',
        ];

        yield 'Safari auf iPad' => [
            'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/604.1',
            'Safari',
            'iOS',
            'tablet',
        ];

        yield 'Googlebot' => [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Bot',
            null,
            'bot',
        ];

        yield 'Leere Zeichenkette' => [
            '',
            null,
            null,
            null,
        ];
    }
}
