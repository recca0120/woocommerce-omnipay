<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Gateways\Features\InstallmentFeature;
use WooCommerceOmnipay\Gateways\Features\MinAmountFeature;
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

        $this->gateway = new ECPayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_credit_installment',
            'title' => '綠界信用卡分期',
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [
                new MinAmountFeature,
                new InstallmentFeature('CreditInstallment', ['periodRules' => ['30' => ['min_amount' => 20000]]]),
            ],
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

    public function test_multiselect_installments_saved_and_retrieved_as_array()
    {
        // Save multiselect value as array
        $installments = ['3', '6', '12'];
        $this->gateway->update_option('installments', $installments);

        // Retrieve should return array (not string)
        $retrieved = $this->gateway->get_option('installments');

        $this->assertIsArray($retrieved, 'installments should be returned as array');
        $this->assertEquals($installments, $retrieved);
        $this->assertCount(3, $retrieved);
    }

    public function test_process_payment_sends_installment_parameter()
    {
        // Set up installments first
        $this->gateway->update_option('installments', ['3', '6', '12', '18', '24']);

        $order = $this->createOrder(3000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('3,6,12,18,24', $redirectData['data']['CreditInstallment']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->gateway->update_option('min_amount', '100');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_process_payment_sends_selected_installment_from_post_data()
    {
        $order = $this->createOrder(3000);

        // Simulate user selecting 6 installments
        $_POST['omnipay_installment'] = '6';

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('6', $redirectData['data']['CreditInstallment']);

        unset($_POST['omnipay_installment']);
    }

    public function test_payment_fields_includes_installment_select()
    {
        $this->gateway->update_option('installments', ['3', '6', '12']);
        $this->gateway->init_settings();

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        $this->assertStringContainsString('omnipay_installment', $output);
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('value="6"', $output);
        $this->assertStringContainsString('value="12"', $output);
    }

    public function test_process_payment_converts_30_to_30_n_for_dream_installment()
    {
        // Test 1: Convert in installment list
        $this->gateway->update_option('installments', ['3', '6', '12', '30']);

        $order = $this->createOrder(25000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        // ECPay should convert '30' to '30N' for Dream Installment
        $this->assertEquals('3,6,12,30N', $redirectData['data']['CreditInstallment']);

        // Test 2: Convert when user selects 30
        $_POST['omnipay_installment'] = '30';

        $order2 = $this->createOrder(25000);
        $result2 = $this->gateway->process_payment($order2->get_id());

        $this->assertEquals('success', $result2['result']);

        $redirectData2 = get_transient('omnipay_redirect_'.$order2->get_id());
        // ECPay should convert selected '30' to '30N'
        $this->assertEquals('30N', $redirectData2['data']['CreditInstallment']);

        unset($_POST['omnipay_installment']);
    }

    public function test_payment_fields_hides_30_period_when_amount_below_20000()
    {
        $this->gateway->update_option('installments', ['3', '6', '12', '30']);
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(15000)->get_id());

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // Should show 3, 6, 12 period options
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('value="6"', $output);
        $this->assertStringContainsString('value="12"', $output);

        // Should NOT show 30 period when amount < 20000
        $this->assertStringNotContainsString('value="30"', $output);
    }

    public function test_payment_fields_shows_30_period_when_amount_above_20000()
    {
        $this->gateway->update_option('installments', ['3', '6', '12', '30']);
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(25000)->get_id());

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // Should show all period options including 30
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('value="6"', $output);
        $this->assertStringContainsString('value="12"', $output);
        $this->assertStringContainsString('value="30"', $output);
    }
}
