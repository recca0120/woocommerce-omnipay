<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\SharedSettings;
use WP_UnitTestCase;

class SharedSettingsTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        delete_option('woocommerce_omnipay_newebpay_shared_settings');
        parent::tearDown();
    }

    public function test_get_settings_returns_saved_values()
    {
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => '2000132',
            'HashKey' => 'test_key',
            'HashIV' => 'test_iv',
        ]);

        $settings = SharedSettings::get('ECPay');

        $this->assertEquals('2000132', $settings['MerchantID']);
        $this->assertEquals('test_key', $settings['HashKey']);
        $this->assertEquals('test_iv', $settings['HashIV']);
    }

    public function test_get_settings_returns_empty_array_when_not_set()
    {
        $settings = SharedSettings::get('ECPay');

        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }

    public function test_get_option_key()
    {
        $this->assertEquals(
            'woocommerce_omnipay_ecpay_shared_settings',
            SharedSettings::getOptionKey('ECPay')
        );

        $this->assertEquals(
            'woocommerce_omnipay_newebpay_shared_settings',
            SharedSettings::getOptionKey('NewebPay')
        );
    }

    public function test_get_single_value()
    {
        update_option('woocommerce_omnipay_ecpay_shared_settings', [
            'MerchantID' => '2000132',
            'transaction_id_prefix' => 'EC_',
        ]);

        $this->assertEquals('2000132', SharedSettings::getValue('ECPay', 'MerchantID'));
        $this->assertEquals('EC_', SharedSettings::getValue('ECPay', 'transaction_id_prefix'));
        $this->assertEquals('', SharedSettings::getValue('ECPay', 'not_exists'));
        $this->assertEquals('default', SharedSettings::getValue('ECPay', 'not_exists', 'default'));
    }
}
