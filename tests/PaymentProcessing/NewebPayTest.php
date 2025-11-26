<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use Omnipay\NewebPay\Encryptor;
use WP_UnitTestCase;

/**
 * NewebPay Payment Processing Integration Tests
 *
 * 測試 NewebPay WooCommerce Gateway 的完整付款處理流程（redirect 型金流）
 *
 * NewebPay 流程：
 * 1. process_payment() -> 回傳 redirect URL，訂單狀態 on-hold（allow_resubmit = false）
 * 2. 使用者被導向 NewebPay 付款頁面
 * 3. NewebPay 透過 notifyUrl 回調 -> accept_notification()
 * 4. 使用者被導回商店 (returnUrl) -> process_complete_purchase()
 */
class NewebPayTest extends WP_UnitTestCase
{
    protected $gateway;

    protected $config_filter_callback;

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected function setUp(): void
    {
        parent::setUp();

        // 禁用測試中的 exit
        add_filter('woocommerce_omnipay_should_exit', '__return_false');

        // 清除 WordPress options cache
        wp_cache_delete('woocommerce_omnipay_newebpay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        // 覆蓋預設配置
        $this->config_filter_callback = function () {
            return [
                'gateways' => [
                    'NewebPay' => [
                        'enabled' => true,
                        'title' => '藍新金流',
                        'description' => '使用藍新金流付款',
                    ],
                ],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);

        // 設定 gateway 選項
        update_option('woocommerce_omnipay_newebpay_settings', [
            'enabled' => 'yes',
            'title' => 'NewebPay',
            'HashKey' => $this->hashKey,
            'HashIV' => $this->hashIV,
            'MerchantID' => $this->merchantId,
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ]);

        // 清空 WooCommerce payment gateways 快取並重新初始化
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_newebpay'];
    }

    // ==================== process_payment 測試 ====================

    /**
     * 測試：process_payment 回傳 success 和 redirect URL
     */
    public function test_process_payment_returns_success_with_redirect()
    {
        $order = $this->create_test_order(100);

        $result = $this->gateway->process_payment($order->get_id());

        // 驗證回傳結構
        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        // 驗證儲存的 redirect 資料
        $redirect_data = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertNotFalse($redirect_data);
        $this->assertStringContainsString('newebpay.com', $redirect_data['url']);
        $this->assertEquals('POST', $redirect_data['method']);

        // 驗證 redirect data 包含必要欄位
        $this->assertArrayHasKey('MerchantID', $redirect_data['data']);
        $this->assertArrayHasKey('TradeInfo', $redirect_data['data']);
        $this->assertArrayHasKey('TradeSha', $redirect_data['data']);
        $this->assertArrayHasKey('Version', $redirect_data['data']);

        // 配置 allow_resubmit = false，訂單應該改為 on-hold
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    // ==================== Callback 測試 ====================

    /**
     * 測試：成功的付款回調
     *
     * @dataProvider productTypeProvider
     */
    public function test_accept_notification_with_successful_payment($virtual, $downloadable, $expected_status)
    {
        $order = $this->create_order_with_product(100, 'TWD', $virtual, $downloadable);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_newebpay_callback_data($order, [
            'Status' => 'SUCCESS',
            'Message' => '授權成功',
            'TradeNo' => '24112500001234',
        ]);
        $this->simulate_newebpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expected_status, $order->get_status());
        $this->assertEquals('24112500001234', $order->get_transaction_id());
    }

    /**
     * 測試：回調驗證 TradeSha
     */
    public function test_accept_notification_validates_trade_sha()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_newebpay_callback_data($order, ['Status' => 'SUCCESS']);
        $callback_data['TradeSha'] = 'INVALID_TRADE_SHA';
        $this->simulate_newebpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('0|Incorrect TradeSha', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    public static function productTypeProvider()
    {
        return [
            'physical product' => [false, false, 'processing'],
            'virtual downloadable product' => [true, true, 'completed'],
        ];
    }

    // ==================== Payment Info 頁面測試 ====================

    /**
     * 測試：ATM 取號後使用者返回，儲存付款資訊並導向感謝頁
     *
     * NewebPay 的 CustomerURL (paymentInfoUrl) 是使用者端導向
     * 取號後使用者被導回商店，需要儲存付款資訊並顯示感謝頁
     */
    public function test_get_payment_info_stores_atm_info_and_redirects_to_thankyou()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_newebpay_payment_info_data($order, [
            'Status' => 'SUCCESS',
            'Message' => '取號成功',
            'PaymentType' => 'VACC',
            'BankCode' => '012',
            'CodeNo' => '9103522175887271',
            'ExpireDate' => '2024-12-01',
            'ExpireTime' => '23:59:59',
        ]);
        $this->simulate_newebpay_callback($callback_data);

        $redirect_url = $this->gateway->get_payment_info();

        // 驗證導向感謝頁
        $this->assertStringContainsString('order-received', $redirect_url);

        // 驗證付款資訊已儲存
        $order = wc_get_order($order->get_id());
        $this->assertEquals('012', $order->get_meta('_omnipay_bank_code'));
        $this->assertEquals('9103522175887271', $order->get_meta('_omnipay_virtual_account'));
        // 取號後訂單應維持 on-hold
        $this->assertEquals('on-hold', $order->get_status());
    }

    /**
     * 測試：CVS 取號後使用者返回，儲存付款資訊並導向感謝頁
     *
     * NewebPay 的 CustomerURL (paymentInfoUrl) 是使用者端導向
     * 取號後使用者被導回商店，需要儲存付款資訊並顯示感謝頁
     */
    public function test_get_payment_info_stores_cvs_info_and_redirects_to_thankyou()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_newebpay_payment_info_data($order, [
            'Status' => 'SUCCESS',
            'Message' => '取號成功',
            'PaymentType' => 'CVS',
            'CodeNo' => 'LLL24112512345',
            'ExpireDate' => '2024-12-01',
            'ExpireTime' => '23:59:59',
        ]);
        $this->simulate_newebpay_callback($callback_data);

        $redirect_url = $this->gateway->get_payment_info();

        // 驗證導向感謝頁
        $this->assertStringContainsString('order-received', $redirect_url);

        // 驗證付款資訊已儲存
        $order = wc_get_order($order->get_id());
        $this->assertEquals('LLL24112512345', $order->get_meta('_omnipay_payment_no'));
        $this->assertEquals('on-hold', $order->get_status());
    }

    // ==================== 輔助方法 ====================

    protected function create_test_order($amount = 100.00, $currency = 'TWD')
    {
        return $this->create_order_with_product($amount, $currency, false, false);
    }

    protected function create_order_with_product($amount, $currency, $virtual = false, $downloadable = false)
    {
        $product = new \WC_Product_Simple;
        $product->set_name('Test Product');
        $product->set_regular_price($amount);
        $product->set_virtual($virtual);
        $product->set_downloadable($downloadable);
        $product->save();

        $order = wc_create_order();
        $order->set_currency($currency);
        $order->add_product($product, 1);
        $order->calculate_totals();
        $order->save();

        return $order;
    }

    protected function create_newebpay_callback_data($order, array $overrides = [])
    {
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '授權成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'CREDIT',
            'RespondType' => 'JSON',
            'PayTime' => date('Y-m-d H:i:s'),
            'IP' => '127.0.0.1',
            'EscrowBank' => 'HNCB',
        ], $overrides);

