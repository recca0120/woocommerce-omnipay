<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\YiPay;

use WooCommerceOmnipay\Gateways\YiPay\YiPayCVSGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay 超商代碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、type、CVS 特有的 meta 儲存
 * 基本 callback 行為已在 YiPayTest 中測試
 */
class YiPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_cvs';

    protected $gatewayName = 'YiPay';

    protected $settings = [
        'merchantId' => '1234567890',
        'key' => 'dGVzdGtleXRlc3QxMjM0NQ==',
        'iv' => 'dGVzdGl2dGVzdDEyMzQ1Ng==',
        'testMode' => 'yes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new YiPayCVSGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_cvs',
            'title' => '乙禾超商代碼',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_yipay_cvs', $this->gateway->id);
        $this->assertEquals('乙禾超商代碼', $this->gateway->method_title);
    }

    public function test_process_payment_sends_cvs_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('3', $redirectData['data']['type']);
    }

    public function test_form_fields_has_amount_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
    }
}
