<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Normalisiert optionale Event-Werte vor der Speicherung: Markup- und
 * Steuerzeichen werden entfernt, die Länge begrenzt. Event-Werte sind
 * standardmäßig deaktiviert und müssen explizit über
 * record_event_values aktiviert werden; ungeprüfte Freitext-Werte
 * werden nicht gespeichert.
 */
final class EventValueNormalizer
{
    private const MAX_LENGTH = 100;

    public function normalize(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }

        $normalized = strip_tags((string) $value);
        $normalized = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $normalized);
        $normalized = trim($normalized);

        if ('' === $normalized) {
            return null;
        }

        return mb_substr($normalized, 0, self::MAX_LENGTH);
    }
}
