<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Zentrale Privacy-Policy für alle Erfassungswege: PageViews und
 * Custom Events, Server-Tracking und Collect-Endpunkte.
 *
 * Bot-Ausschluss (exclude_bots), DNT (respect_dnt) und Sec-GPC
 * (respect_gpc) werden ausschließlich hier ausgewertet, damit beide
 * Datentypen identisches Verhalten zeigen und die Optionen nur an
 * einer Stelle konfiguriert sind. Bei Ablehnung wird weder ein
 * PageView noch ein Event gespeichert.
 */
final class PrivacyPolicy
{
    public function __construct(
        private readonly BotDetector $botDetector,
        private readonly bool $excludeBots,
        private readonly bool $respectDnt,
        private readonly bool $respectGpc,
    ) {
    }

    public function allowsRecording(Request $request): bool
    {
        if ($this->excludeBots && $this->botDetector->isBot($request->headers->get('User-Agent'))) {
            return false;
        }

        if ($this->respectDnt && '1' === $request->headers->get('DNT')) {
            return false;
        }

        if ($this->respectGpc && '1' === $request->headers->get('Sec-GPC')) {
            return false;
        }

        return true;
    }
}
