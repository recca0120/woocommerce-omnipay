<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayWebATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 網路 ATM Gateway 測試
 */
class ECPayWebATMGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_webatm';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new ECPayWebATMGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_webatm',
            'title' => '綠界網路 ATM',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_webatm', $this->gateway->id);
        $this->assertEquals('綠界網路 ATM', $this->gateway->method_title);
    }

    public function test_process_payment_sends_webatm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('WebATM', $redirectData['data']['ChoosePayment']);
    }

    public function test_form_fields_has_amount_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
    }
}
