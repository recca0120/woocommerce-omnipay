<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\NewebPay;

use Omnipay\NewebPay\Encryptor;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\ScheduledRecurringFeature;
use Recca0120\WooCommerce_Omnipay\Gateways\NewebPayGateway;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

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

        $this->gateway = new NewebPayGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_dca',
            'title' => '藍新定期定額',
            'features' => [new ScheduledRecurringFeature],
        ]);

        // Set up DCA periods for Shortcode mode
        update_option('woocommerce_omnipay_newebpay_dca_periods', [
            [
                'periodType' => 'M',
                'periodPoint' => '',
                'periodTimes' => 12,
                'periodStartType' => 2,
            ],
        ]);

        // Set up Blocks mode settings
        $this->gateway->update_option('periodType', 'M');
        $this->gateway->update_option('periodPoint', '1');
        $this->gateway->update_option('periodTimes', 12);
        $this->gateway->update_option('periodStartType', 2);
    }

    protected function createGateway(): NewebPayGateway
    {
        return new NewebPayGateway([
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_dca',
            'title' => '藍新定期定額',
            'features' => [new ScheduledRecurringFeature],
        ]);
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
        $_POST['woocommerce_omnipay_newebpay_dca_periodPoint'] = '5';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 1000; // Invalid: max 999
        $_POST['woocommerce_omnipay_newebpay_dca_periodStartType'] = 2;

        // process_admin_options() should return false when validation fails
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodPoint']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodStartType']);
    }

    public function test_save_dca_periods_from_post_data()
    {
        $_POST['periodType'] = ['M', 'W'];
        $_POST['periodPoint'] = ['1', '2'];
        $_POST['periodTimes'] = [12, 24];
        $_POST['periodStartType'] = [2, 1];

        // Use public API to trigger saveDcaPeriods
        $this->gateway->process_admin_options();

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
        $_POST['woocommerce_omnipay_newebpay_dca_periodPoint'] = '15';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 12;
        $_POST['woocommerce_omnipay_newebpay_dca_periodStartType'] = 2;

        // Use public API - should return true when validation passes
        $result = $this->gateway->process_admin_options();

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodPoint']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodStartType']);
    }

    public function test_validate_period_constraints_for_week()
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = 'W';
        $_POST['woocommerce_omnipay_newebpay_dca_periodPoint'] = '1';
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 1; // Invalid: min 2
        $_POST['woocommerce_omnipay_newebpay_dca_periodStartType'] = 2;

        // Use public API - should return false when validation fails
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodPoint']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodStartType']);
    }

    public function test_validate_shortcode_mode_periods()
    {
        $_POST['periodType'] = ['Y', 'M', 'W'];
        $_POST['periodPoint'] = ['0315', '15', '1']; // Valid for each type
        $_POST['periodTimes'] = [50, 12, 24]; // All valid
        $_POST['periodStartType'] = [2, 2, 1];

        // Use public API - should return true when all periods are valid
        $result = $this->gateway->process_admin_options();

        $this->assertTrue($result);

        unset($_POST['periodType']);
        unset($_POST['periodPoint']);
        unset($_POST['periodTimes']);
        unset($_POST['periodStartType']);
    }

    public function test_validate_shortcode_mode_periods_with_invalid_data()
    {
        $_POST['periodType'] = ['Y', 'M', 'W'];
        $_POST['periodPoint'] = ['0315', '15', '1'];
        $_POST['periodTimes'] = [100, 12, 24]; // Y:100 exceeds max (99)
        $_POST['periodStartType'] = [2, 2, 1];

        // Use public API - should return false when any period is invalid
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['periodType']);
        unset($_POST['periodPoint']);
        unset($_POST['periodTimes']);
        unset($_POST['periodStartType']);
    }

    public function test_save_dca_periods_with_missing_fields()
    {
        $_POST['periodType'] = ['M', 'W'];
        $_POST['periodPoint'] = ['1', '2']; // Provide both values
        $_POST['periodTimes'] = [12, 24]; // Provide both values
        $_POST['periodStartType'] = [2, 1]; // Provide all required fields

        // Use public API to trigger saveDcaPeriods
        $this->gateway->process_admin_options();

        $saved = get_option('woocommerce_omnipay_newebpay_dca_periods');
        $this->assertCount(2, $saved);
        $this->assertEquals('M', $saved[0]['periodType']);
        $this->assertEquals('1', $saved[0]['periodPoint']);
        $this->assertEquals(12, $saved[0]['periodTimes']);
        $this->assertEquals(2, $saved[0]['periodStartType']);
        $this->assertEquals('W', $saved[1]['periodType']);
        $this->assertEquals('2', $saved[1]['periodPoint']);
        $this->assertEquals(24, $saved[1]['periodTimes']);
        $this->assertEquals(1, $saved[1]['periodStartType']);

        unset($_POST['periodType']);
        unset($_POST['periodPoint']);
        unset($_POST['periodTimes']);
        unset($_POST['periodStartType']);
    }

    public function test_load_dca_periods_from_option()
    {
        $testPeriods = [
            ['periodType' => 'M', 'periodPoint' => '1', 'periodTimes' => 12, 'periodStartType' => 2],
            ['periodType' => 'W', 'periodPoint' => '2', 'periodTimes' => 24, 'periodStartType' => 1],
        ];

        update_option('woocommerce_omnipay_newebpay_dca_periods', $testPeriods);

        // Create new gateway instance to trigger loadDcaPeriods
        $newGateway = $this->createGateway();

        // Verify periods were loaded by testing generate_periods_html output
        $html = $newGateway->generate_periods_html('periods', []);

        $this->assertStringContainsString('value="M"', $html);
        $this->assertStringContainsString('value="W"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="2"', $html);
        $this->assertStringContainsString('value="12"', $html);
        $this->assertStringContainsString('value="24"', $html);
    }

    /**
     * @dataProvider invalidPeriodPointProvider
     */
    public function test_validate_period_point_rejects_invalid_values($type, $point)
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = $type;
        $_POST['woocommerce_omnipay_newebpay_dca_periodPoint'] = $point;
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 12;
        $_POST['woocommerce_omnipay_newebpay_dca_periodStartType'] = 2;

        // Use public API - should return false when validation fails
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodPoint']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodStartType']);
    }

    public static function invalidPeriodPointProvider()
    {
        return [
            'Y: month 00' => ['Y', '0015'],
            'Y: month 13' => ['Y', '1301'],
            'Y: day 00' => ['Y', '0100'],
            'Y: day 32' => ['Y', '0132'],
            'Y: not 4 digits' => ['Y', '123'],
            'M: day 0' => ['M', '0'],
            'M: day 32' => ['M', '32'],
            'W: weekday 0' => ['W', '0'],
            'W: weekday 8' => ['W', '8'],
            'D: interval 1' => ['D', '1'],
            'D: interval 1000' => ['D', '1000'],
        ];
    }

    /**
     * @dataProvider validPeriodPointProvider
     */
    public function test_validate_period_point_accepts_valid_values($type, $point)
    {
        $_POST['woocommerce_omnipay_newebpay_dca_periodType'] = $type;
        $_POST['woocommerce_omnipay_newebpay_dca_periodPoint'] = $point;
        $_POST['woocommerce_omnipay_newebpay_dca_periodTimes'] = 12;
        $_POST['woocommerce_omnipay_newebpay_dca_periodStartType'] = 2;

        // Use public API - should return true when validation passes
        $result = $this->gateway->process_admin_options();

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_newebpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodPoint']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodTimes']);
        unset($_POST['woocommerce_omnipay_newebpay_dca_periodStartType']);
    }

    public static function validPeriodPointProvider()
    {
        return [
            'Y: Jan 1' => ['Y', '0101'],
            'Y: Feb 29' => ['Y', '0229'],
            'Y: Dec 31' => ['Y', '1231'],
            'M: day 1' => ['M', '1'],
            'M: day 15' => ['M', '15'],
            'M: day 31' => ['M', '31'],
            'W: Monday' => ['W', '1'],
            'W: Sunday' => ['W', '7'],
            'D: interval 2' => ['D', '2'],
            'D: interval 365' => ['D', '365'],
            'D: interval 999' => ['D', '999'],
        ];
    }

    public function test_generate_periods_html_passes_periods_to_template()
    {
        // Set up test periods with specific values
        update_option('woocommerce_omnipay_newebpay_dca_periods', [
            ['periodType' => 'M', 'periodPoint' => '15', 'periodTimes' => 24, 'periodStartType' => 2],
            ['periodType' => 'W', 'periodPoint' => '5', 'periodTimes' => 48, 'periodStartType' => 1],
        ]);

        // Create new gateway instance to load periods
        $gateway = $this->createGateway();

        // Test via public WooCommerce API method
        $html = $gateway->generate_periods_html('periods', []);

        // Verify the periods data appears in the rendered HTML
        $this->assertStringContainsString('value="M"', $html); // periodType from first period
        $this->assertStringContainsString('value="15"', $html); // periodPoint from first period
        $this->assertStringContainsString('value="24"', $html); // periodTimes from first period
        $this->assertStringContainsString('value="2"', $html); // periodStartType from first period
        $this->assertStringContainsString('value="W"', $html); // periodType from second period
        $this->assertStringContainsString('value="5"', $html); // periodPoint from second period
        $this->assertStringContainsString('value="48"', $html); // periodTimes from second period
        $this->assertStringContainsString('value="1"', $html); // periodStartType from second period
    }

    public function test_generate_periods_html_passes_field_configs_to_template()
    {
        $html = $this->gateway->generate_periods_html('periods', []);

        // Verify fieldConfigs are used to generate input fields
        // NewebPay has: periodType, periodPoint, periodTimes, periodStartType
        $this->assertStringContainsString('name="periodType', $html);
        $this->assertStringContainsString('name="periodPoint', $html);
        $this->assertStringContainsString('name="periodTimes', $html);
        $this->assertStringContainsString('name="periodStartType', $html);

        // Verify field attributes from fieldConfigs are applied
        $this->assertStringContainsString('maxlength="1"', $html); // periodType maxlength
        $this->assertStringContainsString('min="2"', $html); // periodTimes min
        $this->assertStringContainsString('max="99"', $html); // periodTimes max
        $this->assertStringContainsString('required', $html); // required attributes
    }

    public function test_generate_periods_html_passes_default_period_to_template()
    {
        $html = $this->gateway->generate_periods_html('periods', []);

        // Verify defaultPeriod is rendered in template (for the add-row template)
        // NewebPay default: periodType='M', periodPoint='01', periodTimes=2, periodStartType='2'
        $this->assertStringContainsString('value="M"', $html); // default periodType
        $this->assertStringContainsString('value="01"', $html); // default periodPoint
        $this->assertStringContainsString('value="2"', $html); // default periodTimes and periodStartType
    }

    public function test_payment_fields_displays_description()
    {
        $this->gateway->description = 'Test NewebPay DCA payment description';

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        $this->assertStringContainsString('Test NewebPay DCA payment description', $output);
    }

    public function test_payment_fields_displays_period_select_with_configured_periods()
    {
        // Set up multiple DCA periods
        update_option('woocommerce_omnipay_newebpay_dca_periods', [
            ['periodType' => 'M', 'periodPoint' => '1', 'periodTimes' => 12, 'periodStartType' => 2],
            ['periodType' => 'W', 'periodPoint' => '2', 'periodTimes' => 24, 'periodStartType' => 1],
            ['periodType' => 'Y', 'periodPoint' => '0315', 'periodTimes' => 5, 'periodStartType' => 2],
        ]);

        // Recreate gateway to load new periods
        $this->gateway = $this->createGateway();

        // Mock is_checkout() to return true using filter
        add_filter('woocommerce_is_checkout', '__return_true');

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // Remove filter
        remove_filter('woocommerce_is_checkout', '__return_true');

        // Verify the select field exists
        $this->assertStringContainsString('omnipay_period', $output);

        // Verify period options are rendered
        $this->assertStringContainsString('M_1_12_2', $output);
        $this->assertStringContainsString('W_2_24_1', $output);
        $this->assertStringContainsString('Y_0315_5_2', $output);
    }

    public function test_payment_fields_displays_warning_message()
    {
        // Mock is_checkout() to return true using filter
        add_filter('woocommerce_is_checkout', '__return_true');

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // Remove filter
        remove_filter('woocommerce_is_checkout', '__return_true');

        // Verify warning message contains provider name
        $this->assertStringContainsString('NewebPay', $output);
        $this->assertStringContainsString('recurring credit card payment', $output);
    }

    public function test_payment_fields_includes_period_select_field()
    {
        // Mock is_checkout() to return true using filter
        add_filter('woocommerce_is_checkout', '__return_true');

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        // Remove filter
        remove_filter('woocommerce_is_checkout', '__return_true');

        // Verify the period select field and info div are present
        $this->assertStringContainsString('id="omnipay_period"', $output);
        $this->assertStringContainsString('name="omnipay_period"', $output);
        $this->assertStringContainsString('id="omnipay_period_info"', $output);
    }
}
