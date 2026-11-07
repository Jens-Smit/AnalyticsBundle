<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Klassifiziert die Traffic-Quelle eines Seitenaufrufs anhand des
 * Referrer-Hosts, des eigenen Hosts und optionaler Kampagnen-Parameter.
 *
 * Werte: direct, search, social, internal, referral, campaign.
 * Es wird ausschließlich der Hostname ausgewertet, niemals eine
 * vollständige Referrer-URL oder ein Query-String.
 */
final class TrafficSourceClassifier
{
    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_SEARCH = 'search';
    public const SOURCE_SOCIAL = 'social';
    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_CAMPAIGN = 'campaign';

    private const SEARCH_HOST_PATTERNS = [
        'google\.',
        'bing\.com',
        'duckduckgo\.com',
        'ecosia\.org',
        'startpage\.com',
        'search\.yahoo\.com',
        'yahoo\.com',
        'baidu\.com',
        'yandex\.',
        't-online\.de',
        'web\.de',
        'ask\.com',
    ];

    private const SOCIAL_HOST_PATTERNS = [
        'facebook\.com',
        'instagram\.com',
        't\.co',
        'twitter\.com',
        'x\.com',
        'linkedin\.com',
        'pinterest\.',
        'youtube\.com',
        'youtu\.be',
        'tiktok\.com',
        'reddit\.com',
        'whatsapp\.com',
        'telegram\.org',
        'mastodon\.',
    ];

    /**
     * Kampagnen-Parameter (utm_source) haben Vorrang vor der
     * Referrer-Klassifikation, danach folgen Referrer-Host und eigener
     * Host. Ohne Referrer und ohne Kampagnen-Parameter gilt der Aufruf
     * als direkt.
     */
    public function classify(?string $referrerHost, ?string $ownHost, bool $hasCampaign = false): string
    {
        if ($hasCampaign) {
            return self::SOURCE_CAMPAIGN;
        }

        if (null === $referrerHost || '' === $referrerHost) {
            return self::SOURCE_DIRECT;
        }

        if (null !== $ownHost && strtolower($ownHost) === strtolower($referrerHost)) {
            return self::SOURCE_INTERNAL;
        }

        if ($this->matchesAny($referrerHost, self::SEARCH_HOST_PATTERNS)) {
            return self::SOURCE_SEARCH;
        }

        if ($this->matchesAny($referrerHost, self::SOCIAL_HOST_PATTERNS)) {
            return self::SOURCE_SOCIAL;
        }

        return self::SOURCE_REFERRAL;
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (1 === preg_match('/'.$pattern.'/i', $host)) {
                return true;
            }
        }

        return false;
    }
}
