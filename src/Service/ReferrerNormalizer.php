<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Reduziert Referrer auf den Hostnamen. Vollständige Referrer-URLs
 * enthalten häufig Suchbegriffe, Tokens oder andere sensible
 * Query-Parameter und werden deshalb nicht gespeichert.
 */
final class ReferrerNormalizer
{
    private const MAX_LENGTH = 255;

    /**
     * Gibt den kleingeschriebenen Host zurück oder null, wenn der
     * Referrer nicht ausgewertet werden kann.
     */
    public function toHost(?string $referrer): ?string
    {
        if (null === $referrer || '' === trim($referrer)) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (!\is_string($host) || '' === $host) {
            return null;
        }

        $host = strtolower($host);

        if (mb_strlen($host) > self::MAX_LENGTH) {
            $host = mb_substr($host, 0, self::MAX_LENGTH);
        }

        return $host;
    }
}
