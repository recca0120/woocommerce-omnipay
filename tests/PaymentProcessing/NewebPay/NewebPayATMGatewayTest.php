<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay ATM Gateway 測試
 */
class NewebPayATMGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_atm';

    protected $gatewayName = 'NewebPay';

    protected $gatewayClass = NewebPayATMGateway::class;

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

        $this->gateway = new NewebPayATMGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_atm',
            'title' => '藍新 ATM',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_newebpay_atm', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('藍新 ATM', $this->gateway->method_title);
    }

    public function test_process_payment_sends_atm_payment_type()
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
        $this->assertEquals('1', $tradeInfo['VACC']);
    }

    public function test_get_payment_info_stores_atm_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'PaymentType' => 'VACC',
            'BankCode' => '012',
            'CodeNo' => '9103522175887271',
        ]));

        $url = $this->gateway->getPaymentInfo();

        $this->assertStringContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('012', $order->get_meta('_omnipay_bank_code'));
        $this->assertEquals('9103522175887271', $order->get_meta('_omnipay_virtual_account'));
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
            'PaymentType' => 'VACC',
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
