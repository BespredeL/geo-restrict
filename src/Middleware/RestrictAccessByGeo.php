<?php

namespace Bespredel\GeoRestrict\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictAccessByGeo
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldApply($request) || !$this->shouldApplyMethod($request)) {
            return $next($request);
        }

        $ip = $request->ip();

        if (!$this->isValidIp($ip)) {
            Log::warning("GeoRestrict: Invalid IP {$ip}");
            return $this->denyResponse('invalid_ip');
        }

        if ($this->isLocalIp($ip) || $this->isWhitelistedIp($ip)) {
            return $next($request);
        }

        $geoData = $this->getGeoData($ip);

        if (!$geoData) {
            Log::warning("GeoRestrict: Could not resolve geo data for {$ip}");
            return $this->denyResponse('geo_fail');
        }

        $country = $geoData['country'] ?? '??';
        $url = $request->fullUrl();

        if (!$this->passesRules($geoData)) {
            if (config('geo_restrict.logging.blocked_requests', false)) {
                Log::warning("GeoRestrict: Blocked {$ip} from {$country} accessing {$url}");
            }
            return $this->denyResponse($geoData['country'] ?? null);
        }

        if (config('geo_restrict.logging.allowed_requests', false)) {
            Log::info("GeoRestrict: Allowed {$ip} from {$country} accessing {$url}");
        }

        return $next($request);
    }

    /**
     * Determine if the request should be restricted.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function shouldApply(Request $request): bool
    {
        $only = config('geo_restrict.routes.only', []);
        $except = config('geo_restrict.routes.except', []);
        $current = $request->route()?->getName() ?? $request->path();

        foreach ($except as $pattern) {
            if (fnmatch($pattern, $current)) {
                return false;
            }
        }

        if (!empty($only)) {
            foreach ($only as $pattern) {
                if (fnmatch($pattern, $current)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Determine if the request should be restricted.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function shouldApplyMethod(Request $request): bool
    {
        $onlyMethods = config('geo_restrict.routes.methods', []);
        return empty($onlyMethods) || in_array($request->method(), $onlyMethods, true);
    }

    /**
     * Determine if the IP is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Determine if the IP is local.
     *
     * @param string $ip
     *
     * @return bool
     */
    protected function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true) ||
            preg_match('/^(10|192\.168|172\.(1[6-9]|2[0-9]|3[0-1]))\./', $ip);
    }

    /**
     * Determine if the IP is whitelisted.
     *
     * @param string $ip
     *
     * @return bool
     */
    protected function isWhitelistedIp(string $ip): bool
    {
        return in_array($ip, config('geo_restrict.access.whitelisted_ips', []), true);
    }

    /**
     * Get geo data.
     *
     * @param string $ip
     *
     * @return array|null
     */
    protected function getGeoData(string $ip): ?array
    {
        $rateKey = "geoip:rate:{$ip}";
        $count = Cache::get($rateKey, 0);
        $rateLimit = config('geo_restrict.geo.rate_limit', 30);

        if ($count >= $rateLimit) {
            Log::warning("GeoRestrict: Rate limit exceeded for {$ip}");
            return null;
        }

        Cache::put($rateKey, $count + 1, now()->addMinute());

        $cacheKey = "geoip:{$ip}";
        $cacheTtl = config('geo_restrict.geo.cache_ttl', 1440);

        if ($cacheTtl > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $services = config('geo_restrict.services', []);
        foreach ($services as $service) {
            try {
                $url = str_replace(':ip', $ip, $service['url']);
                $response = Http::timeout(5)->get($url);

                if (!$response->successful()) {
                    continue;
                }

                $json = $response->json();
                $map = $service['map'] ?? [];

                $data = [];
                foreach ($map as $field => $path) {
                    $data[$field] = data_get($json, $path);
                }

                if (!empty($data['country']) && $cacheTtl > 0) {
                    Cache::put($cacheKey, $data, now()->addMinutes($cacheTtl));
                }

                return $data;
            }
            catch (\Throwable $e) {
                Log::debug("GeoRestrict: API {$service['name']} failed for {$ip}: {$e->getMessage()}");
            }
        }

        return null;
    }

    /**
     * Determine if the request should be restricted.
     *
     * @param array $geo
     *
     * @return bool
     */
    protected function passesRules(array $geo): bool
    {
        $rules = config('geo_restrict.access.rules', []);

        // Time-based denial
        foreach ($rules['deny']['time'] ?? [] as $period) {
            $from = $period['from'] ?? null;
            $to = $period['to'] ?? null;

            if ($from && $to) {
                $now = now();
                $fromTime = now()->copy()->setTimeFromTimeString($from);
                $toTime = now()->copy()->setTimeFromTimeString($to);
                
                if ($fromTime > $toTime) {
                    if ($now >= $fromTime || $now <= $toTime) {
                        return false;
                    }
                } else {
                    if ($now >= $fromTime && $now <= $toTime) {
                        return false;
                    }
                }
            }
        }

        // Callback denial
        if (is_callable($rules['deny']['callback'] ?? null)) {
            if (call_user_func($rules['deny']['callback'], $geo) === true) {
                return false;
            }
        }

        // Field-based denial
        foreach ($rules['deny'] ?? [] as $field => $blocked) {
            if (in_array($field, ['callback', 'time'], true)) {
                continue;
            }

            if (in_array($geo[$field] ?? null, $blocked, true)) {
                return false;
            }
        }

        // Callback allow
        if (is_callable($rules['allow']['callback'] ?? null)) {
            if (call_user_func($rules['allow']['callback'], $geo) !== true) {
                return false;
            }
        }

        // Field-based allow
        foreach ($rules['allow'] ?? [] as $field => $allowed) {
            if ($field === 'callback') {
                continue;
            }

            if (!in_array($geo[$field] ?? null, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deny the request.
     *
     * @param string|null $reason
     *
     * @return Response
     */
    protected function denyResponse(?string $reason = null): Response
    {
        $type = config('geo_restrict.block_response.type', 'abort');
        $json = config('geo_restrict.block_response.json', []);

        // Determine locale from 2-letter country code if provided
        $locale = is_string($reason) && strlen($reason) === 2 ? strtolower($reason) : null;
        $originalLocale = app()->getLocale();

        if ($locale && Lang::has('geo_restrict.blocked', $locale)) {
            app()->setLocale($locale);
        }

        $message = Lang::get('geo_restrict::messages.blocked');

        // Fallback message if translation key is missing
        if ($message === 'geo_restrict.blocked') {
            $message = 'Access denied by geo restriction.';
        }

        app()->setLocale($originalLocale); // Revert back to original

        if ($json['message'] === null) {
            $json['message'] = $message;
        }

        return match ($type) {
            'json' => response()->json($json, 403),
            'view' => response()->view(
                config('geo_restrict.block_response.view', 'errors.403'),
                ['message' => $message, 'country' => $reason],
                403
            ),
            default => abort(403, $message),
        };
    }
}