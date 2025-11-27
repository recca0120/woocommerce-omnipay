<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use WP_UnitTestCase;

/**
 * Dummy Gateway Payment Processing Tests
 *
 * 測試 Dummy Gateway 的付款處理流程（Direct 型金流）
 * 使用真實的 WooCommerce 訂單和 Omnipay Gateway
 */
class DummyTest extends WP_UnitTestCase
{
    protected $gateway;

    private $config_filter_callback;

    protected function setUp(): void
    {
        parent::setUp();

        // 註冊 Dummy gateway
        $this->config_filter_callback = function ($config) {
            return [
                'gateways' => [
                    [
                        'omnipay_name' => 'Dummy',
                        'gateway_id' => 'dummy',
                        'title' => 'Dummy Gateway',
                        'description' => 'Dummy payment gateway for testing',
                    ],
                ],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);

        // 啟用 Dummy Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'yes',
            'title' => 'Dummy Gateway',
        ]);

        // 清空 WooCommerce payment gateways 快取並重新初始化
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_dummy'];
    }

    /**
     * 測試：成功的付款處理
     */
    public function test_successful_payment_processing()
    {
        // 建立測試訂單
        $order = $this->create_test_order(100.00);

        // 模擬成功的卡片資料
        $card_data = $this->get_successful_card_data();
        $this->simulate_card_post_data($card_data);

        // 執行付款
        $result = $this->gateway->process_payment($order->get_id());

        // 驗證回傳結果
        $this->assertEquals('success', $result['result'], 'Payment should succeed');
        $this->assertArrayHasKey('redirect', $result, 'Should have redirect URL');

        // 重新載入訂單，驗證狀態
        $order = wc_get_order($order->get_id());
        $this->assertContains(
            $order->get_status(),
            ['processing', 'pending', 'on-hold', 'completed'],
            'Order status should be processing, pending, on-hold, or completed'
        );

        // 驗證 transaction reference 已儲存
        $transaction_id = $order->get_transaction_id();
        $this->assertNotEmpty($transaction_id, 'Transaction ID should be saved');

        // 驗證訂單備註包含成功訊息
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $success_note_found = false;
        foreach ($notes as $note) {
            if (stripos($note->content, 'success') !== false || stripos($note->content, 'completed') !== false) {
                $success_note_found = true;
                break;
            }
        }
        $this->assertTrue($success_note_found, 'Should have success message in order notes');
    }

    /**
     * 測試：失敗的付款處理
     */
    public function test_failed_payment_processing()
    {
        // 建立測試訂單
        $order = $this->create_test_order(100.00);

        // 模擬失敗的卡片資料
        $card_data = $this->get_failed_card_data();
        $this->simulate_card_post_data($card_data);

        // 執行付款
        $result = $this->gateway->process_payment($order->get_id());

        // 驗證回傳結果
        $this->assertEquals('failure', $result['result'], 'Payment should fail');

        // 重新載入訂單，驗證狀態
        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status(), 'Order status should be failed');

        // 驗證有錯誤訊息在訂單備註中
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $this->assertNotEmpty($notes, 'Should have order notes');

        $error_note_found = false;
        foreach ($notes as $note) {
            if (stripos($note->content, 'failed') !== false || stripos($note->content, 'failure') !== false) {
                $error_note_found = true;
                break;
            }
        }
        $this->assertTrue($error_note_found, 'Should have error message in order notes');
    }

    /**
     * 測試：無效訂單 ID 的處理
     */
    public function test_payment_with_invalid_order_fails()
    {
        $card_data = $this->get_successful_card_data();
        $this->simulate_card_post_data($card_data);

        // 使用不存在的訂單 ID
        $result = $this->gateway->process_payment(999999);

        // 驗證回傳失敗
        $this->assertEquals('failure', $result['result']);
    }

    // ==================== 輔助方法 ====================

    /**
     * 建立測試訂單
     *
     * @param  float  $amount  訂單金額
     * @param  string  $currency  幣別
     * @return \WC_Order
     */
    protected function create_test_order($amount = 100.00, $currency = 'USD')
    {
        $order = wc_create_order();
        $order->set_currency($currency);
        $order->set_total($amount);
        $order->save();

        return $order;
    }

    /**
     * 建立成功的卡片資料（偶數結尾）
     *
     * @return array
     */
    protected function get_successful_card_data()
    {
        return [
            'number' => '4242424242424242',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'cvv' => '123',
            'firstName' => 'Test',
            'lastName' => 'User',
        ];
    }

    /**
     * 建立失敗的卡片資料（奇數結尾）
     *
     * @return array
     */
    protected function get_failed_card_data()
    {
        return [
            'number' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'cvv' => '123',
            'firstName' => 'Test',
            'lastName' => 'User',
        ];
    }

    /**
     * 模擬 POST 卡片資料
     *
     * @param  array  $card_data  卡片資料
     */
    protected function simulate_card_post_data($card_data)
    {
        foreach ($card_data as $key => $value) {
            $_POST['omnipay_'.$key] = $value;
        }
    }

    protected function tearDown(): void
    {
        $_POST = [];
        remove_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);
        delete_option('woocommerce_omnipay_dummy_settings');
        parent::tearDown();
    }
}
