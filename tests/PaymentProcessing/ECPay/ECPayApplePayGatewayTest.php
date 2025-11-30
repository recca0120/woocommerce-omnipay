<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayApplePayGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay Apple Pay Gateway 測試
 */
class ECPayApplePayGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_applepay';

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

        $this->gateway = new ECPayApplePayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_applepay',
            'title' => '綠界 Apple Pay',
        ]);
    }

    public function test_process_payment_sends_applepay_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('ApplePay', $redirectData['data']['ChoosePayment']);
    }
}
