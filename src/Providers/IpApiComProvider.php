<?php

namespace Bespredel\GeoRestrict\Providers;

class IpApiComProvider extends AbstractGeoProvider
{
    protected ?string $baseUrl        = 'http://ip-api.com/';
    protected ?string $endpoint       = 'json/:ip';
    protected array   $requiredParams = ['ip'];
    protected array   $optionalParams = ['lang'];
    protected array   $responseMap    = [
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

    /**
     * Check if response is valid
     *
     * @param array $data
     *
     * @return bool
     */
    protected function isValidResponse(array $data): bool
    {
        return !empty($data) && ($data['status'] ?? '') === 'success';
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
        return 'ip-api.com: ' . ($data['message'] ?? 'invalid response');
    }
} 