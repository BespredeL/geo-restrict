<?php

declare(strict_types=1);

namespace Bespredel\GeoRestrict\Providers;

class IpWhoIsProvider extends AbstractGeoProvider
{
    /**
     * @var string|null
     */
    protected ?string $baseUrl = 'https://ipwho.is/';

    /**
     * @var string|null
     */
    protected ?string $endpoint = ':ip';

    /**
     * @var array|string[]
     */
    protected array $requiredParams = ['ip'];

    /**
     * @var array|string[]
     */
    protected array $optionalParams = ['api_key'];

    /**
     * @var array|string[]
     */
    protected array $responseMap = [
        'country' => 'country_code',
        'region'  => 'region',
        'city'    => 'city',
        'asn'     => 'connection.asn',
        'isp'     => 'connection.isp',
    ];

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ipwho.is';
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
        return !empty($data) && !empty($data['success']);
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
        return 'ipwho.is: ' . ($data['message'] ?? 'invalid response');
    }
} 