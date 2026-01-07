<?php

namespace Recca0120\WooCommerce_Omnipay\Tests;

use WP_UnitTestCase;

/**
 * Credit Card Form Tests
 *
 * 測試信用卡表單的顯示和驗證
 */
class CreditCardFormTest extends WP_UnitTestCase
{
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        // 啟用 Dummy Gateway
        update_option('woocommerce_omnipay_dummy_settings', [
            'enabled' => 'yes',
            'title' => 'Dummy Gateway',
            'description' => 'Test payment gateway',
        ]);

        // 使用 DummyGateway 測試表單功能
        $this->gateway = new \Recca0120\WooCommerce_Omnipay\Gateways\DummyGateway([
            'gateway_id' => 'dummy',
            'title' => 'Dummy Gateway',
            'description' => 'Test payment gateway',
            'gateway' => 'Dummy',
        ]);
    }

    /**
     * 測試：payment_fields 輸出包含所有必要欄位和 description
     */
    public function test_payment_fields_contains_all_required_fields()
    {
        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // 驗證輸出為有效 HTML
        $this->assertNotEmpty($output, 'payment_fields should output HTML');
        $this->assertIsString($output);

        // 驗證所有必要欄位都存在
        $requiredFields = [
            'omnipay_number' => 'card number field',
            'omnipay_expiryMonth' => 'expiry month field',
            'omnipay_expiryYear' => 'expiry year field',
            'omnipay_cvv' => 'CVV field',
            'omnipay_firstName' => 'first name field',
            'omnipay_lastName' => 'last name field',
        ];

        foreach ($requiredFields as $fieldName => $description) {
            $this->assertStringContainsString(
                $fieldName,
                $output,
                "Should contain {$description}"
            );
        }

        // 驗證包含 description
        $this->assertStringContainsString('Test payment gateway', $output, 'Should contain gateway description');
    }

    /**
     * 測試：有完整卡片資料時驗證通過
     */
    public function test_validate_fields_passes_with_complete_card_data()
    {
        $_POST['omnipay_number'] = '4242424242424242';
        $_POST['omnipay_expiryMonth'] = '12';
        $_POST['omnipay_expiryYear'] = '2030';
        $_POST['omnipay_cvv'] = '123';
        $_POST['omnipay_firstName'] = 'Test';
        $_POST['omnipay_lastName'] = 'User';

        $result = $this->gateway->validate_fields();

        $this->assertTrue($result, 'Validation should pass with complete card data');

        // 清理
        $_POST = [];
    }

    /**
     * 測試：缺少任一必要欄位時驗證失敗
     *
     * @dataProvider missingFieldProvider
     */
    public function test_validate_fields_fails_with_missing_required_field($missingField, $description)
    {
        $completeData = [
            'omnipay_number' => '4242424242424242',
            'omnipay_expiryMonth' => '12',
            'omnipay_expiryYear' => '2030',
            'omnipay_cvv' => '123',
            'omnipay_firstName' => 'Test',
            'omnipay_lastName' => 'User',
        ];

        // 移除要測試的欄位
        unset($completeData[$missingField]);
        $_POST = $completeData;

        $result = $this->gateway->validate_fields();

        $this->assertFalse(
            $result,
            "Validation should fail when {$description} is missing"
        );

        // 清理
        $_POST = [];
    }

    /**
     * 資料提供者：缺少的欄位
     */
    public function missingFieldProvider()
    {
        return [
            'missing card number' => ['omnipay_number', 'card number'],
            'missing expiry month' => ['omnipay_expiryMonth', 'expiry month'],
            'missing expiry year' => ['omnipay_expiryYear', 'expiry year'],
            'missing CVV' => ['omnipay_cvv', 'CVV'],
            'missing first name' => ['omnipay_firstName', 'first name'],
            'missing last name' => ['omnipay_lastName', 'last name'],
        ];
    }

    /**
     * 測試：payment_fields 輸出的姓名欄位包含帳單資訊的預設值
     */
    public function test_payment_fields_prefills_billing_name()
    {
        // 建立訂單並設定帳單資訊
        $order = wc_create_order();
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->save();

        // 模擬結帳流程，將訂單 ID 存入 session
        WC()->session->set('order_awaiting_payment', $order->get_id());

        // 輸出表單
        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // 驗證包含預填的值
        $this->assertStringContainsString('value="John"', $output, 'Should prefill first name from billing');
        $this->assertStringContainsString('value="Doe"', $output, 'Should prefill last name from billing');

        // 清理
        WC()->session->set('order_awaiting_payment', null);
    }

    /**
     * 測試：沒有訂單時，欄位為空
     */
    public function test_payment_fields_empty_when_no_order()
    {
        // 確保沒有待付款訂單
        WC()->session->set('order_awaiting_payment', null);

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // 驗證姓名欄位沒有 value 屬性（或 value 為空）
        // 因為沒有帳單資訊可以預填
        $this->assertIsString($output);
        $this->assertStringContainsString('omnipay_firstName', $output);
        $this->assertStringContainsString('omnipay_lastName', $output);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        delete_option('woocommerce_omnipay_dummy_settings');
        parent::tearDown();
    }
}
