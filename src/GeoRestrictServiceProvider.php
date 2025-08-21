<?php

namespace Bespredel\GeoRestrict;

use Illuminate\Support\ServiceProvider;
use Bespredel\GeoRestrict\Middleware\RestrictAccessByGeo;
use Bespredel\GeoRestrict\Console\ClearGeoCache;

class GeoRestrictServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/geo-restrict.php', 'geo-restrict');

        $this->publishes([
            __DIR__ . '/../config/geo-restrict.php' => config_path('geo-restrict.php'),
        ], 'geo-restrict-config');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/geo-restrict'),
        ], 'geo-restrict-lang');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'geo-restrict');

        app('router')->aliasMiddleware('geo-restrict', RestrictAccessByGeo::class);

        $this->commands([
            ClearGeoCache::class,
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
