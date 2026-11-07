<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Begrenzt Collect-Anfragen je anonymisiertem IP-Präfix auf ein festes
 * Stundenfenster. Der Zähler liegt im Anwendungscache.
 *
 * Prüfung und Inkrement laufen unter einem exklusiven Lock, sodass
 * parallele Anfragen desselben IP-Präfixes den Zähler nicht
 * überholen können (atomares Rate-Limiting).
 *
 * Für verteilte Setups müssen Cache und Lock-Store gemeinsam genutzt
 * werden, sonst gilt das Limit je Host. Der Schwellwert schützt vor
 * automatisiertem Missbrauch, nicht vor gezielten Angriffen, da
 * nicht-browserbasierte Clients jede Herkunft fälschen können.
 */
class CollectRateLimiter
{
    private const CACHE_PREFIX = 'jenssmit_analytics.rate_limit.';
    private const LOCK_PREFIX = 'jenssmit_analytics.rate_limit.';
    private const LOCK_TTL = 30;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LockFactory $lockFactory,
        private readonly int $limit,
    ) {
    }

    /**
     * Entscheidet, ob die Anfrage innerhalb des Stundenfensters
     * noch zulässig ist, und zählt sie andernfalls hoch.
     */
    public function allow(string $anonymizedIp, \DateTimeImmutable $now): bool
    {
        if ($this->limit <= 0) {
            return true;
        }

        $window = (int) $now->format('H');
        $key = self::CACHE_PREFIX
            .$now->format('Y-m-d')
            .\sprintf('-%02d', $window)
            .'.'
            .hash('sha256', $anonymizedIp);

        $lock = $this->lockFactory->createLock(self::LOCK_PREFIX.hash('sha256', $anonymizedIp), self::LOCK_TTL);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem($key);
            $count = $item->isHit() ? (int) $item->get() : 0;

            if ($count >= $this->limit) {
                return false;
            }

            $item->set($count + 1);
            $item->expiresAt($now->setTime($window, 59, 59)->modify('+1 second'));
            $this->cache->save($item);
        } finally {
            $lock->release();
        }

        return true;
    }
}
