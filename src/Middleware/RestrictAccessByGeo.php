<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Middleware;

use Bespredel\GeoRestrict\Services\GeoAccess;
use Bespredel\GeoRestrict\Services\GeoLoggerTrait;
use Bespredel\GeoRestrict\Services\GeoResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class RestrictAccessByGeo
{
    use GeoLoggerTrait;

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
            $this->geoLogger()->warning("GeoRestrict: Invalid IP {$ip}");
            return $this->geoAccessService->denyResponse('invalid_ip');
        }

        if ($this->geoAccessService->isExcludedIp($ip)) {
            return $next($request);
        }

        $geoData = $this->geoResolver->resolve($ip);

        if (!$geoData) {
            $this->geoLogger()->warning("GeoRestrict: Could not resolve geo data for {$ip}");
            return $this->geoAccessService->denyResponse('geo_fail');
        }

        $country = $geoData['country'] ?? '??';
        $ruleResult = $this->geoAccessService->evaluateRules($geoData);
        if (!$ruleResult->allowed) {
            if (Config::get('geo-restrict.logging.blocked_requests', false)) {
                $url = $request->fullUrl();
                $this->geoLogger()->warning("GeoRestrict: Blocked {$ip} from {$country} accessing {$url}");
            }

            if (Config::get('geo-restrict.observability.log_deny_reasons', false)) {
                $reason = $ruleResult->reason ?? 'unknown';
                $this->geoLogger()->debug("GeoRestrict: deny reason '{$reason}' for {$ip}");
            }

            return $this->geoAccessService->denyResponse(
                $geoData['country'] ?? null,
                $ruleResult->toLegacyBlockInfo()
            );
        }

        if (Config::get('geo-restrict.logging.allowed_requests', false)) {
            $url = $request->fullUrl();
            $this->geoLogger()->info("GeoRestrict: Allowed {$ip} from {$country} accessing {$url}");
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
        $only = Config::get('geo-restrict.routes.only', []);
        $except = Config::get('geo-restrict.routes.except', []);

        foreach ($except as $pattern) {
            if ($request->routeIs($pattern) || $request->is($pattern)) {
                return false;
            }
        }

        if (!empty($only)) {
            foreach ($only as $pattern) {
                if ($request->routeIs($pattern) || $request->is($pattern)) {
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
        $onlyMethods = Config::get('geo-restrict.routes.methods', []);
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