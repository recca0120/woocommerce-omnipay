<?php
/**
 * Remittance Form Template for CartFlows
 *
 * 顯示匯款帳號後碼輸入表單（CartFlows 樣式）
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/remittance-form-cartflows.php
 *
 * @var WC_Order $order 訂單
 * @var string $submitted_last5 已填寫的後碼（如有）
 * @var string $submit_url 表單提交 URL
 * @var int $last_digits 帳號後碼位數
 */
defined('ABSPATH') || exit;
?>
<?php if ($submitted_last5) { ?>
    <div class="wcf-ic-review-customer__row">
        <div class="wcf-ic-review-customer__label">
            <label><?php echo sprintf(esc_html__('Last %d Digits of Remittance Account', 'woocommerce-omnipay'), $last_digits); ?></label>
        </div>
        <div class="wcf-ic-review-customer__content">
            <p><?php echo esc_html($submitted_last5); ?> <span style="color: green;">(<?php esc_html_e('submitted', 'woocommerce-omnipay'); ?>)</span></p>
        </div>
    </div>
<?php } else { ?>
    <div class="wcf-ic-review-customer__row">
        <div class="wcf-ic-review-customer__label">
            <label><?php esc_html_e('Remittance Confirmation', 'woocommerce-omnipay'); ?></label>
        </div>
        <div class="wcf-ic-review-customer__content">
            <form method="post" action="<?php echo esc_url($submit_url); ?>" class="omnipay-remittance-form">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
                <input type="hidden" name="order_key" value="<?php echo esc_attr($order->get_order_key()); ?>">
                <?php wp_nonce_field('omnipay_remittance_nonce', 'nonce'); ?>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" id="remittance_last5" name="remittance_last5" maxlength="<?php echo esc_attr($last_digits); ?>" pattern="\d{<?php echo esc_attr($last_digits); ?>}" required placeholder="<?php echo sprintf(esc_attr__('Last %d Digits', 'woocommerce-omnipay'), $last_digits); ?>" style="width: 150px;">
                    <button type="submit" class="wcf-ic-button"><?php esc_html_e('Submit', 'woocommerce-omnipay'); ?></button>
                </div>
                <p style="margin: 8px 0 0; font-size: 12px; color: #6b7280;"><?php echo sprintf(esc_html__('Please enter the last %d digits of your remittance account after payment to help us confirm the transaction.', 'woocommerce-omnipay'), $last_digits); ?></p>
            </form>
        </div>
    </div>
<?php } ?>
