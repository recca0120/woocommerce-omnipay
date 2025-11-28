<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\YiPay;

use Omnipay\YiPay\Hasher;
use WooCommerceOmnipay\Gateways\YiPay\YiPayCVSGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay 超商代碼 Gateway 測試
 *
 * 測試子類別的差異點：gateway_id、title、type、CVS 特有的 meta 儲存
 * 基本 callback 行為已在 YiPayTest 中測試
 */
class YiPayCVSGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_cvs';

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

        $this->gateway = new YiPayCVSGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_cvs',
            'title' => '乙禾超商代碼',
        ]);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_yipay_cvs', $this->gateway->id);
        $this->assertEquals('乙禾超商代碼', $this->gateway->method_title);
    }

    public function test_process_payment_sends_cvs_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('3', $redirect_data['data']['type']);
    }

    public function test_get_payment_info_stores_cvs_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makePaymentInfoData($order, [
            'type' => '3',
            'pinCode' => 'CVS24112512345',
        ]));

        ob_start();
        $this->gateway->getPaymentInfo();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('CVS24112512345', $order->get_meta('_omnipay_payment_no'));
    }

    public function test_form_fields_has_amount_settings()
    {
        $this->assertArrayHasKey('min_amount', $this->gateway->form_fields);
        $this->assertArrayHasKey('max_amount', $this->gateway->form_fields);
    }

    private function makePaymentInfoData($order, array $overrides = [])
    {
        $type = (int) ($overrides['type'] ?? '3');

        $returnUrl = WC()->api_request_url('omnipay_yipay_cvs_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_cvs_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_cvs_payment_info');

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
            'pinCode' => $overrides['pinCode'] ?? 'CVS24112512345',
        ];

        $data = array_merge($data, $overrides);
        $data['checkCode'] = $this->sign($type, $data);

        return $data;
    }

    private function sign(int $type, array $data)
    {
        $keys = ['merchantId', 'amount', 'orderNo', 'returnURL', 'cancelURL', 'backgroundURL', 'transactionNo', 'statusCode'];
        $keys[] = match ($type) {
            3 => 'pinCode',
            4 => 'account',
            default => 'approvalCode',
        };

        $signed = [];
        foreach ($keys as $key) {
            $signed[$key] = $data[$key] ?? '';
        }

        return (new Hasher($this->key, $this->iv))->make($signed);
    }
}
