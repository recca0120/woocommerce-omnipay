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
        $this->gateway->update_option('dca_periodType', 'M');
        $this->gateway->update_option('dca_periodPoint', '');
        $this->gateway->update_option('dca_periodTimes', 12);
        $this->gateway->update_option('dca_periodStartType', 2);
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
        $_POST['omnipay_dca_period'] = 'W_2_24_1';

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

        unset($_POST['omnipay_dca_period']);
    }
}
