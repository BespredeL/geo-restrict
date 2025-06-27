<?php

namespace Tests;

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
        Config::set('geo-restrict.access.rules.deny.country', ['DE']);
        $this->setMockGeoData([
            'country' => 'DE',
            'region' => 'Berlin',
            'city' => 'Berlin',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        $response->assertSee(Lang::get('geo-restrict::messages.blocked'));
    }

    /**
     * Test allowed IP passes
     *
     * @return void
     */
    public function test_allowed_ip_passes()
    {
        Config::set('geo-restrict.access.whitelisted_ips', ['1.2.3.4']);
        $response = $this->get('/test', ['X-Forwarded-For' => '1.2.3.4']);
        $response->assertOk();
    }

    /**
     * Test local IP passes
     *
     * @return void
     */
    public function test_local_ip_passes()
    {
        Config::set('geo-restrict.access.rules.deny.country', ['US']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '127.0.0.1']);
        $response->assertOk();
    }

    /**
     * Test blocked default message if no locale
     *
     * @return void
     */
    public function test_blocked_default_message_if_no_locale()
    {
        Config::set('geo-restrict.access.rules.deny.country', ['ZZ']);
        $this->setMockGeoData([
            'country' => 'ZZ',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
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
        Config::set('geo-restrict.access.rules.deny.region', ['Moscow']);
        $this->setMockGeoData([
            'country' => 'RU',
            'region' => 'Moscow',
            'city' => 'Moscow',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test blocked city gets 403
     *
     * @return void
     */
    public function test_blocked_city_gets_403()
    {
        Config::set('geo-restrict.access.rules.deny.city', ['Berlin']);
        $this->setMockGeoData([
            'country' => 'DE',
            'region' => 'Berlin',
            'city' => 'Berlin',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test blocked ASN gets 403
     *
     * @return void
     */
    public function test_blocked_asn_gets_403()
    {
        Config::set('geo-restrict.access.rules.deny.asn', ['AS12345']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test time based block
     *
     * @return void
     */
    public function test_time_based_block()
    {
        Config::set('geo-restrict.access.rules.deny.time', [['from' => '00:00', 'to' => '23:59']]);
        $this->setMockGeoData([
            'country' => 'RU',
            'region' => 'Moscow',
            'city' => 'Moscow',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test callback block
     *
     * @return void
     */
    public function test_callback_block()
    {
        Config::set('geo-restrict.access.rules.deny.callback', function ($geo) {
            return ($geo['city'] ?? null) === 'Paris';
        });
        $this->setMockGeoData([
            'country' => 'FR',
            'region' => 'Île-de-France',
            'city' => 'Paris',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test allowed country passes
     *
     * @return void
     */
    public function test_allowed_country_passes()
    {
        Config::set('geo-restrict.access.rules.allow.country', ['RU']);
        $this->setMockGeoData([
            'country' => 'RU',
            'region' => 'Moscow',
            'city' => 'Moscow',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertOk();
    }

    /**
     * Test allowed country but denied by other rule
     *
     * @return void
     */
    public function test_allowed_country_but_denied_by_other_rule()
    {
        Config::set('geo-restrict.access.rules.allow.country', ['RU']);
        Config::set('geo-restrict.access.rules.deny.city', ['Moscow']);
        $this->setMockGeoData([
            'country' => 'RU',
            'region' => 'Moscow',
            'city' => 'Moscow',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test JSON response type
     *
     * @return void
     */
    public function test_json_response_type()
    {
        Config::set('geo-restrict.block_response.type', 'json');
        Config::set('geo-restrict.access.rules.deny.country', ['DE']);
        $this->setMockGeoData([
            'country' => 'DE',
            'region' => 'Berlin',
            'city' => 'Berlin',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => Lang::get('geo-restrict::messages.blocked')]);
    }

    /**
     * Test view response type
     *
     * @return void
     */
    public function test_view_response_type()
    {
        Config::set('geo-restrict.block_response.type', 'view');
        Config::set('geo-restrict.block_response.view', 'errors.geo_blocked');
        Config::set('geo-restrict.access.rules.deny.country', ['DE']);
        $this->setMockGeoData([
            'country' => 'DE',
            'region' => 'Berlin',
            'city' => 'Berlin',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        $response->assertViewIs('errors.geo_blocked');
        $response->assertViewHas('message', Lang::get('geo-restrict::messages.blocked'));
    }

    /**
     * Test invalid IP gets blocked
     *
     * @return void
     */
    public function test_invalid_ip_gets_blocked()
    {
        $response = $this->get('/test', ['X-Forwarded-For' => 'invalid-ip']);
        $response->assertStatus(403);
    }

    /**
     * Test geo resolution failure gets blocked
     *
     * @return void
     */
    public function test_geo_resolution_failure_gets_blocked()
    {
        Config::set('geo-restrict.access.rules.allow.country', ['RU']);
        $this->setMockGeoData(null); // No geo data
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test route filtering - only specific routes
     *
     * @return void
     */
    public function test_route_filtering_only_specific_routes()
    {
        Config::set('geo-restrict.routes.only', ['admin/*']);
        Config::set('geo-restrict.access.rules.deny.country', ['US']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        // Should be blocked on admin route
        Route::middleware(['geo.restrict'])->get('/admin/test', function () {
            return 'Admin OK';
        });
        
        $response = $this->get('/admin/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        
        // Should pass on regular route
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertOk();
    }

    /**
     * Test route filtering - except specific routes
     *
     * @return void
     */
    public function test_route_filtering_except_specific_routes()
    {
        Config::set('geo-restrict.routes.except', ['public/*']);
        Config::set('geo-restrict.access.rules.deny.country', ['US']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        // Should pass on public route
        Route::middleware(['geo.restrict'])->get('/public/test', function () {
            return 'Public OK';
        });
        
        $response = $this->get('/public/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertOk();
        
        // Should be blocked on regular route
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
    }

    /**
     * Test HTTP method filtering
     *
     * @return void
     */
    public function test_http_method_filtering()
    {
        Config::set('geo-restrict.routes.methods', ['POST']);
        Config::set('geo-restrict.access.rules.deny.country', ['US']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        Route::middleware(['geo.restrict'])->post('/test', function () {
            return 'POST OK';
        });
        
        // Should be blocked on POST
        $response = $this->post('/test', [], ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        
        // Should pass on GET
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertOk();
    }

    /**
     * Test logging configuration
     *
     * @return void
     */
    public function test_logging_configuration()
    {
        Config::set('geo-restrict.logging.blocked_requests', true);
        Config::set('geo-restrict.logging.allowed_requests', true);
        Config::set('geo-restrict.access.rules.deny.country', ['US']);
        $this->setMockGeoData([
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'asn' => 'AS12345',
            'isp' => 'Test ISP'
        ]);
        
        $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
        $response->assertStatus(403);
        
        // Note: In a real test environment, you would assert that logs were written
        // This is just to ensure the middleware doesn't crash with logging enabled
    }
} 