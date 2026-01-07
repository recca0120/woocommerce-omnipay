<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\YiPay;

use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\YiPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

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

        $this->gateway = new YiPayGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_credit',
            'title' => 'YiPay 信用卡',
            'payment_data' => ['type' => '2'],
            'features' => [new MinAmountFeature],
        ]);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('2', $redirectData['data']['type']);
    }
}
