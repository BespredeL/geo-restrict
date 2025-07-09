<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

use Bespredel\GeoRestrict\Exceptions\GeoRateLimitException;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoCache
{
    use GeoLoggerTrait;

    /**
     * Get cache repository with geoip tag if supported.
     *
     * @return \Illuminate\Contracts\Cache\Repository|\Illuminate\Cache\TaggedCache
     */
    protected function cache()
    {
        $cache = Cache::store();
        if (method_exists($cache, 'tags') && $cache->getStore() instanceof TaggableStore) {
            return $cache->tags('geoip');
        }

        return $cache;
    }

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
        $count = $this->cache()->get($rateKey, 0);
        $rateLimit = config('geo-restrict.geo_services.rate_limit', 30);

        if ($count >= $rateLimit) {
            $this->geoLogger()->warning("GeoRestrict: Rate limit exceeded for {$ip}");
            throw new GeoRateLimitException("Rate limit exceeded for {$ip}");
        }

        $this->cache()->put($rateKey, $count + 1, now()->addMinute());

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
        if ($cacheTtl > 0 && $this->cache()->has($cacheKey)) {
            return $this->cache()->get($cacheKey);
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
            $this->cache()->put($cacheKey, $data, now()->addMinutes($cacheTtl));
        }
    }

    /**
     * Flush all geoip cache (by tag if supported).
     *
     * @return void
     */
    public function clearAllGeoCache(): void
    {
        $cache = Cache::store();
        if (method_exists($cache, 'tags') && $cache->getStore() instanceof TaggableStore) {
            $cache->tags('geoip')->flush();
        }
    }
} 