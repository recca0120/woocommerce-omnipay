<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 定期定額 Gateway
 */
class ECPayDCAGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

    /**
     * 定期定額方案
     *
     * @var array
     */
    protected $dca_periods = [];

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_dca';
        $config['title'] = $config['title'] ?? __('ECPay Recurring Payment', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with credit card recurring payment', 'woocommerce-omnipay');

        parent::__construct($config);

        // Load DCA periods from option
        $this->dca_periods = get_option('woocommerce_omnipay_ecpay_dca_periods', []);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        // Blocks mode settings (single period)
        $this->form_fields['dca_blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('There are two section fields for DCA settings: WooCommerce Blocks and Woocommerce Shortcode. Please fill out the section that matches your current page configuration. If you are uncertain about which page configuration you are using, input the identical setting in both sections.', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_blocks_title'] = [
            'title' => __('DCA (Support WooCommerce Blocks)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the WooCommerce Blocks checkout page and do not support the use of the traditional shortcode-based checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'Y',
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['dca_frequency'] = [
            'title' => __('Frequency', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 1,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->form_fields['dca_execTimes'] = [
            'title' => __('Execute Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        // Shortcode mode settings (multiple periods table)
        $this->form_fields['dca_shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_shortcode_title'] = [
            'title' => __('DCA (Support WooCommerce Shortcode)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the traditional shortcode-based checkout page and do not support the use of the WooCommerce Blocks checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'dca_periods',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * 生成 DCA 設定表格 HTML
     */
    public function generate_dca_periods_html($key, $data)
    {
        return woocommerce_omnipay_get_template('admin/dca-periods-table.php', [
            'field_key' => $this->get_field_key($key),
            'data' => $data,
            'periods' => $this->dca_periods,
        ]);
    }

    /**
     * 處理管理選項更新
     */
    public function process_admin_options()
    {
        // Validate DCA settings
        if (! $this->validate_dca_fields()) {
            return false;
        }

        // Save DCA periods
        $dca_periods = [];
        if (isset($_POST['dca_periodType'])) {
            $periodTypes = array_map('sanitize_text_field', $_POST['dca_periodType']);
            $frequencies = array_map('absint', $_POST['dca_frequency']);
            $execTimes = array_map('absint', $_POST['dca_execTimes']);

            foreach ($periodTypes as $i => $periodType) {
                if (! empty($periodType)) {
                    $dca_periods[] = [
                        'periodType' => $periodType,
                        'frequency' => $frequencies[$i],
                        'execTimes' => $execTimes[$i],
                    ];
                }
            }
        }
        update_option('woocommerce_omnipay_ecpay_dca_periods', $dca_periods);

        return parent::process_admin_options();
    }

    /**
     * 驗證 DCA 欄位
     */
    protected function validate_dca_fields()
    {
        $errorMsg = '';

        // Validate Blocks mode settings
        if (isset($_POST[$this->plugin_id.$this->id.'_dca_periodType'])) {
            $periodType = sanitize_text_field($_POST[$this->plugin_id.$this->id.'_dca_periodType']);
            $frequency = absint($_POST[$this->plugin_id.$this->id.'_dca_frequency'] ?? 0);
            $execTimes = absint($_POST[$this->plugin_id.$this->id.'_dca_execTimes'] ?? 0);

            $errorMsg .= $this->validatePeriodConstraints($periodType, $frequency, $execTimes);
        }

        // Validate Shortcode mode periods
        if (isset($_POST['dca_periodType']) && is_array($_POST['dca_periodType'])) {
            $periodTypes = array_map('sanitize_text_field', $_POST['dca_periodType']);
            $frequencies = isset($_POST['dca_frequency']) && is_array($_POST['dca_frequency'])
                ? array_map('absint', $_POST['dca_frequency'])
                : [];
            $execTimes = isset($_POST['dca_execTimes']) && is_array($_POST['dca_execTimes'])
                ? array_map('absint', $_POST['dca_execTimes'])
                : [];

            foreach ($periodTypes as $i => $periodType) {
                if (! empty($periodType)) {
                    $errorMsg .= $this->validatePeriodConstraints(
                        $periodType,
                        $frequencies[$i] ?? 0,
                        $execTimes[$i] ?? 0
                    );
                }
            }
        }

        if (! empty($errorMsg)) {
            \WC_Admin_Settings::add_error($errorMsg);

            return false;
        }

        return true;
    }

    /**
     * 驗證週期限制
     */
    protected function validatePeriodConstraints($periodType, $frequency, $execTimes)
    {
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
     * 檢查付款方式是否可用
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        // 未設定定期定額選項時，不開放此付款方式
        if (! (function_exists('is_checkout') && is_checkout())) {
            return true;
        }

        // 新版 WooCommerce Blocks - 檢查單一方案設定
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            return ! (empty($this->get_option('dca_periodType'))
                || empty($this->get_option('dca_frequency'))
                || empty($this->get_option('dca_execTimes')));
        }

        // 舊版傳統結帳 - 檢查多組方案設定
        return ! empty($this->dca_periods);
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo '<p>'.wp_kses_post($this->description).'</p>';
        }

        // 只有 Shortcode 版本才顯示下拉選單
        // Blocks 版本不需要顯示（直接使用設定的方案）
        if (is_checkout() && ! is_wc_endpoint_url('order-pay')) {
            $total = WC()->cart ? WC()->cart->total : 0;

            echo woocommerce_omnipay_get_template('checkout/dca-form.php', [
                'periods' => $this->dca_periods,
                'total' => $total,
                'warning_message' => __('You will use <strong>ECPay recurring credit card payment</strong>. Please note that the products you purchased are <strong>non-single payment</strong> products.', 'woocommerce-omnipay'),
            ]);
        }
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

        if (! isset($_POST['omnipay_dca_period'])) {
            // Blocks 模式：從設定讀取單一方案
            $data['PeriodType'] = $this->get_option('dca_periodType', 'M');
            $data['Frequency'] = (int) $this->get_option('dca_frequency', 1);
            $data['ExecTimes'] = (int) $this->get_option('dca_execTimes', 2);
        } else {
            // Shortcode 模式：從 POST 讀取用戶選擇
            $selectedPeriod = sanitize_text_field($_POST['omnipay_dca_period']);
            $parts = explode('_', $selectedPeriod);
            if (count($parts) === 3) {
                [$periodType, $frequency, $execTimes] = $parts;
                $data['PeriodType'] = $periodType;
                $data['Frequency'] = (int) $frequency;
                $data['ExecTimes'] = (int) $execTimes;
            } else {
                // Fallback to default values if format is invalid
                $data['PeriodType'] = 'M';
                $data['Frequency'] = 1;
                $data['ExecTimes'] = 2;
            }
        }

        $data['PeriodAmount'] = (int) $order->get_total();

        return $data;
    }
}
