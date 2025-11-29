<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayWeiXinGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay WeiXin Gateway 測試
 */
class ECPayWeiXinGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_weixin';

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

        $this->gateway = new ECPayWeiXinGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_weixin',
            'title' => '綠界微信支付',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_weixin', $this->gateway->id);
        $this->assertEquals('綠界微信支付', $this->gateway->method_title);
    }

    public function test_process_payment_sends_weixin_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('WeiXin', $redirectData['data']['ChoosePayment']);
    }
}
