# GeoRestrict Middleware for Laravel

[![Readme EN](https://img.shields.io/badge/README-EN-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README.md)
[![Readme RU](https://img.shields.io/badge/README-RU-blue.svg)](https://github.com/bespredel/GeoRestrict/blob/master/README_RU.md)
[![GitHub license](https://img.shields.io/badge/license-MIT-458a7b.svg)](https://github.com/bespredel/GeoRestrict/blob/master/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/bespredel/geo-restrict.svg)](https://packagist.org/packages/bespredel/geo-restrict)

[![Latest Version](https://img.shields.io/github/v/release/bespredel/GeoRestrict?logo=github)](https://github.com/bespredel/GeoRestrict/releases)
[![Latest Version Packagist](https://img.shields.io/packagist/v/bespredel/geo-restrict.svg?logo=packagist&logoColor=white&color=F28D1A)](https://packagist.org/packages/bespredel/geo-restrict)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/bespredel/geo-restrict.svg?logo=php&logoColor=white&color=777BB4)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10-FF2D20?logo=laravel)](https://laravel.com)

Модуль для ограничения доступа к вашему приложению Laravel по геолокации пользователя (страна, регион, ASN, город, ISP и др.).

## Возможности

- Проверка страны, региона, ASN, города, ISP по IP пользователя
- Поддержка нескольких geo-сервисов (порядок в массиве определяет приоритет)
- Кэширование geo-ответов (можно отключить)
- Rate limit на обращения к geo-сервисам
- Гибкая настройка allow/deny правил, включая callback-функции и временные ограничения
- Белый список IP
- Гибкая настройка маршрутов (паттерны, методы)
- Локализация текстов ошибок
- Разные ответы для разных стран/правил
- Логирование блокировок и разрешённых запросов

## Установка

1. Установите пакет:

```bash
composer require bespredel/geo-restrict
```

2. Опубликуйте конфиг:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-config
```

## Пример конфига `config/geo_restrict.php`

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
        // Добавьте другие сервисы, порядок определяет приоритет
    ],
    'geo' => [
        'cache_ttl'  => 1440, // В минутах, 0 = кэш отключён
        'rate_limit' => 30,   // Запросов в минуту на IP
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

### Описание основных параметров

- **services** — список geo-сервисов, используемых для определения местоположения по IP. Можно добавить несколько, порядок определяет приоритет.
- **geo.cache_ttl** — время жизни кэша (в минутах), 0 — кэш отключён.
- **geo.rate_limit** — ограничение количества запросов к geo-сервисам с одного IP в минуту.
- **access.whitelisted_ips** — IP-адреса, которым всегда разрешён доступ (по умолчанию localhost).
- **access.rules.allow/deny** — правила разрешения/запрета по стране, региону, ASN, callback-функции и времени.
- **logging** — параметры логирования (блокировки, разрешённые запросы).
- **block_response.type** — тип ответа при блокировке: 'abort' (стандартный abort), 'json' (JSON-ответ), 'view' (рендер view).
- **routes.only/except/methods** — ограничения по маршрутам и HTTP-методам.

## Использование

1. Добавьте middleware в нужные маршруты:

```php
Route::middleware(['geo.restrict'])->group(function () {
    // ...
});
```

2. Или используйте alias:

```php
Route::get('/secret', 'SecretController@index')->middleware('geo.restrict');
```

## Кастомизация

- Добавьте свои geo-сервисы в массив `services`, порядок определяет приоритет.
- Используйте allow/deny правила для гибкой фильтрации.
- Для сложных кейсов используйте callback-функции в правилах.
- Для локализации ошибок используйте файлы языков.

## Тестирование

Пакет покрыт тестами, которые проверяют:

- Блокировку по стране, региону, городу, ASN
- Временные ограничения (time-based deny)
- Кастомные callback-функции
- Разрешённые IP и страны
- Типы ответов: JSON, View, стандартный abort
- Локализацию сообщений о блокировке

Тесты находятся в директории `tests/` и используют Laravel TestCase.

### Запуск тестов

```bash
./vendor/bin/phpunit
```

или, если PHPUnit установлен глобально:

```bash
phpunit
```

Пример теста:

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

Для добавления новых тестов используйте структуру из файла `tests/GeoRestrictMiddlewareTest.php`.

## Лицензия

MIT

### Локализация сообщений

Сообщение о блокировке автоматически выводится на языке страны пользователя (по коду страны, если есть соответствующий языковой файл), либо на языке
приложения.

Чтобы добавить поддержку нового языка, создайте файл:

resources/lang/it/geo_restrict.php:

```php
return [
    'blocked' => 'Accesso negato: la tua regione è soggetta a restrizioni.',
];
```

В коде ничего менять не нужно — язык будет определён автоматически по коду страны (например, IT, FR, DE, RU, EN и т.д.). 