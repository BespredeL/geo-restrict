<?php

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

trait GeoLoggerTrait
{
    /**
     * Get logger instance for geo-restrict (custom channel if set).
     *
     * @return LoggerInterface
     */
    protected function geoLogger(): LoggerInterface
    {
        $channel = config('geo-restrict.logging.channel');
        return $channel ? Log::channel($channel) : Log::getFacadeRoot();
    }
} 