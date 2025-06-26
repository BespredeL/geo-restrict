<?php

namespace Bespredel\GeoRestrict\Services;

use Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface;
use Bespredel\GeoRestrict\Exceptions\GeoRateLimitException;
use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoResolver
{
    protected GeoCache $cache;

    /**
     * GeoResolver constructor.
     *
     * @param GeoCache $cache GeoCache instance for caching and rate limiting
     */
    public function __construct(GeoCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Resolve geo data for a given IP address.
     * Tries all configured providers/services in order until one returns valid data.
     * Handles rate limiting and caching.
     *
     * @param string $ip
     *
     * @return array|null Array with keys: country, region, city, asn, isp; or null if not found or on error
     */
    public function resolve(string $ip): ?array
    {
        try {
            if ($this->cache->isRateLimited($ip)) {
                return null;
            }
        }
        catch (GeoRateLimitException $e) {
            Log::warning($e->getMessage());
            return null;
        }

        $cached = $this->cache->get($ip);
        if ($cached !== null) {
            return $cached;
        }

        foreach (config('geo_restrict.services', []) as $service) {
            $data = $this->resolveFromService($service, $ip);
            if ($data && !empty($data['country'])) {
                if (!$this->isValidGeoData($data)) {
                    Log::error('GeoResolver: invalid geo data structure', ['data' => $data, 'service' => $service]);
                    continue;
                }
                $this->cache->put($ip, $data);
                return $data;
            }
        }

        return null;
    }

    /**
     * Try to resolve geo data from a single service definition.
     *
     * @param mixed $service
     * @param string $ip
     *
     * @return array|null
     */
    private function resolveFromService(mixed $service, string $ip): ?array
    {
        try {
            // Array with 'provider' key (with options)
            if (is_array($service) && isset($service['provider'])) {
                $provider = $this->makeProvider($service['provider'], $service['options'] ?? []);
                return $provider?->getGeoData($ip);
            }

            // FQCN
            if (is_string($service) && class_exists($service) && is_subclass_of($service, GeoServiceProviderInterface::class)) {
                $provider = $this->makeProvider($service);
                return $provider?->getGeoData($ip);
            }

            // Legacy array format
            if (is_array($service) && isset($service['url'], $service['map'])) {
                return $this->getGeoDataFromArrayService($service, $ip);
            }
        }
        catch (GeoProviderException $e) {
            Log::error("GeoProvider error: " . $e->getMessage());
        }
        catch (\Throwable $e) {
            $serviceName = is_array($service) ? ($service['name'] ?? 'array_service') : (is_string($service) ? $service : 'unknown');
            Log::debug("GeoRestrict: API {$serviceName} failed for {$ip}: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Instantiate a provider and set options if needed.
     *
     * @param string $class
     * @param array $options
     *
     * @return GeoServiceProviderInterface|null
     */
    private function makeProvider(string $class, array $options = []): ?GeoServiceProviderInterface
    {
        if (!class_exists($class) || !is_subclass_of($class, GeoServiceProviderInterface::class)) {
            return null;
        }

        $provider = app($class);
        if (!empty($options)) {
            $provider->setOptions($options);
        }

        return $provider;
    }

    /**
     * Get geo data from an array-defined service (legacy format).
     *
     * @param array  $service Service definition (must have 'url' and 'map')
     * @param string $ip      IP address
     *
     * @return array|null Array with geo data or null on error
     */
    private function getGeoDataFromArrayService(array $service, string $ip): ?array
    {
        $url = str_replace(':ip', $ip, $service['url']);
        $response = Http::timeout(5)->get($url);
        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();
        $map = $service['map'] ?? [];
        $data = [];
        foreach ($map as $field => $path) {
            $data[$field] = data_get($json, $path);
        }

        return $data;
    }

    /**
     * Check that geo data contains all required fields.
     *
     * @param array $data
     *
     * @return bool
     */
    private function isValidGeoData(array $data): bool
    {
        $required = ['country', 'region', 'city', 'asn', 'isp'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }

        return true;
    }
} 