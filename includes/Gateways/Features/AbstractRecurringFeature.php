<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Abstract Recurring Feature
 *
 * 定期定額功能的共用邏輯
 */
abstract class AbstractRecurringFeature extends AbstractFeature implements RecurringFeature
{
    /**
     * @var array 定期定額方案
     */
    protected $dcaPeriods = [];

    /**
     * @var string Gateway ID (for option name)
     */
    protected $gatewayId;

    /**
     * 欄位配置
     */
    abstract protected function getFieldConfigs(): array;

    /**
     * 預設週期
     */
    abstract protected function getDefaultPeriod(): array;

    /**
     * 結帳頁面模板
     */
    abstract protected function getFormTemplate(): string;

    /**
     * 管理頁面模板
     */
    abstract protected function getAdminTemplate(): string;

    /**
     * 金額欄位名稱
     */
    abstract protected function getAmountFieldName(): string;

    /**
     * Blocks 模式的 DCA 資料
     */
    abstract protected function getBlocksModeDcaData(WC_Payment_Gateway $gateway): array;

    /**
     * Shortcode 模式的 DCA 資料
     */
    abstract protected function getShortcodeModeDcaData(): array;

    /**
     * 驗證週期限制
     */
    abstract protected function validatePeriodConstraints(array $values): string;

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
    protected function getPeriodsOptionName(): string
    {
        return 'woocommerce_'.$this->gatewayId.'_periods';
    }

    /**
     * 取得 Blocks 模式需要檢查的欄位
     */
    protected function getBlocksFields(): array
    {
        return array_column($this->getFieldConfigs(), 'name');
    }

    /**
     * 取得週期欄位名稱（用於模板）
     */
    protected function getPeriodFields(): array
    {
        return array_column($this->getFieldConfigs(), 'name');
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
            foreach ($this->getBlocksFields() as $field) {
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

        echo woocommerce_omnipay_get_template($this->getFormTemplate(), [
            'periods' => $this->dcaPeriods,
            'total' => $total,
            'periodFields' => $this->getPeriodFields(),
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
        $data[$this->getAmountFieldName()] = (int) $order->get_total();

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
    protected function isBlocksMode(): bool
    {
        return ! isset($_POST['omnipay_period']);
    }

    /**
     * 生成 periods 欄位 HTML
     */
    public function generatePeriodsHtml(string $key, array $data, WC_Payment_Gateway $gateway): string
    {
        return woocommerce_omnipay_get_template($this->getAdminTemplate(), [
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
    protected function validateAdminFields(WC_Payment_Gateway $gateway): bool
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
     * 儲存方案
     */
    protected function savePeriods(): void
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