        return $this->encrypt_callback_data($result);
    }

    protected function create_newebpay_payment_info_data($order, array $overrides = [])
    {
        $result = array_merge([
            'Status' => 'SUCCESS',
            'Message' => '取號成功',
            'MerchantID' => $this->merchantId,
            'Amt' => (int) $order->get_total(),
            'TradeNo' => '24112500001234',
            'MerchantOrderNo' => (string) $order->get_id(),
            'PaymentType' => 'VACC',
        ], $overrides);

        return $this->encrypt_callback_data($result);
    }

    protected function encrypt_callback_data(array $result)
    {
        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $tradeInfo = $encryptor->encrypt($result);

        return [
            'Status' => $result['Status'],
            'MerchantID' => $this->merchantId,
            'TradeInfo' => $tradeInfo,
            'TradeSha' => $encryptor->tradeSha($tradeInfo),
            'Version' => '2.0',
        ];
    }

    protected function simulate_newebpay_callback(array $data)
    {
        $_POST = $data;
        $_REQUEST = $data;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        delete_option('woocommerce_omnipay_newebpay_settings');

        wp_cache_delete('woocommerce_omnipay_newebpay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        if ($this->config_filter_callback) {
            remove_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);
        }
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        WC()->payment_gateways()->payment_gateways = [];

        parent::tearDown();
    }
}
