<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use PHPUnit\Framework\TestCase;

final class IpAnonymizerTest extends TestCase
{
    private IpAnonymizer $anonymizer;

    protected function setUp(): void
    {
        $this->anonymizer = new IpAnonymizer();
    }

    /**
     * @dataProvider ipv4Provider
     */
    public function testIpv4IsTruncatedTo24Prefix(string $ip, string $expected): void
    {
        self::assertSame($expected, $this->anonymizer->anonymize($ip));
    }

    public static function ipv4Provider(): \Generator
    {
        yield 'Private Adresse' => ['192.168.178.23', '192.168.178.0'];
        yield 'Öffentliche Adresse' => ['203.0.113.99', '203.0.113.0'];
        yield 'Loopback' => ['127.0.0.1', '127.0.0.0'];
    }

    /**
     * @dataProvider ipv6Provider
     */
    public function testIpv6IsTruncatedTo64Prefix(string $ip, string $expected): void
    {
        self::assertSame($expected, $this->anonymizer->anonymize($ip));
    }

    public static function ipv6Provider(): \Generator
    {
        yield 'Vollständige Adresse' => ['2001:0db8:85a3:08d3:1319:8a2e:0370:7344', '2001:db8:85a3:8d3::'];
        yield 'Loopback' => ['::1', '::'];
        yield 'Bereits gekürzte Adresse' => ['2a02:810d:9840:0000:0000:0000:0000:0042', '2a02:810d:9840::'];
    }

    /**
     * @dataProvider invalidIpProvider
     */
    public function testInvalidInputYieldsUnknown(?string $ip): void
    {
        self::assertSame('unknown', $this->anonymizer->anonymize($ip));
    }

    public static function invalidIpProvider(): \Generator
    {
        yield 'Null' => [null];
        yield 'Leere Zeichenkette' => [''];
        yield 'Keine IP-Adresse' => ['garbage'];
        yield 'Hostnamen' => ['example.com'];
        yield 'Zu viele Oktette' => ['192.168.178.23.1'];
    }

    public function testHashIsStablePerDayAndSalt(): void
    {
        $day = new \DateTimeImmutable('2026-11-07');

        $first = $this->anonymizer->hash('192.168.178.0', $day, 'salt-a');
        $second = $this->anonymizer->hash('192.168.178.0', $day, 'salt-a');

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
    }

    public function testHashChangesWithDaySaltAndPrefix(): void
    {
        $day = new \DateTimeImmutable('2026-11-07');
        $nextDay = new \DateTimeImmutable('2026-11-08');

        $base = $this->anonymizer->hash('192.168.178.0', $day, 'salt-a');

        self::assertNotSame($base, $this->anonymizer->hash('192.168.178.0', $nextDay, 'salt-a'));
        self::assertNotSame($base, $this->anonymizer->hash('192.168.178.0', $day, 'salt-b'));
        self::assertNotSame($base, $this->anonymizer->hash('10.0.0.0', $day, 'salt-a'));
    }
}
