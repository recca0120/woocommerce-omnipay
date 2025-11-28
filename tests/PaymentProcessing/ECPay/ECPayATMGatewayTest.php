<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use Ecpay\Sdk\Services\CheckMacValueService;
use WooCommerceOmnipay\Gateways\ECPay\ECPayATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay ATM Gateway 測試
 */
class ECPayATMGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_atm';

    protected $gatewayName = 'ECPay';

    protected $gatewayClass = ECPayATMGateway::class;

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

        $this->gateway = new ECPayATMGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_atm',
            'title' => '綠界 ATM',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_ecpay_atm', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('綠界 ATM', $this->gateway->method_title);
    }

    public function test_process_payment_sends_atm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('ATM', $redirect_data['data']['ChoosePayment']);
    }

    public function test_accept_notification_stores_atm_payment_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '2',
            'PaymentType' => 'ATM_TAISHIN',
            'BankCode' => '812',
            'vAccount' => '9103522175887271',
            'ExpireDate' => '2024/12/01',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('812', $order->get_meta('_omnipay_bank_code'));
        $this->assertEquals('9103522175887271', $order->get_meta('_omnipay_virtual_account'));
        $this->assertEquals('2024/12/01', $order->get_meta('_omnipay_expire_date'));
        $this->assertEquals('on-hold', $order->get_status());
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
            'PaymentType' => 'ATM_TAISHIN',
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
