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
            ['omnipay_name' => 'ECPay'],
            ['omnipay_name' => 'NewebPay'],
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

    public function test_get_sections_returns_all_gateways()
    {
        $sections = $this->page->get_sections();

        $this->assertArrayHasKey('ecpay', $sections);
        $this->assertArrayHasKey('newebpay', $sections);
        $this->assertEquals('ECPay', $sections['ecpay']);
        $this->assertEquals('NewebPay', $sections['newebpay']);
    }

    public function test_get_settings_returns_omnipay_fields_for_ecpay()
    {
        $settings = $this->page->get_settings('ecpay');

        $field_ids = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[MerchantID]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashKey]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashIV]', $field_ids);
    }

    public function test_get_settings_returns_plugin_fields()
    {
        $settings = $this->page->get_settings('ecpay');

        $field_ids = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[transaction_id_prefix]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[allow_resubmit]', $field_ids);
    }

    public function test_get_settings_returns_empty_for_unknown_section()
    {
        $settings = $this->page->get_settings('unknown');

        $this->assertEmpty($settings);
    }

    public function test_first_section_is_default()
    {
        $sections = $this->page->get_sections();

        $first_key = array_key_first($sections);
        $this->assertEquals('ecpay', $first_key);
    }
}
