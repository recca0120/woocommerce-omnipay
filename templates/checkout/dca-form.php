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

    // Normalize period for unified display
    $frequency = $period['frequency'] ?? 1; // NewebPay doesn't have frequency, default to 1
    $execTimes = $period['execTimes'] ?? $period['periodTimes'] ?? 0; // Use periodTimes for NewebPay

    // Build label - unified format
    $label = sprintf(
        __('%s / %s %s, up to a maximum of %s times', 'woocommerce-omnipay'),
        wc_price($total),
        esc_html($frequency),
        esc_html($periodTypeLabels[$period['periodType']] ?? $period['periodType']),
        esc_html($execTimes)
    );
    ?>
    <option value="<?php echo esc_attr($value); ?>"><?php echo wp_kses_post($label); ?></option>
<?php } ?>
</select>
<div id="omnipay_period_info"></div>
<hr style="margin: 12px 0px;background-color: #eeeeee;">
<p style="font-size: 0.8em;color: #c9302c;">
    <?php echo wp_kses_post($warningMessage); ?>
</p>
