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

    public function test_multiselect_installments_saved_and_retrieved_as_array()
    {
        // Save multiselect value as array
        $installments = ['3', '6', '12'];
        $this->gateway->update_option('installments', $installments);

        // Retrieve should return array (not string)
        $retrieved = $this->gateway->get_option('installments');

        $this->assertIsArray($retrieved, 'installments should be returned as array');
        $this->assertEquals($installments, $retrieved);
        $this->assertCount(3, $retrieved);
    }

    public function test_process_payment_sends_credit_with_installment()
    {
        // Set up installments first
        $this->gateway->update_option('installments', ['3', '6', '12', '18', '24']);

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

    public function test_process_payment_sends_selected_installment_from_post_data()
    {
        $order = $this->createOrder(3000);

        // Simulate user selecting 6 installments
        $_POST['omnipay_installment'] = '6';

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirectData['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }

        $this->assertEquals('6', $tradeInfo['InstFlag']);

        unset($_POST['omnipay_installment']);
    }

    public function test_payment_fields_includes_installment_select()
    {
        $this->gateway->update_option('installments', ['3', '6', '12']);
        $this->gateway->init_settings();

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        $this->assertStringContainsString('omnipay_installment', $output);
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('value="6"', $output);
        $this->assertStringContainsString('value="12"', $output);
    }

    public function test_process_payment_keeps_30_as_is_not_30_n()
    {
        // Set up installments including 30 period
        $this->gateway->update_option('installments', ['3', '6', '12', '30']);

        $order = $this->createOrder(25000);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirectData['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }

        // NewebPay should keep '30' as is (not convert to '30N')
        $this->assertEquals('3,6,12,30', $tradeInfo['InstFlag']);
    }

    public function test_process_payment_keeps_selected_30_as_is()
    {
        $order = $this->createOrder(25000);

        // Simulate user selecting 30 installments
        $_POST['omnipay_installment'] = '30';

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->decrypt($redirectData['data']['TradeInfo']);
        if (is_string($tradeInfo)) {
            parse_str($tradeInfo, $tradeInfo);
        }

        // NewebPay should keep selected '30' as is
        $this->assertEquals('30', $tradeInfo['InstFlag']);

        unset($_POST['omnipay_installment']);
    }

    public function test_payment_fields_shows_30_period_regardless_of_amount()
    {
        $this->gateway->update_option('installments', ['3', '6', '12', '30']);
        $this->gateway->init_settings();

        // Test with amount below 20000
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->createProduct(5000)->get_id());

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // NewebPay should show 30 period even when amount < 20000
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('value="6"', $output);
        $this->assertStringContainsString('value="12"', $output);
        $this->assertStringContainsString('value="30"', $output);
    }
}
