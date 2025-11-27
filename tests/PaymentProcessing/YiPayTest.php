<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use Omnipay\YiPay\Hasher;
use WP_UnitTestCase;

/**
 * YiPay Payment Processing Integration Tests
 *
 * 測試 YiPay WooCommerce Gateway 的完整付款處理流程（redirect 型金流）
 *
 * YiPay 流程：
 * 1. process_payment() -> 回傳 redirect URL，訂單狀態 on-hold（allow_resubmit = false）
 * 2. 使用者被導向 YiPay 付款頁面
 * 3. YiPay 透過 backgroundURL 回調 -> accept_notification()
 * 4. 使用者被導回商店 (returnURL) -> complete_purchase()
 *
 * YiPay 付款類型：
 * - type=1: 信用卡付款
 * - type=2: 信用卡 3D 付款
 * - type=3: 超商代碼繳費
 * - type=4: ATM 虛擬帳號繳款
 */
class YiPayTest extends WP_UnitTestCase
{
    protected $gateway;

    protected $config_filter_callback;

    private $merchantId = '1234567890';

    private $key = 'dGVzdGtleXRlc3QxMjM0NQ=='; // base64 encoded, 16 bytes AES key

    private $iv = 'dGVzdGl2dGVzdDEyMzQ1Ng=='; // base64 encoded, 16 bytes IV

    protected function setUp(): void
    {
        parent::setUp();

        // 禁用測試中的 exit
        add_filter('woocommerce_omnipay_should_exit', '__return_false');

        // 清除 WordPress options cache
        wp_cache_delete('woocommerce_omnipay_yipay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        // 覆蓋預設配置
        $this->config_filter_callback = function () {
            return [
                'gateways' => [
                    [
                        'omnipay_name' => 'YiPay',
                        'gateway_id' => 'yipay',
                        'title' => 'YiPay 乙禾金流',
                        'description' => '使用 YiPay 乙禾金流付款',
                    ],
                ],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);

        // 設定 gateway 選項
        update_option('woocommerce_omnipay_yipay_settings', [
            'enabled' => 'yes',
            'title' => 'YiPay',
            'merchantId' => $this->merchantId,
            'key' => $this->key,
            'iv' => $this->iv,
            'testMode' => 'yes',
            'allow_resubmit' => 'no',
        ]);

        // 清空 WooCommerce payment gateways 快取並重新初始化
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_yipay'];
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
        $this->assertStringContainsString('yipay.com.tw', $redirect_data['url']);
        $this->assertEquals('POST', $redirect_data['method']);

        // 驗證 redirect data 包含必要欄位
        $this->assertArrayHasKey('merchantId', $redirect_data['data']);
        $this->assertArrayHasKey('orderNo', $redirect_data['data']);
        $this->assertArrayHasKey('amount', $redirect_data['data']);
        $this->assertArrayHasKey('checkCode', $redirect_data['data']);

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

        $callback_data = $this->create_yipay_callback_data($order, [
            'statusCode' => '00',
            'statusMessage' => '交易成功',
            'transactionNo' => 'YP24112500001234',
            'type' => '2',
        ]);
        $this->simulate_yipay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expected_status, $order->get_status());
        $this->assertEquals('YP24112500001234', $order->get_transaction_id());
    }

    /**
     * 測試：回調驗證 checkCode
     */
    public function test_accept_notification_validates_check_code()
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_yipay_callback_data($order, [
            'statusCode' => '00',
            'type' => '2',
        ]);
        $callback_data['checkCode'] = 'INVALID_CHECK_CODE';
        $this->simulate_yipay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        $output = ob_get_clean();

        $this->assertEquals('0|Incorrect checkCode', $output);

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

    // ==================== ATM/CVS 付款類型測試 ====================

    /**
     * 測試：ATM/CVS 付款成功回調（YiPay 特有的 URL 對應）
     *
     * YiPay type=3,4 時 URLs 對應不同：
     * - returnURL = notifyUrl
     * - backgroundURL = paymentInfoUrl
     *
     * @dataProvider paymentTypeProvider
     */
    public function test_accept_notification_with_payment_type($type, $extraField, $extraValue)
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        $callback_data = $this->create_yipay_callback_data($order, [
            'statusCode' => '00',
            'statusMessage' => '交易成功',
            'type' => $type,
            $extraField => $extraValue,
            'transactionNo' => 'YP24112500005678',
        ]);
        $this->simulate_yipay_callback($callback_data);

        ob_start();
        $this->gateway->accept_notification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('YP24112500005678', $order->get_transaction_id());
    }

