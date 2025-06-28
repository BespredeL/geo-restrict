<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface;
use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractGeoProvider implements GeoServiceProviderInterface
{
    /**
     * Provider-specific options
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Base URL
     *
     * @var string|null
     */
    protected ?string $baseUrl = null;

    /**
     * Endpoint
     *
     * @var string|null
     */
    protected ?string $endpoint = null;

    /**
     * List of required parameters
     *
     * @var array
     */
    protected array $requiredParams = [];

    /**
     * List of optional parameters
     *
     * @var array
     */
    protected array $optionalParams = [];

    /**
     * List of response map
     *
     * @var array
     */
    protected array $responseMap = [];

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
     * Build full URL: base URL + endpoint + query string
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildUrl(array $params = []): string
    {
        $url = ($this->baseUrl ?? '') . ($this->endpoint ?? '');

        // We replace placeholders with actual values
        $url = preg_replace_callback('/:(\w+)/', function ($matches) use ($params) {
            $key = $matches[1];
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                Log::error("GeoProvider: required parameter '{$key}' is missing for endpoint " . static::class);
                throw new \InvalidArgumentException("Required parameter '{$key}' is missing for geo provider endpoint");
            }
            return urlencode($params[$key]);
        }, $url);

        // Collect Query String from optional supported parameters
        $query = [];
        foreach ($this->optionalParams as $key) {
            if (isset($params[$key]) && $params[$key] !== '' && $params[$key] !== null) {
                $query[$key] = $params[$key];
            }
        }

        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return rtrim($url, '&?');
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

    /**
     * Universal method to get geo data
     *
     * @param string $ip
     *
     * @return array|null
     *
     * @throws GeoProviderException
     * @throws ConnectionException
     */
    public function getGeoData(string $ip): ?array
    {
        $params = $this->buildRequestParams($ip);
        $url = $this->buildUrl($params);
        $response = Http::timeout(5)->get($url);
        $data = $response->json();
        if (!$response->successful() || !$this->isValidResponse($data)) {
            throw new GeoProviderException($this->getErrorMessage($data));
        }

        return $this->mapByMap($data, $this->responseMap);
    }

    /**
     * Build request parameters
     *
     * @param string $ip
     *
     * @return array
     */
    protected function buildRequestParams(string $ip): array
    {
        $params = ['ip' => $ip];
        foreach ($this->optionalParams as $key) {
            if (isset($this->options[$key])) {
                $params[$key] = $this->options[$key];
            }
        }

        foreach ($this->requiredParams as $key) {
            if ($key !== 'ip' && isset($this->options[$key])) {
                $params[$key] = $this->options[$key];
            }
        }

        return $params;
    }

    /**
     * Check if response is valid
     *
     * @param array $data
     *
     * @return bool
     */
    abstract protected function isValidResponse(array $data): bool;

    /**
     * Get error message
     *
     * @param array $data
     *
     * @return string
     */
    abstract protected function getErrorMessage(array $data): string;
} 