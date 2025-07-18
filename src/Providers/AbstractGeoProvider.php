<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface;
use Bespredel\GeoRestrict\Exceptions\GeoProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Bespredel\GeoRestrict\Services\GeoLoggerTrait;

abstract class AbstractGeoProvider implements GeoServiceProviderInterface
{
    use GeoLoggerTrait;

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

        // Replace placeholders with actual values
        $url = preg_replace_callback('/:(\w+)/', function ($matches) use ($params) {
            $key = $matches[1];
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                $this->geoLogger()->error("GeoProvider: required parameter '{$key}' is missing for endpoint " . static::class);
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
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address provided.");
        }

        $params = $this->buildRequestParams($ip);
        $url = $this->buildUrl($params);

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;
        if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new GeoProviderException("Disallowed host for geo provider: {$host}");
            }
        }

        try {
            $response = Http::timeout(5)->get($url);
        }
        catch (ConnectionException $e) {
            $this->geoLogger()->error("GeoProvider: connection error in " . static::class . " - " . $e->getMessage());
            throw $e;
        }
        catch (\Throwable $e) {
            $this->geoLogger()->error("GeoProvider: unexpected error in " . static::class . " - " . $e->getMessage());
            throw new GeoProviderException("Unexpected error occurred while requesting geo data.");
        }

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