<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Normalisiert Pfade vor der Speicherung: nur absolute Pfade, ohne
 * Query-Parameter, ohne Fragmente, ohne Steuerzeichen, begrenzte Länge.
 *
 * Query-Parameter werden bewusst verworfen, weil sie sensible Werte
 * wie Tokens oder E-Mail-Adressen enthalten können.
 */
final class PathNormalizer
{
    private const MAX_LENGTH = 500;

    /**
     * Gibt den normalisierten Pfad zurück oder null, wenn der Pfad
     * ungültig ist und nicht gespeichert werden darf.
     */
    public function normalize(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if ('/' !== $path[0]) {
            return null;
        }

        // Query-String und Fragment abschneiden.
        $path = (string) preg_replace('/[?#].*$/', '', $path);

        // Steuerzeichen entfernen.
        $path = (string) preg_replace('/[\x00-\x1F]/', '', $path);

        if ('' === $path || mb_strlen($path) > self::MAX_LENGTH) {
            return null;
        }

        return $path;
    }
}
