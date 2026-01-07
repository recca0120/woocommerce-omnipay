<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\YiPay;

use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\YiPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay 超商代碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、type、CVS 特有的 meta 儲存
 * 基本 callback 行為已在 YiPayTest 中測試
 */
class YiPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_cvs';

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
            'gateway_id' => 'yipay_cvs',
            'title' => 'YiPay 超商代碼',
            'payment_data' => ['type' => '3'],
            'features' => [new MinAmountFeature, new MaxAmountFeature],
        ]);
    }

    public function test_process_payment_sends_cvs_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('3', $redirectData['data']['type']);
    }

    public function test_form_fields_has_amount_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
    }
}
