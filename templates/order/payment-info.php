<?php
/**
 * Payment Info Template (HTML)
 *
 * 顯示 ATM/CVS/BARCODE 付款資訊
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/payment-info.php
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
<section class="omnipay-payment-info">
    <h2><?php esc_html_e('Payment Information', 'woocommerce-omnipay'); ?></h2>
    <table class="woocommerce-table">
        <?php foreach ($payment_info as $meta_key => $value) { ?>
            <?php if (isset($labels[$meta_key])) { ?>
                <tr>
                    <th><?php echo esc_html($labels[$meta_key]); ?></th>
                    <td>
                        <?php if (in_array($meta_key, $barcode_fields, true)) { ?>
                            <svg class="omnipay-barcode" data-barcode="<?php echo esc_attr($value); ?>" data-format="CODE39"></svg>
                            <noscript><?php echo esc_html($value); ?></noscript>
                        <?php } else { ?>
                            <?php echo esc_html($value); ?>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        <?php } ?>
    </table>
</section>
