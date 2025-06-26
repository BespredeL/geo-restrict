<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Support\Facades\Http;

class IpApiCoProvider extends AbstractGeoProvider
{
    /**
     * Get geo data for the given IP using ipapi.co API.
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
        $url = 'https://ipapi.co/' . $ip . '/json/';
        if (!empty($this->options['lang'])) {
            $url .= '?lang=' . urlencode($this->options['lang']);
        }
        $response = Http::timeout(5)->get($url);
        if (!$response->successful()) {
            throw new GeoProviderException('ipapi.co: network error');
        }
        $data = $response->json();
        if (empty($data) || !empty($data['error'])) {
            throw new GeoProviderException('ipapi.co: invalid response');
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
            'region'  => $data['region_code'] ?? null,
            'city'    => $data['city'] ?? null,
            'asn'     => $data['asn'] ?? null,
            'isp'     => $data['org'] ?? null,
        ];
    }

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ipapi.co';
    }
} 