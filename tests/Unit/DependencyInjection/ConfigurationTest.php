<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\DependencyInjection;

use Jenssmit\AnalyticsBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalConfig(): array
    {
        return [
            'site_key' => 'blog',
            'hash_secret' => 'unit-test-hash-secret-1234',
        ];
    }

    public function testPrivacyFirstDefaults(): void
    {
        $config = $this->process([$this->minimalConfig()]);

        // Datensparsame Standardkonfiguration.
        self::assertFalse($config['record_user_identifier']);
        self::assertFalse($config['record_event_values']);
        self::assertSame(90, $config['retention_days']);
        self::assertSame(['/analytics'], $config['excluded_paths']);
        self::assertSame('server', $config['page_view_mode']);
        self::assertTrue($config['track_only_successful']);

        // Collect-Schutzstandardwerte.
        self::assertSame(360, $config['collect_rate_limit']);
        self::assertSame(4096, $config['collect_max_request_size']);
        self::assertSame([], $config['allowed_origins']);

        // Bot- und Privacy-Signale sind standardmäßig aktiv, Recording synchron.
        self::assertFalse($config['async_recording']);
        self::assertTrue($config['exclude_bots']);
        self::assertTrue($config['respect_dnt']);
        self::assertTrue($config['respect_gpc']);
        self::assertSame([], $config['bot_patterns']);
        self::assertSame([], $config['bot_exceptions']);

        // Report-Standardwerte.
        self::assertSame(0, $config['report_cache_ttl']);
        self::assertSame(366, $config['max_report_days']);
        self::assertSame([], $config['campaign_allowlist']);
    }

    public function testSiteKeyAndHashSecretAreRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[]]);
    }

    public function testSiteKeyAloneIsNotEnough(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['site_key' => 'blog']]);
    }

    public function testShortHashSecretIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[
            'site_key' => 'blog',
            'hash_secret' => 'kurz',
        ]]);
    }

    public function testSaltIsAcceptedAsDeprecatedAlias(): void
    {
        $config = $this->process([[
            'site_key' => 'blog',
            'salt' => 'unit-test-hash-secret-1234',
        ]]);

        self::assertSame('unit-test-hash-secret-1234', $config['hash_secret']);
    }

    public function testInvalidSiteKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[
            'site_key' => 'ungültiger site key!',
            'hash_secret' => 'unit-test-hash-secret-1234',
        ]]);
    }

    public function testTooLongSiteKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[
            'site_key' => str_repeat('a', 33),
            'hash_secret' => 'unit-test-hash-secret-1234',
        ]]);
    }

    public function testValuesAreValidated(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[
            'site_key' => 'blog',
            'hash_secret' => 'unit-test-hash-secret-1234',
            'retention_days' => 0,
        ]]);
    }

    public function testDisabledServerSideTrackingMapsToManualMode(): void
    {
        $config = $this->process([[
            'site_key' => 'blog',
            'hash_secret' => 'unit-test-hash-secret-1234',
            'server_side_tracking' => false,
        ]]);

        self::assertSame('manual', $config['page_view_mode']);
    }

    public function testCustomValuesAreAccepted(): void
    {
        $config = $this->process([[
            'site_key' => 'restaurant_001',
            'hash_secret' => 'unit-test-hash-secret-1234',
            'retention_days' => 30,
            'record_user_identifier' => true,
            'page_view_mode' => 'client',
            'collect_rate_limit' => 0,
            'collect_max_request_size' => 1024,
            'allowed_origins' => ['https://partner.example'],
            'exclude_bots' => false,
            'respect_dnt' => false,
            'respect_gpc' => false,
            'bot_patterns' => ['mein-crawler'],
            'bot_exceptions' => ['HeadlessChrome'],
            'campaign_allowlist' => ['newsletter'],
            'report_cache_ttl' => 60,
            'max_report_days' => 90,
        ]]);

        self::assertSame('restaurant_001', $config['site_key']);
        self::assertSame(30, $config['retention_days']);
        self::assertTrue($config['record_user_identifier']);
        self::assertSame('client', $config['page_view_mode']);
        self::assertSame(0, $config['collect_rate_limit']);
        self::assertSame(1024, $config['collect_max_request_size']);
        self::assertSame(['https://partner.example'], $config['allowed_origins']);
        self::assertFalse($config['exclude_bots']);
        self::assertFalse($config['respect_dnt']);
        self::assertFalse($config['respect_gpc']);
        self::assertSame(['mein-crawler'], $config['bot_patterns']);
        self::assertSame(['HeadlessChrome'], $config['bot_exceptions']);
        self::assertSame(['newsletter'], $config['campaign_allowlist']);
        self::assertSame(60, $config['report_cache_ttl']);
        self::assertSame(90, $config['max_report_days']);
    }
}
