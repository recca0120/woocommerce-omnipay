<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayDCAGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 定期定額 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、ChoosePayment、Period 參數）
 * 其他行為已在 ECPayTest 中測試
 */
class ECPayDCAGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_dca';

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

        $this->gateway = new ECPayDCAGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_dca', $this->gateway->id);
        $this->assertEquals('綠界定期定額', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirect_data['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_period_parameters()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('PeriodType', $redirect_data['data']);
        $this->assertArrayHasKey('Frequency', $redirect_data['data']);
        $this->assertArrayHasKey('ExecTimes', $redirect_data['data']);
        $this->assertArrayHasKey('PeriodAmount', $redirect_data['data']);
    }
}
