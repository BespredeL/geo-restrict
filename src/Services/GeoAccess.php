<?php

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

class GeoAccess
{
    /**
     * Check: IP local?
     *
     * @param string $ip
     *
     * @return bool
     */
    public function isLocalIp(string $ip): bool
    {
        $networks = config('geo-restrict.local_networks', []);
        foreach ($networks as $network) {
            if (strpos($network, '/') === false) {
                if ($ip === $network) {
                    return true;
                }
            } else {
                if ($this->ipInCidr($ip, $network)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Проверяет, принадлежит ли IP диапазону CIDR
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            $maskBin = str_repeat("f", $mask / 4);
            $maskBin = pack("H*", str_pad($maskBin, 32, '0'));
            return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
        }

        return false;
    }

    /**
     * Check: IP in Whitelist?
     *
     * @param string $ip
     *
     * @return bool
     */
    public function isWhitelistedIp(string $ip): bool
    {
        return in_array($ip, config('geo-restrict.access.whitelisted_ips', []), true);
    }

    /**
     * Checking: GEO-data pass access rules?
     *
     * @param array $geo
     *
     * @return array|bool Returns false if blocked, or array with reason if blocked
     */
    public function passesRules(array $geo): array|bool
    {
        $rules = config('geo-restrict.access.rules', []);

        // Time-based denial
        foreach ($rules['deny']['time'] ?? [] as $period) {
            $from = $period['from'] ?? null;
            $to = $period['to'] ?? null;
            if ($from && $to) {
                $now = now();
                $fromTime = now()->copy()->setTimeFromTimeString($from);
                $toTime = now()->copy()->setTimeFromTimeString($to);
                
                // Если период пересекает полночь (from > to)
                if ($fromTime > $toTime) {
                    // Проверяем: сейчас >= fromTime ИЛИ сейчас <= toTime
                    if ($now >= $fromTime || $now <= $toTime) {
                        return ['reason' => 'time', 'field' => 'time'];
                    }
                } else {
                    // Обычный период в пределах одного дня
                    if ($now >= $fromTime && $now <= $toTime) {
                        return ['reason' => 'time', 'field' => 'time'];
                    }
                }
            }
        }

        // Callback denial
        if (is_callable($rules['deny']['callback'] ?? null)) {
            if (call_user_func($rules['deny']['callback'], $geo) === true) {
                return ['reason' => 'callback', 'field' => 'callback'];
            }
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
        if (is_callable($rules['allow']['callback'] ?? null)) {
            if (call_user_func($rules['allow']['callback'], $geo) !== true) {
                return ['reason' => 'callback_allow', 'field' => 'callback'];
            }
        }

        // Field-based allow
        foreach ($rules['allow'] ?? [] as $field => $allowed) {
            if ($field === 'callback') {
                continue;
            }

            if (!in_array($geo[$field] ?? null, $allowed) && $allowed) {
                return ['reason' => $field, 'field' => $field, 'value' => $geo[$field] ?? null];
            }
        }

        return true;
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
        $type = config('geo-restrict.block_response.type', 'abort');
        $json = config('geo-restrict.block_response.json', []);
        $locale = is_string($reason) && strlen($reason) === 2 ? strtolower($reason) : null;
        $originalLocale = app()->getLocale();

        if ($locale && Lang::has('geo-restrict.blocked', $locale)) {
            app()->setLocale($locale);
        }

        // We determine the message key based on the cause of the lock
        $messageKey = 'blocked';
        if ($blockInfo && isset($blockInfo['reason'])) {
            $reasonType = $blockInfo['reason'];
            switch ($reasonType) {
                case 'time':
                    $messageKey = 'blocked_time';
                    break;
                case 'region':
                    $messageKey = 'blocked_region';
                    break;
                case 'city':
                    $messageKey = 'blocked_city';
                    break;
                case 'asn':
                    $messageKey = 'blocked_asn';
                    break;
                case 'country':
                default:
                    $messageKey = 'blocked';
                    break;
            }
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
                config('geo-restrict.block_response.view', 'errors.403'),
                ['message' => $message, 'country' => $reason],
                403
            ),
            default => abort(403, $message),
        };
    }
} 