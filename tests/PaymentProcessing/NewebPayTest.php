<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use Omnipay\NewebPay\Encryptor;

/**
 * NewebPay 測試（redirect 型金流）
 */
class NewebPayTest extends TestCase
{
    protected $gatewayId = 'newebpay';

    protected $gatewayName = 'NewebPay';

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected function setUp(): void
    {
        $this->settings = [
            'HashKey' => $this->hashKey,
            'HashIV' => $this->hashIV,
            'MerchantID' => $this->merchantId,
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ];
        parent::setUp();
    }

    // ==================== process_payment ====================

    public function test_process_payment_returns_redirect()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertStringContainsString('newebpay.com', $redirectData['url']);
        $this->assertArrayHasKey('TradeInfo', $redirectData['data']);
        $this->assertArrayHasKey('TradeSha', $redirectData['data']);

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
            'Status' => 'SUCCESS',
            'TradeNo' => '24112500001234',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expectedStatus, $order->get_status());
        $this->assertEquals('24112500001234', $order->get_transaction_id());
    }

    public function test_accept_notification_validates_checksum()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $data = $this->makeCallbackData($order, ['Status' => 'SUCCESS']);
        $data['TradeSha'] = 'INVALID';
        $this->simulateCallback($data);

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('0|Incorrect TradeSha', $output);
    }

    public static function productTypeProvider()
    {
        return [
            'physical' => [false, false, 'processing'],
            'virtual downloadable' => [true, true, 'completed'],
        ];
    }

    // ==================== Payment Info ====================

    public function test_get_payment_info_stores_atm_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'PaymentType' => 'VACC',
            'BankCode' => '012',
            'CodeNo' => '9103522175887271',
        ]));

        $url = $this->gateway->getPaymentInfo();

        $this->assertStringContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('012', $order->get_meta('_omnipay_bank_code'));
        $this->assertEquals('9103522175887271', $order->get_meta('_omnipay_virtual_account'));
        $this->assertEquals('on-hold', $order->get_status());
    }

    public function test_get_payment_info_stores_cvs_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'PaymentType' => 'CVS',
            'CodeNo' => 'LLL24112512345',
        ]));

        $url = $this->gateway->getPaymentInfo();

        $this->assertStringContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('LLL24112512345', $order->get_meta('_omnipay_payment_no'));
        $this->assertEquals('on-hold', $order->get_status());
    }

    // ==================== 金額驗證測試 ====================

    public function test_accept_notification_rejects_amount_mismatch()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'Status' => 'SUCCESS',
            'Amt' => 999,  // 金額不符
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
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '授權成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'CREDIT',
            'RespondType' => 'JSON',
            'PayTime' => date('Y-m-d H:i:s'),
            'IP' => '127.0.0.1',
            'EscrowBank' => 'HNCB',
        ], $overrides);

        return $this->encrypt($result);
    }

    private function makePaymentInfoData($order, array $overrides = [])
    {
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '取號成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'VACC',
        ], $overrides);

        return $this->encrypt($result);
    }

    private function encrypt(array $result)
    {
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->encrypt($result);

        return [
            'Status' => $result['Status'],
            'MerchantID' => $this->merchantId,
            'TradeInfo' => $tradeInfo,
            'TradeSha' => $encryptor->tradeSha($tradeInfo),
            'Version' => '2.0',
        ];
    }
}
