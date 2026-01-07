<?php

namespace Recca0120\WooCommerce_Omnipay;

/**
 * Helper
 *
 * 通用輔助方法
 */
class Helper
{
    /**
     * 轉換 WooCommerce option 值為 Omnipay 參數值
     *
     * @param  string  $settingValue  WC 設定值
     * @param  mixed  $defaultValue  Omnipay 預設值（用於判斷類型）
     * @return mixed
     */
    public static function convertOptionValue($settingValue, $defaultValue)
    {
        if (is_bool($defaultValue)) {
            return $settingValue === 'yes';
        }

        return $settingValue;
    }

    /**
     * 終止程式執行（測試環境可透過 filter 禁用）
     *
     * @codeCoverageIgnore
     */
    public static function terminate()
    {
        if (apply_filters('woocommerce_omnipay_should_exit', true)) {
            exit;
        }
    }

    /**
     * 遮蔽敏感資料
     *
     * @param  array  $data  原始資料
     * @return array 遮蔽後的資料
     */
    public static function maskSensitiveData(array $data)
    {
        $sensitiveKeys = ['HashKey', 'HashIV', 'cvv', 'number', 'card_number', 'password', 'secret'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::maskSensitiveData($value);
            } elseif (in_array(strtolower($key), array_map('strtolower', $sensitiveKeys), true)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
