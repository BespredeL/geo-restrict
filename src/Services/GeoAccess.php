<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class GeoAccess
{

    /**
     * GeoAccess constructor.
     *
     * @param GeoRuleEvaluator   $ruleEvaluator
     * @param GeoResponseFactory $responseFactory
     */
    public function __construct(
        private readonly GeoRuleEvaluator   $ruleEvaluator,
        private readonly GeoResponseFactory $responseFactory
    )
    {
    }

    /**
     * Check: IP/network in excluded list?
     *
     * @param string $ip
     *
     * @return bool
     */
    public function isExcludedIp(string $ip): bool
    {
        $networks = Config::get('geo-restrict.excluded_networks', []);

        // If the new key is empty, let's try old keys for reverse compatibility
        if (empty($networks)) {
            $networks = array_merge(
                Config::get('geo-restrict.local_networks', []),
                Config::get('geo-restrict.access.whitelisted_ips', [])
            );
        }

        foreach ($networks as $network) {
            // Exact IP
            if ($ip === $network) {
                return true;
            }

            // Subset CIDR
            if (str_contains($network, '/') && $this->ipInCidr($ip, $network)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check: IP in CIDR?
     *
     * @param string $ip
     * @param string $cidr
     *
     * @return bool
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            $maskBin = str_repeat("f", $mask / 4);
            $maskBin = pack("H*", str_pad($maskBin, 32, '0'));
            return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
        }

        return false;
    }

    /**
     * Check: GEO-data pass access rules?
     *
     * @param array $geo
     *
     * @return array|bool Returns false if blocked, or array with reason if blocked
     */
    public function passesRules(array $geo): array|bool
    {
        $result = $this->evaluateRules($geo);
        if ($result->allowed) {
            return true;
        }

        return $result->toLegacyBlockInfo() ?? ['reason' => 'blocked', 'field' => 'unknown'];
    }

    /**
     * New structured rules API.
     *
     * @param array $geo
     *
     * @return RuleCheckResult
     */
    public function evaluateRules(array $geo): RuleCheckResult
    {
        return $this->ruleEvaluator->evaluate($geo);
    }

    /**
     * Form Deny Response (Abort, Json, View)
     *
     * @param string|null $reason
     * @param array|null  $blockInfo
     *
     * @return Response
     */
    public function denyResponse(?string $reason = null, ?array $blockInfo = null): Response
    {
        return $this->responseFactory->denyResponse($reason, $blockInfo);
    }
}
