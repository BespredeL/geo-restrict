<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

class GeoResponseFactory
{
    /**
     * Deny response.
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

        try {
            if ($locale && Lang::has('geo-restrict.blocked', $locale)) {
                app()->setLocale($locale);
            }

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
        }
        finally {
            app()->setLocale($originalLocale);
        }

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
