<?php

namespace WooCommerceOmnipay\Tests\Unit\Http;

use Omnipay\Common\Http\ClientInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WooCommerceOmnipay\Http\CurlClient;
use WooCommerceOmnipay\Http\NetworkException;

class CurlClientTest extends TestCase
{
    public function test_implements_client_interface()
    {
        $client = new CurlClient;

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function test_default_timeout_is_30_seconds()
    {
        $client = new CurlClient;

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(30, $property->getValue($client));
    }

    public function test_custom_timeout()
    {
        $client = new CurlClient(60);

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);

        $this->assertEquals(60, $property->getValue($client));
    }

    public function test_parse_headers_extracts_reason_phrase()
    {
        $client = new CurlClient;
        $headerString = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Custom: value\r\n";

        $result = $this->invokePrivateMethod($client, 'parseHeaders', [$headerString]);

        $this->assertEquals('OK', $result[0]);
    }

    public function test_parse_headers_extracts_headers()
    {
        $client = new CurlClient;
        $headerString = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Custom: value\r\n";

        $result = $this->invokePrivateMethod($client, 'parseHeaders', [$headerString]);

        $this->assertEquals([
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ], $result[1]);
    }

    public function test_parse_headers_handles_404_not_found()
    {
        $client = new CurlClient;
        $headerString = "HTTP/1.1 404 Not Found\r\nContent-Type: text/html\r\n";

        $result = $this->invokePrivateMethod($client, 'parseHeaders', [$headerString]);

        $this->assertEquals('Not Found', $result[0]);
    }

    public function test_parse_headers_handles_header_with_colon_in_value()
    {
        $client = new CurlClient;
        $headerString = "HTTP/1.1 200 OK\r\nLocation: https://example.com:8080/path\r\n";

        $result = $this->invokePrivateMethod($client, 'parseHeaders', [$headerString]);

        $this->assertEquals('https://example.com:8080/path', $result[1]['Location']);
    }

    /**
     * @group integration
     */
    public function test_request_to_real_server()
    {
        $client = new CurlClient(10);

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
        $client = new CurlClient(10);

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
        $client = new CurlClient(10);

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
        $client = new CurlClient(1);

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
