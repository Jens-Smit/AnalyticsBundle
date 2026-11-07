<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Kürzt IP-Adressen vor jeder weiteren Verarbeitung auf Netzpräfixe:
 * IPv4 auf /24, IPv6 auf /64. Ungültige oder fehlende Werte werden
 * kontrolliert als "unknown" behandelt.
 */
final class IpAnonymizer
{
    /**
     * Gibt das gekürzte Netzpräfix oder "unknown" zurück. Die
     * Rückgabe ist bewusst ein String und niemals die rohe Adresse.
     */
    public function anonymize(?string $ip): string
    {
        if (null === $ip || '' === $ip) {
            return 'unknown';
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }

        $binary = inet_pton($ip);

        if (false === $binary) {
            return 'unknown';
        }

        $length = \strlen($binary);

        // IPv4: erste 3 Byte behalten, letztes Byte nullen (/24).
        if (4 === $length) {
            $masked = substr($binary, 0, 3)."\x00";

            return false !== ($prefix = inet_ntop($masked)) ? $prefix : 'unknown';
        }

        // IPv6: erste 8 Byte behalten, restliche 8 Byte nullen (/64).
        if (16 === $length) {
            $masked = substr($binary, 0, 8).str_repeat("\x00", 8);

            return false !== ($prefix = inet_ntop($masked)) ? $prefix : 'unknown';
        }

        return 'unknown';
    }

    /**
     * Bildet das tagesbezogene Pseudonym aus dem gekürzten Präfix, dem
     * Kalendertag (UTC) und dem geheimen Hash-Secret. Das Secret wird als
     * kryptografischer Schlüssel über HMAC-SHA256 verwendet und nicht an
     * die Eingabe angehängt.
     *
     * Wichtige Einordnung: Ein solcher Hash ist pseudonym, nicht anonym.
     * Er ermöglicht Näherungen für wiederkehrende Besucher innerhalb
     * eines Tages, ist aber kein personenbezogenes Profil. Das Secret
     * wird niemals geloggt.
     */
    public function hash(string $anonymizedIp, \DateTimeImmutable $day, string $secret): string
    {
        return hash_hmac('sha256', $anonymizedIp.'|'.$day->format('Y-m-d'), $secret);
    }
}
