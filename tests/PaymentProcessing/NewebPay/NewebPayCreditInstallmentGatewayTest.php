<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayCreditInstallmentGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 信用卡分期 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、CREDIT、InstFlag 參數）
 * 其他行為已在 NewebPayTest 中測試
 */
class NewebPayCreditInstallmentGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_credit_installment';

    protected $gatewayName = 'NewebPay';

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
        ];
        parent::setUp();

        $this->gateway = new NewebPayCreditInstallmentGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_credit_installment',
            'title' => '藍新信用卡分期',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_newebpay_credit_installment', $this->gateway->id);
        $this->assertEquals('藍新信用卡分期', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_with_installment()
    {
        $order = $this->createOrder(3000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('TradeInfo', $redirect_data['data']);

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirect_data['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }
        $this->assertEquals('1', $tradeInfo['CREDIT']);
        $this->assertArrayHasKey('InstFlag', $tradeInfo);
    }
}
