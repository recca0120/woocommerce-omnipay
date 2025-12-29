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
        delete_option('woocommerce_omnipay_banktransfer_shared_settings');
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
        $this->assertEquals('General Settings', $sections['']);
        $this->assertEquals('ECPay', $sections['ecpay']);
        $this->assertEquals('NewebPay', $sections['newebpay']);
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

    public function test_output_sections_renders_navigation()
    {
        ob_start();
        $this->page->output_sections();
        $output = ob_get_clean();

        // 驗證有 sections 導航
        $this->assertStringContainsString('<ul class="subsubsub">', $output);
        $this->assertStringContainsString('General Settings', $output);
        $this->assertStringContainsString('ECPay', $output);
        $this->assertStringContainsString('NewebPay', $output);
        $this->assertStringContainsString('section=ecpay', $output);
        $this->assertStringContainsString('section=newebpay', $output);
    }

    public function test_output_sections_marks_current_section()
    {
        $_GET['section'] = 'ecpay';

        ob_start();
        $this->page->output_sections();
        $output = ob_get_clean();

        // 驗證 ECPay 有 current class
        $this->assertMatchesRegularExpression('/<a[^>]+section=ecpay[^>]+class="[^"]*current[^"]*"/', $output);
    }

    public function test_output_settings_renders_general_settings_by_default()
    {
        // 確保 WC_Admin_Settings 類別存在
        if (! class_exists('WC_Admin_Settings')) {
            $this->markTestSkipped('WC_Admin_Settings class not available');
        }

        // 預設 section 為空，應該顯示通用設定
        unset($_GET['section']);

        ob_start();
        $this->page->output_settings();
        $output = ob_get_clean();

        // 應該包含通用設定的欄位
        $this->assertStringContainsString('testMode', $output);
        $this->assertStringContainsString('transaction_id_prefix', $output);
    }

    public function test_duplicate_gateways_are_deduplicated()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
            ['gateway' => 'ECPay'], // 重複
            ['gateway' => 'NewebPay'],
        ]);

        $sections = $page->get_sections();

        // 應該只有 General + ECPay + NewebPay = 3 個 sections
        $this->assertCount(3, $sections);
    }

    public function test_save_settings_handles_checkbox_field()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'ECPay'],
        ]);
        $page->register();

        $_GET['section'] = '';
        $_POST['woocommerce_omnipay_general_settings'] = [
            'testMode' => 'yes',
            'allow_resubmit' => '1', // checkbox 可能是 '1' 或 'yes'
        ];

        $page->save_settings();

        $savedSettings = get_option('woocommerce_omnipay_general_settings', []);

        $this->assertEquals('yes', $savedSettings['testMode']);
        $this->assertEquals('yes', $savedSettings['allow_resubmit']);
    }

    public function test_save_bank_accounts_table_skips_invalid_option_id()
    {
        $page = new SharedSettingsPage([
            ['gateway' => 'BankTransfer'],
        ]);
        $page->register();

        // 先確認選項不存在
        delete_option('invalid_format_without_brackets');

        // 傳入無效的 option ID 格式
        $page->save_bank_accounts_table([
            'id' => 'invalid_format_without_brackets',
            'type' => 'bank_accounts_table',
        ]);

        // 應該不會建立任何選項
        $this->assertFalse(get_option('invalid_format_without_brackets'));
    }
}
