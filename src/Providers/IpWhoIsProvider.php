<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;

class IpWhoIsProvider extends AbstractGeoProvider
{
    /**
     * Get geo data for the given IP using ipwho.is API.
     * Supports 'api_key' in options (if required by service).
     *
     * @param string $ip
     *
     * @return array|null Array with keys: country, region, city, asn, isp; or null on error
     *
     * @throws GeoProviderException
     */
    public function getGeoData(string $ip): ?array
    {
        $url = 'https://ipwho.is/' . $ip;
        // If api_key is set in options, add it as a query param
        if (!empty($this->options['api_key'])) {
            $url .= '?api_key=' . urlencode($this->options['api_key']);
        }

        $response = Http::timeout(5)->get($url);
        if (!$response->successful()) {
            throw new GeoProviderException('ipwho.is: network error');
        }

        $data = $response->json();
        if (empty($data) || empty($data['success'])) {
            throw new GeoProviderException('ipwho.is: invalid response');
        }

        return $this->mapResponse($data);
    }

    /**
     * Map API response to standard geo array
     *
     * @param array $data API answer
     *
     * @return array
     */
    protected function mapResponse(array $data): array
    {
        return [
            'country' => $data['country_code'] ?? null,
            'region'  => $data['region'] ?? null,
            'city'    => $data['city'] ?? null,
            'asn'     => $data['connection']['asn'] ?? null,
            'isp'     => $data['connection']['isp'] ?? null,
        ];
    }

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ipwho.is';
    }
} 