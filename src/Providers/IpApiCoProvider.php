<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;

class IpApiCoProvider extends AbstractGeoProvider
{
    protected ?string $baseUrl = 'https://ipapi.co/';
    protected ?string $endpoint = ':ip/json/';
    protected array $requiredParams = ['ip'];
    protected array $optionalParams = ['lang'];
    protected array $responseMap = [
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

    protected function isValidResponse(array $data): bool
    {
        return !empty($data) && empty($data['error']);
    }

    protected function getErrorMessage(array $data): string
    {
        return 'ipapi.co: ' . ($data['reason'] ?? $data['error'] ?? 'invalid response');
    }
} 