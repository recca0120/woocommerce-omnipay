<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use WooCommerceOmnipay\Repositories\OrderRepository;

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

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('bank_code', $redirect_data['data']);
        $this->assertArrayHasKey('account_number', $redirect_data['data']);
        $this->assertStringContainsString('order-received', $redirect_data['data']['payment_info_url']);

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

    public function test_get_payment_info_output()
    {
        $order = $this->createSimpleOrder(1000);
        $order->update_meta_data('_omnipay_bank_code', '012');
        $order->update_meta_data('_omnipay_bank_account', '1234567890');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        $this->assertStringContainsString('012', $output);
        $this->assertStringContainsString('1234567890', $output);
        $this->assertStringContainsString('remittance_last5', $output);
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

        ob_start();
        $this->gateway->handleRemittance();
        $response = json_decode(ob_get_clean(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('12345', wc_get_order($order->get_id())->get_meta('_omnipay_remittance_last5'));
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

        ob_start();
        $this->gateway->handleRemittance();
        $response = json_decode(ob_get_clean(), true);

        $this->assertFalse($response['success']);
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

        ob_start();
        $this->gateway->handleRemittance();
        $response = json_decode(ob_get_clean(), true);

        $this->assertFalse($response['success']);
    }

    // ==================== Helper ====================

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
