<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayBarcodeGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 超商條碼 Gateway 測試
 */
class NewebPayBarcodeGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_barcode';

    protected $gatewayName = 'NewebPay';

    protected $gatewayClass = NewebPayBarcodeGateway::class;

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

        $this->gateway = new NewebPayBarcodeGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_barcode',
            'title' => '藍新超商條碼',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_newebpay_barcode', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('藍新超商條碼', $this->gateway->method_title);
    }

    public function test_process_payment_sends_barcode_payment_type()
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
        $this->assertEquals('1', $tradeInfo['BARCODE']);
    }
}
