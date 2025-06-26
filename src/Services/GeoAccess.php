<?php

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
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
        return in_array($ip, ['127.0.0.1', '::1'], true)
            || preg_match('/^(10|192\.168|172\.(1[6-9]|2[0-9]|3[0-1]))\./', $ip);
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
        return in_array($ip, config('geo_restrict.access.whitelisted_ips', []), true);
    }

    /**
     * Checking: GEO-data pass access rules?
     *
     * @param array $geo
     *
     * @return bool
     */
    public function passesRules(array $geo): bool
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
            if (in_array($field, ['callback', 'time'], true)) continue;
            if (in_array($geo[$field] ?? null, $blocked, true)) return false;
        }

        // Callback allow
        if (is_callable($rules['allow']['callback'] ?? null)) {
            if (call_user_func($rules['allow']['callback'], $geo) !== true) {
                return false;
            }
        }

        // Field-based allow
        foreach ($rules['allow'] ?? [] as $field => $allowed) {
            if ($field === 'callback') continue;
            if (!in_array($geo[$field] ?? null, $allowed, true)) return false;
        }

        return true;
    }

    /**
     * Form Deny Response (Abort, Json, View)
     *
     * @param string|null $reason
     *
     * @return Response
     */
    public function denyResponse(?string $reason = null): Response
    {
        $type = config('geo_restrict.block_response.type', 'abort');
        $json = config('geo_restrict.block_response.json', []);
        $locale = is_string($reason) && strlen($reason) === 2 ? strtolower($reason) : null;
        $originalLocale = app()->getLocale();

        if ($locale && Lang::has('geo_restrict.blocked', $locale)) {
            app()->setLocale($locale);
        }

        $message = Lang::get('geo_restrict::messages.blocked');
        if ($message === 'geo_restrict.blocked') {
            $message = 'Access denied by geo restriction.';
        }

        app()->setLocale($originalLocale);
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