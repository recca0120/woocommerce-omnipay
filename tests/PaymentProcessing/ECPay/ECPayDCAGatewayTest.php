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

        // Set up DCA periods for Shortcode mode
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            [
                'periodType' => 'M',
                'frequency' => 1,
                'execTimes' => 12,
            ],
        ]);

        $this->gateway = new ECPayDCAGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
        ]);

        // Set up Blocks mode settings
        $this->gateway->update_option('dca_periodType', 'M');
        $this->gateway->update_option('dca_frequency', 1);
        $this->gateway->update_option('dca_execTimes', 12);
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

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirectData['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_period_parameters()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('M', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(12, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);
    }

    public function test_process_payment_with_shortcode_mode_user_selection()
    {
        // Simulate user selection in Shortcode mode
        $_POST['omnipay_dca_period'] = 'Y_1_6';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Y', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(6, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);

        unset($_POST['omnipay_dca_period']);
    }
}
