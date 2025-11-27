<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use WooCommerceOmnipay\Repositories\OrderRepository;
use WP_UnitTestCase;

/**
 * BankTransfer Gateway 測試
 *
 * 測試流程：
 * 1. process_payment() -> 回傳 redirect URL 到 payment_info_url
 * 2. payment_info_url -> 顯示銀行轉帳資訊
 * 3. accept_notification() -> 處理付款完成通知，訂單完成
 */
class BankTransferTest extends WP_UnitTestCase
{
    private $gateway;

    private $config_filter_callback;

    protected function setUp(): void
    {
        parent::setUp();

        // 禁用測試中的 exit
        add_filter('woocommerce_omnipay_should_exit', '__return_false');

        // 註冊 BankTransfer gateway
        $this->config_filter_callback = function ($config) {
            return [
                'gateways' => [
                    [
                        'omnipay_name' => 'BankTransfer',
                        'gateway_id' => 'banktransfer',
                        'title' => '銀行轉帳',
                        'description' => '使用銀行轉帳付款',
                    ],
                ],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);

        // 設定 gateway 選項
        update_option('woocommerce_omnipay_banktransfer_settings', [
            'enabled' => 'yes',
            'title' => '銀行轉帳',
            'bank_code' => '012',
            'account_number' => '1234567890',
            'secret' => 'test_secret',
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ]);

        // 清空 WooCommerce payment gateways 快取並重新初始化
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_banktransfer'];
    }

    protected function tearDown(): void
    {
        remove_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        delete_option('woocommerce_omnipay_banktransfer_settings');
        parent::tearDown();
    }

    // ==================== process_payment 測試 ====================

    /**
     * 測試：process_payment 回傳 success、redirect URL、儲存 transactionId 並設定訂單狀態
     */
    public function test_process_payment_returns_success_with_redirect_and_saves_data()
    {
        $order = $this->create_test_order(1000);

        $result = $this->gateway->process_payment($order->get_id());

        // 驗證回傳結果
        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        // 驗證儲存的 redirect 資料
        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertNotEmpty($redirect_data);
        $this->assertEquals('POST', $redirect_data['method']);
        $this->assertArrayHasKey('bank_code', $redirect_data['data']);
        $this->assertArrayHasKey('account_number', $redirect_data['data']);
        $this->assertArrayHasKey('hash', $redirect_data['data']);
        // payment_info_url 應該指向 thankyou 頁面
        $this->assertStringContainsString('order-received', $redirect_data['data']['payment_info_url']);

        // 驗證訂單狀態和 transactionId
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());

        $transaction_id = $order->get_meta(OrderRepository::META_TRANSACTION_ID);
        $this->assertNotEmpty($transaction_id);
        $this->assertEquals((string) $order->get_id(), $transaction_id);
    }

    // ==================== accept_notification 測試 ====================

