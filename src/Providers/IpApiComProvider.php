<?php

namespace Bespredel\GeoRestrict\Providers;

class IpApiComProvider extends AbstractGeoProvider
{
    /**
     * @var string|null
     */
    protected ?string $baseUrl = 'http://ip-api.com/';

    /**
     * @var string|null
     */
    protected ?string $endpoint = 'json/:ip';

    /**
     * @var array|string[]
     */
    protected array $requiredParams = ['ip'];

    /**
     * @var array|string[]
     */
    protected array $optionalParams = ['lang'];

    /**
     * @var array|string[]
     */
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