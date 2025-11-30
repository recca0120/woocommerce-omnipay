<?php

namespace WooCommerceOmnipay\Traits;

/**
 * HasDcaPeriods Trait
 *
 * 提供 DCA (定期定額) 功能的共用邏輯
 */
trait HasDcaPeriods
{
    /**
     * 定期定額方案
     *
     * @var array
     */
    protected $dcaPeriods = [];

    /**
     * Get DCA periods option name
     */
    protected function getDcaPeriodsOptionName(): string
    {
        return 'woocommerce_'.$this->id.'_periods';
    }

    /**
     * Load DCA periods from option
     */
    protected function loadDcaPeriods()
    {
        $this->dcaPeriods = get_option($this->getDcaPeriodsOptionName(), []);
    }

    /**
     * 初始化 DCA 共同表單欄位
     */
    protected function initDcaFormFields()
    {
        $this->form_fields['blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('Configure settings for both WooCommerce Blocks and Shortcode checkout. Fill in the section matching your checkout page type.', 'woocommerce-omnipay'),
        ];

        $this->form_fields['blocks_title'] = [
            'title' => __('WooCommerce Blocks Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using WooCommerce Blocks checkout page.', 'woocommerce-omnipay'),
        ];
    }

    /**
     * 初始化 DCA Shortcode 表單欄位
     */
    protected function initDcaShortcodeFormFields()
    {
        $this->form_fields['shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['shortcode_title'] = [
            'title' => __('Shortcode Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using traditional shortcode-based checkout page.', 'woocommerce-omnipay'),
        ];

        $this->form_fields['periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'periods',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * Extract provider name from gateway ID
     *
     * @return string Provider name (e.g., 'ecpay', 'newebpay')
     */
    protected function getProviderName(): string
    {
        $provider = str_replace('omnipay_', '', $this->id);

        return explode('_dca', $provider)[0];
    }

    /**
     * Get localized provider name for display
     *
     * @return string Localized provider name
     */
    protected function getLocalizedProviderName(): string
    {
        $providerMap = [
            'ecpay' => __('ECPay', 'woocommerce-omnipay'),
            'newebpay' => __('NewebPay', 'woocommerce-omnipay'),
        ];

        $provider = $this->getProviderName();

        return $providerMap[$provider] ?? ucfirst($provider);
    }

    /**
     * Get required DCA fields for Blocks mode validation
     *
     * @return array Field names required for validation
     */
    protected function getRequiredDcaFields(): array
    {
        return array_column($this->getDcaFieldConfigs(), 'name');
    }

    /**
     * Get period fields for template
     *
     * @return array Field names for template rendering
     */
    protected function getPeriodFields(): array
    {
        return array_column($this->getDcaFieldConfigs(), 'name');
    }

    /**
     * Get warning message for checkout
     *
     * @return string Localized warning message
     */
    protected function getWarningMessage(): string
    {
        return sprintf(
            __('You will use <strong>%s recurring credit card payment</strong>. Please note that the products you purchased are <strong>non-single payment</strong> products.', 'woocommerce-omnipay'),
            $this->getLocalizedProviderName()
        );
    }

    /**
     * 生成 DCA 設定表格 HTML
     *
     * WooCommerce Settings API callback for custom field type 'periods'
     */
    public function generate_periods_html($key, $data)
    {
        $provider = $this->getProviderName();
        $templatePath = "admin/{$provider}-dca-periods-table.php";

        return woocommerce_omnipay_get_template($templatePath, [
            'fieldKey' => $this->get_field_key($key),
            'data' => $data,
            'periods' => $this->dcaPeriods,
            'fieldConfigs' => $this->getDcaFieldConfigs(),
            'defaultPeriod' => $this->getDefaultPeriod(),
        ]);
    }

    /**
     * 處理管理選項更新
     */
    public function process_admin_options()
    {
        // Validate DCA settings
        if (! $this->validateDcaFields()) {
            return false;
        }

        // Save DCA periods
        $this->saveDcaPeriods();

        return parent::process_admin_options();
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
            foreach ($this->getRequiredDcaFields() as $field) {
                if (empty($this->get_option($field))) {
                    return false;
                }
            }

            return true;
        }

        // 舊版傳統結帳 - 檢查多組方案設定
        return ! empty($this->dcaPeriods);
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
            $provider = $this->getProviderName();
            $templatePath = "checkout/{$provider}-dca-form.php";

            echo woocommerce_omnipay_get_template($templatePath, [
                'periods' => $this->dcaPeriods,
                'total' => $total,
                'periodFields' => $this->getPeriodFields(),
                'warningMessage' => $this->getWarningMessage(),
            ]);
        }
    }

