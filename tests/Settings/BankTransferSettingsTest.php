<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Settings;

use Recca0120\WooCommerce_Omnipay\Adapters\BankTransferAdapter;
use Recca0120\WooCommerce_Omnipay\Settings\BankTransferSettingsSection;
use Recca0120\WooCommerce_Omnipay\Settings\GeneralSettingsSection;
use Recca0120\WooCommerce_Omnipay\SharedSettingsPage;
use WP_UnitTestCase;

/**
 * BankTransfer SharedSettings 測試
 */
class BankTransferSettingsTest extends WP_UnitTestCase
{
    private $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = new SharedSettingsPage([
            new GeneralSettingsSection,
            new BankTransferSettingsSection(new BankTransferAdapter),
        ]);
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_omnipay_banktransfer_shared_settings');
        parent::tearDown();
    }

    public function test_get_settings_includes_bank_accounts_and_selection_mode()
    {
        $settings = $this->page->getSettings('banktransfer');

        $fieldIds = array_column($settings, 'id');

        $this->assertContains('woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]', $fieldIds);
        $this->assertContains('woocommerce_omnipay_banktransfer_shared_settings[selection_mode]', $fieldIds);
        // secret 現在是 bank_accounts 表格內的欄位，不是獨立欄位
        $this->assertNotContains('woocommerce_omnipay_banktransfer_shared_settings[secret]', $fieldIds);
    }

    public function test_bank_accounts_field_uses_table_type()
    {
        $settings = $this->page->getSettings('banktransfer');

        $bankAccountsField = null;
        foreach ($settings as $field) {
            if (isset($field['id']) && strpos($field['id'], '[bank_accounts]') !== false) {
                $bankAccountsField = $field;
                break;
            }
        }

        $this->assertNotNull($bankAccountsField);
        $this->assertEquals('bank_accounts_table', $bankAccountsField['type']);
    }

    public function test_save_bank_accounts_table_saves_to_shared_settings()
    {
        $this->page->register();

        // 模擬 POST 資料
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
                1 => [
                    'bank_code' => '822',
                    'account_number' => '0987654321',
                    'secret' => 'test456',
                ],
            ],
        ];

        // 透過 action hook 觸發儲存
        do_action('woocommerce_update_option_bank_accounts_table', [
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
        ]);

        // 驗證儲存結果
        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        $this->assertArrayHasKey('bank_accounts', $savedSettings);
        $this->assertCount(2, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
        $this->assertEquals('1234567890', $savedSettings['bank_accounts'][0]['account_number']);
        $this->assertEquals('822', $savedSettings['bank_accounts'][1]['bank_code']);
    }

    public function test_save_bank_accounts_table_filters_empty_accounts()
    {
        $this->page->register();

        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => '',
                ],
                1 => [
                    'bank_code' => '',
                    'account_number' => '',
                    'secret' => '',
                ],
            ],
        ];

        do_action('woocommerce_update_option_bank_accounts_table', [
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
        ]);

        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        // 空帳號應被過濾
        $this->assertCount(1, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
    }

    public function test_save_bank_accounts_table_skips_invalid_option_id()
    {
        $this->page->register();

        // 先確認選項不存在
        delete_option('invalid_format_without_brackets');

        // 傳入無效的 option ID 格式
        do_action('woocommerce_update_option_bank_accounts_table', [
            'id' => 'invalid_format_without_brackets',
            'type' => 'bank_accounts_table',
        ]);

        // 應該不會建立任何選項
        $this->assertFalse(get_option('invalid_format_without_brackets'));
    }

    public function test_output_bank_accounts_table_reads_from_shared_settings()
    {
        // 預先儲存資料
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => [
                [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
            ],
            'selection_mode' => 'random',
        ]);

        $this->page->register();

        // 透過 action hook 捕獲輸出
        ob_start();
        do_action('woocommerce_admin_field_bank_accounts_table', [
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
            'title' => 'Bank Accounts',
            'default' => [],
        ]);
        $output = ob_get_clean();

        // 驗證輸出包含儲存的資料
        $this->assertStringContainsString('812', $output);
        $this->assertStringContainsString('1234567890', $output);
    }

    public function test_settings_manager_reads_selection_mode_from_shared_settings()
    {
        // 預先儲存資料
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => [
                ['bank_code' => '812', 'account_number' => '123', 'secret' => ''],
            ],
            'selection_mode' => 'user_choice',
        ]);

        $settingsManager = new \Recca0120\WooCommerce_Omnipay\WordPress\SettingsManager('BankTransfer');
        $settings = $settingsManager->getAllSettings();

        $this->assertEquals('user_choice', $settings['selection_mode']);
        $this->assertCount(1, $settings['bank_accounts']);
    }

    public function test_save_settings_saves_selection_mode_to_shared_settings()
    {
        $this->page->register();

        // 模擬 POST 資料
        $_GET['section'] = 'banktransfer';
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'selection_mode' => 'user_choice',
        ];

        // 觸發儲存
        $this->page->saveSettings();

        // 驗證儲存結果
        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        $this->assertArrayHasKey('selection_mode', $savedSettings);
        $this->assertEquals('user_choice', $savedSettings['selection_mode']);
    }

    public function test_save_settings_saves_multiple_bank_accounts()
    {
        $this->page->register();

        $_GET['section'] = 'banktransfer';
        $_POST['woocommerce_omnipay_banktransfer_shared_settings'] = [
            'bank_accounts' => [
                0 => [
                    'bank_code' => '812',
                    'account_number' => '1234567890',
                    'secret' => 'test123',
                ],
                1 => [
                    'bank_code' => '822',
                    'account_number' => '0987654321',
                    'secret' => 'test456',
                ],
            ],
            'selection_mode' => 'user_choice',
        ];

        // 透過 saveSettings() 完整流程儲存
        $this->page->saveSettings();

        $savedSettings = get_option('woocommerce_omnipay_banktransfer_shared_settings', []);

        // 驗證多組帳號都有儲存
        $this->assertArrayHasKey('bank_accounts', $savedSettings);
        $this->assertCount(2, $savedSettings['bank_accounts']);
        $this->assertEquals('812', $savedSettings['bank_accounts'][0]['bank_code']);
        $this->assertEquals('822', $savedSettings['bank_accounts'][1]['bank_code']);
        $this->assertEquals('user_choice', $savedSettings['selection_mode']);
    }

    public function test_output_bank_accounts_table_handles_json_string()
    {
        // 以 JSON 字串格式儲存帳號
        update_option('woocommerce_omnipay_banktransfer_shared_settings', [
            'bank_accounts' => '[{"bank_code": "812", "account_number": "1234567890"}]',
        ]);

        $this->page->register();

        ob_start();
        do_action('woocommerce_admin_field_bank_accounts_table', [
            'id' => 'woocommerce_omnipay_banktransfer_shared_settings[bank_accounts]',
            'type' => 'bank_accounts_table',
            'title' => 'Bank Accounts',
            'default' => [],
        ]);
        $output = ob_get_clean();

        // 驗證能正確解析 JSON 字串
        $this->assertStringContainsString('812', $output);
        $this->assertStringContainsString('1234567890', $output);
    }
}
