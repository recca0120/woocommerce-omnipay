<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing;

use Recca0120\WooCommerce_Omnipay\Repositories\OrderRepository;

/**
 * BankTransfer 測試
 */
class BankTransferTest extends TestCase
{
    protected $gatewayId = 'banktransfer';

    protected $gatewayName = 'BankTransfer';

    protected $settings = [
        'bank_code' => '012',
        'account_number' => '1234567890',
        'secret' => 'test_secret',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    // ==================== process_payment 測試 ====================

    public function test_process_payment_returns_redirect()
    {
        $order = $this->createSimpleOrder(1000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('bank_code', $redirectData['data']);
        $this->assertArrayHasKey('account_number', $redirectData['data']);
        $this->assertStringContainsString('order-received', $redirectData['data']['payment_info_url']);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
        $this->assertEquals((string) $order->get_id(), $order->get_meta(OrderRepository::META_TRANSACTION_ID));
    }

    // ==================== accept_notification 測試 ====================

    public function test_accept_notification_completes_order()
    {
        $order = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transactionId = $order->get_meta(OrderRepository::META_TRANSACTION_ID);

        $_POST = $this->makeNotification($transactionId, 1000);

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);
        $this->assertContains(wc_get_order($order->get_id())->get_status(), ['processing', 'completed']);
    }

    public function test_accept_notification_rejects_invalid_hash()
    {
        $order = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order->get_id());

        $transactionId = wc_get_order($order->get_id())->get_meta(OrderRepository::META_TRANSACTION_ID);

        $_POST = [
            'transaction_id' => $transactionId,
            'account_number' => '1234567890',
            'amount' => 1000,
            'created_at' => date('Y-m-d H:i:s'),
            'hash' => 'invalid_hash',
        ];

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);
        $this->assertEquals('on-hold', wc_get_order($order->get_id())->get_status());
    }

    public function test_accept_notification_ignores_completed_order()
    {
        $order = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transactionId = $order->get_meta(OrderRepository::META_TRANSACTION_ID);
        $order->payment_complete('manual_ref');

        $_POST = $this->makeNotification($transactionId, 1000);

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);
    }

    // ==================== Payment Info 測試 ====================

    public function test_process_payment_saves_payment_info()
    {
        $order = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());

        // 驗證付款資訊已儲存到 order meta
        $this->assertEquals('012', $order->get_meta(OrderRepository::META_BANK_CODE));
        $this->assertEquals('1234567890', $order->get_meta(OrderRepository::META_BANK_ACCOUNT));
    }

    public function test_get_payment_info_output()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // 驗證付款資訊區塊
        $this->assertStringContainsString('woocommerce-order-details', $output);
        $this->assertStringContainsString('Payment Information', $output);
        $this->assertStringContainsString('012-1234567890', $output);

        // 驗證匯款確認表單
        $this->assertStringContainsString('Remittance Confirmation', $output);
        $this->assertStringContainsString('remittance_last5', $output);
    }

    public function test_get_payment_info_output_shows_submitted_last5()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->update_meta_data('_omnipay_remittance_last5', '12345');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // 驗證付款資訊
        $this->assertStringContainsString('012-1234567890', $output);

        // 驗證已提交的匯款帳號後5碼顯示在付款資訊中
        $this->assertStringContainsString('12345', $output);
        $this->assertStringContainsString('Last 5 Digits of Remittance Account', $output);

        // 驗證 remittance form 不再顯示（避免重複）
        $this->assertStringNotContainsString('Remittance Confirmation', $output);
    }

    // ==================== 匯款帳號後5碼 ====================

    public function test_submit_remittance_last5_success()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'remittance_last5' => '12345',
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        // 禁用 redirect 和 exit 以便測試
        add_filter('wp_redirect', '__return_false');
        add_filter('woocommerce_omnipay_should_exit', '__return_false');
        wc_clear_notices();

        $this->gateway->handleRemittance();

        // 驗證資料已儲存
        $this->assertEquals('12345', wc_get_order($order->get_id())->get_meta('_omnipay_remittance_last5'));

        // 驗證成功訊息
        $notices = wc_get_notices('success');
        $this->assertNotEmpty($notices);
    }

    public function test_submit_remittance_last5_validates_format()
    {
        $order = $this->createSimpleOrder(1000);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'remittance_last5' => 'abc',
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        // 禁用 redirect 和 exit 以便測試
        add_filter('wp_redirect', '__return_false');
        add_filter('woocommerce_omnipay_should_exit', '__return_false');
        wc_clear_notices();

        $this->gateway->handleRemittance();

        // 驗證錯誤訊息
        $notices = wc_get_notices('error');
        $this->assertNotEmpty($notices);
    }

    public function test_submit_remittance_last5_rejects_invalid_key()
    {
        $order = $this->createSimpleOrder(1000);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => 'wrong_key',
            'remittance_last5' => '12345',
            'nonce' => wp_create_nonce('omnipay_remittance_nonce'),
        ];

        // 禁用 redirect 和 exit 以便測試
        add_filter('wp_redirect', '__return_false');
        add_filter('woocommerce_omnipay_should_exit', '__return_false');
        wc_clear_notices();

        $this->gateway->handleRemittance();

        // 驗證錯誤訊息
        $notices = wc_get_notices('error');
        $this->assertNotEmpty($notices);
    }

    public function test_submit_remittance_last5_rejects_invalid_nonce()
    {
        $order = $this->createSimpleOrder(1000);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $_POST = [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'remittance_last5' => '12345',
            'nonce' => 'invalid_nonce',
        ];

        // 禁用 redirect 和 exit 以便測試
        add_filter('wp_redirect', '__return_false');
        add_filter('woocommerce_omnipay_should_exit', '__return_false');
        wc_clear_notices();

        $this->gateway->handleRemittance();

        // 驗證錯誤訊息
        $notices = wc_get_notices('error');
        $this->assertNotEmpty($notices);
    }

    // ==================== 帳號池測試 ====================

    public function test_process_payment_with_bank_accounts_as_json_string()
    {
        // 模擬 WooCommerce 儲存的 JSON 字串格式
        $this->updateSharedSettings([
            'bank_accounts' => '[{"bank_code": "013", "account_number": "111111111"}]',
            'selection_mode' => 'random',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        $order = $this->createSimpleOrder(1000);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('013', $redirectData['data']['bank_code']);
        $this->assertEquals('111111111', $redirectData['data']['account_number']);
    }

    public function test_process_payment_with_bank_accounts_pool_random_mode()
    {
        // 設定帳號池（陣列格式）
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'random',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        $order = $this->createSimpleOrder(1000);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $bankCode = $redirectData['data']['bank_code'];
        $accountNumber = $redirectData['data']['account_number'];

        // 應該是帳號池中的其中一個
        $this->assertContains($bankCode, ['013', '808']);
        if ($bankCode === '013') {
            $this->assertEquals('111111111', $accountNumber);
        } else {
            $this->assertEquals('222222222', $accountNumber);
        }
    }

    public function test_process_payment_with_bank_accounts_pool_user_choice_mode()
    {
        // 設定帳號池
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'user_choice',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        $order = $this->createSimpleOrder(1000);

        // 模擬用戶選擇第二個帳號
        $_POST['bank_account_index'] = '1';

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('808', $redirectData['data']['bank_code']);
        $this->assertEquals('222222222', $redirectData['data']['account_number']);
    }

    public function test_process_payment_with_bank_accounts_pool_round_robin_mode()
    {
        // 重設輪詢索引
        delete_option('omnipay_banktransfer_last_account_index');

        // 設定帳號池
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'round_robin',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        // 第一筆訂單應該使用第一個帳號
        $order1 = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order1->get_id());
        $redirectData1 = get_transient('omnipay_redirect_'.$order1->get_id());
        $this->assertEquals('013', $redirectData1['data']['bank_code']);

        // 第二筆訂單應該使用第二個帳號
        $order2 = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order2->get_id());
        $redirectData2 = get_transient('omnipay_redirect_'.$order2->get_id());
        $this->assertEquals('808', $redirectData2['data']['bank_code']);

        // 第三筆訂單應該回到第一個帳號
        $order3 = $this->createSimpleOrder(1000);
        $this->gateway->process_payment($order3->get_id());
        $redirectData3 = get_transient('omnipay_redirect_'.$order3->get_id());
        $this->assertEquals('013', $redirectData3['data']['bank_code']);
    }

    public function test_fallback_to_single_account_when_no_bank_accounts_pool()
    {
        // 只設定單一帳號（原本的方式）
        $this->updateSharedSettings([
            'bank_code' => '012',
            'account_number' => '1234567890',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        $order = $this->createSimpleOrder(1000);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('012', $redirectData['data']['bank_code']);
        $this->assertEquals('1234567890', $redirectData['data']['account_number']);
    }

    // ==================== 結帳頁面 UI 測試 ====================

    public function test_payment_fields_shows_account_selector_in_user_choice_mode()
    {
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'user_choice',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        $this->assertStringContainsString('bank_account_index', $output);
        $this->assertStringContainsString('Select Bank Account', $output);
        $this->assertStringContainsString('013-111111111', $output);
        $this->assertStringContainsString('808-222222222', $output);
        // 應該顯示提示文字
        $this->assertStringContainsString('woocommerce-info', $output);
        $this->assertStringContainsString('last 5 digits', $output);
    }

    public function test_payment_fields_shows_select_for_single_account()
    {
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
            ],
            'selection_mode' => 'random',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // 單一帳號時也使用選單顯示（統一 UI）
        $this->assertStringContainsString('013-111111111', $output);
        $this->assertStringContainsString('Payment Account', $output);
        $this->assertStringContainsString('bank_account_index', $output);
        // 應該顯示提示文字
        $this->assertStringContainsString('woocommerce-info', $output);
        $this->assertStringContainsString('last 5 digits', $output);
    }

    public function test_has_fields_is_true_with_single_account()
    {
        // 只有一個帳號時，has_fields 應該為 true（顯示帳號資訊）
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
            ],
            'selection_mode' => 'user_choice',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        // 單一帳號需要顯示帳號資訊
        $this->assertTrue($this->gateway->has_fields);
    }

    public function test_has_fields_is_true_in_user_choice_mode_with_multiple_accounts()
    {
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'user_choice',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        $this->assertTrue($this->gateway->has_fields);
    }

    public function test_user_choice_mode_fallback_to_random_when_no_post_index()
    {
        // 設定帳號池
        $this->updateSharedSettings([
            'bank_accounts' => [
                ['bank_code' => '013', 'account_number' => '111111111'],
                ['bank_code' => '808', 'account_number' => '222222222'],
            ],
            'selection_mode' => 'user_choice',
            'secret' => 'test_secret',
            'testMode' => 'yes',
        ]);
        $this->reloadGateway();

        // 不設定 $_POST['bank_account_index']，應該 fallback 到隨機選擇
        unset($_POST['bank_account_index']);

        $order = $this->createSimpleOrder(1000);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $bankCode = $redirectData['data']['bank_code'];

        // 應該是帳號池中的其中一個
        $this->assertContains($bankCode, ['013', '808']);
    }

    public function test_get_payment_info_output_plain_text_mode()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order, true);

        // 純文字模式應該包含付款資訊
        $this->assertStringContainsString('012-1234567890', $output);
        // 但不應該包含匯款確認表單（HTML）
        $this->assertStringNotContainsString('<form', $output);
    }

    public function test_get_payment_info_output_for_different_gateway_order()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        // 使用不同的 gateway
        $order->set_payment_method('omnipay_ecpay');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // 應該顯示付款資訊
        $this->assertStringContainsString('012-1234567890', $output);
        // 但不應該顯示匯款確認表單（因為不是此 gateway 的訂單）
        $this->assertStringNotContainsString('remittance_last5', $output);
    }

    public function test_get_payment_info_output_with_empty_bank_info()
    {
        $order = $this->createSimpleOrder(1000);
        // 不設定銀行資訊
        $order->set_payment_method($this->gateway->id);
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // 應該有表單但沒有帳號資訊
        $this->assertStringContainsString('remittance_last5', $output);
    }

    // ==================== Helper ====================

    private function updateSharedSettings(array $settings)
    {
        update_option('woocommerce_omnipay_banktransfer_shared_settings', $settings);
        wp_cache_delete('woocommerce_omnipay_banktransfer_shared_settings', 'options');
        wp_cache_delete('alloptions', 'options');
    }

    private function reloadGateway()
    {
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();
        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_'.$this->gatewayId];
    }

    private function makeNotification($transactionId, $amount)
    {
        $data = [
            'transaction_id' => $transactionId,
            'account_number' => '1234567890',
            'amount' => $amount,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        ksort($data);
        $data['hash'] = hash_hmac('sha256', http_build_query($data), 'test_secret');

        return $data;
    }
}
