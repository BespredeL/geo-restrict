<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bespredel\GeoRestrict\Services\GeoCache;
use Bespredel\GeoRestrict\Services\GeoResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoResolverTest extends TestCase
{
    public function test_resolve_returns_cached_data_when_available(): void
    {
        $cached = [
            'country' => 'RU',
            'region' => 'MOW',
            'city' => 'Moscow',
            'asn' => null,
            'isp' => null,
        ];

        $cache = $this->createMock(GeoCache::class);
        $cache->method('get')->with('10.0.0.5')->willReturn($cached);
        $cache->method('isRateLimited')->willReturn(false);
        $cache->expects(self::never())->method('put');

        Config::set('geo-restrict.services', []);

        $resolver = new GeoResolver($cache);

        self::assertSame($cached, $resolver->resolve('10.0.0.5'));
    }

    public function test_resolve_returns_null_when_no_services_configured(): void
    {
        $cache = $this->createMock(GeoCache::class);
        $cache->method('isRateLimited')->willReturn(false);
        $cache->method('get')->willReturn(null);

        Config::set('geo-restrict.services', []);

        $resolver = new GeoResolver($cache);

        self::assertNull($resolver->resolve('8.8.8.8'));
    }

    public function test_resolve_returns_normalized_data_from_array_service_via_http_fake(): void
    {
        Http::fake([
            '*' => Http::response([
                'country_code' => 'DE',
                'region' => 'Berlin',
                'city' => 'Berlin',
                'asn' => 12345,
                'org' => 'ISP GmbH',
            ], 200),
        ]);

        Config::set('geo-restrict.services', [
            [
                'name' => 'fake',
                'url' => 'https://example.com/:ip',
                'headers' => ['Accept' => 'application/json'],
                'map' => [
                    'country' => 'country_code',
                    'region' => 'region',
                    'city' => 'city',
                    'asn' => 'asn',
                    'isp' => 'org',
                ],
            ],
        ]);
        Config::set('geo-restrict.geo_services.cache_ttl', 60);
        Config::set('geo-restrict.geo_services.rate_limit', 30);

        $cache = new GeoCache();
        $resolver = new GeoResolver($cache);

        $result = $resolver->resolve('203.0.113.10');

        self::assertNotNull($result);
        self::assertSame('DE', $result['country']);
        self::assertSame('Berlin', $result['region']);
        self::assertSame('Berlin', $result['city']);
        self::assertSame(12345, $result['asn']);
        self::assertSame('ISP GmbH', $result['isp']);
    }

    public function test_resolve_returns_null_when_array_service_returns_invalid_response(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'not found'], 404),
        ]);

        Config::set('geo-restrict.services', [
            [
                'name' => 'fake',
                'url' => 'https://example.com/:ip',
                'map' => [
                    'country' => 'country_code',
                    'region' => 'region',
                    'city' => 'city',
                    'asn' => 'asn',
                    'isp' => 'isp',
                ],
            ],
        ]);

        $cache = new GeoCache();
        $resolver = new GeoResolver($cache);

        self::assertNull($resolver->resolve('203.0.113.20'));
    }
}
