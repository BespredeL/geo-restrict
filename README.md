# GeoRestrict Middleware for Laravel

[![Readme EN](https://img.shields.io/badge/README-EN-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README.md)
[![Readme RU](https://img.shields.io/badge/README-RU-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README_RU.md)
[![GitHub license](https://img.shields.io/badge/license-MIT-458a7b.svg)](https://github.com/bespredel/GeoRestrict/blob/master/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/bespredel/geo-restrict.svg)](https://packagist.org/packages/bespredel/geo-restrict)

[![Latest Version](https://img.shields.io/github/v/release/bespredel/GeoRestrict?logo=github)](https://github.com/bespredel/GeoRestrict/releases)
[![Latest Version Packagist](https://img.shields.io/packagist/v/bespredel/geo-restrict.svg?logo=packagist&logoColor=white&color=F28D1A)](https://packagist.org/packages/bespredel/geo-restrict)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/bespredel/geo-restrict.svg?logo=php&logoColor=white&color=777BB4)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10-FF2D20?logo=laravel)](https://laravel.com)

GeoRestrict is a Laravel middleware that restricts access to your application based on the user's IP geolocation. It supports multiple IP lookup
services, complex filtering rules, localization, logging, and custom response types.

---

## Features

- IP-based filtering by country, region, ASN, city, ISP
- Supports multiple GeoIP services (priority based on array order)
- Geo response caching (can be disabled)
- Rate limiting for geo service requests
- Flexible allow/deny rules, including callbacks and time-based restrictions
- IP whitelist
- Route targeting via patterns and HTTP methods
- Localized error messages
- Different responses per country/rule
- Logging for blocked and allowed requests

## Installation

1. Install the package:

```bash
composer require bespredel/georestrict
```

2. Publish the configuration:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-config
```

## Example config `config/geo_restrict.php`

```php
return [
    'services' => [
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
        // Add more services; priority is based on order
    ],
    'geo' => [
        'cache_ttl'  => 1440, // In minutes, 0 = disabled
        'rate_limit' => 30,   // Requests per minute per IP
    ],
    'access' => [
        'whitelisted_ips' => ['127.0.0.1'],
        'rules' => [
            'allow' => [
                'country'  => ['RU'],
                'region'   => [],
                'city'     => [],
                'asn'      => [],
                'callback' => null, // function($geo) { return ...; }
            ],
            'deny'  => [
                'country'  => [],
                'region'   => [],
                'asn'      => [],
                'callback' => null, // function($geo) { return ...; }
                'time'     => [
                    // ['from' => '22:00', 'to' => '06:00']
                ],
            ],
        ],
    ],
    'logging' => [
        'blocked_requests' => true,
        'allowed_requests' => false,
    ],
    'block_response' => [
        'type'  => 'abort', // 'abort', 'json', 'view'
        'view'  => 'errors.geo_blocked',
        'json'  => [
            'message' => 'Access denied: your region is restricted.',
        ],
    ],
    'routes' => [
        'only'    => [], // ['admin/*', 'api/v1/*']
        'except'  => [],
        'methods' => [], // ['GET', 'POST']
    ],
];
```

### Key parameters explained

- **services** — list of GeoIP providers used to resolve location by IP. Priority based on order.
- **geo.cache_ttl** — cache lifetime in minutes (0 disables caching).
- **geo.rate_limit** — max requests per minute per IP to geo services.
- **access.whitelisted_ips** — IPs that are always allowed (e.g., localhost).
- **access.rules.allow/deny** — allow/deny rules by country, region, ASN, callbacks and time periods.
- **logging** — enable logging of blocked or allowed requests.
- **block_response.type** — response type: 'abort', 'json', or 'view'.
- **routes.only/except/methods** — route and method matching.

## Usage

1. Add the middleware to routes:

```php
Route::middleware(['geo.restrict'])->group(function () {
    // ...
});
```

2. Or apply directly:

```php
Route::get('/secret', 'SecretController@index')->middleware('geo.restrict');
```

## Customization

- Add custom geo services in the `services` array.
- Use allow/deny rules for flexible filtering.
- Use callback functions for complex logic.
- Add language files for localized block messages.

## Testing

The package is covered by tests for:

- Country, region, city, ASN blocking
- Time-based deny restrictions
- Custom callback functions
- Allowed IPs and countries
- Response types: JSON, view, abort
- Message localization

Tests are located in the `tests/` directory.

### Run tests

```bash
./vendor/bin/phpunit
```

Or globally:

```bash
phpunit
```

Example test:

```php
public function test_blocked_country_gets_403_and_localized_message()
{
    Config::set('geo_restrict.access.rules.deny.country', ['DE']);
    $this->withSession(['geoip' => ['country' => 'DE']]);
    $response = $this->get('/test', ['X-Forwarded-For' => '8.8.8.8']);
    $response->assertStatus(403);
    $response->assertSee(Lang::get('geo_restrict.blocked', [], 'de'));
}
```

To add new tests, use the structure from the file `tests/GeoRestrictMiddlewareTest.php`.

## License

MIT

### Message Localization

Block message is shown in the language of the user's country (if a language file exists), or the app default locale.

To add a new language:

resources/lang/it/geo_restrict.php:

```php
return [
    'blocked' => 'Accesso negato: la tua regione è soggetta a restrizioni.',
];
```

No code changes required — language is detected automatically based on country code (e.g., IT, FR, DE, RU, EN, etc.). 