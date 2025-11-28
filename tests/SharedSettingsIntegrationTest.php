<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\Gateways\OmnipayGateway;
use WooCommerceOmnipay\SharedSettings;
use WP_UnitTestCase;

/**
 * 測試 OmnipayGateway 與 SharedSettings 整合
 */
class SharedSettingsIntegrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('woocommerce_omnipay_should_exit', '__return_false');
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        delete_option('woocommerce_omnipay_ecpay_credit_settings');
        delete_option('woocommerce_omnipay_ecpay_atm_settings');
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        parent::tearDown();
    }

    public function test_gateway_reads_omnipay_parameters_from_shared_settings()
    {
        // 設定共用設定
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => '2000132',
            'HashKey' => 'shared_key',
            'HashIV' => 'shared_iv',
            'testMode' => 'yes',
        ]);

        $gateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_credit',
            'title' => 'ECPay 信用卡',
            'omnipay_name' => 'ECPay',
        ]);

        $omnipayGateway = $gateway->get_omnipay_gateway();

        $this->assertEquals('2000132', $omnipayGateway->getMerchantID());
        $this->assertEquals('shared_key', $omnipayGateway->getHashKey());
        $this->assertEquals('shared_iv', $omnipayGateway->getHashIV());
    }

    public function test_gateway_setting_overrides_shared_settings_for_plugin_options()
    {
        // 共用設定
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => '2000132',
            'HashKey' => 'shared_key',
            'HashIV' => 'shared_iv',
            'transaction_id_prefix' => 'SHARED_',
        ]);

        // Gateway 自己的設定覆寫 transaction_id_prefix
        update_option('woocommerce_omnipay_ecpay_credit_settings', [
            'transaction_id_prefix' => 'CREDIT_',
        ]);

        $gateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_credit',
            'title' => 'ECPay 信用卡',
            'omnipay_name' => 'ECPay',
        ]);

        // Omnipay 參數應該來自共用設定
        $omnipayGateway = $gateway->get_omnipay_gateway();
        $this->assertEquals('2000132', $omnipayGateway->getMerchantID());

        // transaction_id_prefix: Gateway 設定 > 共用設定
        $this->assertEquals('CREDIT_', $gateway->get_effective_option('transaction_id_prefix'));
    }

    public function test_plugin_option_falls_back_to_shared_settings()
    {
        // 共用設定有 transaction_id_prefix
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'transaction_id_prefix' => 'SHARED_',
        ]);

        // Gateway 沒有設定 transaction_id_prefix
        update_option('woocommerce_omnipay_ecpay_credit_settings', []);

        $gateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_credit',
            'title' => 'ECPay 信用卡',
            'omnipay_name' => 'ECPay',
        ]);

        // 應該 fallback 到共用設定
        $this->assertEquals('SHARED_', $gateway->get_effective_option('transaction_id_prefix'));
    }

    public function test_multiple_gateways_share_same_omnipay_settings()
    {
        // 設定共用設定
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => '2000132',
            'HashKey' => 'shared_key',
            'HashIV' => 'shared_iv',
        ]);

        $creditGateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_credit',
            'title' => 'ECPay 信用卡',
            'omnipay_name' => 'ECPay',
        ]);

        $atmGateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_atm',
            'title' => 'ECPay ATM',
            'omnipay_name' => 'ECPay',
        ]);

        // 兩個 Gateway 應該使用相同的 Omnipay 參數
        $this->assertEquals(
            $creditGateway->get_omnipay_gateway()->getMerchantID(),
            $atmGateway->get_omnipay_gateway()->getMerchantID()
        );
    }

    public function test_gateway_uses_own_settings_when_shared_not_set()
    {
        // 只設定 Gateway 自己的設定（向後相容）
        update_option('woocommerce_omnipay_ecpay_credit_settings', [
            'MerchantID' => '3000132',
            'HashKey' => 'gateway_key',
            'HashIV' => 'gateway_iv',
        ]);

        $gateway = new OmnipayGateway([
            'gateway_id' => 'ecpay_credit',
            'title' => 'ECPay 信用卡',
            'omnipay_name' => 'ECPay',
        ]);

        $omnipayGateway = $gateway->get_omnipay_gateway();

        $this->assertEquals('3000132', $omnipayGateway->getMerchantID());
        $this->assertEquals('gateway_key', $omnipayGateway->getHashKey());
    }
}
