<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayDCAGateway;
use WooCommerceOmnipay\Tests\PaymentProcessing\TestCase;

/**
 * NewebPay 定期定額 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、CREDIT、Period 參數）
 * 其他行為已在 NewebPayTest 中測試
 */
class NewebPayDCAGatewayTest extends TestCase
{
    protected $gatewayId = 'newebpay_dca';

    protected $gatewayName = 'NewebPay';

    private $hashKey = 'Fs5cX7xLlHwjbKKW6rxNfEOI3I1WxqWt';

    private $hashIV = 'VVcW9t4feCshKOTi';

    private $merchantId = 'MS350098593';

    protected function setUp(): void
    {
        $this->settings = [
            'HashKey' => $this->hashKey,
            'HashIV' => $this->hashIV,
            'MerchantID' => $this->merchantId,
            'testMode' => 'yes',
        ];
        parent::setUp();

        // Set up DCA periods for Shortcode mode
        update_option('woocommerce_omnipay_newebpay_dca_periods', [
            [
                'periodType' => 'M',
                'periodPoint' => '',
                'periodTimes' => 12,
                'periodStartType' => 2,
            ],
        ]);

        $this->gateway = new NewebPayDCAGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_dca',
            'title' => '藍新定期定額',
        ]);

        // Set up Blocks mode settings
        $this->gateway->update_option('periodType', 'M');
        $this->gateway->update_option('periodPoint', '1');
        $this->gateway->update_option('periodTimes', 12);
        $this->gateway->update_option('periodStartType', 2);
    }

    public function test_gateway_has_correct_id_and_title()
    {
        $this->assertEquals('omnipay_newebpay_dca', $this->gateway->id);
        $this->assertEquals('藍新定期定額', $this->gateway->method_title);
    }

    public function test_process_payment_sends_credit_parameter()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());

        // 定期定額使用 PostData_，不是 TradeInfo
        $this->assertArrayHasKey('PostData_', $redirectData['data']);

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $periodData = $encryptor->decrypt($redirectData['data']['PostData_']);
        if (is_string($periodData)) {
            parse_str($periodData, $periodData);
        }

        // 驗證定期定額參數（omnipay-newebpay v1.0.2+ 支援）
        $this->assertEquals('M', $periodData['PeriodType']);
        $this->assertEquals('12', $periodData['PeriodTimes']);
        $this->assertEquals('500', $periodData['PeriodAmt']);
        $this->assertEquals('2', $periodData['PeriodStartType']);
        $this->assertNotEmpty($periodData['PayerEmail']);
    }

    public function test_process_payment_with_shortcode_mode_user_selection()
    {
        // Simulate user selection in Shortcode mode
        $_POST['omnipay_period'] = 'W_2_24_1';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $periodData = $encryptor->decrypt($redirectData['data']['PostData_']);
        if (is_string($periodData)) {
            parse_str($periodData, $periodData);
        }

        // 驗證用戶選擇的參數
        $this->assertEquals('W', $periodData['PeriodType']);
        $this->assertEquals('2', $periodData['PeriodPoint']);
        $this->assertEquals('24', $periodData['PeriodTimes']);
        $this->assertEquals('1', $periodData['PeriodStartType']);
        $this->assertEquals('500', $periodData['PeriodAmt']);

        unset($_POST['omnipay_period']);
    }

    public function test_process_payment_with_invalid_period_format_uses_fallback()
    {
        // Invalid format - should fallback to defaults
        $_POST['omnipay_period'] = 'invalid';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());

        $encryptor = new Encryptor($this->hashKey, $this->hashIV);
        $periodData = $encryptor->decrypt($redirectData['data']['PostData_']);
        if (is_string($periodData)) {
            parse_str($periodData, $periodData);
        }

        // Should use fallback defaults
        $this->assertEquals('M', $periodData['PeriodType']);
        $this->assertEquals('1', $periodData['PeriodPoint']);
        $this->assertEquals('12', $periodData['PeriodTimes']);
        $this->assertEquals('2', $periodData['PeriodStartType']);

        unset($_POST['omnipay_period']);
    }

    public function test_validate_period_constraints_for_day()
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = 'D';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 1000; // Invalid: max 999

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
    }

    public function test_save_dca_periods_from_post_data()
    {
        $_POST['periodType'] = ['M', 'W'];
        $_POST['periodPoint'] = ['1', '2'];
        $_POST['periodTimes'] = [12, 24];
        $_POST['periodStartType'] = [2, 1];

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('saveDcaPeriods');
        $method->setAccessible(true);

        $method->invoke($this->gateway);

        $saved = get_option('woocommerce_omnipay_newebpay_dca_periods');
        $this->assertCount(2, $saved);
        $this->assertEquals('M', $saved[0]['periodType']);
        $this->assertEquals('1', $saved[0]['periodPoint']);
        $this->assertEquals(12, $saved[0]['periodTimes']);
        $this->assertEquals(2, $saved[0]['periodStartType']);

        unset($_POST['periodType']);
        unset($_POST['periodPoint']);
        unset($_POST['periodTimes']);
        unset($_POST['periodStartType']);
    }

    public function test_validate_period_constraints_passes_valid_data()
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = 'M';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 12;

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
    }

    public function test_validate_period_constraints_for_week()
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = 'W';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 1; // Invalid: min 2

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
    }

    public function test_validate_shortcode_mode_periods()
    {
        $_POST['periodType'] = ['Y', 'M', 'W'];
        $_POST['periodTimes'] = [50, 12, 24]; // All valid

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertTrue($result);

        unset($_POST['periodType']);
        unset($_POST['periodTimes']);
    }

    public function test_validate_shortcode_mode_periods_with_invalid_data()
    {
        $_POST['periodType'] = ['Y', 'M', 'W'];
        $_POST['periodTimes'] = [100, 12, 24]; // Y:100 exceeds max (99)

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('validateDcaFields');
        $method->setAccessible(true);

        $result = $method->invoke($this->gateway);

        $this->assertFalse($result);

        unset($_POST['periodType']);
        unset($_POST['periodTimes']);
    }

    public function test_save_dca_periods_with_missing_fields()
    {
        $_POST['periodType'] = ['M', 'W'];
        $_POST['periodPoint'] = ['1']; // Missing second value
        $_POST['periodTimes'] = [12]; // Missing second value
        // periodStartType completely missing

        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('saveDcaPeriods');
        $method->setAccessible(true);

        $method->invoke($this->gateway);

        $saved = get_option('woocommerce_omnipay_newebpay_dca_periods');
        $this->assertCount(2, $saved);
        $this->assertEquals('M', $saved[0]['periodType']);
        $this->assertEquals('1', $saved[0]['periodPoint']);
        // Second period should have defaults for missing values
        $this->assertEquals('W', $saved[1]['periodType']);
        $this->assertEquals('', $saved[1]['periodPoint']);
        $this->assertEquals(0, $saved[1]['periodTimes']);
        $this->assertEquals(0, $saved[1]['periodStartType']);

        unset($_POST['periodType']);
        unset($_POST['periodPoint']);
        unset($_POST['periodTimes']);
    }

    public function test_load_dca_periods_from_option()
    {
        $testPeriods = [
            ['periodType' => 'M', 'periodPoint' => '1', 'periodTimes' => 12, 'periodStartType' => 2],
            ['periodType' => 'W', 'periodPoint' => '2', 'periodTimes' => 24, 'periodStartType' => 1],
        ];

        update_option('woocommerce_omnipay_newebpay_dca_periods', $testPeriods);

        // Create new gateway instance to trigger loadDcaPeriods
        $newGateway = new NewebPayDCAGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_dca',
        ]);

        $reflection = new \ReflectionClass($newGateway);
        $property = $reflection->getProperty('dcaPeriods');
        $property->setAccessible(true);
        $loaded = $property->getValue($newGateway);

        $this->assertCount(2, $loaded);
        $this->assertEquals('M', $loaded[0]['periodType']);
        $this->assertEquals('W', $loaded[1]['periodType']);
    }
}
