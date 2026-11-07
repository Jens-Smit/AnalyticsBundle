<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class CollectRateLimiterTest extends TestCase
{
    public function testAllowsRequestsUpToTheLimitThenRejects(): void
    {
        $limiter = new CollectRateLimiter(new ArrayAdapter(), self::createLockFactory(), 2);
        $now = new \DateTimeImmutable('now');

        self::assertTrue($limiter->allow('192.168.178.0', $now));
        self::assertTrue($limiter->allow('192.168.178.0', $now));
        self::assertFalse($limiter->allow('192.168.178.0', $now));
    }

    public function testCountsIpPrefixesIndependently(): void
    {
        $limiter = new CollectRateLimiter(new ArrayAdapter(), self::createLockFactory(), 1);
        $now = new \DateTimeImmutable('now');

        self::assertTrue($limiter->allow('192.168.178.0', $now));
        self::assertFalse($limiter->allow('192.168.178.0', $now));
        self::assertTrue($limiter->allow('10.0.0.0', $now));
    }

    public function testZeroLimitDisablesRateLimiting(): void
    {
        $limiter = new CollectRateLimiter(new ArrayAdapter(), self::createLockFactory(), 0);
        $now = new \DateTimeImmutable('now');

        for ($i = 0; $i < 5; ++$i) {
            self::assertTrue($limiter->allow('192.168.178.0', $now));
        }
    }

    public function testNewHourOpensANewWindow(): void
    {
        $limiter = new CollectRateLimiter(new ArrayAdapter(), self::createLockFactory(), 1);
        $now = new \DateTimeImmutable('now');

        self::assertTrue($limiter->allow('192.168.178.0', $now));
        self::assertFalse($limiter->allow('192.168.178.0', $now));

        self::assertTrue($limiter->allow('192.168.178.0', $now->modify('+1 hour')));
    }

    public function testConcurrentRequestsCannotOvertakeTheLimit(): void
    {
        $limiter = new CollectRateLimiter(new ArrayAdapter(), self::createLockFactory(), 3);
        $now = new \DateTimeImmutable('now');

        $results = [];
        for ($i = 0; $i < 5; ++$i) {
            $results[] = $limiter->allow('192.168.178.0', $now);
        }

        self::assertSame([true, true, true, false, false], $results);
    }

    private static function createLockFactory(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }
}
