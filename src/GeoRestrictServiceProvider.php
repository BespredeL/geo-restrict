<?php

namespace Bespredel\GeoRestrict;

use Illuminate\Support\ServiceProvider;
use Bespredel\GeoRestrict\Middleware\RestrictAccessByGeo;

class GeoRestrictServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/geo_restrict.php', 'geo_restrict');

        $this->publishes([
            __DIR__ . '/../config/geo_restrict.php' => config_path('geo_restrict.php'),
        ], 'geo-restrict-config');

        app('router')->aliasMiddleware('geo.restrict', RestrictAccessByGeo::class);
    }
}
