<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Bespredel\GeoRestrict\Providers\AbstractGeoProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbstractGeoProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geo-restrict.geo_services.provider_timeout', 3);
        Config::set('geo-restrict.geo_services.provider_retries', 0);
        Config::set('geo-restrict.geo_services.provider_retry_delay_ms', 10);
    }

    public function test_get_geo_data_maps_provider_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok'           => true,
                'countryCode'  => 'NL',
                'regionName'   => 'Noord-Holland',
                'cityName'     => 'Amsterdam',
                'asnCode'      => 'AS42',
                'providerName' => 'DemoNet',
            ], 200),
        ]);

        $provider = new DemoProvider();
        $result = $provider->getGeoData('198.51.100.50');

        self::assertSame('NL', $result['country']);
        self::assertSame('Amsterdam', $result['city']);
        self::assertSame('AS42', $result['asn']);
    }

    public function test_get_geo_data_throws_for_invalid_response(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => false, 'message' => 'bad'], 200),
        ]);

        $provider = new DemoProvider();

        $this->expectException(GeoProviderException::class);
        $provider->getGeoData('198.51.100.51');
    }
}

class DemoProvider extends AbstractGeoProvider
{
    protected ?string $baseUrl        = 'https://provider.example/';
    protected ?string $endpoint       = 'geo/:ip';
    protected array   $requiredParams = ['ip'];
    protected array   $optionalParams = ['lang'];
    protected array   $responseMap    = [
        'country' => 'countryCode',
        'region'  => 'regionName',
        'city'    => 'cityName',
        'asn'     => 'asnCode',
        'isp'     => 'providerName',
    ];

    public function getName(): string
    {
        return 'demo-provider';
    }

    protected function isValidResponse(array $data): bool
    {
        return ($data['ok'] ?? false) === true;
    }

    protected function getErrorMessage(array $data): string
    {
        return $data['message'] ?? 'invalid response';
    }
}
