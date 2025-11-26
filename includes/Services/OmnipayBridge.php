<?php

namespace WooCommerceOmnipay\Services;

use Omnipay\Omnipay;

class OmnipayBridge
{
    /**
     * @var string
     */
    protected $omnipay_gateway_name;

    /**
     * @param  string  $omnipay_gateway_name
     */
    public function __construct($omnipay_gateway_name)
    {
        $this->omnipay_gateway_name = $omnipay_gateway_name;
    }

    /**
     * 從 Omnipay Gateway 產生 WooCommerce 設定欄位
     *
     * @return array
     */
    public function buildFormFields()
    {
        $fields = [];

        foreach ($this->getDefaultParameters() as $key => $value) {
            $fields[$key] = $this->createFieldFromParameter($key, $value);
        }

        return $fields;
    }

    /**
     * 建立已初始化的 Omnipay Gateway 實例
     *
     * @return \Omnipay\Common\GatewayInterface
     */
    public function createGateway(array $parameters = [])
    {
        $gateway = Omnipay::create($this->omnipay_gateway_name);
        $gateway->initialize($parameters);

        return $gateway;
    }

    /**
     * 取得 Omnipay Gateway 預設參數
     *
     * @return array
     */
    public function getDefaultParameters()
    {
        try {
            $gateway = Omnipay::create($this->omnipay_gateway_name);

            return $gateway->getDefaultParameters();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 確保 option 值為字串
     *
     * @param  mixed  $value
     * @return string
     */
    public static function sanitizeOptionValue($value)
    {
        if (is_array($value)) {
            return ! empty($value) ? (string) reset($value) : '';
        }

        return $value !== null ? (string) $value : '';
    }

    /**
     * 轉換 WooCommerce option 值為 Omnipay 參數值
     *
     * @param  string  $settingValue  WC 設定值
     * @param  mixed  $defaultValue  Omnipay 預設值（用於判斷類型）
     * @return mixed
     */
    public static function convertOptionValue($settingValue, $defaultValue)
    {
        // checkbox 值轉換為 boolean
        if (is_bool($defaultValue)) {
            return $settingValue === 'yes';
        }

        return $settingValue;
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
