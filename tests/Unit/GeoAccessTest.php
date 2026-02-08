<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bespredel\GeoRestrict\Services\GeoAccess;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GeoAccessTest extends TestCase
{
    private GeoAccess $geoAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geoAccess = new GeoAccess();
    }

    public function test_is_excluded_ip_returns_true_for_exact_ip_in_list(): void
    {
        Config::set('geo-restrict.excluded_networks', ['192.168.1.100', '10.0.0.1']);

        self::assertTrue($this->geoAccess->isExcludedIp('192.168.1.100'));
        self::assertTrue($this->geoAccess->isExcludedIp('10.0.0.1'));
    }

    public function test_is_excluded_ip_returns_false_when_ip_not_in_list(): void
    {
        Config::set('geo-restrict.excluded_networks', ['192.168.1.100']);

        self::assertFalse($this->geoAccess->isExcludedIp('192.168.1.101'));
        self::assertFalse($this->geoAccess->isExcludedIp('8.8.8.8'));
    }

    public function test_is_excluded_ip_returns_true_for_ip_in_cidr(): void
    {
        Config::set('geo-restrict.excluded_networks', ['192.168.0.0/24', '10.0.0.0/8']);

        self::assertTrue($this->geoAccess->isExcludedIp('192.168.0.1'));
        self::assertTrue($this->geoAccess->isExcludedIp('192.168.0.255'));
        self::assertTrue($this->geoAccess->isExcludedIp('10.1.2.3'));
        self::assertFalse($this->geoAccess->isExcludedIp('192.168.1.1'));
    }

    public function test_is_excluded_ip_falls_back_to_legacy_config_keys(): void
    {
        Config::set('geo-restrict.excluded_networks', []);
        Config::set('geo-restrict.local_networks', []);
        Config::set('geo-restrict.access.whitelisted_ips', ['127.0.0.1']);

        self::assertTrue($this->geoAccess->isExcludedIp('127.0.0.1'));
    }

    public function test_passes_rules_returns_true_when_country_in_allow_list(): void
    {
        Config::set('geo-restrict.access.rules', [
            'allow' => [
                'country' => ['RU', 'DE'],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
            'deny' => [
                'country' => [],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
        ]);

        self::assertTrue($this->geoAccess->passesRules([
            'country' => 'RU',
            'region' => 'MOW',
            'city' => 'Moscow',
            'asn' => null,
            'isp' => null,
        ]));
    }

    public function test_passes_rules_returns_block_info_when_country_not_in_allow_list(): void
    {
        Config::set('geo-restrict.access.rules', [
            'allow' => [
                'country' => ['RU'],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
            'deny' => [
                'country' => [],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
        ]);

        $result = $this->geoAccess->passesRules([
            'country' => 'US',
            'region' => null,
            'city' => null,
            'asn' => null,
            'isp' => null,
        ]);

        self::assertIsArray($result);
        self::assertSame('country', $result['field']);
        self::assertSame('US', $result['value']);
    }

    public function test_passes_rules_returns_block_info_when_country_in_deny_list(): void
    {
        Config::set('geo-restrict.access.rules', [
            'allow' => [
                'country' => [],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
            'deny' => [
                'country' => ['US'],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
        ]);

        $result = $this->geoAccess->passesRules([
            'country' => 'US',
            'region' => null,
            'city' => null,
            'asn' => null,
            'isp' => null,
        ]);

        self::assertIsArray($result);
        self::assertSame('country', $result['field']);
    }

    public function test_passes_rules_allow_callback_must_return_true(): void
    {
        Config::set('geo-restrict.access.rules', [
            'allow' => [
                'country' => [],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => static function (array $geo): bool {
                    return ($geo['country'] ?? '') === 'RU';
                },
                'time' => [],
            ],
            'deny' => [
                'country' => [],
                'region' => [],
                'city' => [],
                'asn' => [],
                'callback' => null,
                'time' => [],
            ],
        ]);

        self::assertTrue($this->geoAccess->passesRules([
            'country' => 'RU',
            'region' => null,
            'city' => null,
            'asn' => null,
            'isp' => null,
        ]));

        $result = $this->geoAccess->passesRules([
            'country' => 'US',
            'region' => null,
            'city' => null,
            'asn' => null,
            'isp' => null,
        ]);
        self::assertIsArray($result);
        self::assertSame('callback_allow', $result['reason']);
    }

    public function test_deny_response_returns_json_when_configured(): void
    {
        Config::set('geo-restrict.block_response.type', 'json');
        Config::set('geo-restrict.block_response.json', ['message' => 'Custom denied.']);

        $response = $this->geoAccess->denyResponse('US', ['reason' => 'country']);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('message', $content);
        self::assertNotEmpty($content['message']);
    }
}
