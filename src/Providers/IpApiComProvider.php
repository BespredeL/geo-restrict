<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;

class IpApiComProvider extends AbstractGeoProvider
{
    protected ?string $baseUrl = 'http://ip-api.com/';
    protected ?string $endpoint = 'json/:ip';
    protected array $requiredParams = ['ip'];
    protected array $optionalParams = ['lang'];
    protected array $responseMap = [
        'country' => 'countryCode',
        'region'  => 'region',
        'city'    => 'city',
        'asn'     => 'as',
        'isp'     => 'isp',
    ];

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ip-api.com';
    }

    protected function isValidResponse(array $data): bool
    {
        return !empty($data) && ($data['status'] ?? '') === 'success';
    }

    protected function getErrorMessage(array $data): string
    {
        return 'ip-api.com: ' . ($data['message'] ?? 'invalid response');
    }
} 