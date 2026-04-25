<?php

declare(strict_types=1);

namespace Tests\Feature;

use Bespredel\GeoRestrict\Services\GeoResolver;
use Bespredel\GeoRestrict\Middleware\RestrictAccessByGeo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('geo-restrict.routes.only', []);
        Config::set('geo-restrict.routes.except', []);
        Config::set('geo-restrict.routes.methods', []);
        Config::set('geo-restrict.excluded_networks', ['127.0.0.1', '::1']);
        Config::set('geo-restrict.access.rules', [
            'allow' => [
                'country'  => ['RU'],
                'region'   => [],
                'city'     => [],
                'asn'      => [],
                'callback' => null,
                'time'     => [],
            ],
            'deny'  => [
                'country'  => [],
                'region'   => [],
                'city'     => [],
                'asn'      => [],
                'callback' => null,
                'time'     => [],
            ],
        ]);
        Config::set('geo-restrict.block_response.type', 'json');
        Config::set('geo-restrict.block_response.json', ['message' => 'Access denied.']);
        Config::set('geo-restrict.logging.blocked_requests', false);
        Config::set('geo-restrict.logging.allowed_requests', false);

        Route::get('/test-geo', static fn() => response('ok', 200))
            ->middleware(RestrictAccessByGeo::class);
        Route::post('/test-geo', static fn() => response('ok-post', 200))
            ->middleware(RestrictAccessByGeo::class);
        Route::get('/public-geo', static fn() => response('public-ok', 200))
            ->middleware(RestrictAccessByGeo::class);
    }

    public function test_excluded_ip_bypasses_restriction(): void
    {
        $this->serverVariables = ['REMOTE_ADDR' => '127.0.0.1'];

        $response = $this->get('/test-geo');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_blocked_country_returns_403_json(): void
    {
        $resolver = \Mockery::mock(GeoResolver::class);
        $resolver->shouldReceive('resolve')
            ->with('203.0.113.100')
            ->andReturn([
                'country' => 'US',
                'region'  => null,
                'city'    => null,
                'asn'     => null,
                'isp'     => null,
            ]);
        $this->app->instance(GeoResolver::class, $resolver);

        $this->serverVariables = ['REMOTE_ADDR' => '203.0.113.100'];
        $response = $this->get('/test-geo');

        $response->assertStatus(403);
        $response->assertJsonStructure(['message']);
    }

    public function test_allowed_country_returns_200(): void
    {
        $resolver = \Mockery::mock(GeoResolver::class);
        $resolver->shouldReceive('resolve')
            ->with('203.0.113.101')
            ->andReturn([
                'country' => 'RU',
                'region'  => null,
                'city'    => null,
                'asn'     => null,
                'isp'     => null,
            ]);
        $this->app->instance(GeoResolver::class, $resolver);

        $this->serverVariables = ['REMOTE_ADDR' => '203.0.113.101'];
        $response = $this->get('/test-geo');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_geo_resolve_failure_returns_403(): void
    {
        $resolver = \Mockery::mock(GeoResolver::class);
        $resolver->shouldReceive('resolve')
            ->with('203.0.113.102')
            ->andReturn(null);
        $this->app->instance(GeoResolver::class, $resolver);

        $this->serverVariables = ['REMOTE_ADDR' => '203.0.113.102'];
        $response = $this->get('/test-geo');

        $response->assertStatus(403);
    }

    public function test_except_route_skips_restriction(): void
    {
        Config::set('geo-restrict.routes.except', ['public-geo']);
        $this->serverVariables = ['REMOTE_ADDR' => 'bad-ip'];

        $response = $this->get('/public-geo');

        $response->assertOk();
        $response->assertSee('public-ok');
    }

    public function test_methods_filter_skips_non_matching_method(): void
    {
        Config::set('geo-restrict.routes.methods', ['POST']);
        $this->serverVariables = ['REMOTE_ADDR' => 'bad-ip'];

        $response = $this->get('/test-geo');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_invalid_ip_returns_403_when_restriction_applies(): void
    {
        Config::set('geo-restrict.routes.only', ['test-geo']);
        $this->serverVariables = ['REMOTE_ADDR' => 'bad-ip'];

        $response = $this->get('/test-geo');

        $response->assertStatus(403);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
