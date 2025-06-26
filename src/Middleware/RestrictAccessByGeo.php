<?php

namespace Bespredel\GeoRestrict\Middleware;

use Bespredel\GeoRestrict\Providers\GeoServiceProviderInterface;
use Bespredel\GeoRestrict\Services\GeoResolver;
use Bespredel\GeoRestrict\Services\GeoAccess;
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
    protected GeoResolver $geoResolver;
    protected GeoAccess   $geoAccessService;

    /**
     * RestrictAccessByGeo constructor.
     *
     * @param GeoResolver $geoResolver
     * @param GeoAccess   $geoAccessService
     */
    public function __construct(GeoResolver $geoResolver, GeoAccess $geoAccessService)
    {
        $this->geoResolver = $geoResolver;
        $this->geoAccessService = $geoAccessService;
    }

    /**
     * Handle an incoming request and restrict access based on geo rules.
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
            return $this->geoAccessService->denyResponse('invalid_ip');
        }

        if ($this->geoAccessService->isLocalIp($ip) || $this->geoAccessService->isWhitelistedIp($ip)) {
            return $next($request);
        }

        $geoData = $this->geoResolver->resolve($ip);

        if (!$geoData) {
            Log::warning("GeoRestrict: Could not resolve geo data for {$ip}");
            return $this->geoAccessService->denyResponse('geo_fail');
        }

        $country = $geoData['country'] ?? '??';
        $url = $request->fullUrl();

        if (!$this->geoAccessService->passesRules($geoData)) {
            if (config('geo_restrict.logging.blocked_requests', false)) {
                Log::warning("GeoRestrict: Blocked {$ip} from {$country} accessing {$url}");
            }
            return $this->geoAccessService->denyResponse($geoData['country'] ?? null);
        }

        if (config('geo_restrict.logging.allowed_requests', false)) {
            Log::info("GeoRestrict: Allowed {$ip} from {$country} accessing {$url}");
        }

        return $next($request);
    }

    /**
     * Determine if the geo restriction should be applied to this route.
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
     * Determine if the geo restriction should be applied to this HTTP method.
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
     * Check if the IP address is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}