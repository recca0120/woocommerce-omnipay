<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing;

/**
 * Dummy Gateway 測試（Direct 型金流）
 */
class DummyTest extends TestCase
{
    protected $gatewayId = 'dummy';

    protected $gatewayName = 'Dummy';

    /**
     * 測試：成功的付款處理
     */
    public function test_successful_payment()
    {
        $order = $this->createSimpleOrder(100, 'USD');
        $this->simulateCardData('4242424242424242');

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $order = wc_get_order($order->get_id());
        $this->assertContains($order->get_status(), ['processing', 'completed']);
        $this->assertNotEmpty($order->get_transaction_id());
    }

    /**
     * 測試：失敗的付款處理
     */
    public function test_failed_payment()
    {
        $order = $this->createSimpleOrder(100, 'USD');
        $this->simulateCardData('4111111111111111'); // 奇數結尾會失敗

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('failure', $result['result']);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    /**
     * 測試：無效訂單
     */
    public function test_invalid_order()
    {
        $this->simulateCardData('4242424242424242');

        $result = $this->gateway->process_payment(999999);

        $this->assertEquals('failure', $result['result']);
    }

    private function simulateCardData($number)
    {
        $_POST['omnipay_number'] = $number;
        $_POST['omnipay_expiryMonth'] = '12';
        $_POST['omnipay_expiryYear'] = '2030';
        $_POST['omnipay_cvv'] = '123';
        $_POST['omnipay_firstName'] = 'Test';
        $_POST['omnipay_lastName'] = 'User';
    }
}
