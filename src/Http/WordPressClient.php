<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Http;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use OmnipayTaiwan\WooCommerce_Omnipay\Exceptions\NetworkException;

/**
 * WordPress HTTP Client for Omnipay
 *
 * 使用 WordPress 內建的 wp_remote_request() 取代 php-http/curl-client
 */
class WordPressClient implements ClientInterface
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
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => $this->options['timeout'],
            'httpversion' => $protocolVersion,
        ];

        if ($body !== null) {
            $args['body'] = is_resource($body) ? stream_get_contents($body) : (string) $body;
        }

        $response = wp_remote_request((string) $uri, $args);

        if (is_wp_error($response)) {
            throw new NetworkException($response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $reasonPhrase = wp_remote_retrieve_response_message($response);
        $responseHeaders = wp_remote_retrieve_headers($response)->getAll();
        $responseBody = wp_remote_retrieve_body($response);

        return new Response(
            $statusCode,
            $responseHeaders,
            $responseBody,
            $protocolVersion,
            $reasonPhrase
        );
    }
}
