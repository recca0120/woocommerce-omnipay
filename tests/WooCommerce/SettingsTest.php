<?php

namespace WooCommerceOmnipay\Tests\WooCommerce;

use WP_UnitTestCase;

/**
 * Gateway Settings Tests
 *
 * 測試 Gateway 設定管理與效果驗證，包含 Gateway 註冊與初始化
 */
class SettingsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 確保 WooCommerce payment gateways 已初始化
        if (empty(WC()->payment_gateways()->payment_gateways)) {
            WC()->payment_gateways()->init();
        }
    }

    // ==================== 註冊測試 ====================

    /**
     * 測試：配置的 gateway 註冊到 WooCommerce 並能建立 Omnipay 實例
     */
    public function test_configured_gateways_registered_and_create_omnipay_instance()
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();

        // 驗證預設配置的 gateways 都註冊了
        $this->assertArrayHasKey('omnipay_dummy', $payment_gateways);
        $this->assertArrayHasKey('omnipay_ecpay', $payment_gateways);

        // 驗證 gateway 屬性
        $gateway = $payment_gateways['omnipay_dummy'];
        $this->assertInstanceOf('WC_Payment_Gateway', $gateway);
        $this->assertEquals('omnipay_dummy', $gateway->id);
        $this->assertNotEmpty($gateway->method_title);
        $this->assertNotEmpty($gateway->method_description);

        // 驗證能建立 Omnipay 實例
        $omnipayGateway = $gateway->get_omnipay_gateway();
        $this->assertInstanceOf(
            \Omnipay\Dummy\Gateway::class,
            $omnipayGateway,
            'Should create Omnipay Dummy Gateway instance'
        );
    }

    /**
     * 測試：ECPay Gateway 有從 Omnipay 取得的參數欄位
     */
    public function test_ecpay_gateway_has_parameters_from_omnipay()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];
        $form_fields = $gateway->form_fields;

        // 驗證基本欄位
        $this->assertArrayHasKey('enabled', $form_fields);
        $this->assertArrayHasKey('title', $form_fields);
        $this->assertArrayHasKey('description', $form_fields);

        // 驗證 Omnipay ECPay 特定參數
        $this->assertArrayHasKey('MerchantID', $form_fields, 'Should have MerchantID field from Omnipay');
        $this->assertArrayHasKey('HashKey', $form_fields, 'Should have HashKey field from Omnipay');
        $this->assertArrayHasKey('HashIV', $form_fields, 'Should have HashIV field from Omnipay');
    }

    // ==================== 設定測試 ====================

    /**
     * 測試：表單欄位可以安全渲染且結構正確
     */
    public function test_form_fields_are_valid_and_renderable()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_dummy'];

        // 1. 測試表單可以安全渲染
        ob_start();
        try {
            $gateway->admin_options();
            $output = ob_get_clean();

            $this->assertIsString($output);
            $this->assertNotEmpty($output);
            // Dummy gateway 只驗證有輸出即可
        } catch (\Exception $e) {
            ob_end_clean();
            $this->fail('Form rendering failed: '.$e->getMessage());
        }

        // 2. 測試所有欄位值都是有效類型
        foreach ($gateway->form_fields as $key => $field) {
            // 檢查 default 值
            if (isset($field['default'])) {
                $this->assertTrue(
                    is_string($field['default']) || is_numeric($field['default']),
                    sprintf('Field "%s" default must be string/numeric, got: %s', $key, gettype($field['default']))
                );
            }

            // 檢查字串欄位
            foreach (['description', 'title', 'label'] as $string_field) {
                if (isset($field[$string_field])) {
                    $this->assertIsString(
                        $field[$string_field],
                        sprintf('Field "%s" %s must be string', $key, $string_field)
                    );
                }
            }

            // 3. 檢查 select 欄位結構
            if ($field['type'] === 'select') {
                $this->assertArrayHasKey('options', $field, sprintf('Select field "%s" must have options', $key));
                $this->assertIsArray($field['options'], sprintf('Field "%s" options must be array', $key));

                foreach ($field['options'] as $option_key => $option_value) {
                    $this->assertTrue(
                        is_string($option_key) || is_numeric($option_key),
                        sprintf('Field "%s" option key must be string/numeric', $key)
                    );
                    $this->assertIsString(
                        $option_value,
                        sprintf('Field "%s" option value must be string', $key)
                    );
                }
            }
        }
    }

    /**
     * 測試：模擬表單提交並驗證設定被儲存
     */
    public function test_settings_can_be_saved_via_form_submission()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];

        // 清除現有設定
        delete_option('woocommerce_'.$gateway->id.'_settings');

        // 模擬 POST 資料
        $_POST = [
            'woocommerce_'.$gateway->id.'_enabled' => 'yes',
            'woocommerce_'.$gateway->id.'_title' => 'Test ECPay',
            'woocommerce_'.$gateway->id.'_description' => 'Test Description',
            'woocommerce_'.$gateway->id.'_MerchantID' => 'test_merchant',
            'woocommerce_'.$gateway->id.'_HashKey' => 'test_key',
            'woocommerce_'.$gateway->id.'_HashIV' => 'test_iv',
        ];

        // 執行儲存（WooCommerce 會調用 process_admin_options）
        $gateway->process_admin_options();

        // 重新載入 gateway 以讀取儲存的設定
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'title' => 'ECPay',
            'description' => '綠界金流',
            'omnipay_name' => 'ECPay',
        ]);

        // 驗證設定已儲存
        $this->assertEquals('yes', $reloaded_gateway->get_option('enabled'));
        $this->assertEquals('Test ECPay', $reloaded_gateway->get_option('title'));
        $this->assertEquals('Test Description', $reloaded_gateway->get_option('description'));
        $this->assertEquals('test_merchant', $reloaded_gateway->get_option('MerchantID'));
        $this->assertEquals('test_key', $reloaded_gateway->get_option('HashKey'));
        $this->assertEquals('test_iv', $reloaded_gateway->get_option('HashIV'));

        // 清理
        $_POST = [];
        delete_option('woocommerce_'.$gateway->id.'_settings');
    }

    /**
     * 測試：checkbox 欄位正確儲存
     */
    public function test_checkbox_fields_save_correctly()
    {
        $gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];

        // 清除現有設定
        delete_option('woocommerce_'.$gateway->id.'_settings');

        // 測試勾選的情況
        $_POST = [
            'woocommerce_'.$gateway->id.'_testMode' => 'yes',
        ];

        $gateway->process_admin_options();
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'omnipay_name' => 'ECPay',
        ]);
        $this->assertEquals('yes', $reloaded_gateway->get_option('testMode'));

        // 測試未勾選的情況（checkbox 未勾選時不會出現在 POST 資料中）
        $_POST = [];

        $gateway->process_admin_options();
        $reloaded_gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'omnipay_name' => 'ECPay',
        ]);
        $this->assertEquals('no', $reloaded_gateway->get_option('testMode'));

        // 清理
        $_POST = [];
        delete_option('woocommerce_'.$gateway->id.'_settings');
    }

    /**
     * 測試：ECPay Gateway 設定參數能傳遞給 Omnipay
     */
    public function test_ecpay_gateway_passes_settings_to_omnipay()
    {
        // 設定 ECPay 參數
        $settings = [
            'MerchantID' => 'test_merchant_id',
            'HashKey' => 'test_hash_key',
            'HashIV' => 'test_hash_iv',
        ];
        update_option('woocommerce_omnipay_ecpay_settings', $settings);

        // 重新建立 gateway
        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'omnipay_name' => 'ECPay',
        ]);
        $omnipayGateway = $gateway->get_omnipay_gateway();

        // 驗證參數正確傳遞
        $this->assertEquals('test_merchant_id', $omnipayGateway->getMerchantID());
        $this->assertEquals('test_hash_key', $omnipayGateway->getHashKey());
        $this->assertEquals('test_hash_iv', $omnipayGateway->getHashIV());

        // 清理
        delete_option('woocommerce_omnipay_ecpay_settings');
    }

    /**
     * 測試：不同 Gateway 的設定互不影響
     */
    public function test_different_gateways_have_separate_settings()
    {
        // 設定 Dummy gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'title' => 'Dummy Title',
        ]);

        // 設定 ECPay gateway
        update_option('woocommerce_omnipay_ecpay_settings', [
            'title' => 'ECPay Title',
            'MerchantID' => 'test_merchant',
        ]);

        // 建立兩個 gateway 實例
        $dummyGateway = new \WooCommerceOmnipay\Gateways\DummyGateway([
            'gateway_id' => 'dummy',
            'omnipay_name' => 'Dummy',
        ]);
        $ecpayGateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => 'ecpay',
            'omnipay_name' => 'ECPay',
        ]);

        // 驗證設定獨立
        $this->assertEquals('Dummy Title', $dummyGateway->title);
        $this->assertEquals('ECPay Title', $ecpayGateway->title);

        // ECPay 有 MerchantID，Dummy 沒有
        $this->assertEquals('test_merchant', $ecpayGateway->get_option('MerchantID'));
        $this->assertEmpty($dummyGateway->get_option('MerchantID'));
    }

    /**
     * 測試：Gateway 在結帳時可用
     */
    public function test_gateway_is_available_at_checkout()
    {
        // 啟用 Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'yes',
        ]);

        // 重新載入 payment gateways
        WC()->payment_gateways()->init();

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        // 驗證 Dummy Gateway 在可用列表中
        $this->assertArrayHasKey('omnipay_dummy', $available_gateways);
    }

    /**
     * 測試：Gateway 可以被停用
     */
    public function test_gateway_can_be_disabled()
    {
        // 停用 Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'no',
        ]);

        // 重新載入 payment gateways
        WC()->payment_gateways()->init();

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        // 驗證 Dummy Gateway 不在可用列表中
        $this->assertArrayNotHasKey('omnipay_dummy', $available_gateways);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        delete_option('woocommerce_omnipay_dummy_settings');
        delete_option('woocommerce_omnipay_ecpay_settings');
        parent::tearDown();
    }
}
