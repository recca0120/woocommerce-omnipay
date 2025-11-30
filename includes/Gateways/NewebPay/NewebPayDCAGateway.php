<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;
use WooCommerceOmnipay\Traits\HasDcaPeriods;

/**
 * NewebPay 定期定額 Gateway
 */
class NewebPayDCAGateway extends NewebPayGateway
{
    use HasDcaPeriods;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        // Load DCA periods from option
        $this->loadDcaPeriods();
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->initDcaFormFields();

        $this->form_fields['periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'M',
            'description' => '',
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'W' => __('Week', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['periodPoint'] = [
            'title' => __('Period Point', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '01',
            'description' => __('Y: MMDD (e.g., 0315), M: 01-31, W: 1-7, D: 2-999', 'woocommerce-omnipay'),
        ];

        $this->form_fields['periodTimes'] = [
            'title' => __('Period Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => [
                'min' => 2,
                'max' => 99,
                'step' => 1,
            ],
        ];

        $this->form_fields['periodStartType'] = [
            'title' => __('Period Start Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => '2',
            'description' => '',
            'options' => [
                '1' => __('1 - Authorize and start immediately', 'woocommerce-omnipay'),
                '2' => __('2 - Authorize only, start manually', 'woocommerce-omnipay'),
                '3' => __('3 - Delegate to merchant', 'woocommerce-omnipay'),
            ],
        ];

        $this->initDcaShortcodeFormFields();
    }

    /**
     * Get default period data
     */
    protected function getDefaultPeriod(): array
    {
        return [
            'periodType' => 'M',
            'periodPoint' => '01',
            'periodTimes' => 2,
            'periodStartType' => '2',
        ];
    }

    /**
     * Get DCA field configurations
     */
    protected function getDcaFieldConfigs(): array
    {
        return [
            [
                'name' => 'periodType',
                'type' => 'text',
                'default' => 'M',
                'attributes' => ['maxlength' => '1', 'required' => 'required'],
            ],
            [
                'name' => 'periodPoint',
                'type' => 'text',
                'default' => '01',
                'attributes' => ['required' => 'required', 'placeholder' => 'Y:MMDD M:DD W:1-7 D:2-999'],
            ],
            [
                'name' => 'periodTimes',
                'type' => 'number',
                'default' => 2,
                'attributes' => ['min' => '2', 'max' => '99', 'required' => 'required'],
            ],
            [
                'name' => 'periodStartType',
                'type' => 'number',
                'default' => 2,
                'attributes' => ['min' => '1', 'max' => '3', 'required' => 'required'],
            ],
        ];
    }

    /**
     * 驗證週期限制
     */
    protected function validatePeriodConstraints(array $values): string
    {
        $periodType = $values['periodType'] ?? '';
        $periodPoint = $values['periodPoint'] ?? '';
        $periodTimes = $values['periodTimes'] ?? 0;

        // Validate PeriodPoint format based on PeriodType
        $pointError = $this->validatePeriodPoint($periodType, $periodPoint);
        if ($pointError) {
            return $pointError;
        }

        $constraints = [
            'Y' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to year, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'M' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to month, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'W' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to week, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'D' => [
                'periodTimes' => [2, 999],
                'message' => __('When the periodType field is set to day, The periodTimes field can only be between 2 and 999.', 'woocommerce-omnipay'),
            ],
        ];

        if (! isset($constraints[$periodType])) {
            return '';
        }

        $config = $constraints[$periodType];
        [$minTimes, $maxTimes] = $config['periodTimes'];

        if ($periodTimes < $minTimes || $periodTimes > $maxTimes) {
            return $config['message'].' ';
        }

        return '';
    }

    /**
     * 驗證 PeriodPoint 格式
     */
    protected function validatePeriodPoint(string $periodType, string $periodPoint): string
    {
        if ($periodType === 'Y') {
            // MMDD format (4 digits)
            if (! preg_match('/^\d{4}$/', $periodPoint)) {
                return __('For yearly periods, PeriodPoint must be in MMDD format (e.g., 0315 for March 15th).', 'woocommerce-omnipay').' ';
            }
            // Validate month (01-12) and day (01-31)
            $month = (int) substr($periodPoint, 0, 2);
            $day = (int) substr($periodPoint, 2, 2);
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return __('Invalid date in PeriodPoint. Month must be 01-12, day must be 01-31.', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'M') {
            // DD format (01-31)
            $day = (int) $periodPoint;
            if ($day < 1 || $day > 31) {
                return __('For monthly periods, PeriodPoint must be 1-31 (day of month).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'W') {
            // 1-7 (Monday to Sunday)
            $weekday = (int) $periodPoint;
            if ($weekday < 1 || $weekday > 7) {
                return __('For weekly periods, PeriodPoint must be 1-7 (1=Monday, 7=Sunday).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'D') {
            // 2-999 (fixed day interval)
            $interval = (int) $periodPoint;
            if ($interval < 2 || $interval > 999) {
                return __('For daily periods, PeriodPoint must be 2-999 (day interval).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        return '';
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function preparePaymentData($order)
    {
        $data = parent::preparePaymentData($order);
        $data['CREDIT'] = '1';

        // Get DCA settings based on mode
        $dcaData = $this->isBlocksMode()
            ? $this->getBlocksModeDcaData()
            : $this->getShortcodeModeDcaData();

        $data = array_merge($data, $dcaData);
        $data['PeriodAmt'] = (int) $order->get_total();

        // PayerEmail is required for recurring payment
        $data['PayerEmail'] = $order->get_billing_email() ?: get_bloginfo('admin_email');

        return $data;
    }

    /**
     * Get DCA data for Blocks mode
     */
    protected function getBlocksModeDcaData(): array
    {
        return [
            'PeriodType' => $this->get_option('periodType', 'M'),
            'PeriodPoint' => $this->get_option('periodPoint', '1'),
            'PeriodTimes' => (int) $this->get_option('periodTimes', 2),
            'PeriodStartType' => (int) $this->get_option('periodStartType', 2),
        ];
    }

    /**
     * Get DCA data for Shortcode mode
     */
    protected function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period']);
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 4) {
            [$periodType, $periodPoint, $periodTimes, $periodStartType] = $parts;

            return [
                'PeriodType' => $periodType,
                'PeriodPoint' => $periodPoint,
                'PeriodTimes' => (int) $periodTimes,
                'PeriodStartType' => (int) $periodStartType,
            ];
        }

        // Fallback to default values if format is invalid
        return [
            'PeriodType' => 'M',
            'PeriodPoint' => '1',
            'PeriodTimes' => 12,
            'PeriodStartType' => 2,
        ];
    }
}
