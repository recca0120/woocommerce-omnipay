<?php

namespace Recca0120\WooCommerce_Omnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Gateway Feature Interface
 *
 * 定義 Gateway 功能組件的契約
 * 透過組合模式，讓 Gateway 可以動態組合不同功能
 */
interface GatewayFeature
{
    /**
     * 初始化後台設定欄位
     *
     * @param  array  $formFields  現有的表單欄位（傳址修改）
     */
    public function initFormFields(array &$formFields): void;

    /**
     * 檢查付款方式是否可用
     *
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     * @return bool 如果不可用返回 false，否則返回 true
     */
    public function isAvailable(WC_Payment_Gateway $gateway): bool;

    /**
     * 準備付款資料
     *
     * @param  array  $data  現有的付款資料
     * @param  WC_Order  $order  訂單
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     * @return array 修改後的付款資料
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array;

    /**
     * 渲染結帳頁付款欄位
     *
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     */
    public function paymentFields(WC_Payment_Gateway $gateway): void;

    /**
     * 驗證結帳表單欄位
     *
     * @return bool 驗證通過返回 true
     */
    public function validateFields(): bool;

    /**
     * 是否有付款欄位需要顯示
     *
     * @return bool 有付款欄位返回 true
     */
    public function hasPaymentFields(): bool;
}
