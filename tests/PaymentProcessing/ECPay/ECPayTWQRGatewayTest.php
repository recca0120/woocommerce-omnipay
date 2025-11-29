<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayTWQRGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay TWQR Gateway 測試
 */
class ECPayTWQRGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_twqr';

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

        $this->gateway = new ECPayTWQRGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_twqr',
            'title' => '綠界台灣 Pay',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_twqr', $this->gateway->id);
        $this->assertEquals('綠界台灣 Pay', $this->gateway->method_title);
    }

    public function test_process_payment_sends_twqr_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('TWQR', $redirectData['data']['ChoosePayment']);
    }
}
