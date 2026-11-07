<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Begrenzt Collect-Anfragen je anonymisiertem IP-Präfix auf ein festes
 * Stundenfenster. Der Zähler liegt im Anwendungscache.
 *
 * Der Zähler ist bewusst einfach gehalten: Ein nicht-atomares
 * Inkrementieren ist für diese Schutzfunktion akzeptabel, verteilte
 * Setups sollten einen gemeinsamen Cache verwenden. Der Schwellwert
 * schützt vor automatisiertem Missbrauch, nicht vor gezielten
 * Angriffen, da nicht-browserbasierte Clients jede Herkunft fälschen
 * können.
 */
final class CollectRateLimiter
{
    private const CACHE_PREFIX = 'jenssmit_analytics.rate_limit.';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
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

        $item = $this->cache->getItem($key);
        $count = $item->isHit() ? (int) $item->get() : 0;

        if ($count >= $this->limit) {
            return false;
        }

        $item->set($count + 1);
        $item->expiresAt($now->setTime($window, 59, 59)->modify('+1 second'));
        $this->cache->save($item);

        return true;
    }
}