    /**
     * Check if current checkout is using Blocks mode
     */
    protected function isBlocksMode(): bool
    {
        return ! isset($_POST['omnipay_period']);
    }

    /**
     * Save DCA periods from POST data
     */
    protected function saveDcaPeriods()
    {
        $dcaPeriods = [];
        $fieldConfigs = $this->getDcaFieldConfigs();
        $firstField = $fieldConfigs[0]['name'];

        if (isset($_POST[$firstField]) && is_array($_POST[$firstField])) {
            $count = count($_POST[$firstField]);

            for ($i = 0; $i < $count; $i++) {
                $period = [];
                $hasValue = false;

                foreach ($fieldConfigs as $config) {
                    $fieldName = $config['name'];
                    $value = $_POST[$fieldName][$i] ?? $config['default'];

                    if ($config['type'] === 'number') {
                        $period[$fieldName] = absint($value);
                    } else {
                        $period[$fieldName] = sanitize_text_field($value);
                    }

                    if (! empty($value)) {
                        $hasValue = true;
                    }
                }

                if ($hasValue) {
                    $dcaPeriods[] = $period;
                }
            }
        }

        update_option($this->getDcaPeriodsOptionName(), $dcaPeriods);
    }

    /**
     * 驗證 DCA 欄位
     */
    protected function validateDcaFields()
    {
        $errorMsg = '';

        // Validate Blocks mode settings
        $requiredFields = $this->getRequiredDcaFields();
        if (isset($_POST[$this->plugin_id.$this->id.'_'.$requiredFields[0]])) {
            $values = [];
            $fieldConfigs = $this->getDcaFieldConfigs();
            $configMap = [];
            foreach ($fieldConfigs as $config) {
                $configMap[$config['name']] = $config;
            }

            foreach ($requiredFields as $field) {
                $values[$field] = $_POST[$this->plugin_id.$this->id.'_'.$field] ?? null;
                if (isset($configMap[$field]) && $configMap[$field]['type'] === 'number') {
                    $values[$field] = absint($values[$field]);
                } else {
                    $values[$field] = sanitize_text_field($values[$field]);
                }
            }
            $errorMsg .= $this->validatePeriodConstraints($values);
        }

        // Validate Shortcode mode periods
        $fieldConfigs = $this->getDcaFieldConfigs();
        $firstField = $fieldConfigs[0]['name'];

        if (isset($_POST[$firstField]) && is_array($_POST[$firstField])) {
            $count = count($_POST[$firstField]);

            for ($i = 0; $i < $count; $i++) {
                $values = [];
                $hasValue = false;

                foreach ($fieldConfigs as $config) {
                    $fieldName = $config['name'];
                    if (isset($_POST[$fieldName][$i])) {
                        $value = $_POST[$fieldName][$i];

                        if ($config['type'] === 'number') {
                            $values[$fieldName] = absint($value);
                        } else {
                            $values[$fieldName] = sanitize_text_field($value);
                        }

                        if (! empty($value)) {
                            $hasValue = true;
                        }
                    }
                }

                if ($hasValue) {
                    $errorMsg .= $this->validatePeriodConstraints($values);
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
     * Get DCA field configurations
     */
    abstract protected function getDcaFieldConfigs(): array;

    /**
     * Get default period data
     */
    abstract protected function getDefaultPeriod(): array;

    /**
     * Validate period constraints
     */
    abstract protected function validatePeriodConstraints(array $values): string;
}
