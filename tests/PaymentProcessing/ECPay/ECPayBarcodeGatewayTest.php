<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\ECPay;

use Ecpay\Sdk\Services\CheckMacValueService;
use WooCommerceOmnipay\Gateways\ECPay\ECPayBarcodeGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 超商條碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、ChoosePayment、Barcode 特有的 meta 儲存
 * 基本 callback 行為已在 ECPayTest 中測試
 */
class ECPayBarcodeGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_barcode';

    protected $gatewayName = 'ECPay';

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

        $this->gateway = new ECPayBarcodeGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_barcode',
            'title' => '綠界超商條碼',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_ecpay_barcode', $this->gateway->id);
        $this->assertEquals('綠界超商條碼', $this->gateway->method_title);
    }

    public function test_process_payment_sends_barcode_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('BARCODE', $redirectData['data']['ChoosePayment']);
    }

    public function test_accept_notification_stores_barcode_payment_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '10100073',
            'PaymentType' => 'BARCODE_BARCODE',
            'Barcode1' => '1104ES0987654321',
            'Barcode2' => '3453010192168',
            'Barcode3' => '110400100000100',
            'ExpireDate' => '2024/12/01 23:59:59',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('1104ES0987654321', $order->get_meta('_omnipay_barcode_1'));
        $this->assertEquals('3453010192168', $order->get_meta('_omnipay_barcode_2'));
        $this->assertEquals('110400100000100', $order->get_meta('_omnipay_barcode_3'));
        $this->assertEquals('2024/12/01 23:59:59', $order->get_meta('_omnipay_expire_date'));
        $this->assertEquals('on-hold', $order->get_status());
    }

    public function test_form_fields_has_amount_and_expire_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('expire_date', $this->gateway->form_fields);
    }

    public function test_process_payment_sends_store_expire_date()
    {
        $this->setGatewaySettings(['expire_date' => '7']);

        $order = $this->createOrder(100);
        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('7', $redirectData['data']['StoreExpireDate']);
    }

    private function setGatewaySettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            $this->gateway->update_option($key, $value);
        }
        $this->gateway->init_settings();
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
            'PaymentType' => 'BARCODE_BARCODE',
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
