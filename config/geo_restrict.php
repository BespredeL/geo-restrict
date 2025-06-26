<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GeoIP Services
    |--------------------------------------------------------------------------
    |
    | A prioritized list of GeoIP services to detect location by IP address.
    | If a service is unavailable or fails, the next one will be used.
    |
    */
    'services'       => [
        [
            'name' => 'ipwho.is',
            'url'  => 'https://ipwho.is/:ip',
            'map'  => [
                'country' => 'country_code',
                'region'  => 'region',
                'city'    => 'city',
                'asn'     => 'connection.asn',
                'isp'     => 'connection.isp',
            ],
        ],

        [
            'name' => 'ip-api.com',
            'url'  => 'http://ip-api.com/json/:ip',
            'map'  => [
                'country' => 'countryCode',
                'region'  => 'region',
                'city'    => 'city',
                'asn'     => 'as',
                'isp'     => 'isp',
            ],
        ],

        [
            'name' => 'ipapi.co',
            'url'  => 'https://ipapi.co/:ip/json/',
            'map'  => [
                'country' => 'country_code',
                'region'  => 'region_code',
                'city'    => 'city',
                'asn'     => 'asn',
                'isp'     => 'org',
            ],
        ],

        // Add more services here; order defines priority.
    ],

    /*
    |--------------------------------------------------------------------------
    | Geo: Caching & Rate Limiting
    |--------------------------------------------------------------------------
    |
    | cache_ttl   - Duration in minutes to cache IP geo data (0 = disabled)
    | rate_limit  - Max number of requests per IP per minute
    |
    */
    'geo'            => [
        'cache_ttl'  => 1440,
        'rate_limit' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access: Whitelist & Rule-Based Restrictions
    |--------------------------------------------------------------------------
    |
    | whitelisted_ips - Always allowed IP addresses (local IPs are allowed by default)
    |
    | Rules:
    | - 'allow' and 'deny' sections define access restrictions by geo fields.
    | - country, region, city, asn - ISO/codes to allow/deny access.
    | - callback - A custom function: function($geo) { return true/false; }
    | - time - Time-based restrictions (array of periods): ['from' => '22:00', 'to' => '06:00']
    |
    */
    'access'         => [
        'whitelisted_ips' => [],
        'rules'           => [
            'allow' => [
                'country'  => ['RU'],
                'region'   => [],
                'city'     => [],
                'asn'      => [],
                'callback' => null,
            ],
            'deny'  => [
                'country'  => [],
                'region'   => [],
                'asn'      => [],
                'callback' => null,
                'time'     => [
                    // ['from' => '22:00', 'to' => '06:00']
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | blocked_requests - Enable logging of blocked IP requests
    | allowed_requests - Enable logging of allowed IP requests
    |
    */
    'logging'        => [
        'blocked_requests' => true,
        'allowed_requests' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Response
    |--------------------------------------------------------------------------
    |
    | type  - Response type when access is denied: 'abort', 'json', or 'view'
    | view  - Blade view to return if type = 'view'
    | json  - Default message for JSON responses
    |
    */
    'block_response' => [
        'type' => 'abort',
        'view' => 'errors.403',
        'json' => [
            'message' => null, // By default, taken from the language file
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Filter
    |--------------------------------------------------------------------------
    |
    | only    - Apply restriction only to these routes (wildcards allowed, e.g., 'admin/*')
    | except  - Exclude these routes from restriction (wildcards allowed)
    | methods - Apply restriction only to specified HTTP methods (e.g., ['GET', 'POST'])
    |
    */
    'routes'         => [
        'only'    => [],
        'except'  => [],
        'methods' => [],
    ],
];