<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\YiPay;

use WooCommerceOmnipay\Gateways\YiPay\YiPayCreditGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay 信用卡 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、type 參數）
 * 其他行為已在 YiPayTest 中測試
 */
class YiPayCreditGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_credit';

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

        $this->gateway = new YiPayCreditGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_credit',
            'title' => '乙禾信用卡',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_yipay_credit', $this->gateway->id);
        $this->assertEquals('乙禾信用卡', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('2', $redirectData['data']['type']);
    }

    public function test_form_fields_has_min_amount_setting()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
    }
}
