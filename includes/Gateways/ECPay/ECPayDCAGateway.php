<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Traits\HasDcaPeriods;

/**
 * ECPay 定期定額 Gateway
 */
class ECPayDCAGateway extends ECPayGateway
{
    use HasDcaPeriods;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

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
            'default' => 'Y',
            'description' => '',
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['frequency'] = [
            'title' => __('Frequency', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 1,
            'description' => '',
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->form_fields['execTimes'] = [
            'title' => __('Execute Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->initDcaShortcodeFormFields();
    }

    /**
     * Get default period data
     */
    protected function getDefaultPeriod(): array
    {
        return ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12];
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
                'name' => 'frequency',
                'type' => 'number',
                'default' => 1,
                'attributes' => ['min' => '1', 'max' => '365', 'required' => 'required'],
            ],
            [
                'name' => 'execTimes',
                'type' => 'number',
                'default' => 12,
                'attributes' => ['min' => '1', 'max' => '999', 'required' => 'required'],
            ],
        ];
    }

    /**
     * 驗證週期限制
     */
    protected function validatePeriodConstraints(array $values): string
    {
        $periodType = $values['periodType'] ?? '';
        $frequency = $values['frequency'] ?? 0;
        $execTimes = $values['execTimes'] ?? 0;

        $constraints = [
            'Y' => [
                'frequency' => [1, 1],
                'execTimes' => [1, 9],
                'messages' => [
                    'frequency' => __('When the periodType field is set to year, the execution frequency field can only be set to 1.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to year, The execTimes field can only be between 1 and 9.', 'woocommerce-omnipay'),
                ],
            ],
            'M' => [
                'frequency' => [1, 12],
                'execTimes' => [1, 99],
                'messages' => [
                    'frequency' => __('When the periodType field is set to month, The frequency field can only be between 1 and 12.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to month, The execTimes field can only be between 1 and 99.', 'woocommerce-omnipay'),
                ],
            ],
            'D' => [
                'frequency' => [1, 365],
                'execTimes' => [1, 999],
                'messages' => [
                    'frequency' => __('When the periodType field is set to day, The frequency field can only be between 1 and 365.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to day, The execTimes field can only be between 1 and 999.', 'woocommerce-omnipay'),
                ],
            ],
        ];

        if (! isset($constraints[$periodType])) {
            return '';
        }

        $config = $constraints[$periodType];
        $errors = [];

        [$minFreq, $maxFreq] = $config['frequency'];
        if ($frequency < $minFreq || $frequency > $maxFreq) {
            $errors[] = $config['messages']['frequency'];
        }

        [$minExec, $maxExec] = $config['execTimes'];
        if ($execTimes < $minExec || $execTimes > $maxExec) {
            $errors[] = $config['messages']['execTimes'];
        }

        return implode(' ', $errors);
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
        $data['ChoosePayment'] = $this->paymentType;

        // Get DCA settings based on mode
        $dcaData = $this->isBlocksMode()
            ? $this->getBlocksModeDcaData()
            : $this->getShortcodeModeDcaData();

        $data = array_merge($data, $dcaData);
        $data['PeriodAmount'] = (int) $order->get_total();

        return $data;
    }

    /**
     * Get DCA data for Blocks mode
     */
    protected function getBlocksModeDcaData(): array
    {
        return [
            'PeriodType' => $this->get_option('periodType', 'M'),
            'Frequency' => (int) $this->get_option('frequency', 1),
            'ExecTimes' => (int) $this->get_option('execTimes', 2),
        ];
    }

    /**
     * Get DCA data for Shortcode mode
     */
    protected function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period']);
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 3) {
            [$periodType, $frequency, $execTimes] = $parts;

            return [
                'PeriodType' => $periodType,
                'Frequency' => (int) $frequency,
                'ExecTimes' => (int) $execTimes,
            ];
        }

        // Fallback to default values if format is invalid
        return [
            'PeriodType' => 'M',
            'Frequency' => 1,
            'ExecTimes' => 2,
        ];
    }
}
