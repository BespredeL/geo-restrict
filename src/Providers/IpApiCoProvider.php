<?php

namespace Bespredel\GeoRestrict\Providers;

class IpApiCoProvider extends AbstractGeoProvider
{
    protected ?string $baseUrl        = 'https://ipapi.co/';
    protected ?string $endpoint       = ':ip/json/';
    protected array   $requiredParams = ['ip'];
    protected array   $optionalParams = ['lang'];
    protected array   $responseMap    = [
        'country' => 'country_code',
        'region'  => 'region_code',
        'city'    => 'city',
        'asn'     => 'asn',
        'isp'     => 'org',
    ];

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ipapi.co';
    }

    /**
     * Check if response is valid.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function isValidResponse(array $data): bool
    {
        return !empty($data) && empty($data['error']);
    }

    /**
     * Get error message.
     *
     * @param array $data
     *
     * @return string
     */
    protected function getErrorMessage(array $data): string
    {
        return 'ipapi.co: ' . ($data['reason'] ?? $data['error'] ?? 'invalid response');
    }
} 