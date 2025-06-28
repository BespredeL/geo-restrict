# GeoRestrict Middleware for Laravel

[![Readme EN](https://img.shields.io/badge/README-EN-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README.md)
[![Readme RU](https://img.shields.io/badge/README-RU-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README_RU.md)
[![GitHub license](https://img.shields.io/badge/license-MIT-458a7b.svg)](https://github.com/bespredel/GeoRestrict/blob/master/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/bespredel/geo-restrict.svg)](https://packagist.org/packages/bespredel/geo-restrict)

[![Latest Version](https://img.shields.io/github/v/release/bespredel/GeoRestrict?logo=github)](https://github.com/bespredel/GeoRestrict/releases)
[![Latest Version Packagist](https://img.shields.io/packagist/v/bespredel/geo-restrict.svg?logo=packagist&logoColor=white&color=F28D1A)](https://packagist.org/packages/bespredel/geo-restrict)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/bespredel/geo-restrict.svg?logo=php&logoColor=white&color=777BB4)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10-FF2D20?logo=laravel)](https://laravel.com)

GeoRestrict is a Laravel middleware that restricts access to your application based on the user's IP geolocation (country, region, ASN, city, ISP,
etc).

## Features

- Country, region, ASN, city, ISP filtering by IP
- Multiple geo-services support (priority by order)
- Geo response caching (can be disabled)
- Rate limiting for geo service requests
- Flexible allow/deny rules, including callbacks and time-based restrictions
- IP whitelist (local addresses are always allowed)
- Route targeting via patterns and HTTP methods
- Localized error messages (multi-language, easy to extend)
- Different responses per country/rule
- Logging for blocked and allowed requests

## Installation

1. Install the package:

```bash
composer require bespredel/geo-restrict
```

2. Publish the configuration:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-config
```

3. (Optional) Publish language files for customization:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-lang
```

## Example config `config/geo-restrict.php`

```php
return [
    'services' => [
        // Example provider with options
        [
            'provider' => \Bespredel\GeoRestrict\Providers\Ip2LocationIoProvider::class,
            'options'  => [
                'api_key' => 'your-ip2location-api-key', // required
                'lang'    => 'en', // optional
            ],
        ],

        // Example provider without options
        \Bespredel\GeoRestrict\Providers\IpWhoIsProvider::class,

        // or
        [
            'provider' => \Bespredel\GeoRestrict\Providers\IpWhoIsProvider::class,
            'options'  => [],
        ],

        // Example array provider
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

        // Add more services; priority is based on order
    ],

    'geo_services' => [
        'cache_ttl'  => 1440,
        'rate_limit' => 30,
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
                'city'     => [],
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

- **services** — list of geo-services used to resolve location by IP. Each provider supports only its documented parameters (see table below).
- **geo_services.cache_ttl** — cache lifetime in minutes (0 disables caching).
- **geo_services.rate_limit** — max requests per minute per IP to geo services.
- **access.whitelisted_ips** — IPs that are always allowed (e.g., localhost).
- **access.rules.allow/deny** — allow/deny rules by country, region, ASN, callbacks and time periods.
- **logging** — enable logging of blocked or allowed requests.
- **block_response.type** — response type: 'abort', 'json', or 'view'.
- **routes.only/except/methods** — route and method matching.

#### Supported Providers and Parameters

| Provider       | Class                 | Required Params | Optional Params |
|----------------|-----------------------|-----------------|-----------------|
| IP2Location.io | Ip2LocationIoProvider | api_key, ip     | lang            |
| ipwho.is       | IpWhoIsProvider       | ip              | api_key         |
| ip-api.com     | IpApiComProvider      | ip              | lang            |
| ipapi.co       | IpApiCoProvider       | ip              | lang            |

Only parameters listed in `requiredParams` and `optionalParams` for each provider will be used in the request. If a required parameter is missing, an
error will be thrown and logged. Optional parameters are included only if set in config.

---

### Provider architecture

All geo providers now inherit from `AbstractGeoProvider` and only need to define:

- `baseUrl`, `endpoint` (with :param placeholders)
- `requiredParams`, `optionalParams`
- `responseMap` (array for mapping API fields to standard keys)
- `isValidResponse(array $data)` (checks if API response is valid)
- `getErrorMessage(array $data)` (returns error message for invalid response)

**Example of a minimal provider:**

```php
class ExampleProvider extends AbstractGeoProvider {
    protected ?string $baseUrl = 'https://example.com/';
    protected ?string $endpoint = 'api/:ip';
    protected array $requiredParams = ['ip'];
    protected array $optionalParams = ['lang'];
    protected array $responseMap = [
        'country' => 'country_code',
        'region'  => 'region',
        'city'    => 'city',
        'asn'     => 'asn',
        'isp'     => 'isp',
    ];
    protected function isValidResponse(array $data): bool {
        return isset($data['country_code']);
    }
    protected function getErrorMessage(array $data): string {
        return 'example.com: invalid response';
    }
    public function getName(): string { return 'example.com'; }
}
```

This makes it easy to add new providers and ensures all logic is unified and DRY.

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

- Add your geo-services to the `services` array; order defines priority.
- Use allow/deny rules for flexible filtering.
- For complex cases, use callback functions in rules.
- For localization, use language files.

## Localization and Language Files

GeoRestrict supports multi-language block messages. To customize or add new translations:

1. Publish the language files:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-lang
```

2. Edit files in `resources/lang/vendor/geo-restrict/` as needed. Add new locales by creating new folders (e.g., `it`, `es`).

- The block message is automatically shown in the user's country language (based on the country code, if a corresponding language file exists), or in
  the application's default language.
- To add a new language, create a file like:

```
resources/lang/it/messages.php
```

No code changes are required — the language is detected automatically based on the country code (e.g., IT, FR, DE, RU, EN, etc.).

## License

MIT 