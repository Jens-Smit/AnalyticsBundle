<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use PHPUnit\Framework\TestCase;

final class PathNormalizerTest extends TestCase
{
    private PathNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PathNormalizer();
    }

    /**
     * @dataProvider validPathProvider
     */
    public function testValidPathsAreNormalized(string $path, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($path));
    }

    public static function validPathProvider(): \Generator
    {
        yield 'Wurzel' => ['/', '/'];
        yield 'Einfacher Pfad' => ['/blog', '/blog'];
        yield 'Query wird entfernt' => ['/blog/post?utm_source=mail', '/blog/post'];
        yield 'Fragment wird entfernt' => ['/blog/post#kopfzeile', '/blog/post'];
        yield 'Query und Fragment' => ['/blog/post?a=1#top', '/blog/post'];
        yield 'Token-Parameter wird entfernt' => ['/reset?token=geheim', '/reset'];
        yield 'Steuerzeichen werden entfernt' => ["/a\x00b", '/ab'];
    }

    /**
     * @dataProvider invalidPathProvider
     */
    public function testInvalidPathsAreRejected(?string $path): void
    {
        self::assertNull($this->normalizer->normalize($path));
    }

    public static function invalidPathProvider(): \Generator
    {
        yield 'Null' => [null];
        yield 'Leere Zeichenkette' => [''];
        yield 'Relativer Pfad' => ['blog/post'];
        yield 'Nur Query' => ['?query=1'];
        yield 'Nur Fragment' => ['#fragment'];
        yield 'Zu lang' => ['/'.str_repeat('a', 600)];
    }
}
