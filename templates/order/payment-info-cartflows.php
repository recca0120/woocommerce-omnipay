<?php
/**
 * Payment Info Template for CartFlows
 *
 * 顯示 ATM/CVS/BARCODE 付款資訊（CartFlows 樣式）
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/payment-info-cartflows.php
 *
 * @var array $payment_info 付款資訊陣列 (meta_key => value)
 * @var array $labels 標籤對應陣列 (meta_key => label)
 */
defined('ABSPATH') || exit;

if (empty($payment_info)) {
    return;
}

// 條碼欄位（需要渲染成條碼圖片）
$barcode_fields = [
    '_omnipay_barcode_1',
    '_omnipay_barcode_2',
    '_omnipay_barcode_3',
];
?>
<?php foreach ($payment_info as $meta_key => $value) { ?>
    <?php if (isset($labels[$meta_key])) { ?>
        <div class="wcf-ic-review-customer__row">
            <div class="wcf-ic-review-customer__label">
                <label><?php echo esc_html($labels[$meta_key]); ?></label>
            </div>
            <div class="wcf-ic-review-customer__content">
                <?php if (in_array($meta_key, $barcode_fields, true)) { ?>
                    <p>
                        <svg class="omnipay-barcode" data-barcode="<?php echo esc_attr($value); ?>" data-format="CODE39"></svg>
                        <noscript><?php echo esc_html($value); ?></noscript>
                    </p>
                <?php } else { ?>
                    <p><?php echo esc_html($value); ?></p>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
<?php } ?>
