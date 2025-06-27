<?php

namespace Bespredel\GeoRestrict\Providers;

use Bespredel\GeoRestrict\Contracts\GeoServiceProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractGeoProvider implements GeoServiceProviderInterface
{
    /**
     * @var array Provider-specific options
     */
    protected array $options = [];

    /**
     * @var string|null Базовый URL API
     */
    protected ?string $baseUrl = null;

    /**
     * @var string|null Endpoint-шаблон (может содержать {placeholders})
     */
    protected ?string $endpoint = null;

    /**
     * @var array Список обязательных параметров для endpoint
     */
    protected array $requiredParams = [];

    /**
     * @var array Список необязательных параметров для endpoint
     */
    protected array $optionalParams = [];

    /**
     * Карта соответствий для mapByMap
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
     * Build full URL: обязательные параметры в endpoint, необязательные — в query string
     *
     * @param array $params
     * @return string
     */
    protected function buildUrl(array $params = []): string
    {
        $url = ($this->baseUrl ?? '') . ($this->endpoint ?? '');

        // Подставляем обязательные параметры
        $url = preg_replace_callback('/:(\w+)/', function ($matches) use ($params) {
            $key = $matches[1];
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                Log::error("GeoProvider: required parameter '{$key}' is missing for endpoint " . static::class);
                throw new \InvalidArgumentException("Required parameter '{$key}' is missing for geo provider endpoint");
            }
            return urlencode($params[$key]);
        }, $url);

        // Собираем query string из необязательных поддерживаемых параметров
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
     * Универсальный метод получения гео-данных
     */
    public function getGeoData(string $ip): ?array
    {
        $params = $this->buildRequestParams($ip);
        $url = $this->buildUrl($params);
        $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
        $data = $response->json();
        if (!$response->successful() || !$this->isValidResponse($data)) {
            throw new \Bespredel\GeoRestrict\Exceptions\GeoProviderException($this->getErrorMessage($data));
        }
        
        return $this->mapByMap($data, $this->responseMap);
    }

    /**
     * Собрать параметры для запроса (можно переопределять в наследниках)
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
     * Проверка валидности ответа (переопределять в наследниках)
     */
    abstract protected function isValidResponse(array $data): bool;

    /**
     * Сообщение об ошибке (переопределять в наследниках)
     */
    abstract protected function getErrorMessage(array $data): string;
} 