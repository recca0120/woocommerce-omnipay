<?php

namespace WooCommerceOmnipay\Tests;

use WooCommerceOmnipay\SharedSettingsPage;
use WP_UnitTestCase;

class SharedSettingsPageTest extends WP_UnitTestCase
{
    private $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
            ['gateway' => 'NewebPay'],
        ]);
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        delete_option('woocommerce_omnipay_newebpay_shared_settings');
        parent::tearDown();
    }

    public function test_adds_omnipay_tab_to_woocommerce_settings()
    {
        $tabs = $this->page->add_tab([]);

        $this->assertArrayHasKey('omnipay', $tabs);
        $this->assertEquals('Omnipay', $tabs['omnipay']);
    }

    public function test_get_sections_returns_general_and_all_gateways()
    {
        $sections = $this->page->get_sections();

        $this->assertArrayHasKey('', $sections);
        $this->assertArrayHasKey('ecpay', $sections);
        $this->assertArrayHasKey('newebpay', $sections);
        $this->assertEquals('通用設定', $sections['']);
        $this->assertEquals('ECPay', $sections['ecpay']);
        $this->assertEquals('NewebPay', $sections['newebpay']);
    }

    public function test_get_settings_returns_omnipay_fields_for_ecpay()
    {
        $settings = $this->page->get_settings('ecpay');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[MerchantID]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashKey]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashIV]', $fieldIds);
    }

    public function test_get_settings_for_gateway_excludes_general_fields()
    {
        $settings = $this->page->get_settings('ecpay');

        $fieldIds = array_column($settings, 'id');

        // 這些欄位應該只在通用設定中，不應該在個別 gateway 設定中
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[testMode]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[transaction_id_prefix]', $fieldIds);
        $this->assertNotContains('woocommerce_omnipay_ecpay_shared_settings[allow_resubmit]', $fieldIds);
    }

    public function test_get_settings_returns_empty_for_unknown_section()
    {
        $settings = $this->page->get_settings('unknown');

        $this->assertEmpty($settings);
    }

    public function test_first_section_is_general()
    {
        $sections = $this->page->get_sections();

        $firstKey = array_key_first($sections);
        $this->assertEquals('', $firstKey);
    }

    public function test_get_settings_returns_general_settings()
    {
        $settings = $this->page->get_settings('');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_general_settings[testMode]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_general_settings[transaction_id_prefix]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_general_settings[allow_resubmit]', $fieldIds);
    }
}
