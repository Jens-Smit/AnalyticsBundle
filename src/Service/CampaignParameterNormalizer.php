<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Normalisiert und validiert Kampagnen-Parameter vor der Speicherung.
 *
 * Gespeichert werden ausschließlich utm_source, utm_medium und
 * utm_campaign. utm_term und utm_content werden bewusst nicht
 * gespeichert. Werte werden gekürzt und von Steuer- und
 * Markup-Zeichen befreit.
 *
 * Die Allowlist gilt ausschließlich für utm_source: Sie beschreibt
 * zulässige Verkehrsquellen. utm_medium und utm_campaign werden
 * unabhängig davon nur normalisiert und gekürzt, damit zulässige
 * Medium- und Kampagnenwerte nicht an einer Quellen-Allowlist
 * scheitern. Ungültige Werte werden verworfen, nicht gespeichert.
 */
final class CampaignParameterNormalizer
{
    private const MAX_SOURCE_LENGTH = 100;
    private const MAX_MEDIUM_LENGTH = 50;
    private const MAX_CAMPAIGN_LENGTH = 100;

    /**
     * @param list<string> $allowlist Erlaubte utm_source-Werte; leer bedeutet keine Allowlist-Prüfung
     */
    public function __construct(
        private readonly array $allowlist = [],
    ) {
    }

    /**
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    /**
     * @param array<string, mixed>|null $campaign
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    public function normalize(?array $campaign): array
    {
        return [
            'utm_source' => $this->normalizeValue($campaign['utm_source'] ?? null, self::MAX_SOURCE_LENGTH, true),
            'utm_medium' => $this->normalizeValue($campaign['utm_medium'] ?? null, self::MAX_MEDIUM_LENGTH),
            'utm_campaign' => $this->normalizeValue($campaign['utm_campaign'] ?? null, self::MAX_CAMPAIGN_LENGTH),
        ];
    }

    private function normalizeValue(mixed $value, int $maxLength, bool $applyAllowlist = false): ?string
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = strip_tags($value);
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        $value = mb_substr($value, 0, $maxLength);

        if ($applyAllowlist && [] !== $this->allowlist && !\in_array($value, $this->allowlist, true)) {
            return null;
        }

        return $value;
    }
}
