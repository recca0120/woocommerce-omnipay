<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\ECPay;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 信用卡 Gateway 測試
 */
class ECPayCreditGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_credit';

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
            'gateway_id' => 'ecpay_credit',
            'title' => '綠界信用卡',
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [new MinAmountFeature],
        ]);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirectData['data']['ChoosePayment']);
    }
}
