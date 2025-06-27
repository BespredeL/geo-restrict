<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Bespredel\GeoRestrict\GeoRestrictServiceProvider;
use Tests\Mocks\MockGeoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Register mock service provider
        $this->app->register(MockGeoServiceProvider::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            GeoRestrictServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setMockGeoData($geoData): void
    {
        if ($geoData === null) {
            $mockProvider = new \Tests\Mocks\MockGeoProvider([]);
            $mockProvider->shouldReturnNull = true;
        } else {
            $mockProvider = new \Tests\Mocks\MockGeoProvider($geoData);
        }
        $this->app->make(\Tests\Mocks\MockGeoServiceProvider::class)->setMockProvider($mockProvider);
    }
} 