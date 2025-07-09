# GeoRestrict Middleware for Laravel

[![Readme EN](https://img.shields.io/badge/README-EN-blue.svg)](https://github.com/BespredeL/geo-restrict/blob/master/README.md)
[![Readme RU](https://img.shields.io/badge/README-RU-blue.svg)](https://github.com/BespredeL/geo-restrict/blob/master/README_RU.md)
[![GitHub license](https://img.shields.io/badge/license-MIT-458a7b.svg)](https://github.com/BespredeL/geo-restrict/blob/master/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/bespredel/geo-restrict.svg)](https://packagist.org/packages/bespredel/geo-restrict)

[![Latest Version](https://img.shields.io/github/v/release/bespredel/GeoRestrict?logo=github)](https://github.com/BespredeL/geo-restrict/releases)
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
- Белый список IP (локальные адреса всегда разрешены)
- Гибкая настройка маршрутов (паттерны, методы)
- Локализация текстов ошибок (мультиязычность, легко расширять)
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

3. (Опционально) Опубликуйте языковые файлы для кастомизации:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-lang
```

## Пример конфига `config/geo-restrict.php`

```php
return [
    'services' => [
        // Пример провайдера с опциями
        [
            'provider' => \Bespredel\GeoRestrict\Providers\Ip2LocationIoProvider::class,
            'options'  => [
                'api_key' => 'your-ip2location-api-key', // обязательно
                'lang'    => 'en', // опционально
            ],
        ],

        // Пример провайдеров без опций
        \Bespredel\GeoRestrict\Providers\IpWhoIsProvider::class,

        // или
        [
            'provider' => \Bespredel\GeoRestrict\Providers\IpWhoIsProvider::class,
            'options'  => [],
        ],

        // Пример провайдера в массиве
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

        // Добавьте другие сервисы, порядок определяет приоритет
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
        'channel' => 'geo-restrict', // Имя вашего канала
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

- **services** — список geo-сервисов, используемых для определения местоположения по IP. Каждый провайдер поддерживает только свои параметры (см.
  таблицу ниже).
- **geo_services.cache_ttl** — время жизни кэша (в минутах), 0 — кэш отключён.
- **geo_services.rate_limit** — ограничение количества запросов к geo-сервисам с одного IP в минуту.
- **access.whitelisted_ips** — IP-адреса, которым всегда разрешён доступ (по умолчанию localhost).
- **access.rules.allow/deny** — правила разрешения/запрета по стране, региону, ASN, callback-функции и времени.
- **logging** — параметры логирования (блокировки, разрешённые запросы).
- **block_response.type** — тип ответа при блокировке: 'abort' (стандартный abort), 'json' (JSON-ответ), 'view' (рендер view).
- **routes.only/except/methods** — ограничения по маршрутам и HTTP-методам.

#### Поддерживаемые провайдеры и параметры

| Провайдер      | Класс                 | Обязательные параметры | Необязательные параметры |
|----------------|-----------------------|------------------------|--------------------------|
| IP2Location.io | Ip2LocationIoProvider | api_key, ip            | lang                     |
| ipwho.is       | IpWhoIsProvider       | ip                     | api_key                  |
| ip-api.com     | IpApiComProvider      | ip                     | lang                     |
| ipapi.co       | IpApiCoProvider       | ip                     | lang                     |

В запросе будут использоваться только параметры, указанные в `requiredParams` и `optionalParams` для каждого провайдера. Если обязательный параметр не
задан, будет выброшена и залогирована ошибка. Необязательные параметры добавляются только если заданы в конфиге.

---

### Архитектура провайдеров

Все geo-провайдеры теперь наследуются от `AbstractGeoProvider` и должны определить только:

- `baseUrl`, `endpoint` (с плейсхолдерами :param)
- `requiredParams`, `optionalParams`
- `responseMap` (карта соответствий полей API)
- `isValidResponse(array $data)` (проверка валидности ответа)
- `getErrorMessage(array $data)` (текст ошибки для невалидного ответа)

**Пример минимального провайдера:**

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

Это упрощает добавление новых провайдеров и гарантирует единообразие и отсутствие дублирования логики.

## Использование

1. Добавьте middleware в нужные маршруты:

```php
Route::middleware(['geo-restrict'])->group(function () {
    // ...
});
```

2. Или используйте alias:

```php
Route::get('/secret', 'SecretController@index')->middleware('geo-restrict');
```

## Кастомизация

- Добавьте свои geo-сервисы в массив `services`, порядок определяет приоритет.
- Используйте allow/deny правила для гибкой фильтрации.
- Для сложных кейсов используйте callback-функции в правилах.
- Для локализации ошибок используйте языковые файлы.

## Локализация и языковые файлы

GeoRestrict поддерживает мультиязычные сообщения о блокировке. Для кастомизации или добавления новых переводов:

1. Опубликуйте языковые файлы:

```bash
php artisan vendor:publish --provider="Bespredel\GeoRestrict\GeoRestrictServiceProvider" --tag=geo-restrict-lang
```

2. Редактируйте файлы в `resources/lang/vendor/geo-restrict/` по необходимости. Для новых языков создайте новую папку (например, `it`, `es`).

- Сообщение о блокировке автоматически выводится на языке страны пользователя (по коду страны, если есть соответствующий языковой файл), либо на языке
  приложения по умолчанию.
- Чтобы добавить поддержку нового языка, создайте файл:

```
resources/lang/it/messages.php
```

Изменения в коде не требуются — язык определяется автоматически по коду страны (например, IT, FR, DE, RU, EN и др.).


## Управление кэшем и массовая очистка (tag-based flush)

GeoRestrict использует кэш Laravel для хранения geo-данных и лимитов. Если ваш драйвер кэша поддерживает теги (Redis, Memcached), все записи geoip кэшируются с тегом `geoip`.

- Для массовой очистки geoip-кэша используйте artisan-команду:

```bash
php artisan geo-restrict:clear-cache
```

Вы увидите:

    GeoIP cache flushed (if supported by cache driver).

> **Внимание:** Массовая очистка кэша по тегу работает только с драйверами Redis и Memcached. Для других драйверов кэш не будет очищен пакетно.

## Логирование: поддержка отдельного канала

GeoRestrict поддерживает логирование в отдельный канал. По умолчанию все логи (блокировки, разрешения, ошибки провайдеров, rate limit) пишутся в основной лог Laravel. Чтобы использовать отдельный канал, укажите параметр `logging.channel` в `config/geo-restrict.php`:

```php
'logging' => [
    'blocked_requests' => true,
    'allowed_requests' => false,
    'channel' => 'geo-restrict', // Имя вашего канала
],
```

Добавьте канал в `config/logging.php`:

```php
'channels' => [
    // ...
    'geo-restrict' => [
        'driver' => 'single',
        'path' => storage_path('logs/geo-restrict.log'),
        'level' => 'info',
    ],
],
```

Если `channel` не указан или равен `null`, логи будут писаться в основной канал.

## Лицензия

Этот пакет представляет собой программное обеспечение с открытым исходным кодом, лицензированное по лицензии MIT.
