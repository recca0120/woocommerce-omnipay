<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use Ecpay\Sdk\Services\CheckMacValueService;
use WP_UnitTestCase;

/**
 * ECPay Payment Processing Integration Tests
 *
 * 測試 ECPay WooCommerce Gateway 的完整付款處理流程（redirect 型金流）
 *
 * ECPay 流程：
 * 1. process_payment() -> 回傳 redirect URL，訂單狀態 on-hold（allow_resubmit = false）
 * 2. 使用者被導向 ECPay 付款頁面
 * 3. ECPay 透過 notifyUrl 回調 -> accept_notification()
 * 4. 使用者被導回商店 (returnUrl) -> complete_purchase()
 */
class ECPayTest extends WP_UnitTestCase
{
    protected $gateway;

    protected $config_filter_callback;

    protected function setUp(): void
    {
        parent::setUp();

        // 禁用測試中的 exit
        add_filter('woocommerce_omnipay_should_exit', '__return_false');

        // 清除 WordPress options cache，確保之前的測試不影響
        wp_cache_delete('woocommerce_omnipay_ecpay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        // 覆蓋預設配置，使測試不受 woocommerce_omnipay_get_config 影響
        $this->config_filter_callback = function () {
            return [
                'gateways' => [
                    [
                        'omnipay_name' => 'ECPay',
                        'gateway_id' => 'ecpay',
                        'title' => '綠界金流',
                        'description' => '使用綠界金流付款',
                    ],
                ],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);

        // 先設定選項（確保 gateway 讀取到正確的設定值）
        // allow_resubmit = no：使用 order_id 作為 transactionId，訂單改為 on-hold
        update_option('woocommerce_omnipay_ecpay_settings', [
            'enabled' => 'yes',
            'title' => 'ECPay',
            'HashKey' => '5294y06JbISpM5x9',
            'HashIV' => 'v77hoKGq4kWxNNIS',
            'MerchantID' => '2000132',
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ]);

        // 清空 WooCommerce payment gateways 快取並重新初始化
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_ecpay'];
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
        $this->assertStringContainsString('ecpay.com.tw', $redirect_data['url']);
        $this->assertEquals('POST', $redirect_data['method']);

        // 驗證 PaymentInfoURL 有被加入（用於 ATM/CVS/BARCODE 取號回調）
        $this->assertArrayHasKey('PaymentInfoURL', $redirect_data['data']);
        $this->assertStringContainsString('wc-api=omnipay_ecpay_notify', $redirect_data['data']['PaymentInfoURL']);

        // ECPay 配置 allow_resubmit = false，訂單應該改為 on-hold
        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    /**
     * 測試：無效訂單 ID 的處理
     */
    public function test_process_payment_with_invalid_order_fails()
    {
        $result = $this->gateway->process_payment(999999);

        $this->assertEquals('failure', $result['result']);
    }

    // ==================== Redirect Form 測試 ====================

    /**
     * 測試：redirect 請求時產生自動提交的 POST 表單
     */
    public function test_redirect_request_renders_auto_submit_form()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $_GET['omnipay_redirect'] = '1';
        $_GET['order_id'] = $order->get_id();
        $_GET['key'] = $order->get_order_key();

        ob_start();
        woocommerce_omnipay_maybe_render_redirect_form();
        $html = ob_get_clean();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('ecpay.com.tw', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('.submit()', $html);
    }

    /**
     * 測試：redirect 請求驗證 order key 或沒有 redirect 資料時不產生表單
     *
     * @dataProvider invalidRedirectRequestProvider
     */
    public function test_redirect_request_does_not_render_form($setup_callback)
    {
        $order = $this->create_test_order(100);
        $setup_callback($order, $this->gateway);

        ob_start();
        woocommerce_omnipay_maybe_render_redirect_form();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('ecpay.com.tw', $html);
    }

    public static function invalidRedirectRequestProvider()
    {
        return [
            'wrong order key' => [function ($order, $gateway) {
                $gateway->process_payment($order->get_id());
                $_GET['omnipay_redirect'] = '1';
                $_GET['order_id'] = $order->get_id();
                $_GET['key'] = 'wrong_key';
            }],
            'no redirect data' => [function ($order, $gateway) {
                $_GET['omnipay_redirect'] = '1';
                $_GET['order_id'] = $order->get_id();
                $_GET['key'] = $order->get_order_key();
            }],
        ];
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

        $callback_data = $this->create_ecpay_callback_data($order, [
            'RtnCode' => '1',
            'RtnMsg' => '交易成功',
            'TradeNo' => '2024112500001234',
        ]);
        $this->simulate_ecpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expected_status, $order->get_status());
        $this->assertEquals('2024112500001234', $order->get_transaction_id());
    }

    /**
     * 測試：失敗的付款回調
     */
    public function test_accept_notification_with_failed_payment()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_ecpay_callback_data($order, [
            'RtnCode' => '0',
            'RtnMsg' => '交易失敗',
        ]);
        $this->simulate_ecpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('0|交易失敗', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    /**
     * 測試：回調驗證 CheckMacValue
     */
    public function test_accept_notification_validates_check_mac_value()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_ecpay_callback_data($order, ['RtnCode' => '1']);
        $callback_data['CheckMacValue'] = 'INVALID_CHECK_MAC_VALUE';
        $this->simulate_ecpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('0|CheckMacValue verify failed', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    /**
     * 測試：已處理的訂單不會重複處理
     */
    public function test_accept_notification_skips_already_processed_orders()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        // 手動將訂單改為 processing（模擬已處理）
        $order = wc_get_order($order->get_id());
        $order->set_status('processing');
        $order->save();

        $callback_data = $this->create_ecpay_callback_data($order, ['RtnCode' => '1']);
        $this->simulate_ecpay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);
        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
    }

    public static function productTypeProvider()
    {
        return [
            'physical product' => [false, false, 'processing'],
            'virtual downloadable product' => [true, true, 'completed'],
        ];
    }

    // ==================== Return URL 測試 ====================

    /**
     * 測試：用戶返回成功
     *
     * @dataProvider productTypeProvider
     */
    public function test_complete_purchase_with_successful_payment($virtual, $downloadable, $expected_status)
    {
        $order = $this->create_order_with_product(100, 'TWD', $virtual, $downloadable);
        $this->gateway->process_payment($order->get_id());

        $return_data = $this->create_ecpay_callback_data($order, [
            'RtnCode' => '1',
            'TradeNo' => '2024112500001234',
        ]);
        $this->simulate_ecpay_callback($return_data);

        $redirect_url = $this->gateway->complete_purchase();

        $this->assertStringContainsString('order-received', $redirect_url);
        $order = wc_get_order($order->get_id());
        $this->assertEquals($expected_status, $order->get_status());
    }

    /**
     * 測試：用戶返回失敗
     */
    public function test_complete_purchase_with_failed_payment()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $return_data = $this->create_ecpay_callback_data($order, [
            'RtnCode' => '0',
            'RtnMsg' => '交易失敗',
        ]);
        $this->simulate_ecpay_callback($return_data);

        $redirect_url = $this->gateway->complete_purchase();

        $this->assertStringNotContainsString('order-received', $redirect_url);
    }

    // ==================== allow_resubmit 和 transaction_id_prefix 測試 ====================

    /**
     * 測試：allow_resubmit 控制 transactionId 格式和訂單狀態
     *
     * @dataProvider allowResubmitProvider
     */
    public function test_allow_resubmit_controls_transaction_id_and_order_status(
        $allow_resubmit,
        $prefix,
        $expected_status,
        $expected_id_pattern
    ) {
        $gateway_id = 'ecpay_test';

        // 透過 WooCommerce 設定來設定 allow_resubmit 和 transaction_id_prefix
        $settings = [
            'allow_resubmit' => $allow_resubmit ? 'yes' : 'no',
        ];
        if ($prefix !== null) {
            $settings['transaction_id_prefix'] = $prefix;
        }
        // OmnipayGateway 會自動加上 omnipay_ 前綴，所以 option key 是 woocommerce_omnipay_{gateway_id}_settings
        update_option('woocommerce_omnipay_'.$gateway_id.'_settings', $settings);

        $gateway = new \WooCommerceOmnipay\Gateways\OmnipayGateway([
            'gateway_id' => $gateway_id,
            'title' => 'ECPay Test',
            'omnipay_name' => 'ECPay',
        ]);

        $order = $this->create_test_order(100);
        $gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transaction_id = $order->get_meta('_omnipay_transaction_id');

        $this->assertEquals($expected_status, $order->get_status());

        $expected_base = ($prefix ?? '').$order->get_id();
        if ($expected_id_pattern === 'exact') {
            $this->assertEquals($expected_base, $transaction_id);
        } else {
            $this->assertStringStartsWith($expected_base.'T', $transaction_id);
        }

        // 清理設定
        delete_option('woocommerce_omnipay_'.$gateway_id.'_settings');
    }

    public static function allowResubmitProvider()
    {
        return [
            'allow_resubmit=no (default)' => [false, null, 'on-hold', 'exact'],
            'allow_resubmit=yes' => [true, null, 'pending', 'random'],
            'allow_resubmit=no with prefix' => [false, 'TEST', 'on-hold', 'exact'],
            'allow_resubmit=yes with prefix' => [true, 'PRE', 'pending', 'random'],
        ];
    }

    // ==================== Payment Info 回調測試 ====================

    /**
     * 測試：ATM/CVS/BARCODE 取號回調儲存付款資訊
     *
     * @dataProvider paymentInfoProvider
     */
    public function test_accept_notification_stores_payment_info($callback_data, $expected_meta)
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $data = $this->create_ecpay_callback_data($order, $callback_data);
        $this->simulate_ecpay_callback($data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        $order = wc_get_order($order->get_id());
        foreach ($expected_meta as $key => $value) {
            $this->assertEquals($value, $order->get_meta($key));
        }
        // 取號後訂單應維持 on-hold
        $this->assertEquals('on-hold', $order->get_status());
    }

    public static function paymentInfoProvider()
    {
        return [
            'ATM' => [
                [
                    'RtnCode' => '2',
                    'PaymentType' => 'ATM_TAISHIN',
                    'BankCode' => '812',
                    'vAccount' => '9103522175887271',
                    'ExpireDate' => '2024/12/01',
                ],
                [
                    '_omnipay_bank_code' => '812',
                    '_omnipay_virtual_account' => '9103522175887271',
                    '_omnipay_expire_date' => '2024/12/01',
                ],
            ],
            'CVS' => [
                [
                    'RtnCode' => '10100073',
                    'PaymentType' => 'CVS_CVS',
                    'PaymentNo' => 'LLL24112512345',
                    'ExpireDate' => '2024/12/01 23:59:59',
                ],
                [
                    '_omnipay_payment_no' => 'LLL24112512345',
                    '_omnipay_expire_date' => '2024/12/01 23:59:59',
                ],
            ],
            'BARCODE' => [
                [
                    'RtnCode' => '10100073',
                    'PaymentType' => 'BARCODE_BARCODE',
                    'Barcode1' => '1104ES0987654321',
                    'Barcode2' => '3453010192168',
                    'Barcode3' => '110400100000100',
                    'ExpireDate' => '2024/12/01 23:59:59',
                ],
                [
                    '_omnipay_barcode_1' => '1104ES0987654321',
                    '_omnipay_barcode_2' => '3453010192168',
                    '_omnipay_barcode_3' => '110400100000100',
                    '_omnipay_expire_date' => '2024/12/01 23:59:59',
                ],
            ],
        ];
    }

    // ==================== Payment Info 顯示測試 ====================

    /**
     * 測試：付款資訊 HTML 輸出
     *
     * @dataProvider paymentInfoDisplayProvider
     */
    public function test_get_payment_info_output($meta_data, $expected_contains)
    {
        $order = $this->create_test_order(100);
        foreach ($meta_data as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        $order->save();

        $html = $this->gateway->get_payment_info_output($order);

        foreach ($expected_contains as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    /**
     * 測試：沒有付款資訊時回傳空字串
     */
    public function test_get_payment_info_output_returns_empty_when_no_info()
    {
        $order = $this->create_test_order(100);
        $html = $this->gateway->get_payment_info_output($order);
        $this->assertEmpty($html);
    }

    public static function paymentInfoDisplayProvider()
    {
        return [
            'ATM' => [
                ['_omnipay_bank_code' => '812', '_omnipay_virtual_account' => '9103522175887271', '_omnipay_expire_date' => '2024/12/01'],
                ['812', '9103522175887271', '2024/12/01'],
            ],
            'CVS' => [
                ['_omnipay_payment_no' => 'LLL24112512345', '_omnipay_expire_date' => '2024/12/01 23:59:59'],
                ['LLL24112512345', '2024/12/01 23:59:59'],
            ],
            'BARCODE' => [
                ['_omnipay_barcode_1' => '1104ES0987654321', '_omnipay_barcode_2' => '3453010192168', '_omnipay_barcode_3' => '110400100000100'],
                ['1104ES0987654321', '3453010192168', '110400100000100'],
            ],
        ];
    }

    /**
     * 測試：各 hook 正確顯示付款資訊
     *
     * @dataProvider paymentInfoHooksProvider
     */
    public function test_payment_info_displayed_on_hooks($hook, $hook_args_callback)
    {
        $order = $this->create_test_order(100);
        $order->set_payment_method($this->gateway->id);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->save();

        ob_start();
        $args = $hook_args_callback($order);
        do_action($hook, ...$args);
        $html = ob_get_clean();

        $this->assertStringContainsString('9103522175887271', $html);
    }

    /**
     * 測試：非 ECPay 訂單不顯示付款資訊
     */
    public function test_payment_info_not_displayed_for_other_gateways()
    {
        $order = $this->create_test_order(100);
        $order->set_payment_method('other_gateway');
        $order->update_meta_data('_omnipay_bank_code', '812');
        $order->save();

        ob_start();
        do_action('woocommerce_admin_order_data_after_billing_address', $order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public static function paymentInfoHooksProvider()
    {
        return [
            'admin order page' => [
                'woocommerce_admin_order_data_after_billing_address',
                fn ($order) => [$order],
            ],
            'email' => [
                'woocommerce_email_after_order_table',
                fn ($order) => [$order, true, false],
            ],
        ];
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

    protected function create_ecpay_callback_data($order, array $overrides = [])
    {
        $data = [
            'MerchantID' => $this->gateway->get_option('MerchantID'),
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
        ];

        $data = array_merge($data, $overrides);
        $data['CheckMacValue'] = $this->calculate_check_mac_value($data);

        return $data;
    }

    protected function calculate_check_mac_value(array $data)
    {
        $service = new CheckMacValueService(
            $this->gateway->get_option('HashKey'),
            $this->gateway->get_option('HashIV'),
            CheckMacValueService::METHOD_SHA256
        );

        return $service->generate($data);
    }

    protected function simulate_ecpay_callback(array $data)
    {
        $_POST = $data;
        $_REQUEST = $data;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        delete_option('woocommerce_omnipay_ecpay_settings');

        // 清除 WordPress options cache
        wp_cache_delete('woocommerce_omnipay_ecpay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        // 移除 filter 並清空 gateway 快取
        if ($this->config_filter_callback) {
            remove_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);
        }
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        WC()->payment_gateways()->payment_gateways = [];

        parent::tearDown();
    }
}
