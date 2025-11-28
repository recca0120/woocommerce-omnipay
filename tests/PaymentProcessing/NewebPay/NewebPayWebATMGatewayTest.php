<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayWebATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 網路 ATM Gateway 測試
 */
class NewebPayWebATMGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_webatm';

    protected $gatewayName = 'NewebPay';

    protected $gatewayClass = NewebPayWebATMGateway::class;

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected function setUp(): void
    {
        $this->settings = [
            'HashKey' => $this->hashKey,
            'HashIV' => $this->hashIV,
            'MerchantID' => $this->merchantId,
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ];
        parent::setUp();

        $this->gateway = new NewebPayWebATMGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_webatm',
            'title' => '藍新網路 ATM',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_newebpay_webatm', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('藍新網路 ATM', $this->gateway->method_title);
    }

    public function test_process_payment_sends_webatm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('TradeInfo', $redirect_data['data']);

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirect_data['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }
        $this->assertEquals('1', $tradeInfo['WEBATM']);
    }
}
