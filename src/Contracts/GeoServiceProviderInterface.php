<?php

namespace Bespredel\GeoRestrict\Contracts;

/**
 * Interface for geo service providers.
 * Implement this interface to provide geo data by IP address.
 */
interface GeoServiceProviderInterface
{
    /**
     * Get geo data for the given IP address.
     *
     * @param string $ip
     *
     * @return array|null Array with keys: country, region, city, asn, isp; or null on error
     */
    public function getGeoData(string $ip): ?array;

    /**
     * Get the provider name (for logging or diagnostics).
     *
     * @return string
     */
    public function getName(): string;
} 