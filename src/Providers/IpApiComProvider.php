<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;

class IpApiComProvider extends AbstractGeoProvider
{
    /**
     * Get geo data for the given IP using ip-api.com API.
     * Supports 'lang' in options (if required by service).
     *
     * @param string $ip
     *
     * @return array|null Array with keys: country, region, city, asn, isp; or null on error
     *
     * @throws GeoProviderException
     */
    public function getGeoData(string $ip): ?array
    {
        $url = 'http://ip-api.com/json/' . $ip;
        // If lang is set in options, add it as a query param
        if (!empty($this->options['lang'])) {
            $url .= '?lang=' . urlencode($this->options['lang']);
        }

        $response = Http::timeout(5)->get($url);
        if (!$response->successful()) {
            throw new GeoProviderException('ip-api.com: network error');
        }

        $data = $response->json();
        if (empty($data) || ($data['status'] ?? '') !== 'success') {
            throw new GeoProviderException('ip-api.com: invalid response');
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
            'country' => $data['countryCode'] ?? null,
            'region'  => $data['region'] ?? null,
            'city'    => $data['city'] ?? null,
            'asn'     => $data['as'] ?? null,
            'isp'     => $data['isp'] ?? null,
        ];
    }

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ip-api.com';
    }
} 