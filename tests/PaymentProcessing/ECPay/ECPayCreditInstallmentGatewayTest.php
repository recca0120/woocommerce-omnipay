<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPay\ECPayCreditInstallmentGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 信用卡分期 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、ChoosePayment、CreditInstallment 參數）
 * 其他行為已在 ECPayTest 中測試
 */
class ECPayCreditInstallmentGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_credit_installment';

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

        $this->gateway = new ECPayCreditInstallmentGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_credit_installment',
            'title' => '綠界信用卡分期',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_credit_installment', $this->gateway->id);
        $this->assertEquals('綠界信用卡分期', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirectData['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_installment_parameter()
    {
        $order = $this->createOrder(3000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('3,6,12,18,24', $redirectData['data']['CreditInstallment']);
    }

    public function test_form_fields_has_min_amount_and_installments()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('installments', $this->gateway->form_fields);
        $this->assertEquals('multiselect', $this->gateway->form_fields['installments']['type']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->gateway->update_option('min_amount', '100');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }
}
