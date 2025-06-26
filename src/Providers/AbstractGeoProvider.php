<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface;

abstract class AbstractGeoProvider implements GeoServiceProviderInterface
{
    /**
     * @var array Provider-specific options
     */
    protected array $options = [];

    /**
     * Set provider-specific options (e.g., API key, language).
     *
     * @param array $options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Map API response to standard geo array using a map array
     *
     * @param array $data API answer
     * @param array $map  Array of correspondences ['country' => 'country_code', ...]
     *
     * @return array
     */
    protected function mapByMap(array $data, array $map): array
    {
        $result = [];
        foreach ($map as $field => $path) {
            $result[$field] = data_get($data, $path);
        }
        return $result;
    }
} 