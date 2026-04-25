<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Bespredel\GeoRestrict\Services\GeoCache;
use Bespredel\GeoRestrict\Services\GeoResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GeoResolverProviderFlowTest extends TestCase
{
    public function test_resolve_falls_back_to_next_provider_when_first_fails(): void
    {
        Config::set('geo-restrict.services', [
            FirstFailingProvider::class,
            SecondWorkingProvider::class,
        ]);

        $cache = $this->createMock(GeoCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects(self::once())->method('incrementRateLimitOrFail');
        $cache->expects(self::once())->method('put');

        $resolver = new GeoResolver($cache);
        $result = $resolver->resolve('203.0.113.88');

        self::assertSame('DE', $result['country']);
        self::assertSame('Berlin', $result['city']);
    }

    public function test_resolve_passes_options_to_provider_configuration(): void
    {
        Config::set('geo-restrict.services', [
            [
                'provider' => ConfigurableProvider::class,
                'options'  => [
                    'country' => 'FR',
                ],
            ],
        ]);

        $cache = $this->createMock(GeoCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects(self::once())->method('incrementRateLimitOrFail');
        $cache->expects(self::once())->method('put');

        $resolver = new GeoResolver($cache);
        $result = $resolver->resolve('203.0.113.99');

        self::assertSame('FR', $result['country']);
    }
}

class FirstFailingProvider implements \Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface
{
    public function setOptions(array $options): void
    {
    }

    public function getGeoData(string $ip): ?array
    {
        throw new GeoProviderException('provider failed');
    }

    public function getName(): string
    {
        return 'failing';
    }
}

class SecondWorkingProvider implements \Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface
{
    public function setOptions(array $options): void
    {
    }

    public function getGeoData(string $ip): ?array
    {
        return [
            'country' => 'DE',
            'region'  => 'BE',
            'city'    => 'Berlin',
            'asn'     => 'AS123',
            'isp'     => 'Test ISP',
        ];
    }

    public function getName(): string
    {
        return 'working';
    }
}

class ConfigurableProvider implements \Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface
{
    private array $options = [];

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getGeoData(string $ip): ?array
    {
        return [
            'country' => $this->options['country'] ?? 'US',
            'region'  => null,
            'city'    => null,
            'asn'     => null,
            'isp'     => null,
        ];
    }

    public function getName(): string
    {
        return 'configurable';
    }
}
