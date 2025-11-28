<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayCreditGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 信用卡 Gateway 測試
 */
class NewebPayCreditGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_credit';

    protected $gatewayName = 'NewebPay';

    protected $gatewayClass = NewebPayCreditGateway::class;

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

        $this->gateway = new NewebPayCreditGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_credit',
            'title' => '藍新信用卡',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_newebpay_credit', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('藍新信用卡', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('TradeInfo', $redirect_data['data']);

        // 解密 TradeInfo 驗證 CREDIT=1
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirect_data['data']['TradeInfo']);

        // 解密後可能是陣列或 query string 格式
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }
        $this->assertEquals('1', $tradeInfo['CREDIT']);
    }

    public function test_accept_notification_success()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'Status' => 'SUCCESS',
            'TradeNo' => '24112500001234',
            'PaymentType' => 'CREDIT',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
    }

    private function makeCallbackData($order, array $overrides = [])
    {
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '授權成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'CREDIT',
            'RespondType' => 'JSON',
            'PayTime' => date('Y-m-d H:i:s'),
            'IP' => '127.0.0.1',
            'EscrowBank' => 'HNCB',
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
