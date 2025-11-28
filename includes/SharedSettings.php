<?php

namespace WooCommerceOmnipay;

/**
 * 共用設定管理
 *
 * 管理 Omnipay Gateway 的共用設定（如 MerchantID、HashKey 等）
 */
class SharedSettings
{
    /**
     * 取得共用設定
     *
     * @param  string  $omnipayName  Omnipay gateway 名稱（如 ECPay、NewebPay）
     * @return array
     */
    public static function get($omnipayName)
    {
        return get_option(self::getOptionKey($omnipayName), []);
    }

    /**
     * 取得單一設定值
     *
     * @param  string  $omnipayName  Omnipay gateway 名稱
     * @param  string  $key  設定鍵
     * @param  mixed  $default  預設值
     * @return mixed
     */
    public static function getValue($omnipayName, $key, $default = '')
    {
        $settings = self::get($omnipayName);

        return $settings[$key] ?? $default;
    }

    /**
     * 取得設定的 option key
     *
     * @param  string  $omnipayName  Omnipay gateway 名稱
     * @return string
     */
    public static function getOptionKey($omnipayName)
    {
        return 'woocommerce_omnipay_'.strtolower($omnipayName).'_shared_settings';
    }
}
