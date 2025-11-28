<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use Ecpay\Sdk\Services\CheckMacValueService;
use WooCommerceOmnipay\Gateways\ECPay\ECPayCVSGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 超商代碼 Gateway 測試
 */
class ECPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_cvs';

    protected $gatewayName = 'ECPay';

    protected $gatewayClass = ECPayCVSGateway::class;

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

        $this->gateway = new ECPayCVSGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_cvs',
            'title' => '綠界超商代碼',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_ecpay_cvs', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('綠界超商代碼', $this->gateway->method_title);
    }

    public function test_process_payment_sends_cvs_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('CVS', $redirect_data['data']['ChoosePayment']);
    }

    public function test_accept_notification_stores_cvs_payment_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '10100073',
            'PaymentType' => 'CVS_CVS',
            'PaymentNo' => 'LLL24112512345',
            'ExpireDate' => '2024/12/01 23:59:59',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('LLL24112512345', $order->get_meta('_omnipay_payment_no'));
        $this->assertEquals('2024/12/01 23:59:59', $order->get_meta('_omnipay_expire_date'));
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
            'PaymentType' => 'CVS_CVS',
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
