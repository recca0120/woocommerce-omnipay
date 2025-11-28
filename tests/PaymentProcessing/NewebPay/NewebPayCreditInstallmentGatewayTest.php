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

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('TradeInfo', $redirectData['data']);

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirectData['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }
        $this->assertEquals('1', $tradeInfo['CREDIT']);
        $this->assertEquals('3,6,12,18,24', $tradeInfo['InstFlag']);
    }

    public function test_form_fields_has_min_amount_and_installments()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('installments', $this->gateway->form_fields);
        $this->assertEquals('multiselect', $this->gateway->form_fields['installments']['type']);
    }

    public function test_is_available_returns_false_when_below_min_amount()
    {
        $this->gateway->update_option('min_amount', '100');
        $this->gateway->init_settings();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(50)->get_id());

        $this->assertFalse($this->gateway->is_available());
    }
}
