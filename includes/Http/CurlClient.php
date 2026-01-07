<?php

namespace Recca0120\WooCommerce_Omnipay\Http;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Recca0120\WooCommerce_Omnipay\Exceptions\NetworkException;

/**
 * Curl HTTP Client for Omnipay
 *
 * 使用 PHP curl 擴充功能
 */
class CurlClient implements ClientInterface
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
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => (string) $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->options['timeout'],
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTP_VERSION => $protocolVersion === '1.0' ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1,
        ]);

        if (! empty($headers)) {
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                $curlHeaders[] = $name.': '.(is_array($value) ? implode(', ', $value) : $value);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        if ($body !== null) {
            $bodyContent = is_resource($body) ? stream_get_contents($body) : (string) $body;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new NetworkException($error);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerString = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        [$reasonPhrase, $responseHeaders] = $this->parseHeaders($headerString);

        return new Response(
            $statusCode,
            $responseHeaders,
            $responseBody,
            $protocolVersion,
            $reasonPhrase
        );
    }

    /**
     * 解析 HTTP headers
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $reasonPhrase = '';
        $lines = explode("\r\n", trim($headerString));

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                // HTTP/1.1 200 OK
                if (preg_match('/^HTTP\/[\d.]+ \d+ (.*)$/', $line, $matches)) {
                    $reasonPhrase = $matches[1];
                }

                continue;
            }

            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return [$reasonPhrase, $headers];
    }
}
