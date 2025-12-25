<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Frequency-based Recurring Payment Feature
 *
 * 頻率式定期付款（每隔 N 天/月/年執行一次）
 * 使用 periodType + frequency + execTimes 參數
 */
class FrequencyRecurringFeature extends AbstractFeature implements RecurringFeature
{
    /**
     * @var array 定期定額方案
     */
    private $dcaPeriods = [];

    /**
     * @var string Gateway ID (for option name)
     */
    private $gatewayId;

    /**
     * 欄位配置
     */
    private function getFieldConfigs(): array
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
     * 預設週期
     */
    private function getDefaultPeriod(): array
    {
        return ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12];
    }

    /**
     * 載入 DCA 方案
     */
    public function loadPeriods(WC_Payment_Gateway $gateway): void
    {
        $this->gatewayId = $gateway->id;
        $this->dcaPeriods = get_option($this->getPeriodsOptionName(), []);
    }

    /**
     * 取得方案儲存的 option name
     */
    private function getPeriodsOptionName(): string
    {
        return 'woocommerce_'.$this->gatewayId.'_periods';
    }

    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        // Blocks 與 Shortcode 說明區塊
        $formFields['blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $formFields['blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('Configure settings for both WooCommerce Blocks and Shortcode checkout. Fill in the section matching your checkout page type.', 'woocommerce-omnipay'),
        ];

        $formFields['blocks_title'] = [
            'title' => __('WooCommerce Blocks Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using WooCommerce Blocks checkout page.', 'woocommerce-omnipay'),
        ];

        // Blocks 模式欄位
        $formFields['periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'M',
            'description' => '',
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $formFields['frequency'] = [
            'title' => __('Frequency', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 1,
            'description' => '',
            'custom_attributes' => ['min' => 1, 'step' => 1],
        ];

        $formFields['execTimes'] = [
            'title' => __('Execute Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => ['min' => 1, 'step' => 1],
        ];

        // Shortcode 模式欄位
        $formFields['shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $formFields['shortcode_title'] = [
            'title' => __('Shortcode Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using traditional shortcode-based checkout page.', 'woocommerce-omnipay'),
        ];

        $formFields['periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'periods',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(WC_Payment_Gateway $gateway): bool
    {
        if (! (function_exists('is_checkout') && is_checkout())) {
            return true;
        }

        // WooCommerce Blocks - 檢查單一方案設定
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            foreach (['periodType', 'frequency', 'execTimes'] as $field) {
                if (empty($gateway->get_option($field))) {
                    return false;
                }
            }

            return true;
        }

        // 傳統結帳 - 檢查多組方案設定
        return ! empty($this->dcaPeriods);
    }

    /**
     * {@inheritdoc}
     */
    public function paymentFields(WC_Payment_Gateway $gateway): void
    {
        // 只有 Shortcode 版本才顯示下拉選單
        if (! is_checkout() || is_wc_endpoint_url('order-pay')) {
            return;
        }

        $total = WC()->cart ? WC()->cart->total : 0;

        $gatewayName = method_exists($gateway, 'getGatewayName') ? $gateway->getGatewayName() : '';

        echo woocommerce_omnipay_get_template('checkout/frequency-recurring-form.php', [
            'periods' => $this->dcaPeriods,
            'total' => $total,
            'periodFields' => ['periodType', 'frequency', 'execTimes'],
            'warningMessage' => sprintf(
                __('You will use <strong>%s recurring credit card payment</strong>. Please note that the products you purchased are <strong>non-single payment</strong> products.', 'woocommerce-omnipay'),
                $gatewayName
            ),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        // 根據模式取得 DCA 設定
        $dcaData = $this->isBlocksMode()
            ? $this->getBlocksModeDcaData($gateway)
            : $this->getShortcodeModeDcaData();

        $data = array_merge($data, $dcaData);
        $data['PeriodAmount'] = (int) $order->get_total();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPaymentFields(): bool
    {
        return true;
    }

    /**
     * 是否為 Blocks 模式
     */
    private function isBlocksMode(): bool
    {
        return ! isset($_POST['omnipay_period']);
    }

    /**
     * Blocks 模式的 DCA 資料
     */
    private function getBlocksModeDcaData(WC_Payment_Gateway $gateway): array
    {
        return [
            'PeriodType' => $gateway->get_option('periodType', 'M'),
            'Frequency' => (int) $gateway->get_option('frequency', 1),
            'ExecTimes' => (int) $gateway->get_option('execTimes', 2),
        ];
    }

    /**
     * Shortcode 模式的 DCA 資料
     */
    private function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period'] ?? '');
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 3) {
            [$periodType, $frequency, $execTimes] = $parts;

            return [
                'PeriodType' => $periodType,
                'Frequency' => (int) $frequency,
                'ExecTimes' => (int) $execTimes,
            ];
        }

        return [
            'PeriodType' => 'M',
            'Frequency' => 1,
            'ExecTimes' => 2,
        ];
    }

    /**
     * 生成 periods 欄位 HTML
     */
    public function generatePeriodsHtml(string $key, array $data, WC_Payment_Gateway $gateway): string
    {
        return woocommerce_omnipay_get_template('admin/frequency-recurring-periods-table.php', [
            'fieldKey' => $gateway->get_field_key($key),
            'data' => $data,
            'periods' => $this->dcaPeriods,
            'fieldConfigs' => $this->getFieldConfigs(),
            'defaultPeriod' => $this->getDefaultPeriod(),
        ]);
    }

    /**
     * 處理管理選項
     */
    public function processAdminOptions(WC_Payment_Gateway $gateway): bool
    {
        if (! $this->validateAdminFields($gateway)) {
            return false;
        }

        $this->savePeriods();

        return true;
    }

    /**
     * 驗證管理欄位
     */
    private function validateAdminFields(WC_Payment_Gateway $gateway): bool
    {
        $errorMsg = '';
        $fieldConfigs = $this->getFieldConfigs();
        $requiredFields = array_column($fieldConfigs, 'name');

        // 驗證 Blocks 模式設定
        $pluginId = $gateway->plugin_id;
        $gatewayId = $gateway->id;
        if (isset($_POST[$pluginId.$gatewayId.'_'.$requiredFields[0]])) {
            $values = [];
            $configMap = [];
            foreach ($fieldConfigs as $config) {
                $configMap[$config['name']] = $config;
            }

            foreach ($requiredFields as $field) {
                $values[$field] = $_POST[$pluginId.$gatewayId.'_'.$field] ?? null;
                if (isset($configMap[$field]) && $configMap[$field]['type'] === 'number') {
                    $values[$field] = absint($values[$field]);
                } else {
                    $values[$field] = sanitize_text_field($values[$field]);
                }
            }
            $errorMsg .= $this->validatePeriodConstraints($values);
        }

        // 驗證 Shortcode 模式方案
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
     * 驗證週期限制
     */
    private function validatePeriodConstraints(array $values): string
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
     * 儲存方案
     */
    private function savePeriods(): void
    {
        $dcaPeriods = [];
        $fieldConfigs = $this->getFieldConfigs();
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

        update_option($this->getPeriodsOptionName(), $dcaPeriods);
    }
}