    public static function paymentTypeProvider()
    {
        return [
            'ATM (type=4)' => ['4', 'account', '9103522175887271'],
            'CVS (type=3)' => ['3', 'pinCode', 'CVS24112512345'],
        ];
    }

    // ==================== Payment Info 儲存測試 ====================

    /**
     * 測試：ATM/CVS 付款資訊儲存（get_payment_info 端點）
     *
     * YiPay ATM/CVS 的 backgroundURL 對應 paymentInfoUrl，
     * 會呼叫 get_payment_info() 來儲存付款資訊
     *
     * @dataProvider paymentInfoProvider
     */
    public function test_get_payment_info_stores_payment_info($type, $yipayField, $yipayValue, $standardField)
    {
        $order = $this->create_test_order(100);
        $this->gateway->process_payment($order->get_id());

        // 模擬 YiPay 的 payment info callback（backgroundURL）
        $callback_data = $this->create_yipay_payment_info_data($order, [
            'type' => $type,
            $yipayField => $yipayValue,
        ]);
        $this->simulate_yipay_callback($callback_data);

        ob_start();
        $this->gateway->get_payment_info();
        ob_get_clean();

        // 驗證付款資訊已儲存（使用 OrderRepository 的 meta key）
        $order = wc_get_order($order->get_id());
        $this->assertEquals($yipayValue, $order->get_meta($standardField));

        // 驗證訂單備註
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $noteTexts = array_column($notes, 'content');
        $typeName = $type === '4' ? 'ATM' : 'CVS';
        $this->assertTrue(
            in_array("YiPay 取號成功 ({$typeName})，等待付款", $noteTexts),
            'Order note should contain payment info success message'
        );
    }

