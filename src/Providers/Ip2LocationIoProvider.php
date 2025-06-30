<?php

namespace Bespredel\GeoRestrict\Providers;

class Ip2LocationIoProvider extends AbstractGeoProvider
{
    /**
     * @var string|null
     */
    protected ?string $baseUrl = 'https://api.ip2location.io/';

    /**
     * @var string|null
     */
    protected ?string $endpoint = '?key=:api_key&ip=:ip';

    /**
     * @var array|string[]
     */
    protected array $requiredParams = ['api_key', 'ip'];

    /**
     * @var array|string[]
     */
    protected array $optionalParams = ['lang'];

    /**
     * @var array|string[]
     */
    protected array $responseMap = [
        'country' => 'country_code',
        'region'  => 'region_name',
        'city'    => 'city_name',
        'asn'     => 'as_number',
        'isp'     => 'isp',
    ];

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ip2location.io';
    }

    /**
     * Check if the response is valid.
     *
     * @param array $data
     *
     * @return bool
     */
    protected function isValidResponse(array $data): bool
    {
        return !isset($data['response']) && !empty($data['country_code']);
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
        if (isset($data['response']) && $data['response'] === 'INVALID ACCOUNT') {
            return 'ip2location.io: invalid API key';
        }

        return 'ip2location.io: invalid response';
    }
} 