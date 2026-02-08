<?php

declare(strict_types=1);

namespace Tests;

use Bespredel\GeoRestrict\GeoRestrictServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GeoRestrictServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('geo-restrict', require __DIR__ . '/../config/geo-restrict.php');
    }

    protected function setGeoRestrictConfig(array $overrides): void
    {
        $config = require __DIR__ . '/../config/geo-restrict.php';
        $this->app['config']->set('geo-restrict', array_replace_recursive($config, $overrides));
    }
}
