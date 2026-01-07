<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Unit;

use Nyholm\Psr7\Response;
use Omnipay\Common\Http\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test HTTP Client
 *
 * 用於單元測試的 HTTP Client，不需要實際網路請求
 */
class TestHttpClient implements ClientInterface
{
    /**
     * @var ResponseInterface|null
     */
    private $response;

    /**
     * @var array
     */
    private $requests = [];

    /**
     * 設定要返回的 Response
     */
    public function setResponse(ResponseInterface $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * 取得所有請求
     */
    public function getRequests(): array
    {
        return $this->requests;
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
        $this->requests[] = [
            'method' => $method,
            'uri' => $uri,
            'headers' => $headers,
            'body' => $body,
        ];

        return $this->response ?? new Response(200, [], '');
    }
}
