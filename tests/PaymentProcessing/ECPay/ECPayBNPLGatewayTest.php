<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayBNPLGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay BNPL Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、ChoosePayment、金額限制）
 * 其他行為已在 ECPayTest 中測試
 */
class ECPayBNPLGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_bnpl';

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

        $this->gateway = new ECPayBNPLGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_bnpl',
            'title' => '綠界無卡分期',
        ]);
    }

    public function test_process_payment_sends_bnpl_payment_type()
    {
        $order = $this->createOrder(50000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('BNPL', $redirectData['data']['ChoosePayment']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->setGatewaySettings(['min_amount' => '100000']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50000)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_false_when_above_300000()
    {
        $this->setGatewaySettings(['max_amount' => '300000']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(300001)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_true_when_exactly_300000()
    {
        $this->setGatewaySettings(['max_amount' => '300000']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(300000)->get_id());

        $this->assertTrue($this->gateway->is_available());
    }

    private function setGatewaySettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            $this->gateway->update_option($key, $value);
        }
        $this->gateway->init_settings();
    }
}