    public static function paymentInfoProvider()
    {
        return [
            'ATM (type=4)' => ['4', 'account', '9103522175887271', '_omnipay_virtual_account'],
            'CVS (type=3)' => ['3', 'pinCode', 'CVS24112512345', '_omnipay_payment_no'],
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

    protected function create_yipay_callback_data($order, array $overrides = [])
    {
        $type = (int) ($overrides['type'] ?? '2');

        // YiPay CompletePurchaseRequest 會用 $this->getUrls() 重新計算 URLs
        // YiPayGateway::get_callback_parameters() 會傳入 returnUrl, notifyUrl, paymentInfoUrl
        // URLs 對應會根據 type 不同：
        // - type=1,2（信用卡）：returnURL=returnUrl, backgroundURL=notifyUrl
        // - type=3,4（ATM/CVS）且有 paymentInfoUrl：returnURL=notifyUrl, backgroundURL=paymentInfoUrl
        $returnUrl = WC()->api_request_url('omnipay_yipay_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_payment_info');

        // 根據 type 決定 URLs 對應
        if (in_array($type, [3, 4], true)) {
            // ATM/CVS: returnURL=notifyUrl, backgroundURL=paymentInfoUrl
            $data = [
                'merchantId' => $this->merchantId,
                'orderNo' => (string) $order->get_id(),
                'amount' => (string) ((int) $order->get_total()),
                'statusCode' => '00',
                'statusMessage' => '交易成功',
                'transactionNo' => 'YP24112500001234',
                'type' => (string) $type,
                'returnURL' => $notifyUrl,
                'cancelURL' => $returnUrl,
                'backgroundURL' => $paymentInfoUrl,
            ];
        } else {
            // 信用卡: returnURL=returnUrl, backgroundURL=notifyUrl
            $data = [
                'merchantId' => $this->merchantId,
                'orderNo' => (string) $order->get_id(),
                'amount' => (string) ((int) $order->get_total()),
                'statusCode' => '00',
                'statusMessage' => '交易成功',
                'transactionNo' => 'YP24112500001234',
                'type' => (string) $type,
                'returnURL' => $returnUrl,
                'cancelURL' => $returnUrl,
                'backgroundURL' => $notifyUrl,
            ];
        }

        // 根據 type 加入對應欄位
        // type=1,2: 信用卡需要 approvalCode
        // type=3: 超商需要 pinCode
        // type=4: ATM 需要 account
        if ($type === 3) {
            $data['pinCode'] = $overrides['pinCode'] ?? 'CVS123456';
        } elseif ($type === 4) {
            $data['account'] = $overrides['account'] ?? '9103522175887271';
        } else {
            $data['approvalCode'] = $overrides['approvalCode'] ?? 'ABC123';
        }

        $data = array_merge($data, $overrides);

        // 根據 type 決定簽名欄位
        $signedKeys = $this->getSignedKeys($type, $data);
        $data['checkCode'] = $this->calculateCheckCode($signedKeys, $data);

        return $data;
    }

    protected function getSignedKeys(int $type, array $data)
    {
        $keys = [
            'merchantId',
            'amount',
            'orderNo',
            'returnURL',
            'cancelURL',
            'backgroundURL',
            'transactionNo',
            'statusCode',
        ];

        $lookup = [3 => 'pinCode', 4 => 'account'];
        $keys[] = array_key_exists($type, $lookup) ? $lookup[$type] : 'approvalCode';

        return $keys;
    }

    protected function calculateCheckCode(array $keys, array $data)
    {
        $signed = [];
        foreach ($keys as $key) {
            $signed[$key] = $data[$key] ?? '';
        }

        return (new Hasher($this->key, $this->iv))->make($signed);
    }

    protected function create_yipay_payment_info_data($order, array $overrides = [])
    {
        $type = (int) ($overrides['type'] ?? '4');

        // Payment info callback 使用 paymentInfoUrl 作為 backgroundURL
        // URLs 對應（type=3,4 且有 paymentInfoUrl）：
        // - returnURL = notifyUrl
        // - backgroundURL = paymentInfoUrl
        $returnUrl = WC()->api_request_url('omnipay_yipay_complete');
        $notifyUrl = WC()->api_request_url('omnipay_yipay_notify');
        $paymentInfoUrl = WC()->api_request_url('omnipay_yipay_payment_info');

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
        ];

        // 根據 type 加入對應欄位
        if ($type === 3) {
            $data['pinCode'] = $overrides['pinCode'] ?? 'CVS123456';
        } elseif ($type === 4) {
            $data['account'] = $overrides['account'] ?? '9103522175887271';
        }

        $data = array_merge($data, $overrides);

        // 簽名欄位（payment info 不含 transactionNo，但仍需簽名）
        $signedKeys = $this->getSignedKeys($type, $data);
        $data['checkCode'] = $this->calculateCheckCode($signedKeys, $data);

        return $data;
    }

    protected function simulate_yipay_callback(array $data)
    {
        $_POST = $data;
        $_REQUEST = $data;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        delete_option('woocommerce_omnipay_yipay_settings');

        wp_cache_delete('woocommerce_omnipay_yipay_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        if ($this->config_filter_callback) {
            remove_filter('woocommerce_omnipay_gateway_config', $this->config_filter_callback);
        }
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        WC()->payment_gateways()->payment_gateways = [];

        parent::tearDown();
    }
}
