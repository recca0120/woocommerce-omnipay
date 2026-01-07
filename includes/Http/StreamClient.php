<?php

namespace Recca0120\WooCommerce_Omnipay\Http;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Recca0120\WooCommerce_Omnipay\Exceptions\NetworkException;

/**
 * Stream HTTP Client for Omnipay
 *
 * 使用 PHP file_get_contents 與 stream context
 */
class StreamClient implements ClientInterface
{
    /**
     * @var array
     */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge(['timeout' => 30], $options);
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
        $httpHeaders = [];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = $name.': '.(is_array($value) ? implode(', ', $value) : $value);
        }

        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $httpHeaders),
                'timeout' => $this->options['timeout'],
                'protocol_version' => (float) $protocolVersion,
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $options['http']['content'] = is_resource($body) ? stream_get_contents($body) : (string) $body;
        }

        $context = stream_context_create($options);

        $responseBody = @file_get_contents((string) $uri, false, $context);

        if ($responseBody === false) {
            $error = error_get_last();

            throw new NetworkException($error['message'] ?? 'Unknown error');
        }

        [$statusCode, $reasonPhrase, $responseHeaders] = $this->parseResponseHeaders($http_response_header ?? []);

        return new Response(
            $statusCode,
            $responseHeaders,
            $responseBody,
            $protocolVersion,
            $reasonPhrase
        );
    }

    /**
     * 解析 HTTP response headers
     *
     * @param  array  $httpResponseHeader  PHP 的 $http_response_header 變數
     */
    private function parseResponseHeaders(array $httpResponseHeader): array
    {
        $statusCode = 200;
        $reasonPhrase = 'OK';
        $headers = [];

        foreach ($httpResponseHeader as $index => $line) {
            if ($index === 0) {
                // HTTP/1.1 200 OK
                if (preg_match('/^HTTP\/[\d.]+ (\d+) (.*)$/', $line, $matches)) {
                    $statusCode = (int) $matches[1];
                    $reasonPhrase = $matches[2];
                }

                continue;
            }

            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return [$statusCode, $reasonPhrase, $headers];
    }
}
