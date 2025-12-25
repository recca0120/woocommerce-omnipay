<?php

namespace WooCommerceOmnipay\Tests\Unit\Http;

use Omnipay\Common\Http\ClientInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WooCommerceOmnipay\Http\NetworkException;
use WooCommerceOmnipay\Http\StreamClient;

class StreamClientTest extends TestCase
{
    public function test_implements_client_interface()
    {
        $client = new StreamClient;

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function test_default_timeout_is_30_seconds()
    {
        $client = new StreamClient;

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(30, $property->getValue($client));
    }

    public function test_custom_timeout()
    {
        $client = new StreamClient(60);

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(60, $property->getValue($client));
    }

    public function test_parse_response_headers_extracts_status_code()
    {
        $client = new StreamClient;
        $httpResponseHeader = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Custom: value',
        ];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals(200, $result[0]);
    }

    public function test_parse_response_headers_extracts_reason_phrase()
    {
        $client = new StreamClient;
        $httpResponseHeader = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
        ];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals('OK', $result[1]);
    }

    public function test_parse_response_headers_extracts_headers()
    {
        $client = new StreamClient;
        $httpResponseHeader = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Custom: value',
        ];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals([
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ], $result[2]);
    }

    public function test_parse_response_headers_handles_404_not_found()
    {
        $client = new StreamClient;
        $httpResponseHeader = [
            'HTTP/1.1 404 Not Found',
            'Content-Type: text/html',
        ];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals(404, $result[0]);
        $this->assertEquals('Not Found', $result[1]);
    }

    public function test_parse_response_headers_handles_header_with_colon_in_value()
    {
        $client = new StreamClient;
        $httpResponseHeader = [
            'HTTP/1.1 200 OK',
            'Location: https://example.com:8080/path',
        ];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals('https://example.com:8080/path', $result[2]['Location']);
    }

    public function test_parse_response_headers_defaults_to_200_ok()
    {
        $client = new StreamClient;
        $httpResponseHeader = [];

        $result = $this->invokePrivateMethod($client, 'parseResponseHeaders', [$httpResponseHeader]);

        $this->assertEquals(200, $result[0]);
        $this->assertEquals('OK', $result[1]);
    }

    /**
     * @group integration
     */
    public function test_request_to_real_server()
    {
        $client = new StreamClient(10);

        $response = $client->request('GET', 'https://httpbin.org/get');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('url', $body);
    }

    /**
     * @group integration
     */
    public function test_post_request_with_body()
    {
        $client = new StreamClient(10);

        $response = $client->request(
            'POST',
            'https://httpbin.org/post',
            ['Content-Type' => 'application/json'],
            json_encode(['test' => 'value'])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('{"test":"value"}', $body['data']);
    }

    /**
     * @group integration
     */
    public function test_request_with_custom_headers()
    {
        $client = new StreamClient(10);

        $response = $client->request(
            'GET',
            'https://httpbin.org/headers',
            ['X-Custom-Header' => 'test-value']
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('test-value', $body['headers']['X-Custom-Header']);
    }

    /**
     * @group integration
     */
    public function test_throws_network_exception_on_connection_failure()
    {
        $client = new StreamClient(1);

        $this->expectException(NetworkException::class);

        // 使用不可連接的 IP 地址來確保連線失敗
        $client->request('GET', 'http://10.255.255.1');
    }

    private function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
