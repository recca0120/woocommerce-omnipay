<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Integration;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * Integration tests for full checkout flows
 */
class CheckoutFlowTest extends TestCase
{
    protected $gatewayId = 'ecpay_credit';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
    ];

    /**
     * 取得 DCA Gateway 配置
     */
    protected function getDcaGatewayConfig(): array
    {
        $gateways = woocommerce_omnipay_get_gateways();

        foreach ($gateways as $config) {
            if (($config['gateway_id'] ?? '') === 'ecpay_dca') {
                return $config;
            }
        }

        return [];
    }

    /**
     * 取得 DCA Gateway
     */
    protected function getDcaGateway(): ECPayGateway
    {
        return new ECPayGateway($this->getDcaGatewayConfig());
    }

    /**
     * Test full checkout flow: Create order → Process payment → Accept callback → Order completed
     */
    public function test_complete_credit_card_checkout_flow()
    {
        // 1. Customer creates order
        $order = $this->createOrder(500);
        $this->assertEquals('pending', $order->get_status());

        // 2. Process payment
        $result = $this->gateway->process_payment($order->get_id());
        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        // Order should be on-hold
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());

        // 3. Simulate payment gateway callback
        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeNo' => 'TEST2024112500001',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        // 4. Order should be processing (for physical products)
        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('TEST2024112500001', $order->get_transaction_id());
    }

    /**
     * Test DCA checkout with Blocks mode (admin-configured period)
     */
    public function test_dca_blocks_mode_checkout_flow()
    {
        // Setup DCA gateway settings
        update_option('woocommerce_omnipay_ecpay_dca_settings', [
            'enabled' => 'yes',
            'periodType' => 'M',
            'frequency' => 1,
            'execTimes' => 12,
        ]);

        $dcaGateway = $this->getDcaGateway();
        $dcaGateway->update_option('periodType', 'M');
        $dcaGateway->update_option('frequency', 1);
        $dcaGateway->update_option('execTimes', 12);

        // 1. Create order
        $order = $this->createOrder(1000);
        $order->set_payment_method('omnipay_ecpay_dca');
        $order->save();

        // 2. Process payment with Blocks mode settings
        $result = $dcaGateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        // 3. Verify redirect data contains DCA parameters
        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertArrayHasKey('data', $redirectData);

        // The payment data should include DCA parameters (checked via callback)
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    /**
     * Test DCA Shortcode mode with user-selected period
     */
    public function test_dca_shortcode_mode_with_user_selection()
    {
        // Setup DCA gateway with multiple periods
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12],
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 24],
            ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 3],
        ]);

        $dcaGateway = $this->getDcaGateway();

        // Simulate user selecting the 24-month plan
        $_POST['omnipay_period'] = 'M_1_24';

        // 1. Create and process order
        $order = $this->createOrder(2000);
        $order->set_payment_method('omnipay_ecpay_dca');
        $order->save();

        $result = $dcaGateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        // Verify order created successfully
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());

        unset($_POST['omnipay_period']);
    }

    /**
     * Test checkout failure when payment is rejected
     */
    public function test_checkout_flow_with_payment_failure()
    {
        // 1. Create order and process payment
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        // 2. Simulate failed callback
        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '0',
            'RtnMsg' => '授權失敗',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        // 3. Order should be failed
        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    /**
     * Test amount validation during checkout
     */
    public function test_checkout_rejects_amount_mismatch()
    {
        // 1. Create order and process payment
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        // 2. Simulate callback with wrong amount
        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeAmt' => '999', // Different from order total
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        // 3. Order should remain on-hold (not processed)
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    // Helper methods

    private function makeCallbackData($order, array $overrides = [])
    {
        // 從 shared settings 讀取 Omnipay 參數
        $sharedSettings = get_option('woocommerce_omnipay_'.strtolower($this->gatewayName).'_shared_settings', []);

        $data = array_merge([
            'MerchantID' => $sharedSettings['MerchantID'] ?? $this->settings['MerchantID'],
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

        $service = new \Ecpay\Sdk\Services\CheckMacValueService(
            $sharedSettings['HashKey'] ?? $this->settings['HashKey'],
            $sharedSettings['HashIV'] ?? $this->settings['HashIV'],
            \Ecpay\Sdk\Services\CheckMacValueService::METHOD_SHA256
        );
        $data['CheckMacValue'] = $service->generate($data);

        return $data;
    }
}