    /**
     * 測試：付款完成通知 - 訂單完成
     */
    public function test_accept_notification_completes_order_on_valid_notification()
    {
        $order = $this->create_test_order(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transaction_id = $order->get_meta(OrderRepository::META_TRANSACTION_ID);

        // 模擬付款完成通知
        $notification_data = [
            'transaction_id' => $transaction_id,
            'account_number' => '1234567890',
            'amount' => 1000,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $notification_data['hash'] = $this->generate_hash($notification_data, 'test_secret');

        $_POST = $notification_data;

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);

        $order = wc_get_order($order->get_id());
        // WooCommerce payment_complete() 會設為 processing 或 completed
        $this->assertTrue(in_array($order->get_status(), ['processing', 'completed'], true));
    }

    /**
     * 測試：付款通知 hash 錯誤
     */
    public function test_accept_notification_rejects_invalid_hash()
    {
        $order = $this->create_test_order(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transaction_id = $order->get_meta(OrderRepository::META_TRANSACTION_ID);

        // 模擬錯誤的 hash
        $_POST = [
            'transaction_id' => $transaction_id,
            'account_number' => '1234567890',
            'amount' => 1000,
            'created_at' => date('Y-m-d H:i:s'),
            'hash' => 'invalid_hash',
        ];

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        // 訂單狀態不應該改變
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    /**
     * 測試：已完成訂單不應重複處理
     */
    public function test_accept_notification_ignores_already_completed_order()
    {
        $order = $this->create_test_order(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transaction_id = $order->get_meta(OrderRepository::META_TRANSACTION_ID);

        // 先手動完成訂單
        $order->payment_complete('manual_ref');

        // 模擬付款完成通知
        $notification_data = [
            'transaction_id' => $transaction_id,
            'account_number' => '1234567890',
            'amount' => 1000,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $notification_data['hash'] = $this->generate_hash($notification_data, 'test_secret');

        $_POST = $notification_data;

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        // 應該回傳成功但不處理
        $this->assertEquals('1|OK', $output);
    }

    // ==================== Payment Info 顯示測試 ====================

    /**
     * 測試：顯示銀行轉帳資訊
     */
    public function test_get_payment_info_output_shows_bank_info()
    {
        $order = $this->create_test_order(1000);

        // 儲存付款資訊
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->save();

        $output = $this->gateway->get_payment_info_output($order);

        $this->assertStringContainsString('012', $output);
        $this->assertStringContainsString('1234567890', $output);
    }

    // ==================== 客戶匯款帳號後5碼測試 ====================

    /**
     * 測試：付款資訊輸出包含匯款帳號後5碼表單
     */
    public function test_payment_info_output_contains_remittance_form()
    {
        $order = $this->create_test_order(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->save();

        $output = $this->gateway->get_payment_info_output($order);

        // 應該包含輸入表單
        $this->assertStringContainsString('omnipay-payment-info', $output);
        $this->assertStringContainsString('remittance_last5', $output);
    }

    /**
     * 測試：客戶提交匯款帳號後5碼成功
     */
    public function test_submit_remittance_last5_success()
    {
        $order = $this->create_test_order(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        // 模擬 AJAX 請求
        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'remittance_last5' => '12345',
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        ob_start();
        $this->gateway->handle_remittance();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);

        // 驗證資料已儲存
        $order = wc_get_order($order->get_id());
        $this->assertEquals('12345', $order->get_meta('_omnipay_remittance_last5'));
    }

    /**
     * 測試：匯款帳號後5碼格式驗證（必須是5位數字）
     */
    public function test_submit_remittance_last5_validates_format()
    {
        $order = $this->create_test_order(1000);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'remittance_last5' => 'abc',  // 無效格式
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        ob_start();
        $this->gateway->handle_remittance();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('5', $response['message']);
    }

    /**
     * 測試：驗證 order_key 錯誤時拒絕提交
     */
    public function test_submit_remittance_last5_rejects_invalid_order_key()
    {
        $order = $this->create_test_order(1000);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => 'wrong_key',
            'remittance_last5' => '12345',
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        ob_start();
        $this->gateway->handle_remittance();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    /**
     * 測試：已填寫的匯款帳號後5碼會顯示在付款資訊中
     */
    public function test_payment_info_shows_submitted_remittance_last5()
    {
        $order = $this->create_test_order(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->update_meta_data('_omnipay_remittance_last5', '12345');
        $order->save();

        $output = $this->gateway->get_payment_info_output($order);

        // 應該顯示已填寫的帳號後5碼
        $this->assertStringContainsString('12345', $output);
    }

    // ==================== Helper Methods ====================

    /**
     * 建立測試訂單
     */
    private function create_test_order($total = 100)
    {
        $order = wc_create_order();
        $order->set_total($total);
        $order->set_currency('TWD');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        return $order;
    }

    /**
     * 產生 hash（模擬 Omnipay BankTransfer Hasher）
     */
    private function generate_hash(array $data, string $secret): string
    {
        unset($data['hash']);
        ksort($data);

        return hash_hmac('sha256', http_build_query($data), $secret);
    }
}
