<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class GeoRuleEvaluator
{
    /**
     * Evaluate the geo rules.
     *
     * @param array $geo Geo data
     *
     * @return RuleCheckResult
     */
    public function evaluate(array $geo): RuleCheckResult
    {
        $rules = Config::get('geo-restrict.access.rules', []);

        if (!empty($rules['deny']['time']) && $this->isNowInPeriods($rules['deny']['time'])) {
            return new RuleCheckResult(false, 'time', 'time');
        }

        if (!empty($rules['allow']['time']) && !$this->isNowInPeriods($rules['allow']['time'])) {
            return new RuleCheckResult(false, 'time', 'time');
        }

        if (is_callable($rules['deny']['callback'] ?? null) && ($rules['deny']['callback'])($geo) === true) {
            return new RuleCheckResult(false, 'callback', 'callback');
        }

        foreach ($rules['deny'] ?? [] as $field => $blocked) {
            if (in_array($field, ['callback', 'time'], true)) {
                continue;
            }

            if (in_array($geo[$field] ?? null, $blocked, true)) {
                return new RuleCheckResult(false, $field, $field, $geo[$field] ?? null);
            }
        }

        if (is_callable($rules['allow']['callback'] ?? null) && ($rules['allow']['callback'])($geo) !== true) {
            return new RuleCheckResult(false, 'callback_allow', 'callback');
        }

        foreach ($rules['allow'] ?? [] as $field => $allowed) {
            if (in_array($field, ['callback', 'time'], true)) {
                continue;
            }

            if (!in_array($geo[$field] ?? null, $allowed, true) && !empty($allowed)) {
                return new RuleCheckResult(false, $field, $field, $geo[$field] ?? null);
            }
        }

        return new RuleCheckResult(true);
    }

    /**
     * Check if the current time is in the periods.
     *
     * @param array $periods Array of periods
     *
     * @return bool True if the current time is in the periods, false otherwise
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
}
