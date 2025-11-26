<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\GatewayRegistry;
use WP_UnitTestCase;

/**
 * Test Gateway Registry
 *
 * 測試 GatewayRegistry 類別功能
 */
class GatewayRegistryTest extends WP_UnitTestCase
{
    protected $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new GatewayRegistry;
    }

    /**
     * 測試：能夠驗證 gateway 是否已安裝
     */
    public function test_can_verify_gateway_availability()
    {
        // Dummy 已安裝（在 composer require-dev）
        $this->assertTrue(
            $this->registry->isGatewayAvailable('Dummy'),
            'Dummy should be available'
        );

        // ECPay 已安裝
        $this->assertTrue(
            $this->registry->isGatewayAvailable('ECPay'),
            'ECPay should be available'
        );

        // 不存在的 gateway
        $this->assertFalse(
            $this->registry->isGatewayAvailable('NonExistentGateway'),
            'NonExistentGateway should not be available'
        );
    }

    /**
     * 測試：從配置讀取啟用的 gateways，包含必要資訊且格式正確
     */
    public function test_get_enabled_gateways_from_config()
    {
        $config = [
            'gateways' => [
                'Dummy' => [
                    'enabled' => true,
                    'title' => 'Dummy',
                ],
                'ECPay' => [
                    'enabled' => true,
                    'title' => '綠界金流',
                    'description' => '使用綠界金流付款',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getEnabledGateways();

        // 驗證數量和內容
        $this->assertCount(2, $gateways);
        $this->assertArrayHasKey('Dummy', $gateways);
        $this->assertArrayHasKey('ECPay', $gateways);

        // 驗證配置覆寫
        $this->assertEquals('綠界金流', $gateways['ECPay']['title']);
        $this->assertEquals('使用綠界金流付款', $gateways['ECPay']['description']);

        // 驗證所有 gateway 都有必要的欄位和正確格式
        foreach ($gateways as $name => $info) {
            $this->assertArrayHasKey('omnipay_name', $info, "Gateway $name should have omnipay_name");
            $this->assertArrayHasKey('title', $info, "Gateway $name should have title");
            $this->assertArrayHasKey('description', $info, "Gateway $name should have description");
            $this->assertArrayHasKey('gateway_id', $info, "Gateway $name should have gateway_id");

            $this->assertMatchesRegularExpression(
                '/^omnipay_[a-z0-9_]+$/',
                $info['gateway_id'],
                'Gateway ID should be lowercase with underscores and start with omnipay_'
            );
        }

        // 驗證未配置的 gateway 不會出現
        $this->assertArrayNotHasKey('NonExistent', $gateways);
    }
}
