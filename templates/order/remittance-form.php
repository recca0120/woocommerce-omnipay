<?php
/**
 * Remittance Form Template (HTML)
 *
 * 顯示匯款帳號後碼輸入表單
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/remittance-form.php
 *
 * @var WC_Order $order 訂單
 * @var string $submitted_last5 已填寫的後碼（如有）
 * @var string $submit_url 表單提交 URL
 * @var int $last_digits 帳號後碼位數
 */
defined('ABSPATH') || exit;
?>
<section class="omnipay-payment-info">
    <h2><?php esc_html_e('Remittance Confirmation', 'woocommerce-omnipay'); ?></h2>
    <?php if ($submitted_last5) { ?>
        <p>
            <?php echo sprintf(esc_html__('Last %d digits of remittance account submitted:', 'woocommerce-omnipay'), $last_digits); ?>
            <strong><?php echo esc_html($submitted_last5); ?></strong>
        </p>
    <?php } else { ?>
        <p><?php echo sprintf(esc_html__('Please enter the last %d digits of your remittance account after payment to help us confirm the transaction.', 'woocommerce-omnipay'), $last_digits); ?></p>
        <form method="post" action="<?php echo esc_url($submit_url); ?>">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
            <input type="hidden" name="order_key" value="<?php echo esc_attr($order->get_order_key()); ?>">
            <?php wp_nonce_field('omnipay_remittance_nonce', 'nonce'); ?>
            <p>
                <label for="remittance_last5"><?php echo sprintf(esc_html__('Last %d Digits of Remittance Account', 'woocommerce-omnipay'), $last_digits); ?></label>
                <input type="text" id="remittance_last5" name="remittance_last5" maxlength="<?php echo esc_attr($last_digits); ?>" pattern="\d{<?php echo esc_attr($last_digits); ?>}" required>
            </p>
            <button type="submit" class="button"><?php esc_html_e('Submit', 'woocommerce-omnipay'); ?></button>
        </form>
    <?php } ?>
</section>
