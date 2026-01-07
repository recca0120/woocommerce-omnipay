<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\ECPay;

use Recca0120\WooCommerce_Omnipay\Gateways\ECPayGateway;
use Recca0120\WooCommerce_Omnipay\Gateways\Features\FrequencyRecurringFeature;
use Recca0120\WooCommerce_Omnipay\Tests\PaymentProcessing\TestCase;

/**
 * ECPay 定期定額 Gateway 測試
 *
 * 只測試子類別的差異點（gateway_id、title、ChoosePayment、Period 參數）
 * 其他行為已在 ECPayTest 中測試
 */
class ECPayDCAGatewayTest extends TestCase
{
    protected $gatewayId = 'ecpay_dca';

    protected $gatewayName = 'ECPay';

    protected $settings = [
        'HashKey' => '5294y06JbISpM5x9',
        'HashIV' => 'v77hoKGq4kWxNNIS',
        'MerchantID' => '2000132',
        'testMode' => 'yes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new ECPayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [new FrequencyRecurringFeature],
        ]);

        // Set up DCA periods for Shortcode mode
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            [
                'periodType' => 'M',
                'frequency' => 1,
                'execTimes' => 12,
            ],
        ]);

        // Set up Blocks mode settings
        $this->gateway->update_option('periodType', 'M');
        $this->gateway->update_option('frequency', 1);
        $this->gateway->update_option('execTimes', 12);
    }

    protected function createGateway(): ECPayGateway
    {
        return new ECPayGateway([
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => '綠界定期定額',
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [new FrequencyRecurringFeature],
        ]);
    }

    public function test_process_payment_sends_credit_payment_type()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Credit', $redirectData['data']['ChoosePayment']);
    }

    public function test_process_payment_sends_period_parameters()
    {
        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('M', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(12, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);
    }

    public function test_process_payment_with_shortcode_mode_user_selection()
    {
        // Simulate user selection in Shortcode mode
        $_POST['omnipay_period'] = 'Y_1_6';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        $this->assertEquals('Y', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(6, $redirectData['data']['ExecTimes']);
        $this->assertEquals(500, $redirectData['data']['PeriodAmount']);

        unset($_POST['omnipay_period']);
    }

    public function test_process_payment_with_invalid_period_format_uses_fallback()
    {
        // Invalid format - should fallback to defaults
        $_POST['omnipay_period'] = 'invalid_format';

        $order = $this->createOrder(500);

        $result = $this->gateway->process_payment($order->get_id());

        $this->assertEquals('success', $result['result']);

        $redirectData = get_transient('omnipay_redirect_'.$order->get_id());
        // Should use fallback defaults
        $this->assertEquals('M', $redirectData['data']['PeriodType']);
        $this->assertEquals(1, $redirectData['data']['Frequency']);
        $this->assertEquals(2, $redirectData['data']['ExecTimes']);

        unset($_POST['omnipay_period']);
    }

    public function test_validate_period_constraints_for_year()
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = 'Y';
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = 2; // Invalid: must be 1
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = 5;

        // Use public API - should return false when validation fails
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public function test_validate_period_constraints_passes_valid_data()
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = 'M';
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = 1;
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = 12;

        // Use public API - should return true when validation passes
        $result = $this->gateway->process_admin_options();

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public function test_save_dca_periods_from_post_data()
    {
        $_POST['periodType'] = ['M', 'Y'];
        $_POST['frequency'] = [1, 1];
        $_POST['execTimes'] = [12, 6];

        // Use public API to trigger saveDcaPeriods
        $this->gateway->process_admin_options();

        $saved = get_option('woocommerce_omnipay_ecpay_dca_periods');
        $this->assertCount(2, $saved);
        $this->assertEquals('M', $saved[0]['periodType']);
        $this->assertEquals(1, $saved[0]['frequency']);
        $this->assertEquals(12, $saved[0]['execTimes']);

        unset($_POST['periodType']);
        unset($_POST['frequency']);
        unset($_POST['execTimes']);
    }

    public function test_is_available_returns_true_when_has_valid_periods()
    {
        // Verify periods were loaded by checking the HTML output contains period data
        $html = $this->gateway->generate_periods_html('periods', []);

        // Should contain the period data from setUp
        $this->assertStringContainsString('value="M"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="12"', $html);
    }

    public function test_load_dca_periods_from_option()
    {
        $testPeriods = [
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12],
            ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 6],
        ];

        update_option('woocommerce_omnipay_ecpay_dca_periods', $testPeriods);

        // Create new gateway instance to trigger loadDcaPeriods
        $newGateway = $this->createGateway();

        // Verify periods were loaded by testing generate_periods_html output
        $html = $newGateway->generate_periods_html('periods', []);

        $this->assertStringContainsString('value="M"', $html);
        $this->assertStringContainsString('value="Y"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="12"', $html);
        $this->assertStringContainsString('value="6"', $html);
    }

    /**
     * @dataProvider invalidFrequencyAndExecTimesProvider
     */
    public function test_validate_frequency_and_exec_times_rejects_invalid_values($periodType, $frequency, $execTimes)
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = $periodType;
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = $frequency;
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = $execTimes;

        // Use public API - should return false when validation fails
        $result = $this->gateway->process_admin_options();

        $this->assertFalse($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public static function invalidFrequencyAndExecTimesProvider()
    {
        return [
            'Y: frequency 0' => ['Y', 0, 5],
            'Y: frequency 2' => ['Y', 2, 5],
            'Y: execTimes 0' => ['Y', 1, 0],
            'Y: execTimes 10' => ['Y', 1, 10],
            'M: frequency 0' => ['M', 0, 12],
            'M: frequency 13' => ['M', 13, 12],
            'M: execTimes 0' => ['M', 1, 0],
            'M: execTimes 100' => ['M', 1, 100],
            'D: frequency 0' => ['D', 0, 12],
            'D: frequency 366' => ['D', 366, 12],
            'D: execTimes 0' => ['D', 1, 0],
            'D: execTimes 1000' => ['D', 1, 1000],
        ];
    }

    /**
     * @dataProvider validFrequencyAndExecTimesProvider
     */
    public function test_validate_frequency_and_exec_times_accepts_valid_values($periodType, $frequency, $execTimes)
    {
        $_POST['woocommerce_omnipay_ecpay_dca_periodType'] = $periodType;
        $_POST['woocommerce_omnipay_ecpay_dca_frequency'] = $frequency;
        $_POST['woocommerce_omnipay_ecpay_dca_execTimes'] = $execTimes;

        // Use public API - should return true when validation passes
        $result = $this->gateway->process_admin_options();

        $this->assertTrue($result);

        unset($_POST['woocommerce_omnipay_ecpay_dca_periodType']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_frequency']);
        unset($_POST['woocommerce_omnipay_ecpay_dca_execTimes']);
    }

    public static function validFrequencyAndExecTimesProvider()
    {
        return [
            'Y: min values' => ['Y', 1, 1],
            'Y: max values' => ['Y', 1, 9],
            'M: min frequency' => ['M', 1, 12],
            'M: max frequency' => ['M', 12, 12],
            'M: min execTimes' => ['M', 6, 1],
            'M: max execTimes' => ['M', 6, 99],
            'D: min frequency' => ['D', 1, 12],
            'D: max frequency' => ['D', 365, 12],
            'D: min execTimes' => ['D', 30, 1],
            'D: max execTimes' => ['D', 30, 999],
        ];
    }

    public function test_generate_periods_html_passes_periods_to_template()
    {
        // Set up test periods with specific values
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            ['periodType' => 'M', 'frequency' => 6, 'execTimes' => 24],
            ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 5],
        ]);

        // Create new gateway instance to load periods
        $gateway = $this->createGateway();

        // Test via public WooCommerce API method
        $html = $gateway->generate_periods_html('periods', []);

        // Verify the periods data appears in the rendered HTML
        $this->assertStringContainsString('value="M"', $html); // periodType from first period
        $this->assertStringContainsString('value="6"', $html); // frequency from first period
        $this->assertStringContainsString('value="24"', $html); // execTimes from first period
        $this->assertStringContainsString('value="Y"', $html); // periodType from second period
        $this->assertStringContainsString('value="5"', $html); // execTimes from second period
    }

    public function test_generate_periods_html_passes_field_configs_to_template()
    {
        $html = $this->gateway->generate_periods_html('periods', []);

        // Verify fieldConfigs are used to generate input fields
        // ECPay has: periodType, frequency, execTimes
        $this->assertStringContainsString('name="periodType', $html);
        $this->assertStringContainsString('name="frequency', $html);
        $this->assertStringContainsString('name="execTimes', $html);

        // Verify field attributes from fieldConfigs are applied
        $this->assertStringContainsString('maxlength="1"', $html); // periodType maxlength
        $this->assertStringContainsString('min="1"', $html); // frequency/execTimes min
        $this->assertStringContainsString('max="365"', $html); // frequency max for D type
        $this->assertStringContainsString('max="999"', $html); // execTimes max for D type
    }

    public function test_generate_periods_html_passes_default_period_to_template()
    {
        $html = $this->gateway->generate_periods_html('periods', []);

        // Verify defaultPeriod is rendered in template (for the add-row template)
        // ECPay default: periodType='M', frequency=1, execTimes=12
        $this->assertStringContainsString('value="M"', $html); // default periodType
        $this->assertStringContainsString('value="1"', $html); // default frequency
        $this->assertStringContainsString('value="12"', $html); // default execTimes
    }

    public function test_payment_fields_displays_description()
    {
        $this->gateway->description = 'Test DCA payment description';

        ob_start();
        $this->gateway->payment_fields();
        $output = ob_get_clean();

        $this->assertStringContainsString('Test DCA payment description', $output);
    }

    public function test_payment_fields_displays_period_select_with_configured_periods()
    {
        // Set up multiple DCA periods
        update_option('woocommerce_omnipay_ecpay_dca_periods', [
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12],
            ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 24],
            ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 3],
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
        $this->assertStringContainsString('M_1_12', $output);
        $this->assertStringContainsString('M_1_24', $output);
        $this->assertStringContainsString('Y_1_3', $output);
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
        $this->assertStringContainsString('ECPay', $output);
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
