<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\NewebPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 網路 ATM Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、WEBATM 參數）
 * 其他行為已在 NewebPayTest 中測試
 */
class NewebPayWebATMGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_webatm';

    protected $gatewayName = 'NewebPay';

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected $settings = [
        'HashKey' => 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt',
        'HashIV' => 'VVcW9t4feCshKOTi',
        'MerchantID' => 'MS350098593',
        'testMode' => 'yes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new NewebPayGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_webatm',
            'title' => '藍新網路 ATM',
            'payment_data' => ['WEBATM' => 1],
            'features' => [new MinAmountFeature, new MaxAmountFeature],
        ]);
    }

    public function test_process_payment_sends_webatm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('TradeInfo', $redirectData['data']);

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirectData['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }
        $this->assertEquals('1', $tradeInfo['WEBATM']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->gateway->update_option('min_amount', '100');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }

    public function test_is_available_returns_false_when_above_max_amount()
    {
        $this->gateway->update_option('max_amount', '10000');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(15000)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }
}
