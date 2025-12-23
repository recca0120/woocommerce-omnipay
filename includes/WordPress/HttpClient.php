<?php

namespace WooCommerceOmnipay\WordPress;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * WordPress HTTP Client for Omnipay
 *
 * 使用 WordPress 的 wp_remote_request() 實作 Omnipay HTTP Client
 */
class HttpClient implements ClientInterface
{
    /**
     * @var array
     */
    private $defaultArgs;

    /**
     * @param  array  $defaultArgs  預設的 wp_remote_request 參數
     */
    public function __construct(array $defaultArgs = [])
    {
        $this->defaultArgs = array_merge([
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => true,
        ], $defaultArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ): ResponseInterface {
        $args = array_merge($this->defaultArgs, [
            'method' => strtoupper($method),
            'headers' => $this->prepareHeaders($headers),
            'body' => $this->prepareBody($body),
            'httpversion' => $protocolVersion,
        ]);

        $response = wp_remote_request((string) $uri, $args);

        if (is_wp_error($response)) {
            throw new NetworkException($response->get_error_message());
        }

        return $this->createResponse($response);
    }

    /**
     * 準備請求 headers
     *
     * @param  array  $headers  PSR-7 格式的 headers
     * @return array WordPress 格式的 headers
     */
    private function prepareHeaders(array $headers): array
    {
        $prepared = [];

        foreach ($headers as $name => $value) {
            // PSR-7 headers 可能是陣列
            $prepared[$name] = is_array($value) ? implode(', ', $value) : $value;
        }

        return $prepared;
    }

    /**
     * 準備請求 body
     *
     * @param  mixed  $body
     */
    private function prepareBody($body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        // StreamInterface
        if (is_object($body) && method_exists($body, '__toString')) {
            return (string) $body;
        }

        // Resource
        if (is_resource($body)) {
            return stream_get_contents($body);
        }

        return null;
    }

    /**
     * 建立 PSR-7 Response
     *
     * @param  array  $response  WordPress response
     */
    private function createResponse(array $response): ResponseInterface
    {
        $statusCode = wp_remote_retrieve_response_code($response);
        $headers = $this->extractHeaders(wp_remote_retrieve_headers($response));
        $body = wp_remote_retrieve_body($response);

        return new Response($statusCode, $headers, $body);
    }

    /**
     * 提取 response headers
     *
     * @param  mixed  $responseHeaders  WordPress headers (array 或 Requests_Utility_CaseInsensitiveDictionary)
     */
    private function extractHeaders($responseHeaders): array
    {
        // Handle Requests_Utility_CaseInsensitiveDictionary
        if (is_object($responseHeaders) && method_exists($responseHeaders, 'getAll')) {
            return $responseHeaders->getAll();
        }

        return (array) $responseHeaders;
    }
}
