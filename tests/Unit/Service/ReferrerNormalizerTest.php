<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use PHPUnit\Framework\TestCase;

final class ReferrerNormalizerTest extends TestCase
{
    private ReferrerNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ReferrerNormalizer();
    }

    /**
     * @dataProvider referrerProvider
     */
    public function testReferrerIsReducedToHost(string $referrer, ?string $expected): void
    {
        self::assertSame($expected, $this->normalizer->toHost($referrer));
    }

    public static function referrerProvider(): \Generator
    {
        yield 'Google-Suche' => ['https://www.google.com/search?q=geheim', 'www.google.com'];
        yield 'Pfad und Fragment' => ['http://example.com/artikel/1#kommentar', 'example.com'];
        yield 'Großbuchstaben' => ['https://DE.Example.COM/', 'de.example.com'];
        yield 'Query-Parameter' => ['https://example.com/reset?token=geheim', 'example.com'];
    }

    /**
     * @dataProvider invalidReferrerProvider
     */
    public function testInvalidReferrerIsNull(?string $referrer): void
    {
        self::assertNull($this->normalizer->toHost($referrer));
    }

    public static function invalidReferrerProvider(): \Generator
    {
        yield 'Null' => [null];
        yield 'Leere Zeichenkette' => [''];
        yield 'Leerzeichen' => ['   '];
        yield 'Keine URL' => ['kein gültiger referrer'];
    }
}
