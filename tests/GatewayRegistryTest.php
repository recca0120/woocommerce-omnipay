<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\GatewayRegistry;
use WP_UnitTestCase;

/**
 * Test Gateway Registry
 *
 * 測試 GatewayRegistry 類別功能
 *
 * 配置格式：純陣列，每個元素包含：
 * - gateway: 必須指定的 Omnipay gateway 名稱
 * - gateway_id: 必須指定的 WooCommerce gateway ID
 * - 不需要 enabled 欄位，實際啟用由 WooCommerce 設定管理
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
            $this->registry->isAvailable('Dummy'),
            'Dummy should be available'
        );

        // ECPay 已安裝
        $this->assertTrue(
            $this->registry->isAvailable('ECPay'),
            'ECPay should be available'
        );

        // 不存在的 gateway
        $this->assertFalse(
            $this->registry->isAvailable('NonExistentGateway'),
            'NonExistentGateway should not be available'
        );
    }

    /**
     * 測試：從配置讀取 gateways，不需要 enabled 欄位
     */
    public function test_get_gateways_without_enabled_flag()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Dummy',
                ],
                [
                    'gateway' => 'ECPay',
                    'gateway_id' => 'ecpay',
                    'title' => '綠界金流',
                    'description' => '使用綠界金流付款',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(2, $gateways);
    }

    /**
     * 測試：多個 gateway 可以共用同一個 gateway 名稱
     */
    public function test_multiple_gateways_can_share_same_gateway()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'ECPay',
                    'gateway_id' => 'ecpay_credit',
                    'title' => 'ECPay 信用卡',
                ],
                [
                    'gateway' => 'ECPay',
                    'gateway_id' => 'ecpay_atm',
                    'title' => 'ECPay ATM',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(2, $gateways);

        // 兩個 gateway 共用 ECPay
        $this->assertEquals('ECPay', $gateways[0]['gateway']);
        $this->assertEquals('ECPay', $gateways[1]['gateway']);

        // gateway_id 各自獨立
        $this->assertEquals('ecpay_credit', $gateways[0]['gateway_id']);
        $this->assertEquals('ecpay_atm', $gateways[1]['gateway_id']);
    }

    /**
     * 測試：沒有指定 gateway 的會被過濾掉
     */
    public function test_gateways_without_gateway_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    // 沒有 gateway
                    'gateway_id' => 'invalid',
                    'title' => 'Invalid Gateway',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(1, $gateways);
        $this->assertEquals('dummy', $gateways[0]['gateway_id']);
    }

    /**
     * 測試：沒有指定 gateway_id 的會被過濾掉
     */
    public function test_gateways_without_gateway_id_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    'gateway' => 'ECPay',
                    // 沒有 gateway_id
                    'title' => 'Invalid Gateway',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(1, $gateways);
        $this->assertEquals('dummy', $gateways[0]['gateway_id']);
    }

    /**
     * 測試：Omnipay 不可用的 gateway 會被過濾掉
     */
    public function test_unavailable_gateways_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    'gateway' => 'NonExistentGateway',
                    'gateway_id' => 'nonexistent',
                    'title' => 'Non Existent',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(1, $gateways);
        $this->assertEquals('dummy', $gateways[0]['gateway_id']);
    }

    /**
     * 測試：gateway 資訊包含所有必要欄位
     */
    public function test_gateway_info_contains_required_fields()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'testgateway',
                    'title' => 'Test Title',
                    'description' => 'Test Description',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $gateway = $gateways[0];

        $this->assertArrayHasKey('gateway', $gateway);
        $this->assertArrayHasKey('gateway_id', $gateway);
        $this->assertArrayHasKey('title', $gateway);
        $this->assertArrayHasKey('description', $gateway);

        $this->assertEquals('Dummy', $gateway['gateway']);
        $this->assertEquals('testgateway', $gateway['gateway_id']);
        $this->assertEquals('Test Title', $gateway['title']);
        $this->assertEquals('Test Description', $gateway['description']);
    }

    /**
     * 測試：沒有 title 時使用 gateway 作為預設 title
     */
    public function test_generates_default_title_from_gateway()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'ECPay',
                    'gateway_id' => 'ecpay',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertEquals('ECPay', $gateways[0]['title']);
    }

    /**
     * 測試：沒有 description 時預設為空字串
     */
    public function test_description_defaults_to_empty_when_not_provided()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Test Payment',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertEmpty($gateways[0]['description']);
    }

    /**
     * 測試：config 傳入 description 時使用該值
     */
    public function test_uses_description_from_config()
    {
        $config = [
            'gateways' => [
                [
                    'gateway' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Test Payment',
                    'description' => 'Custom description',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertEquals('Custom description', $gateways[0]['description']);
    }
}
