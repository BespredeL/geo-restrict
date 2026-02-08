<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bespredel\GeoRestrict\Exceptions\GeoRateLimitException;
use Bespredel\GeoRestrict\Services\GeoCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GeoCacheTest extends TestCase
{
    private GeoCache $geoCache;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geo-restrict.geo_services.cache_ttl', 60);
        Config::set('geo-restrict.geo_services.rate_limit', 3);
        $this->geoCache = new GeoCache();
    }

    public function test_get_returns_null_when_not_cached(): void
    {
        self::assertNull($this->geoCache->get('192.168.1.1'));
    }

    public function test_put_and_get_store_and_retrieve_geo_data(): void
    {
        $data = [
            'country' => 'RU',
            'region' => 'MOW',
            'city' => 'Moscow',
            'asn' => null,
            'isp' => null,
        ];

        $this->geoCache->put('10.0.0.1', $data);

        self::assertSame($data, $this->geoCache->get('10.0.0.1'));
    }

    public function test_is_rate_limited_returns_false_until_limit_reached(): void
    {
        $ip = '203.0.113.1';

        self::assertFalse($this->geoCache->isRateLimited($ip));
        self::assertFalse($this->geoCache->isRateLimited($ip));
        self::assertFalse($this->geoCache->isRateLimited($ip));

        $this->expectException(GeoRateLimitException::class);
        $this->geoCache->isRateLimited($ip);
    }

    public function test_cache_ttl_zero_prevents_storing(): void
    {
        Config::set('geo-restrict.geo_services.cache_ttl', 0);

        $geoCache = new GeoCache();
        $data = ['country' => 'RU', 'region' => null, 'city' => null, 'asn' => null, 'isp' => null];
        $geoCache->put('10.0.0.2', $data);

        self::assertNull($geoCache->get('10.0.0.2'));
    }

    public function test_clear_all_geo_cache_does_not_throw_with_array_driver(): void
    {
        $this->geoCache->put('10.0.0.3', [
            'country' => 'DE',
            'region' => null,
            'city' => null,
            'asn' => null,
            'isp' => null,
        ]);

        $this->geoCache->clearAllGeoCache();

        // With array driver, tags are not used, so flush is no-op; get may still return if driver doesn't support tags
        // We only assert no exception
        $this->addToAssertionCount(1);
    }
}
