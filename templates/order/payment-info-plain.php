<?php

/**
 * Payment Info Template (Plain Text)
 *
 * 顯示 ATM/CVS/BARCODE 付款資訊（純文字格式，用於 email）
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/payment-info-plain.php
 *
 * @var array $payment_info 付款資訊陣列 (meta_key => value)
 * @var array $labels 標籤對應陣列 (meta_key => label)
 */
defined('ABSPATH') || exit;

if (empty($payment_info)) {
    return;
}

echo "\n".__('Payment Information', 'woocommerce-omnipay')."\n";
echo str_repeat('-', 40)."\n";

foreach ($payment_info as $meta_key => $value) {
    if (isset($labels[$meta_key])) {
        echo $labels[$meta_key].': '.$value."\n";
    }
}
