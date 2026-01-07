<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\NewebPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay ATM Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、VACC 參數、ATM 特有的 meta 儲存
 * 基本 callback 行為已在 NewebPayTest 中測試
 */
class NewebPayATMGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_atm';

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
            'gateway_id' => 'newebpay_atm',
            'title' => '藍新 ATM',
            'payment_data' => ['VACC' => 1],
            'features' => [new MinAmountFeature, new MaxAmountFeature],
        ]);
    }

    public function test_process_payment_sends_atm_payment_type()
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
        $this->assertEquals('1', $tradeInfo['VACC']);
    }
}
