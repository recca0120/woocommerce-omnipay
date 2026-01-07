<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\NewebPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 超商代碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、CVS 參數、CVS 特有的 meta 儲存
 * 基本 callback 行為已在 NewebPayTest 中測試
 */
class NewebPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_cvs';

    protected $gatewayName = 'NewebPay';

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected $settings = [
        'HashKey' => 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt',
        'HashIV' => 'VVcW9t4feCshKOTi',
        'MerchantID' => 'MS350098593',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new NewebPayGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_cvs',
            'title' => '藍新超商代碼',
            'payment_data' => ['CVS' => 1],
            'features' => [new MinAmountFeature, new MaxAmountFeature],
        ]);
    }

    public function test_process_payment_sends_cvs_payment_type()
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
        $this->assertEquals('1', $tradeInfo['CVS']);
    }

    public function test_get_payment_info_stores_cvs_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'PaymentType' => 'CVS',
            'CodeNo' => 'LLL24112512345',
        ]));

        $url = $this->gateway->handlePaymentInfoCallback();

        $this->assertStringContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('LLL24112512345', $order->get_meta('_omnipay_payment_no'));
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

    private function makePaymentInfoData($order, array $overrides = [])
    {
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '取號成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'CVS',
        ], $overrides);

        return $this->encrypt($result);
    }

    private function encrypt(array $result)
    {
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->encrypt($result);

        return [
            'Status' => $result['Status'],
            'MerchantID' => $this->merchantId,
            'TradeInfo' => $tradeInfo,
            'TradeSha' => $encryptor->tradeSha($tradeInfo),
            'Version' => '2.0',
        ];
    }
}
