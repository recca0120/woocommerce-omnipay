<?php
/**
 * Installment Form Template
 *
 * @var array $installments Available installment periods
 * @var float $total Order total amount
 * @var array $period_rules Period rules (e.g., ['30' => ['min_amount' => 20000]])
 */
defined('ABSPATH') || exit;

// Filter installments based on period rules
$available_installments = array_filter($installments, function ($period) use ($total, $period_rules) {
    if (isset($period_rules[$period]['min_amount']) && $total < $period_rules[$period]['min_amount']) {
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
