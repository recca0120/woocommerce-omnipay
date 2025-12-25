<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Scheduled Recurring Payment Feature
 *
 * 排程式定期付款（指定日期/星期執行）
 * 使用 periodType + periodPoint + periodTimes + periodStartType 參數
 */
class ScheduledRecurringFeature extends AbstractFeature implements RecurringFeature
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
     * 預設週期
     */
    private function getDefaultPeriod(): array
    {
        return [
            'periodType' => 'M',
            'periodPoint' => '01',
            'periodTimes' => 2,
            'periodStartType' => '2',
        ];
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
                'W' => __('Week', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $formFields['periodPoint'] = [
            'title' => __('Period Point', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '01',
            'description' => __('Y: MMDD (e.g., 0315), M: 01-31, W: 1-7, D: 2-999', 'woocommerce-omnipay'),
        ];

        $formFields['periodTimes'] = [
            'title' => __('Period Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => ['min' => 2, 'max' => 99, 'step' => 1],
        ];

        $formFields['periodStartType'] = [
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
            foreach (['periodType', 'periodPoint', 'periodTimes', 'periodStartType'] as $field) {
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

        echo woocommerce_omnipay_get_template('checkout/scheduled-recurring-form.php', [
            'periods' => $this->dcaPeriods,
            'total' => $total,
            'periodFields' => ['periodType', 'periodPoint', 'periodTimes', 'periodStartType'],
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
        $data['PeriodAmt'] = (int) $order->get_total();

        // PayerEmail is required for recurring payment
        $data['PayerEmail'] = $order->get_billing_email() ?: get_bloginfo('admin_email');

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
            'PeriodPoint' => $gateway->get_option('periodPoint', '1'),
            'PeriodTimes' => (int) $gateway->get_option('periodTimes', 2),
            'PeriodStartType' => (int) $gateway->get_option('periodStartType', 2),
        ];
    }

    /**
     * Shortcode 模式的 DCA 資料
     */
    private function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period'] ?? '');
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

        return [
            'PeriodType' => 'M',
            'PeriodPoint' => '1',
            'PeriodTimes' => 12,
            'PeriodStartType' => 2,
        ];
    }

    /**
     * 生成 periods 欄位 HTML
     */
    public function generatePeriodsHtml(string $key, array $data, WC_Payment_Gateway $gateway): string
    {
        return woocommerce_omnipay_get_template('admin/scheduled-recurring-periods-table.php', [
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
        $periodPoint = $values['periodPoint'] ?? '';
        $periodTimes = $values['periodTimes'] ?? 0;

        // 驗證 PeriodPoint 格式
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
    private function validatePeriodPoint(string $periodType, string $periodPoint): string
    {
        if ($periodType === 'Y') {
            if (! preg_match('/^\d{4}$/', $periodPoint)) {
                return __('For yearly periods, PeriodPoint must be in MMDD format (e.g., 0315 for March 15th).', 'woocommerce-omnipay').' ';
            }
            $month = (int) substr($periodPoint, 0, 2);
            $day = (int) substr($periodPoint, 2, 2);
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return __('Invalid date in PeriodPoint. Month must be 01-12, day must be 01-31.', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'M') {
            $day = (int) $periodPoint;
            if ($day < 1 || $day > 31) {
                return __('For monthly periods, PeriodPoint must be 1-31 (day of month).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'W') {
            $weekday = (int) $periodPoint;
            if ($weekday < 1 || $weekday > 7) {
                return __('For weekly periods, PeriodPoint must be 1-7 (1=Monday, 7=Sunday).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'D') {
            $interval = (int) $periodPoint;
            if ($interval < 2 || $interval > 999) {
                return __('For daily periods, PeriodPoint must be 2-999 (day interval).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        return '';
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
