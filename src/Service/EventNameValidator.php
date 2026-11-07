<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Validiert Event-Namen und -Kategorien streng, bevor sie gespeichert
 * werden: keine freien Zeichenketten, keine Markup- oder
 * Steuerzeichen, begrenzte Länge. Ungültige Namen werden verworfen,
 * nicht bereinigt.
 */
final class EventNameValidator
{
    public const MAX_NAME_LENGTH = 80;
    public const MAX_CATEGORY_LENGTH = 64;

    public function isValidName(string $name): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9_.-]{1,80}$/', $name);
    }

    public function isValidCategory(?string $category): bool
    {
        if (null === $category || '' === $category) {
            return true;
        }

        return 1 === preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $category);
    }
}
