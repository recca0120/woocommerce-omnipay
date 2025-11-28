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
        $this->page = new SharedSettingsPage('ECPay', 'ecpay');
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_ecpay_shared_settings');
        parent::tearDown();
    }

    public function test_adds_section_to_checkout_settings()
    {
        $sections = $this->page->add_section([]);

        $this->assertArrayHasKey('omnipay_ecpay', $sections);
        $this->assertEquals('ECPay', $sections['omnipay_ecpay']);
    }

    public function test_returns_original_sections_for_other_pages()
    {
        $original = ['other' => 'Other Section'];
        $sections = $this->page->add_section($original);

        $this->assertArrayHasKey('other', $sections);
        $this->assertArrayHasKey('omnipay_ecpay', $sections);
    }

    public function test_get_settings_returns_omnipay_fields()
    {
        $settings = $this->page->get_settings([], 'omnipay_ecpay');

        // 應該包含 Omnipay 參數欄位
        $field_ids = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[MerchantID]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashKey]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[HashIV]', $field_ids);
    }

    public function test_get_settings_returns_plugin_fields()
    {
        $settings = $this->page->get_settings([], 'omnipay_ecpay');

        $field_ids = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[transaction_id_prefix]', $field_ids);
        $this->assertContains('woocommerce_omnipay_ecpay_shared_settings[allow_resubmit]', $field_ids);
    }

    public function test_get_settings_returns_original_for_other_section()
    {
        $original = [['id' => 'other_setting']];
        $settings = $this->page->get_settings($original, 'other_section');

        $this->assertEquals($original, $settings);
    }
}
