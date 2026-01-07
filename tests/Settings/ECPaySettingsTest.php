<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Settings;

use Recca0120\WooCommerce_Omnipay\Adapters\DefaultGatewayAdapter;
use Recca0120\WooCommerce_Omnipay\Http\WordPressClient;
use Recca0120\WooCommerce_Omnipay\Settings\GatewaySettingsSection;
use Recca0120\WooCommerce_Omnipay\Settings\GeneralSettingsSection;
use Recca0120\WooCommerce_Omnipay\SharedSettingsPage;
use WP_UnitTestCase;

/**
 * ECPay SharedSettings 測試
 */
class ECPaySettingsTest extends WP_UnitTestCase
{
    private $page;

    protected function setUp(): void
    {
        parent::setUp();
        $httpClient = new WordPressClient;
        $this->page = new SharedSettingsPage([
            new GeneralSettingsSection,
            new GatewaySettingsSection((new DefaultGatewayAdapter('ECPay'))->setHttpClient($httpClient)),
        ]);
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        parent::tearDown();
    }

    public function test_get_settings_returns_omnipay_fields()
    {
        $settings = $this->page->getSettings('ecpay');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[MerchantID]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashKey]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashIV]', $fieldIds);
    }

    public function test_get_settings_excludes_general_fields()
    {
        $settings = $this->page->getSettings('ecpay');

        $fieldIds = array_column($settings, 'id');

        // 這些欄位應該只在通用設定中，不應該在個別 gateway 設定中
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[testMode]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[transaction_id_prefix]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[allow_resubmit]', $fieldIds);
    }

    public function test_output_settings_renders_gateway_settings()
    {
        // 確保 WC_Admin_Settings 類別存在
        if (! class_exists('WC_Admin_Settings')) {
            $this->markTestSkipped('WC_Admin_Settings class not available');
        }

        $_GET['section'] = 'ecpay';

        ob_start();
        $this->page->outputSettings();
        $output = ob_get_clean();

        // 應該包含 ECPay 的欄位
        $this->assertStringContainsString('MerchantID', $output);
        $this->assertStringContainsString('HashKey', $output);
    }

    public function test_get_settings_creates_password_field_for_secret_keys()
    {
        $settings = $this->page->getSettings('ecpay');

        $hashKeyField = null;
        $hashIVField = null;

        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[HashKey]') !== false) {
                $hashKeyField = $field;
            }
            if (isset($field['id']) && strpos($field['id'], '[HashIV]') !== false) {
                $hashIVField = $field;
            }
        }

        // HashKey 和 HashIV 應該是 password 類型
        $this->assertNotNull($hashKeyField);
        $this->assertNotNull($hashIVField);
        $this->assertEquals('password', $hashKeyField['type']);
        $this->assertEquals('password', $hashIVField['type']);
    }

    public function test_get_settings_creates_text_field_for_merchant_id()
    {
        $settings = $this->page->getSettings('ecpay');

        $merchantIdField = null;
        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[MerchantID]') !== false) {
                $merchantIdField = $field;
                break;
            }
        }

        $this->assertNotNull($merchantIdField);
        $this->assertEquals('text', $merchantIdField['type']);
    }
}
