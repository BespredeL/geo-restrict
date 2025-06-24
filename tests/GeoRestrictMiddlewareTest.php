<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GeoRestrictMiddlewareTest extends TestCase
{
    /**
     * Prepare the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        Route::middleware(['geo.restrict'])->get('/test', function () {
            return 'OK';
        });
    }

    /**
     * Test blocked country gets 403 and localized message
     *
     * @return void
     */
    public function test_blocked_country_gets_403_and_localized_message()
    {
        Config::set('geo_restrict.access.rules.deny.country', ['DE']);
        $this->withSession(['geoip' => ['country' => 'DE']]);
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        $response->assertSee(Lang::get('geo_restrict.blocked', [], 'de'));
    }

    /**
     * Test allowed IP passes
     *
     * @return void
     */
    public function test_allowed_ip_passes()
    {
        Config::set('geo_restrict.access.whitelisted_ips', ['1.2.3.4']);
        $response = $this->get('/test', ['X-Forwarded-For' => '1.2.3.4']);
        $response->assertOk();
    }

    /**
     * Test blocked default message if no locale
     *
     * @return void
     */
    public function test_blocked_default_message_if_no_locale()
    {
        Config::set('geo_restrict.access.rules.deny.country', ['ZZ']);
        $this->withSession(['geoip' => ['country' => 'ZZ']]);
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        $response->assertSee('Access denied by geo restriction.');
    }

    /**
     * Test blocked region gets 403
     *
     * @return void
     */
    public function test_blocked_region_gets_403()
    {
        Config::set('geo_restrict.access.rules.deny.region', ['Moscow']);
        $this->withSession(['geoip' => ['region' => 'Moscow']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
    }

    /**
     * Test blocked city gets 403
     *
     * @return void
     */
    public function test_blocked_city_gets_403()
    {
        Config::set('geo_restrict.access.rules.deny.city', ['Berlin']);
        $this->withSession(['geoip' => ['city' => 'Berlin']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
    }

    /**
     * Test blocked ASN gets 403
     *
     * @return void
     */
    public function test_blocked_asn_gets_403()
    {
        Config::set('geo_restrict.access.rules.deny.asn', ['AS12345']);
        $this->withSession(['geoip' => ['asn' => 'AS12345']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
    }

    /**
     * Test time based block
     *
     * @return void
     */
    public function test_time_based_block()
    {
        Config::set('geo_restrict.access.rules.deny.time', [['from' => '00:00', 'to' => '23:59']]);
        $this->withSession(['geoip' => ['country' => 'RU']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
    }

    /**
     * Test callback block
     *
     * @return void
     */
    public function test_callback_block()
    {
        Config::set('geo_restrict.access.rules.deny.callback', function ($geo) {
            return ($geo['city'] ?? null) === 'Paris';
        });
        $this->withSession(['geoip' => ['city' => 'Paris']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
    }

    /**
     * Test allowed country passes
     *
     * @return void
     */
    public function test_allowed_country_passes()
    {
        Config::set('geo_restrict.access.rules.allow.country', ['RU']);
        $this->withSession(['geoip' => ['country' => 'RU']]);
        $response = $this->get('/test');
        $response->assertOk();
    }

    /**
     * Test JSON response type
     *
     * @return void
     */
    public function test_json_response_type()
    {
        Config::set('geo_restrict.block_response.type', 'json');
        Config::set('geo_restrict.access.rules.deny.country', ['DE']);
        $this->withSession(['geoip' => ['country' => 'DE']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => Lang::get('geo_restrict.blocked', [], 'de')]);
    }

    /**
     * Test view response type
     *
     * @return void
     */
    public function test_view_response_type()
    {
        Config::set('geo_restrict.block_response.type', 'view');
        Config::set('geo_restrict.block_response.view', 'errors.geo_blocked');
        Config::set('geo_restrict.access.rules.deny.country', ['DE']);
        $this->withSession(['geoip' => ['country' => 'DE']]);
        $response = $this->get('/test');
        $response->assertStatus(403);
        $response->assertViewIs('errors.geo_blocked');
        $response->assertViewHas('message', Lang::get('geo_restrict.blocked', [], 'de'));
    }
} 