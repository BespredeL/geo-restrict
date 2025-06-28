<?php

namespace Bespredel\GeoRestrict\Services;

use Bespredel\GeoRestrict\Exceptions\GeoRateLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoCache
{
    /**
     * Checks if the given IP has exceeded the rate limit.
     * Increments the request count for the IP.
     *
     * @param string $ip
     *
     * @return bool Always false (throws exception if limit exceeded)
     *
     * @throws GeoRateLimitException
     */
    public function isRateLimited(string $ip): bool
    {
        $rateKey = "geoip:rate:{$ip}";
        $count = Cache::get($rateKey, 0);
        $rateLimit = config('geo-restrict.geo_services.rate_limit', 30);

        if ($count >= $rateLimit) {
            Log::warning("GeoRestrict: Rate limit exceeded for {$ip}");
            throw new GeoRateLimitException("Rate limit exceeded for {$ip}");
        }

        Cache::put($rateKey, $count + 1, now()->addMinute());

        return false;
    }

    /**
     * Get cached geo data for the given IP, if available.
     *
     * @param string $ip
     *
     * @return array|null
     */
    public function get(string $ip): ?array
    {
        $cacheKey = "geoip:{$ip}";
        $cacheTtl = config('geo-restrict.geo_services.cache_ttl', 1440);
        if ($cacheTtl > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        return null;
    }

    /**
     * Store geo data for the given IP in cache.
     *
     * @param string $ip
     * @param array  $data
     *
     * @return void
     */
    public function put(string $ip, array $data): void
    {
        $cacheKey = "geoip:{$ip}";
        $cacheTtl = config('geo-restrict.geo_services.cache_ttl', 1440);
        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $data, now()->addMinutes($cacheTtl));
        }
    }
} 