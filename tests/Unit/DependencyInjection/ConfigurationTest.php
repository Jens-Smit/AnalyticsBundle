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
            'salt' => 'test-salt',
        ];
    }

    public function testPrivacyFirstDefaults(): void
    {
        $config = $this->process([$this->minimalConfig()]);

        // Datensparsame Standardkonfiguration.
        self::assertFalse($config['record_user_identifier']);
        self::assertSame(90, $config['retention_days']);
        self::assertSame(['/analytics'], $config['excluded_paths']);
        self::assertTrue($config['server_side_tracking']);
        self::assertTrue($config['track_only_successful']);

        // Collect-Schutzstandardwerte.
        self::assertSame(360, $config['collect_rate_limit']);
        self::assertSame(4096, $config['collect_max_request_size']);
        self::assertSame([], $config['allowed_origins']);
    }

    public function testSiteKeyAndSaltAreRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[]]);
    }

    public function testSiteKeyAloneIsNotEnough(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['site_key' => 'blog']]);
    }

    public function testValuesAreValidated(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([[
            'site_key' => 'blog',
            'salt' => 'test-salt',
            'retention_days' => 0,
        ]]);
    }

    public function testCustomValuesAreAccepted(): void
    {
        $config = $this->process([[
            'site_key' => 'restaurant_001',
            'salt' => 'test-salt',
            'retention_days' => 30,
            'record_user_identifier' => true,
            'collect_rate_limit' => 0,
            'collect_max_request_size' => 1024,
            'allowed_origins' => ['https://partner.example'],
        ]]);

        self::assertSame('restaurant_001', $config['site_key']);
        self::assertSame(30, $config['retention_days']);
        self::assertTrue($config['record_user_identifier']);
        self::assertSame(0, $config['collect_rate_limit']);
        self::assertSame(1024, $config['collect_max_request_size']);
        self::assertSame(['https://partner.example'], $config['allowed_origins']);
    }
}
