<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\ECPay;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\ExpireDateFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

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

        $this->gateway = new ECPayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_atm',
            'title' => '綠界 ATM',
            'payment_data' => ['ChoosePayment' => 'ATM'],
            'features' => [
                new MinAmountFeature,
                new MaxAmountFeature,
                new ExpireDateFeature('ExpireDate', 3, 1, 60),
            ],
        ]);
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
