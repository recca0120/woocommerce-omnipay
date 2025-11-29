<?php
/**
 * DCA (定期定額) Form Template - Unified for all gateways
 *
 * @var array $periods Available DCA periods
 * @var float $total Order total amount
 * @var array $periodFields Period field names (e.g., ['periodType', 'frequency', 'execTimes'] for ECPay)
 * @var string $warningMessage Warning message to display
 */
defined('ABSPATH') || exit;

// Period type labels - defined in template
$periodTypeLabels = [
    'Y' => __('year', 'woocommerce-omnipay'),
    'M' => __('month', 'woocommerce-omnipay'),
    'W' => __('week', 'woocommerce-omnipay'),
    'D' => __('day', 'woocommerce-omnipay'),
];
?>
<select id="omnipay_period" name="omnipay_period">
<?php foreach ($periods as $period) { ?>
    <?php
    // Build value from period fields
    $values = [];
    foreach ($periodFields as $field) {
        $values[] = $period[$field] ?? '';
    }
    $value = implode('_', $values);

    // Build label based on gateway type
    if (isset($period['frequency']) && isset($period['execTimes'])) {
        // ECPay format: frequency + execTimes
        $label = sprintf(
            __('%s / %s %s, up to a maximum of %s times', 'woocommerce-omnipay'),
            wc_price($total),
            esc_html($period['frequency']),
            esc_html($periodTypeLabels[$period['periodType']] ?? $period['periodType']),
            esc_html($period['execTimes'])
        );
    } elseif (isset($period['periodTimes'])) {
        // NewebPay format: periodTimes only
        $label = sprintf(
            __('%s / per %s, total %s times', 'woocommerce-omnipay'),
            wc_price($total),
            esc_html($periodTypeLabels[$period['periodType']] ?? $period['periodType']),
            esc_html($period['periodTimes'])
        );
    } else {
        // Fallback
        $label = wc_price($total);
    }
    ?>
    <option value="<?php echo esc_attr($value); ?>"><?php echo wp_kses_post($label); ?></option>
<?php } ?>
</select>
<div id="omnipay_period_info"></div>
<hr style="margin: 12px 0px;background-color: #eeeeee;">
<p style="font-size: 0.8em;color: #c9302c;">
    <?php echo wp_kses_post($warningMessage); ?>
</p>
