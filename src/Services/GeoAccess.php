<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

class GeoAccess
{
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
        $rules = Config::get('geo-restrict.access.rules', []);

        // Time-based denial
        if (!empty($rules['deny']['time']) && $this->isNowInPeriods($rules['deny']['time'])) {
            return ['reason' => 'time', 'field' => 'time'];
        }

        // Time-based allow
        if (!empty($rules['allow']['time']) && !$this->isNowInPeriods($rules['allow']['time'])) {
            return ['reason' => 'time', 'field' => 'time'];
        }

        // Callback denial
        if (is_callable($rules['deny']['callback'] ?? null) && call_user_func($rules['deny']['callback'], $geo) === true) {
            return ['reason' => 'callback', 'field' => 'callback'];
        }

        // Field-based denial
        foreach ($rules['deny'] ?? [] as $field => $blocked) {
            if (in_array($field, ['callback', 'time'], true)) {
                continue;
            }

            if (in_array($geo[$field] ?? null, $blocked, true)) {
                return ['reason' => $field, 'field' => $field, 'value' => $geo[$field] ?? null];
            }
        }

        // Callback allow
        if (is_callable($rules['allow']['callback'] ?? null) && call_user_func($rules['allow']['callback'], $geo) !== true) {
            return ['reason' => 'callback_allow', 'field' => 'callback'];
        }

        // Field-based allow
        foreach ($rules['allow'] ?? [] as $field => $allowed) {
            if (in_array($field, ['callback', 'time'], true)) {
                continue;
            }

            if (!in_array($geo[$field] ?? null, $allowed) && $allowed) {
                return ['reason' => $field, 'field' => $field, 'value' => $geo[$field] ?? null];
            }
        }

        return true;
    }

    /**
     * Check: Whether the current time falls into at least one of the given periods
     *
     * @param array $periods
     *
     * @return bool
     */
    private function isNowInPeriods(array $periods): bool
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        foreach ($periods as $period) {
            $from = $period['from'] ?? null;
            $to = $period['to'] ?? null;

            if ($from && $to) {
                $fromTime = $today->copy()->setTimeFromTimeString($from);
                $toTime = $today->copy()->setTimeFromTimeString($to);

                if ($fromTime > $toTime) {
                    if ($now < $toTime) {
                        $fromTime->subDay();
                    } else {
                        $toTime->addDay();
                    }
                }

                if ($now->between($fromTime, $toTime)) {
                    return true;
                }
            }
        }

        return false;
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
        $type = Config::get('geo-restrict.block_response.type', 'abort');
        $json = Config::get('geo-restrict.block_response.json', []);
        $locale = is_string($reason) && strlen($reason) === 2 ? strtolower($reason) : null;
        $originalLocale = app()->getLocale();

        if ($locale && Lang::has('geo-restrict.blocked', $locale)) {
            app()->setLocale($locale);
        }

        // We determine the message key based on the cause of the lock
        $messageKey = 'blocked';
        if ($blockInfo && isset($blockInfo['reason'])) {
            $reasonType = $blockInfo['reason'];
            $messageKey = match ($reasonType) {
                'time' => 'blocked_time',
                'region' => 'blocked_region',
                'city' => 'blocked_city',
                'asn' => 'blocked_asn',
                default => 'blocked',
            };
        }

        $message = Lang::get('geo-restrict::messages.' . $messageKey);
        if ($message === 'geo-restrict::messages.' . $messageKey) {
            $message = 'Access denied by geo restriction.';
        }

        app()->setLocale($originalLocale);
        if (($json['message'] ?? null) === null) {
            $json['message'] = $message;
        }

        return match ($type) {
            'json' => response()->json($json, 403),
            'view' => response()->view(
                Config::get('geo-restrict.block_response.view', 'errors.403'),
                ['message' => $message, 'country' => $reason],
                403
            ),
            default => abort(403, $message),
        };
    }
} 