<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay ATM Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、ChoosePayment、ATM 特有的 meta 儲存
 * 基本 callback 行為已在 ECPayTest 中測試
 */
class ECPayATMGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_atm';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new ECPayATMGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_atm',
            'title' => '綠界 ATM',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_atm', $this->gateway->id);
        $this->assertEquals('綠界 ATM', $this->gateway->method_title);
    }

    public function test_process_payment_sends_atm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('ATM', $redirectData['data']['ChoosePayment']);
    }

    public function test_form_fields_has_amount_and_expire_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('expire_date', $this->gateway->form_fields);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->setGatewaySettings(['min_amount' => '100']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_false_when_above_max_amount()
    {
        $this->setGatewaySettings(['max_amount' => '100']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(150)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_true_when_within_amount_range()
    {
        $this->setGatewaySettings(['min_amount' => '50', 'max_amount' => '200']);

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(100)->get_id());

        $this->assertTrue($this->gateway->is_available());
    }

    public function test_process_payment_sends_expire_date()
    {
        $this->setGatewaySettings(['expire_date' => '7']);

        $order = $this->createOrder(100);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('7', $redirectData['data']['ExpireDate']);
    }

    private function setGatewaySettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            $this->gateway->update_option($key, $value);
        }
        $this->gateway->init_settings();
    }
}
