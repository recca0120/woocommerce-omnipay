<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\YiPay;

use Omnipay\YiPay\Hasher;
use WooCommerceOmnipay\Gateways\YiPay\YiPayATMGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay ATM Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、type、ATM 特有的 meta 儲存
 * 基本 callback 行為已在 YiPayTest 中測試
 */
class YiPayATMGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_atm';

    protected $gatewayName = 'YiPay';

    private $merchantId = '1234567890';

    private $key = 'dGVzdGtleXRlc3QxMjM0NQ==';

    private $iv = 'dGVzdGl2dGVzdDEyMzQ1Ng==';

    protected function setUp(): void
    {
        $this->settings = [
            'merchantId' => $this->merchantId,
            'key' => $this->key,
            'iv' => $this->iv,
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ];
        parent::setUp();

        $this->gateway = new YiPayATMGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_atm',
            'title' => '乙禾 ATM',
        ]);
    }

    public function test_process_payment_sends_atm_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('4', $redirectData['data']['type']);
    }

    public function test_get_payment_info_stores_atm_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'type' => '4',
            'account' => '9103522175887271',
        ]));

        ob_start();
        $this->gateway->getPaymentInfo();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('9103522175887271', $order->get_meta('_omnipay_virtual_account'));
    }

    public function test_form_fields_has_amount_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
    }

    private function makePaymentInfoData($order, array $overrides = [])
    {
        $type = (int) ($overrides['type'] ?? '4');

        $returnUrl = WC()->api_request_url('omnipay_yipay_atm_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_atm_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_atm_payment_info');

        $data = [
            'merchantId' => $this->merchantId,
            'orderNo' => (string) $order->get_id(),
            'amount' => (string) ((int) $order->get_total()),
            'statusCode' => '00',
            'statusMessage' => '取號成功',
            'transactionNo' => '',
            'type' => (string) $type,
            'returnURL' => $notifyUrl,
            'cancelURL' => $returnUrl,
            'backgroundURL' => $paymentInfoUrl,
            'account' => $overrides['account'] ?? '9103522175887271',
        ];

        $data = array_merge($data, $overrides);
        $data['checkCode'] = $this->sign($type, $data);

        return $data;
    }

    private function sign(int $type, array $data)
    {
        $keys = ['merchantId', 'amount', 'orderNo', 'returnURL', 'cancelURL', 'backgroundURL', 'transactionNo', 'statusCode'];
        $typeKeys = [3 => 'pinCode', 4 => 'account'];
        $keys[] = $typeKeys[$type] ?? 'approvalCode';

        $signed = [];
        foreach ($keys as $key) {
            $signed[$key] = $data[$key] ?? '';
        }

        return (new Hasher($this->key, $this->iv))->make($signed);
    }
}
