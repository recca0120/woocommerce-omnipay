<?php
/**
 * Remittance Form Template (HTML)
 *
 * 顯示匯款帳號後5碼輸入表單
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-omnipay/order/remittance-form.php
 *
 * @var WC_Order $order 訂單
 * @var string $submitted_last5 已填寫的後5碼（如有）
 * @var string $submit_url 表單提交 URL
 */
defined('ABSPATH') || exit;
?>
<section class="omnipay-payment-info">
    <h2><?php esc_html_e('匯款確認', 'woocommerce-omnipay'); ?></h2>
    <?php if ($submitted_last5) { ?>
        <p>
            <?php echo esc_html__('已填寫匯款帳號後5碼：', 'woocommerce-omnipay'); ?>
            <strong><?php echo esc_html($submitted_last5); ?></strong>
        </p>
    <?php } else { ?>
        <p><?php esc_html_e('請於匯款後填寫您的匯款帳號後5碼，以便我們確認款項。', 'woocommerce-omnipay'); ?></p>
        <form method="post" action="<?php echo esc_url($submit_url); ?>">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>">
            <input type="hidden" name="order_key" value="<?php echo esc_attr($order->get_order_key()); ?>">
            <?php wp_nonce_field('omnipay_remittance_nonce', 'nonce'); ?>
            <p>
                <label for="remittance_last5"><?php esc_html_e('匯款帳號後5碼', 'woocommerce-omnipay'); ?></label>
                <input type="text" id="remittance_last5" name="remittance_last5" maxlength="5" pattern="\d{5}" required>
            </p>
            <button type="submit" class="button"><?php esc_html_e('確認送出', 'woocommerce-omnipay'); ?></button>
        </form>
    <?php } ?>
</section>
