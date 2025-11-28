<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use Ecpay\Sdk\Services\CheckMacValueService;
use WooCommerceOmnipay\Gateways\ECPay\ECPayDCAGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 定期定額 Gateway 測試
 */
class ECPayDCAGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_dca';

    protected $gatewayName = 'ECPay';

    protected $gatewayClass = ECPayDCAGateway::class;

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new ECPayDCAGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_ecpay_dca', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('綠界定期定額', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirect_data['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_period_parameters()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('PeriodType', $redirect_data['data']);
        $this->assertArrayHasKey('Frequency', $redirect_data['data']);
        $this->assertArrayHasKey('ExecTimes', $redirect_data['data']);
        $this->assertArrayHasKey('PeriodAmount', $redirect_data['data']);
    }

    public function test_accept_notification_success()
    {
        $order = $this->createOrder(500);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeNo' => '2024112500001234',
            'PaymentType' => 'Credit_CreditCard',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
    }

    private function makeCallbackData($order, array $overrides = [])
    {
        $data = array_merge([
            'MerchantID' => $this->settings['MerchantID'],
            'MerchantTradeNo' => (string) $order->get_id(),
            'StoreID' => '',
            'RtnCode' => '1',
            'RtnMsg' => '交易成功',
            'TradeNo' => '2024112500001234',
            'TradeAmt' => (string) $order->get_total(),
            'PaymentDate' => date('Y/m/d H:i:s'),
            'PaymentType' => 'Credit_CreditCard',
            'PaymentTypeChargeFee' => '0',
            'TradeDate' => date('Y/m/d H:i:s'),
            'SimulatePaid' => '0',
        ], $overrides);

        $service = new CheckMacValueService(
            $this->settings['HashKey'],
            $this->settings['HashIV'],
            CheckMacValueService::METHOD_SHA256
        );
        $data['CheckMacValue'] = $service->generate($data);

        return $data;
    }
}
