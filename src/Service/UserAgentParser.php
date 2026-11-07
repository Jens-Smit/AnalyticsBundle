<?php

namespace Jenssmit\AnalyticsBundle\Service;

/**
 * Klassifiziert User-Agents für Browser-, System- und Geräte-Statistiken.
 * Die Bot-Erkennung ist bewusst nicht Teil der Klassifikation: Sie liegt
 * in der eigenen BotDetector-Klasse, damit der Bot-Ausschluss ein
 * getrennter, konfigurierbarer Schritt ist.
 */
final class UserAgentParser
{
    /**
     * @return array{browser: ?string, operating_system: ?string, device: ?string}
     */
    public function parse(?string $userAgent): array
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            return ['browser' => null, 'operating_system' => null, 'device' => null];
        }

        $device = 'desktop';
        if (preg_match('/iPad|Android(?!.*Mobi)|Tablet(?!.*Mobi)/i', $userAgent)) {
            $device = 'tablet';
        } elseif (preg_match('/Mobi|iPhone|Windows Phone/i', $userAgent)) {
            $device = 'mobile';
        }

        $browser = 'Sonstige';
        if (preg_match('/Edg(e|A|iOS)?\//', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/SamsungBrowser\//', $userAgent)) {
            $browser = 'Samsung Internet';
        } elseif (preg_match('/Firefox\//', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome\//', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\//', $userAgent)) {
            $browser = 'Safari';
        }

        $operatingSystem = 'Sonstige';
        if (preg_match('/Windows/', $userAgent)) {
            $operatingSystem = 'Windows';
        } elseif (preg_match('/iPhone|iPad|iPod/', $userAgent)) {
            $operatingSystem = 'iOS';
        } elseif (preg_match('/Mac OS X|Macintosh/', $userAgent)) {
            $operatingSystem = 'macOS';
        } elseif (preg_match('/Android/', $userAgent)) {
            $operatingSystem = 'Android';
        } elseif (preg_match('/Linux|X11/', $userAgent)) {
            $operatingSystem = 'Linux';
        }

        return [
            'browser' => $browser,
            'operating_system' => $operatingSystem,
            'device' => $device,
        ];
    }
}
