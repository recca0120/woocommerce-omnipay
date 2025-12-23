<?php

namespace WooCommerceOmnipay\WordPress;

/**
 * Settings Manager
 *
 * 管理 WooCommerce Gateway 設定與表單欄位產生
 */
class SettingsManager
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param  string  $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * 取得所有設定（已合併優先順序：Gateway > 共用 > 通用）
     *
     * @param  array  $gatewaySettings  Gateway 自身的設定
     * @return array
     */
    public function getAllSettings(array $gatewaySettings = [])
    {
        return array_merge(
            $this->getGeneralSettings(),
            $this->getSharedSettings(),
            array_filter($gatewaySettings, function ($value) {
                return $value !== '';
            })
        );
    }

    /**
     * 從 Omnipay Gateway 產生 WooCommerce 設定欄位
     *
     * @param  array  $defaultParameters  預設參數
     * @return array
     */
    public function buildFormFields(array $defaultParameters)
    {
        $fields = [];

        foreach ($defaultParameters as $key => $value) {
            $fields[$key] = $this->createFieldFromParameter($key, $value);
        }

        return $fields;
    }

    /**
     * 取得共用設定
     *
     * @return array
     */
    public function getSharedSettings()
    {
        return get_option(self::getOptionKey($this->name), []);
    }

    /**
     * 取得通用設定
     *
     * @return array
     */
    public function getGeneralSettings()
    {
        return get_option('woocommerce_omnipay_general_settings', []);
    }

    /**
     * 取得單一共用設定值
     *
     * @param  string  $key  設定鍵
     * @param  mixed  $default  預設值
     * @return mixed
     */
    public function getSharedValue($key, $default = '')
    {
        $settings = $this->getSharedSettings();
        $generalSettings = $this->getGeneralSettings();

        // 優先使用共用設定，其次通用設定
        return $settings[$key] ?? $generalSettings[$key] ?? $default;
    }

    /**
     * 取得設定的 option key
     *
     * @param  string  $name  Gateway 名稱
     * @return string
     */
    public static function getOptionKey($name)
    {
        return 'woocommerce_omnipay_'.strtolower($name).'_shared_settings';
    }

    /**
     * 根據參數建立表單欄位
     *
     * @param  string  $key  參數名稱
     * @param  mixed  $defaultValue  預設值
     * @return array
     */
    protected function createFieldFromParameter($key, $defaultValue)
    {
        $field = [
            'title' => ucwords(str_replace('_', ' ', $key)),
            'type' => 'text',
        ];

        if (is_bool($defaultValue)) {
            return $this->createCheckboxField($field, $key, $defaultValue);
        }

        return $this->createTextField($field, $key, $defaultValue);
    }

    /**
     * 建立 checkbox 欄位
     */
    protected function createCheckboxField($field, $key, $defaultValue)
    {
        $field['type'] = 'checkbox';
        $field['label'] = $field['title'];
        $field['default'] = $defaultValue ? 'yes' : 'no';
        $field['description'] = sprintf('Omnipay parameter: %s (boolean)', $key);
        $field['desc_tip'] = true;

        return $field;
    }

    /**
     * 建立 text 欄位（包含 password 特殊處理）
     */
    protected function createTextField($field, $key, $defaultValue)
    {
        $field['default'] = $defaultValue !== null ? (string) $defaultValue : '';
        $field['description'] = sprintf('Omnipay parameter: %s', $key);
        $field['desc_tip'] = true;

        if (stripos($key, 'password') !== false || stripos($key, 'secret') !== false) {
            $field['type'] = 'password';
        }

        return $field;
    }
}
