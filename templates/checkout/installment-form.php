<?php
/**
 * Installment Form Template
 *
 * @var array $installments Available installment periods
 * @var float $total Order total amount
 * @var bool $validate_30_min_amount Whether 30 period requires minimum amount validation (ECPay Dream Installment requires >= 20000)
 */
defined('ABSPATH') || exit;

// Filter installments based on amount validation
$available_installments = array_filter($installments, function ($period) use ($total, $validate_30_min_amount) {
    // ECPay's 30 period (Dream Installment) requires amount >= 20000
    if ($period === '30' && $validate_30_min_amount && $total < 20000) {
        return false;
    }

    return true;
});
?>
<p><?php echo esc_html(_x('Number of periods', 'Checkout info', 'woocommerce-omnipay')); ?>
<select name="omnipay_installment">
<?php foreach ($available_installments as $period) { ?>
    <option value="<?php echo esc_attr($period); ?>"><?php echo wp_kses_post($period); ?></option>
<?php } ?>
</select>
</p>
