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
 * - omnipay_name: 必須指定的 Omnipay gateway 名稱
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
     * 測試：從配置讀取 gateways，不需要 enabled 欄位
     */
    public function test_get_gateways_without_enabled_flag()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Dummy',
                ],
                [
                    'omnipay_name' => 'ECPay',
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
     * 測試：多個 gateway 可以共用同一個 omnipay_name
     */
    public function test_multiple_gateways_can_share_same_omnipay_name()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'ECPay',
                    'gateway_id' => 'ecpay_credit',
                    'title' => 'ECPay 信用卡',
                ],
                [
                    'omnipay_name' => 'ECPay',
                    'gateway_id' => 'ecpay_atm',
                    'title' => 'ECPay ATM',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertCount(2, $gateways);

        // 兩個 gateway 共用 ECPay 作為 omnipay_name
        $this->assertEquals('ECPay', $gateways[0]['omnipay_name']);
        $this->assertEquals('ECPay', $gateways[1]['omnipay_name']);

        // gateway_id 各自獨立
        $this->assertEquals('ecpay_credit', $gateways[0]['gateway_id']);
        $this->assertEquals('ecpay_atm', $gateways[1]['gateway_id']);
    }

    /**
     * 測試：沒有指定 omnipay_name 的 gateway 會被過濾掉
     */
    public function test_gateways_without_omnipay_name_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    // 沒有 omnipay_name
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
     * 測試：沒有指定 gateway_id 的 gateway 會被過濾掉
     */
    public function test_gateways_without_gateway_id_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    'omnipay_name' => 'ECPay',
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
    public function test_unavailable_omnipay_gateways_are_filtered()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Valid Gateway',
                ],
                [
                    'omnipay_name' => 'NonExistentOmnipayGateway',
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
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'testgateway',
                    'title' => 'Test Title',
                    'description' => 'Test Description',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $gateway = $gateways[0];

        $this->assertArrayHasKey('omnipay_name', $gateway);
        $this->assertArrayHasKey('gateway_id', $gateway);
        $this->assertArrayHasKey('title', $gateway);
        $this->assertArrayHasKey('description', $gateway);

        $this->assertEquals('Dummy', $gateway['omnipay_name']);
        $this->assertEquals('testgateway', $gateway['gateway_id']);
        $this->assertEquals('Test Title', $gateway['title']);
        $this->assertEquals('Test Description', $gateway['description']);
    }

    /**
     * 測試：沒有 title 時使用 omnipay_name 作為預設 title
     */
    public function test_generates_default_title_from_omnipay_name()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'ECPay',
                    'gateway_id' => 'ecpay',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertEquals('ECPay', $gateways[0]['title']);
    }

    /**
     * 測試：沒有 description 時產生預設 description
     */
    public function test_generates_default_description_when_not_provided()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Test Payment',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);
        $gateways = $registry->getGateways();

        $this->assertNotEmpty($gateways[0]['description']);
    }

    /**
     * 測試：getEnabledGateways 已棄用，回傳同 getGateways
     */
    public function test_get_enabled_gateways_is_alias_for_get_gateways()
    {
        $config = [
            'gateways' => [
                [
                    'omnipay_name' => 'Dummy',
                    'gateway_id' => 'dummy',
                    'title' => 'Test',
                ],
            ],
        ];

        $registry = new GatewayRegistry($config);

        $this->assertEquals(
            $registry->getGateways(),
            $registry->getEnabledGateways()
        );
    }
}
