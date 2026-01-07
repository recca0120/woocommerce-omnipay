<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing;

use Ecpay\Sdk\Services\CheckMacValueService;

/**
 * ECPay 測試（redirect 型金流）
 */
class ECPayTest extends TestCase
{
    protected $gatewayId = 'ecpay';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
        'allow_resubmit' => 'no',
    ];

    // ==================== process_payment 測試 ====================

    public function test_process_payment_returns_redirect()
    {
        $order = $this->createOrder(100);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);
        $this->assertStringContainsString('omnipay_redirect=1', $result['redirect']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertStringContainsString('ecpay.com.tw', $redirectData['url']);
        $this->assertArrayHasKey('PaymentInfoURL', $redirectData['data']);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());
    }

    public function test_process_payment_with_invalid_order()
    {
        $result = $this->gateway->process_payment(999999);
        $this->assertEquals('failure', $result['result']);
    }

    // ==================== Redirect Form 測試 ====================

    public function test_redirect_renders_auto_submit_form()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $_GET['omnipay_redirect'] = '1';
        $_GET['order_id'] = $order->get_id();
        $_GET['key'] = $order->get_order_key();

        ob_start();
        woocommerce_omnipay_maybe_render_redirect_form();
        $html = ob_get_clean();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('ecpay.com.tw', $html);
        $this->assertStringContainsString('.submit()', $html);
    }

    /**
     * @dataProvider invalidRedirectProvider
     */
    public function test_redirect_does_not_render_form($setup)
    {
        $order = $this->createOrder(100);
        $setup($order, $this->gateway);

        ob_start();
        woocommerce_omnipay_maybe_render_redirect_form();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('ecpay.com.tw', $html);
    }

    public static function invalidRedirectProvider()
    {
        return [
            'wrong key' => [function ($order, $gateway) {
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
     * @dataProvider productTypeProvider
     */
    public function test_accept_notification_success($virtual, $downloadable, $expectedStatus)
    {
        $order = $this->createOrder(100, 'TWD', $virtual, $downloadable);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeNo' => '2024112500001234',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals($expectedStatus, $order->get_status());
        $this->assertEquals('2024112500001234', $order->get_transaction_id());
    }

    public function test_accept_notification_failed()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '0',
            'RtnMsg' => '交易失敗',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('0|交易失敗', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    public function test_accept_notification_validates_checksum()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $data = $this->makeCallbackData($order, ['RtnCode' => '1']);
        $data['CheckMacValue'] = 'INVALID';
        $this->simulateCallback($data);

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('0|CheckMacValue verify failed', $output);
    }

    public function test_accept_notification_skips_processed_order()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $order->set_status('processing');
        $order->save();

        $this->simulateCallback($this->makeCallbackData($order, ['RtnCode' => '1']));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);
        $this->assertEquals('processing', wc_get_order($order->get_id())->get_status());
    }

    public static function productTypeProvider()
    {
        return [
            'physical' => [false, false, 'processing'],
            'virtual downloadable' => [true, true, 'completed'],
        ];
    }

    // ==================== Return URL 測試 ====================

    /**
     * @dataProvider productTypeProvider
     */
    public function test_complete_purchase_success($virtual, $downloadable, $expectedStatus)
    {
        $order = $this->createOrder(100, 'TWD', $virtual, $downloadable);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeNo' => '2024112500001234',
        ]));

        $url = $this->gateway->completePurchase();

        $this->assertStringContainsString('order-received', $url);
        $this->assertEquals($expectedStatus, wc_get_order($order->get_id())->get_status());
    }

    public function test_complete_purchase_failed()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '0',
            'RtnMsg' => '交易失敗',
        ]));

        $url = $this->gateway->completePurchase();

        $this->assertStringNotContainsString('order-received', $url);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    // ==================== allow_resubmit 測試 ====================

    /**
     * @dataProvider allowResubmitProvider
     */
    public function test_allow_resubmit($allowResubmit, $prefix, $expectedStatus, $idPattern)
    {
        $gatewayId = 'ecpay_test';
        $settings = ['allow_resubmit' => $allowResubmit ? 'yes' : 'no'];
        if ($prefix) {
            $settings['transaction_id_prefix'] = $prefix;
        }
        update_option('woocommerce_omnipay_'.$gatewayId.'_settings', $settings);

        $gateway = new \Recca0120\WooCommerce_Omnipay\Gateways\OmnipayGateway([
            'gateway_id' => $gatewayId,
            'title' => 'ECPay Test',
            'gateway' => 'ECPay',
        ]);

        $order = $this->createOrder(100);
        $gateway->process_payment($order->get_id());

        $order = wc_get_order($order->get_id());
        $transactionId = $order->get_meta('_omnipay_transaction_id');

        $this->assertEquals($expectedStatus, $order->get_status());

        $expectedBase = ($prefix ?? '').$order->get_id();
        if ($idPattern === 'exact') {
            $this->assertEquals($expectedBase, $transactionId);
        } else {
            $this->assertStringStartsWith($expectedBase.'T', $transactionId);
        }

        delete_option('woocommerce_omnipay_'.$gatewayId.'_settings');
    }

    public static function allowResubmitProvider()
    {
        return [
            'no' => [false, null, 'on-hold', 'exact'],
            'yes' => [true, null, 'pending', 'random'],
            'no with prefix' => [false, 'TEST', 'on-hold', 'exact'],
            'yes with prefix' => [true, 'PRE', 'pending', 'random'],
        ];
    }

    // ==================== 金額驗證測試 ====================

    public function test_accept_notification_rejects_amount_mismatch()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'TradeAmt' => '999',  // 金額不符
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('on-hold', $order->get_status());  // 狀態不變
    }

    // ==================== 模擬付款測試 ====================

    public function test_accept_notification_handles_simulated_payment()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'SimulatePaid' => '1',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertEquals('1|OK', $output);

        $order = wc_get_order($order->get_id());
        // 模擬付款只記錄備註，不改訂單狀態
        $this->assertEquals('on-hold', $order->get_status());

        // 確認有記錄備註
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $hasSimulateNote = false;
        foreach ($notes as $note) {
            if (strpos($note->content, '模擬付款') !== false || strpos($note->content, 'Simulate') !== false) {
                $hasSimulateNote = true;
                break;
            }
        }
        $this->assertTrue($hasSimulateNote);
    }

    // ==================== 信用卡資訊儲存測試 ====================

    public function test_accept_notification_stores_credit_card_info()
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => '1',
            'PaymentType' => 'Credit_CreditCard',
            'card6no' => '431195',
            'card4no' => '1234',
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        $this->assertEquals('431195', $order->get_meta('_omnipay_card6no'));
        $this->assertEquals('1234', $order->get_meta('_omnipay_card4no'));
    }

    // ==================== 付款超時失敗 RtnCode 測試 ====================

    /**
     * @dataProvider expiredRtnCodeProvider
     */
    public function test_accept_notification_handles_expired_payment($rtnCode, $rtnMsg)
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, [
            'RtnCode' => $rtnCode,
            'RtnMsg' => $rtnMsg,
        ]));

        ob_start();
        $this->gateway->acceptNotification();
        $output = ob_get_clean();

        $this->assertStringContainsString('0|', $output);

        $order = wc_get_order($order->get_id());
        $this->assertEquals('failed', $order->get_status());
    }

    public static function expiredRtnCodeProvider()
    {
        return [
            'CVS expired' => ['10100058', '超商代碼繳費超過期限'],
            'Barcode expired' => ['10200163', '條碼繳費超過期限'],
        ];
    }

    // ==================== Payment Info 回調測試 ====================

    /**
     * @dataProvider paymentInfoProvider
     */
    public function test_accept_notification_stores_payment_info($callbackData, $expectedMeta)
    {
        $order = $this->createOrder(100);
        $this->gateway->process_payment($order->get_id());

        $this->simulateCallback($this->makeCallbackData($order, $callbackData));

        ob_start();
        $this->gateway->acceptNotification();
        ob_get_clean();

        $order = wc_get_order($order->get_id());
        foreach ($expectedMeta as $key => $value) {
            $this->assertEquals($value, $order->get_meta($key));
        }
        $this->assertEquals('on-hold', $order->get_status());
    }

    public static function paymentInfoProvider()
    {
        return [
            'ATM' => [
                ['RtnCode' => '2', 'PaymentType' => 'ATM_TAISHIN', 'BankCode' => '812', 'vAccount' => '9103522175887271', 'ExpireDate' => '2024/12/01'],
                ['_omnipay_bank_code' => '812', '_omnipay_virtual_account' => '9103522175887271', '_omnipay_expire_date' => '2024/12/01'],
            ],
            'CVS' => [
                ['RtnCode' => '10100073', 'PaymentType' => 'CVS_CVS', 'PaymentNo' => 'LLL24112512345', 'ExpireDate' => '2024/12/01 23:59:59'],
                ['_omnipay_payment_no' => 'LLL24112512345', '_omnipay_expire_date' => '2024/12/01 23:59:59'],
            ],
            'BARCODE' => [
                ['RtnCode' => '10100073', 'PaymentType' => 'BARCODE_BARCODE', 'Barcode1' => '1104ES0987654321', 'Barcode2' => '3453010192168', 'Barcode3' => '110400100000100', 'ExpireDate' => '2024/12/01 23:59:59'],
                ['_omnipay_barcode_1' => '1104ES0987654321', '_omnipay_barcode_2' => '3453010192168', '_omnipay_barcode_3' => '110400100000100', '_omnipay_expire_date' => '2024/12/01 23:59:59'],
            ],
        ];
    }

    // ==================== Payment Info 顯示測試 ====================

    /**
     * @dataProvider paymentInfoDisplayProvider
     */
    public function test_get_payment_info_output($meta, $expected)
    {
        $order = $this->createOrder(100);
        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, $value);
        }
        $order->save();

        $html = $this->gateway->getPaymentInfoOutput($order);

        foreach ($expected as $value) {
            $this->assertStringContainsString($value, $html);
        }
    }

    public function test_get_payment_info_output_empty()
    {
        $order = $this->createOrder(100);
        $this->assertEmpty($this->gateway->getPaymentInfoOutput($order));
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
     * @dataProvider paymentInfoHooksProvider
     */
    public function test_payment_info_displayed_on_hooks($hook, $argsCallback)
    {
        $order = $this->createOrder(100);
        $order->set_payment_method($this->gateway->id);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->save();

        ob_start();
        do_action($hook, ...$argsCallback($order));
        $html = ob_get_clean();

        $this->assertStringContainsString('9103522175887271', $html);
    }

    public function test_payment_info_not_displayed_for_other_gateway()
    {
        $order = $this->createOrder(100);
        $order->set_payment_method('other_gateway');
        $order->update_meta_data('_omnipay_bank_code', '812');
        $order->save();

        ob_start();
        do_action('woocommerce_admin_order_data_after_billing_address', $order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function paymentInfoHooksProvider()
    {
        return [
            'thankyou' => ['woocommerce_thankyou_omnipay_ecpay', function ($order) {
                return [$order->get_id()];
            }],
            'receipt (CartFlows Instant)' => ['woocommerce_receipt_omnipay_ecpay', function ($order) {
                return [$order->get_id()];
            }],
            'admin' => ['woocommerce_admin_order_data_after_billing_address', function ($order) {
                return [$order];
            }],
            'email' => ['woocommerce_email_after_order_table', function ($order) {
                return [$order, true, false];
            }],
        ];
    }

    public function test_payment_info_displayed_on_view_order()
    {
        global $wp;

        $order = $this->createOrder(100);
        $order->set_payment_method($this->gateway->id);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->save();

        // Mock is_wc_endpoint_url('view-order') by setting query vars
        $wp->query_vars['view-order'] = $order->get_id();

        ob_start();
        do_action('woocommerce_order_details_after_order_table', $order);
        $html = ob_get_clean();

        unset($wp->query_vars['view-order']);

        $this->assertStringContainsString('9103522175887271', $html);
    }

    public function test_payment_info_not_displayed_on_non_view_order_page()
    {
        $order = $this->createOrder(100);
        $order->set_payment_method($this->gateway->id);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->save();

        // Ensure we're not on view-order page
        ob_start();
        do_action('woocommerce_order_details_after_order_table', $order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function test_get_payment_info_output_plain_text()
    {
        $order = $this->createOrder(100);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->update_meta_data('_omnipay_expire_date', '2024/12/01');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order, true);

        // Plain text should not contain HTML tags
        $this->assertStringNotContainsString('<table', $output);
        $this->assertStringNotContainsString('<section', $output);
        $this->assertStringContainsString('9103522175887271', $output);
        $this->assertStringContainsString('2024/12/01', $output);
    }

    public function test_get_payment_info_output_uses_cartflows_template()
    {
        global $omnipay_test_cartflows_mode;

        // Enable CartFlows mode
        $omnipay_test_cartflows_mode = true;

        $order = $this->createOrder(100);
        $order->update_meta_data('_omnipay_virtual_account', '9103522175887271');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // Reset CartFlows mode
        $omnipay_test_cartflows_mode = false;

        // CartFlows template uses specific CSS classes
        $this->assertStringContainsString('wcf-ic-review-customer__row', $output);
        $this->assertStringContainsString('wcf-ic-review-customer__label', $output);
        $this->assertStringContainsString('wcf-ic-review-customer__content', $output);
        $this->assertStringContainsString('9103522175887271', $output);
    }

    public function test_get_payment_info_output_barcode_renders_svg()
    {
        $order = $this->createOrder(100);
        $order->update_meta_data('_omnipay_barcode_1', '1104ES0987654321');
        $order->update_meta_data('_omnipay_barcode_2', '3453010192168');
        $order->update_meta_data('_omnipay_barcode_3', '110400100000100');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // Verify SVG barcode elements are present
        $this->assertStringContainsString('omnipay-barcode', $output);
        $this->assertStringContainsString('data-barcode="1104ES0987654321"', $output);
        $this->assertStringContainsString('data-barcode="3453010192168"', $output);
        $this->assertStringContainsString('data-barcode="110400100000100"', $output);
        $this->assertStringContainsString('data-format="CODE39"', $output);
    }

    public function test_get_payment_info_output_barcode_renders_svg_in_cartflows()
    {
        global $omnipay_test_cartflows_mode;

        // Enable CartFlows mode
        $omnipay_test_cartflows_mode = true;

        $order = $this->createOrder(100);
        $order->update_meta_data('_omnipay_barcode_1', '1104ES0987654321');
        $order->update_meta_data('_omnipay_barcode_2', '3453010192168');
        $order->update_meta_data('_omnipay_barcode_3', '110400100000100');
        $order->save();

        $output = $this->gateway->getPaymentInfoOutput($order);

        // Reset CartFlows mode
        $omnipay_test_cartflows_mode = false;

        // Verify CartFlows template structure with barcode SVG
        $this->assertStringContainsString('wcf-ic-review-customer__row', $output);
        $this->assertStringContainsString('omnipay-barcode', $output);
        $this->assertStringContainsString('data-barcode="1104ES0987654321"', $output);
        $this->assertStringContainsString('data-barcode="3453010192168"', $output);
        $this->assertStringContainsString('data-barcode="110400100000100"', $output);
        $this->assertStringContainsString('data-format="CODE39"', $output);
        // Verify noscript fallback is present
        $this->assertStringContainsString('<noscript>', $output);
    }

    // ==================== Helper ====================

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

        $service = new CheckMacValueService(
            $sharedSettings['HashKey'] ?? $this->settings['HashKey'],
            $sharedSettings['HashIV'] ?? $this->settings['HashIV'],
            CheckMacValueService::METHOD_SHA256
        );
        $data['CheckMacValue'] = $service->generate($data);

        return $data;
    }
}
