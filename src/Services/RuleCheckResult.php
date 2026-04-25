<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Services;

final class RuleCheckResult
{
    /**
     * RuleCheckResult constructor.
     *
     * @param bool        $allowed True if the rule check is allowed, false otherwise
     * @param string|null $reason  Reason for the rule check
     * @param string|null $field   Field that caused the rule check
     * @param mixed       $value   Value that caused the rule check
     */
    public function __construct(
        public readonly bool    $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $field = null,
        public readonly mixed   $value = null
    )
    {
    }

    /**
     * Convert the rule check result to a legacy block info array.
     *
     * @return array|null Array with reason, field, and value
     */
    public function toLegacyBlockInfo(): ?array
    {
        if ($this->allowed || $this->reason === null || $this->field === null) {
            return null;
        }

        $blockInfo = [
            'reason' => $this->reason,
            'field'  => $this->field,
        ];

        if ($this->value !== null) {
            $blockInfo['value'] = $this->value;
        }

        return $blockInfo;
    }
}