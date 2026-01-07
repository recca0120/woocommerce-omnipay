<?php

namespace Recca0120\WooCommerce_Omnipay\Gateways\Features;

use WC_Payment_Gateway;

/**
 * Recurring Feature Interface
 *
 * 定期定額功能的共用介面
 * 包含：載入方案、產生欄位 HTML、處理管理選項
 */
interface RecurringFeature extends GatewayFeature
{
    /**
     * 載入定期定額方案
     *
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     */
    public function loadPeriods(WC_Payment_Gateway $gateway): void;

    /**
     * 產生方案欄位 HTML
     *
     * @param  string  $key  欄位鍵
     * @param  array  $data  欄位資料
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     * @return string HTML 內容
     */
    public function generatePeriodsHtml(string $key, array $data, WC_Payment_Gateway $gateway): string;

    /**
     * 處理管理選項
     *
     * @param  WC_Payment_Gateway  $gateway  Gateway 實例
     * @return bool 處理成功返回 true
     */
    public function processAdminOptions(WC_Payment_Gateway $gateway): bool;
}
