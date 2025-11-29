<?php
/**
 * Installment Form Template
 *
 * @var array $installments Available installment periods
 * @var float $total Order total amount
 * @var bool $has_30n_validation Whether to apply 30N validation (ECPay only)
 */
defined('ABSPATH') || exit;
?>
<p><?php echo esc_html(_x('Number of periods', 'Checkout info', 'woocommerce-omnipay')); ?>
<select name="omnipay_installment">
<?php foreach ($installments as $period) { ?>
    <?php if ($period === '30N' && $has_30n_validation) { ?>
        <?php if ($total >= 20000) { ?>
            <option value="<?php echo esc_attr($period); ?>"><?php echo wp_kses_post($period); ?></option>
        <?php } ?>
    <?php } else { ?>
        <option value="<?php echo esc_attr($period); ?>"><?php echo wp_kses_post($period); ?></option>
    <?php } ?>
<?php } ?>
</select>
</p>
