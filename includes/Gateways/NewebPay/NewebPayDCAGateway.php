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
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_dca';
        $config['title'] = $config['title'] ?? __('NewebPay Recurring Payment', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with credit card recurring payment', 'woocommerce-omnipay');

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
            'default' => '1',
            'description' => '',
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
            'periodPoint' => '1',
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
                'default' => '1',
                'attributes' => ['required' => 'required'],
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
        $periodTimes = $values['periodTimes'] ?? 0;

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
     * Get required DCA fields for Blocks mode validation
     */
    protected function getRequiredDcaFields(): array
    {
        return ['periodType', 'periodPoint', 'periodTimes', 'periodStartType'];
    }

    /**
     * Get period fields for template
     */
    protected function getPeriodFields(): array
    {
        return ['periodType', 'periodPoint', 'periodTimes', 'periodStartType'];
    }

    /**
     * Get warning message for checkout
     */
    protected function getWarningMessage(): string
    {
        return sprintf(
            __('You will use <strong>%s recurring credit card payment</strong>. Please note that the products you purchased are <strong>non-single payment</strong> products.', 'woocommerce-omnipay'),
            __('NewebPay', 'woocommerce-omnipay')
        );
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
