<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing;

use Omnipay\YiPay\Hasher;

/**
 * YiPay 測試（redirect 型金流）
 */
class YiPayTest extends TestCase
{
    protected $gatewayId = 'yipay';

    protected $gatewayName = 'YiPay';

    protected $settings = [
        'merchantId' => '1234567890',
        'key' => 'dGVzdGtleXRlc3QxMjM0NQ==',
        'iv' => 'dGVzdGl2dGVzdDEyMzQ1Ng==',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    // ==================== process_payment ====================

    public function test_process_payment_returns_redirect()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertStringContainsString('yipay.com.tw', $redirectData['url']);
        $this->assertArrayHasKey('checkCode', $redirectData['data']);

        $this->assertEquals('on-hold', wc_get_order($order->get_id())->get_status());
    }

    // ==================== Callback ====================

    /**
     * @dataProvider productTypeProvider
     */
    public function test_accept_notification_success($virtual, $downloadable, $expectedStatus)
    {
        $order = $this->createOrder(100, 'TWD', $virtual, $downloadable);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '00',
            'transactionNo' => 'YP24112500001234',
            'type' => '2',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expectedStatus, $order->get_status());
        $this->assertEquals('YP24112500001234', $order->get_transaction_id());
    }

    public function test_accept_notification_validates_checksum()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $data = $this->makeCallbackData($order, ['statusCode' => '00', 'type' => '2']);
        $data['checkCode'] = 'INVALID';
        $this->simulateCallback($data);

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('0|Incorrect checkCode', $output);
    }

    public function test_accept_notification_failed()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '99',
            'statusMessage' => '交易失敗',
            'type' => '2',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('0|交易失敗', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    public static function productTypeProvider()
    {
        return [
            'physical' => [false, false, 'processing'],
            'virtual downloadable' => [true, true, 'completed'],
        ];
    }

    // ==================== ATM/CVS ====================

    /**
     * @dataProvider paymentTypeProvider
     */
    public function test_accept_notification_with_payment_type($type, $field, $value)
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '00',
            'type' => $type,
            $field => $value,
            'transactionNo' => 'YP24112500005678',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('YP24112500005678', $order->get_transaction_id());
    }

    public static function paymentTypeProvider()
    {
        return [
            'ATM' => ['4', 'account', '9103522175887271'],
            'CVS' => ['3', 'pinCode', 'CVS24112512345'],
        ];
    }

    // ==================== Payment Info ====================

    /**
     * @dataProvider paymentInfoProvider
     */
    public function test_get_payment_info_stores_info($type, $field, $value, $metaKey)
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'type' => $type,
            $field => $value,
        ]));

        ob_start();
        $this->gateway->handlePaymentInfoCallback();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals($value, $order->get_meta($metaKey));

        $notes = array_column(wc_get_order_notes(['order_id' => $order->get_id()]), 'content');
        $typeName = $type === '4' ? 'ATM' : 'CVS';
        $this->assertContains("YiPay 取號成功 ({$typeName})，等待付款", $notes);
    }

    public static function paymentInfoProvider()
    {
        return [
            'ATM account' => ['4', 'account', '9103522175887271', '_omnipay_virtual_account'],
            'ATM bankCode' => ['4', 'bankCode', '009', '_omnipay_bank_code'],
            'CVS' => ['3', 'pinCode', 'CVS24112512345', '_omnipay_payment_no'],
        ];
    }

    // ==================== completePurchase ====================

    public function test_complete_purchase_failed()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '99',
            'statusMessage' => '交易失敗',
            'type' => '2',
        ]));

        $url = $this->gateway->completePurchase();

        $this->assertStringNotContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    // ==================== 金額驗證測試 ====================

    public function test_accept_notification_rejects_amount_mismatch()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '00',
            'type' => '2',
            'amount' => '999',  // 金額不符
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    // ==================== Helper ====================

    private function makeCallbackData($order, array $overrides = [])
    {
        // 從 shared settings 讀取 Omnipay 參數
        $sharedSettings = get_option('woocommerce_omnipay_'.strtolower($this->gatewayName).'_shared_settings', []);
        $merchantId = $sharedSettings['merchantId'] ?? $this->settings['merchantId'];

        $type = (int) ($overrides['type'] ?? '2');

        $returnUrl = WC()->api_request_url('omnipay_yipay_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_payment_info');

        $isOffline = in_array($type, [3, 4], true);
        $data = [
            'merchantId' => $merchantId,
            'orderNo' => (string) $order->get_id(),
            'amount' => (string) ((int) $order->get_total()),
            'statusCode' => '00',
            'statusMessage' => '交易成功',
            'transactionNo' => 'YP24112500001234',
            'type' => (string) $type,
            'returnURL' => $isOffline ? $notifyUrl : $returnUrl,
            'cancelURL' => $returnUrl,
            'backgroundURL' => $isOffline ? $paymentInfoUrl : $notifyUrl,
        ];

        if ($type === 3) {
            $data['pinCode'] = $overrides['pinCode'] ?? 'CVS123456';
        } elseif ($type === 4) {
            $data['account'] = $overrides['account'] ?? '9103522175887271';
        } else {
            $data['approvalCode'] = $overrides['approvalCode'] ?? 'ABC123';
        }

        $data = array_merge($data, $overrides);
        $data['checkCode'] = $this->sign($type, $data);

        return $data;
    }

    private function makePaymentInfoData($order, array $overrides = [])
    {
        // 從 shared settings 讀取 Omnipay 參數
        $sharedSettings = get_option('woocommerce_omnipay_'.strtolower($this->gatewayName).'_shared_settings', []);
        $merchantId = $sharedSettings['merchantId'] ?? $this->settings['merchantId'];

        $type = (int) ($overrides['type'] ?? '4');

        $returnUrl = WC()->api_request_url('omnipay_yipay_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_payment_info');

        $data = [
            'merchantId' => $merchantId,
            'orderNo' => (string) $order->get_id(),
            'amount' => (string) ((int) $order->get_total()),
            'statusCode' => '00',
            'statusMessage' => '取號成功',
            'transactionNo' => '',
            'type' => (string) $type,
            'returnURL' => $notifyUrl,
            'cancelURL' => $returnUrl,
            'backgroundURL' => $paymentInfoUrl,
        ];

        if ($type === 3) {
            $data['pinCode'] = $overrides['pinCode'] ?? 'CVS123456';
        } elseif ($type === 4) {
            $data['account'] = $overrides['account'] ?? '9103522175887271';
        }

        $data = array_merge($data, $overrides);
        $data['checkCode'] = $this->sign($type, $data);

        return $data;
    }

    private function sign(int $type, array $data)
    {
        // 從 shared settings 讀取 Omnipay 參數
        $sharedSettings = get_option('woocommerce_omnipay_'.strtolower($this->gatewayName).'_shared_settings', []);
        $key = $sharedSettings['key'] ?? $this->settings['key'];
        $iv = $sharedSettings['iv'] ?? $this->settings['iv'];

        $keys = ['merchantId', 'amount', 'orderNo', 'returnURL', 'cancelURL', 'backgroundURL', 'transactionNo', 'statusCode'];
        $typeKeys = [3 => 'pinCode', 4 => 'account'];
        $keys[] = $typeKeys[$type] ?? 'approvalCode';

        $signed = [];
        foreach ($keys as $key_name) {
            $signed[$key_name] = $data[$key_name] ?? '';
        }

        return (new Hasher($key, $iv))->make($signed);
    }
}
