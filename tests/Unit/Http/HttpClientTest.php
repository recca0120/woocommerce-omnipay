<?php

namespace WooCommerceOmnipay\Tests\Unit\Http;

use Omnipay\Common\Http\ClientInterface;
use WooCommerceOmnipay\Http\HttpClient;
use WooCommerceOmnipay\Http\NetworkException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * HttpClient Test (WordPress)
 *
 * 需要 WordPress 環境執行
 */
class HttpClientTest extends TestCase
{
    public function test_implements_client_interface()
    {
        $client = new HttpClient;

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    /**
     * @group integration
     */
    public function test_request_to_real_server()
    {
        $client = new HttpClient;

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
        $client = new HttpClient;

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
        $client = new HttpClient;

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
        $client = new HttpClient;

        $this->expectException(NetworkException::class);

        // 使用不可連接的 IP 地址來確保連線失敗
        $client->request('GET', 'http://10.255.255.1');
    }
}
