<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\YiPay;

use Omnipay\YiPay\Hasher;
use WooCommerceOmnipay\Gateways\YiPay\YiPayCreditGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * YiPay 信用卡 Gateway 測試
 */
class YiPayCreditGatewayTest extends TestCase
{
    protected $gatewayId = 'yipay_credit';

    protected $gatewayName = 'YiPay';

    protected $gatewayClass = YiPayCreditGateway::class;

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

        $this->gateway = new YiPayCreditGateway([
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_credit',
            'title' => '乙禾信用卡',
        ]);
    }

    public function test_gateway_has_correct_id()
    {
        $this->assertEquals('omnipay_yipay_credit', $this->gateway->id);
    }

    public function test_gateway_has_correct_title()
    {
        $this->assertEquals('乙禾信用卡', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('2', $redirect_data['data']['type']);
    }

    public function test_accept_notification_success()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'statusCode' => '00',
            'transactionNo' => 'YP24112500001234',
            'type' => '2',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
    }

    private function makeCallbackData($order, array $overrides = [])
    {
        $type = (int) ($overrides['type'] ?? '2');

        $returnUrl = WC()->api_request_url('omnipay_yipay_credit_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_credit_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_credit_payment_info');

        $isOffline = in_array($type, [3, 4], true);
        $data = [
            'merchantId' => $this->merchantId,
            'orderNo' => (string) $order->get_id(),
            'amount' => (string) ((int) $order->get_total()),
            'statusCode' => '00',
            'statusMessage' => '交易成功',
            'transactionNo' => 'YP24112500001234',
            'type' => (string) $type,
            'returnURL' => $isOffline ? $notifyUrl : $returnUrl,
            'cancelURL' => $returnUrl,
            'backgroundURL' => $isOffline ? $paymentInfoUrl : $notifyUrl,
            'approvalCode' => $overrides['approvalCode'] ?? 'ABC123',
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
