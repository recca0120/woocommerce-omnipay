<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\ECPay;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

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

        $this->gateway = new ECPayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_weixin',
            'title' => '綠界微信支付',
            'payment_data' => ['ChoosePayment' => 'WeiXin'],
            'features' => [new MinAmountFeature, new MaxAmountFeature],
        ]);
    }

    public function test_process_payment_sends_weixin_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('WeiXin', $redirectData['data']['ChoosePayment']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->gateway->update_option('min_amount', '100');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_false_when_above_max_amount()
    {
        $this->gateway->update_option('max_amount', '10000');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(15000)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }
}
