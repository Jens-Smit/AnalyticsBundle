<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Zentrale Zeitquelle des Bundles. Alle gespeicherten Zeitpunkte und
 * Tagesgrenzen (Hash-Tag, Report-Zeiträume, Rate-Limit-Fenster) werden
 * in UTC erzeugt, damit Tagesgrenzen nicht von der Server-Zeitzone
 * abhängen. Eine lokale Report-Zeitzone wäre ein expliziter
 * Report-Parameter, nicht eine globale Einstellung.
 */
final class SystemClock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
    }
}
