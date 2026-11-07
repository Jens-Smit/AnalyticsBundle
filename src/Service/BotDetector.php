<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Entscheidet anhand des User-Agents, ob ein Aufruf von einem Bot oder
 * Crawler stammt. Die Entscheidung ist von der Browser-Klassifikation
 * getrennt, damit der Ausschluss ein eigener, konfigurierbarer Schritt
 * ist und kein versteckter Seiteneffekt der Klassifikation.
 *
 * Ausnahmen (bot_exceptions) werden zuerst geprüft und heben eine
 * Bot-Erkennung auf, damit bekannte False Positives erlaubt werden
 * können. Zusätzliche Muster (bot_patterns) erweitern die eingebauten
 * Muster. Vollständige User-Agent-Zeichenketten werden nie geloggt.
 */
class BotDetector
{
    private const DEFAULT_PATTERNS = [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'curl\/',
        'wget',
        'monitoring',
        'uptime',
        'headless',
        'facebookexternalhit',
        'python-requests',
        'go-http-client',
        'libwww',
        'java\/',
    ];

    /**
     * @param list<string> $additionalPatterns
     * @param list<string> $exceptions
     */
    public function __construct(
        private readonly array $additionalPatterns = [],
        private readonly array $exceptions = [],
    ) {
    }

    public function isBot(?string $userAgent): bool
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            return false;
        }

        foreach ($this->exceptions as $exception) {
            if ('' !== $exception && 1 === preg_match('/'.$exception.'/i', $userAgent)) {
                return false;
            }
        }

        foreach ([...$this->additionalPatterns, ...self::DEFAULT_PATTERNS] as $pattern) {
            if ('' !== $pattern && 1 === preg_match('/'.$pattern.'/i', $userAgent)) {
                return true;
            }
        }

        return false;
    }
}
