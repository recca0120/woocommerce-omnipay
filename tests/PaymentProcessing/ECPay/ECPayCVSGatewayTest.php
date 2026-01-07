<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\ECPay;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\ExpireDateFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 超商代碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、ChoosePayment、CVS 特有的 meta 儲存
 * 基本 callback 行為已在 ECPayTest 中測試
 */
class ECPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_cvs';

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
            'gateway_id' => 'ecpay_cvs',
            'title' => '綠界超商代碼',
            'payment_data' => ['ChoosePayment' => 'CVS'],
            'features' => [
                new MinAmountFeature,
                new MaxAmountFeature,
                new ExpireDateFeature('StoreExpireDate', 10080, 1, 43200),
            ],
        ]);
    }

    public function test_process_payment_sends_cvs_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('CVS', $redirectData['data']['ChoosePayment']);
    }

    public function test_form_fields_has_amount_and_expire_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('expire_date', $this->gateway->form_fields);
    }

    public function test_process_payment_sends_store_expire_date()
    {
        $this->setGatewaySettings(['expire_date' => '10080']);

        $order = $this->createOrder(100);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('10080', $redirectData['data']['StoreExpireDate']);
    }

    private function setGatewaySettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            $this->gateway->update_option($key, $value);
        }
        $this->gateway->init_settings();
    }
}
