<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Integration\Http;

use Recca0120\WooCommerce_Omnipay\Exceptions\NetworkException;
use Recca0120\WooCommerce_Omnipay\Http\StreamClient;

class StreamClientTest extends HttpClientTestCase
{
    public function test_it_sends_get_request()
    {
        $client = new StreamClient;

        $response = $client->request('GET', $this->getServerUrl('/api/test'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('GET', $body['method']);
        $this->assertEquals('/api/test', $body['uri']);
    }

    public function test_it_sends_post_request_with_body()
    {
        $client = new StreamClient;

        $response = $client->request(
            'POST',
            $this->getServerUrl('/api/test'),
            ['Content-Type' => 'application/json'],
            '{"data":"test"}'
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('POST', $body['method']);
        $this->assertEquals('{"data":"test"}', $body['body']);
    }

    public function test_it_sends_request_headers()
    {
        $client = new StreamClient;

        $response = $client->request('GET', $this->getServerUrl('/'), [
            'Accept' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ]);

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('application/json', $body['headers']['ACCEPT']);
        $this->assertEquals('custom-value', $body['headers']['X-CUSTOM-HEADER']);
    }

    public function test_it_handles_error_status_codes()
    {
        $client = new StreamClient;

        $response404 = $client->request('GET', $this->getServerUrl('/status/404'));
        $this->assertEquals(404, $response404->getStatusCode());

        $response500 = $client->request('GET', $this->getServerUrl('/status/500'));
        $this->assertEquals(500, $response500->getStatusCode());
    }

    public function test_it_throws_exception_on_connection_error()
    {
        $this->expectException(NetworkException::class);

        $client = new StreamClient(['timeout' => 1]);

        $client->request('GET', 'http://localhost:59999/not-exist');
    }

    public function test_it_respects_timeout_option()
    {
        $this->expectException(NetworkException::class);

        $client = new StreamClient(['timeout' => 1]);

        $client->request('GET', $this->getServerUrl('/delay'));
    }
}
