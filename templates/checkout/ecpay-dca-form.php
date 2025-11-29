<?php
/**
 * ECPay DCA (定期定額) Form Template
 *
 * Fields: periodType, frequency, execTimes
 * Display format: 金額 / 每 {frequency} {periodType}，最多 {execTimes} 次
 *
 * @var array $periods Available DCA periods
 * @var float $total Order total amount
 * @var array $periodFields Period field names (e.g., ['periodType', 'frequency', 'execTimes'])
 * @var string $warningMessage Warning message to display
 */
defined('ABSPATH') || exit;

// Period type labels
$periodTypeLabels = [
    'Y' => __('year', 'woocommerce-omnipay'),
    'M' => __('month', 'woocommerce-omnipay'),
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

    // ECPay format: frequency and execTimes
    $periodType = $period['periodType'] ?? 'M';
    $frequency = $period['frequency'] ?? 1;
    $execTimes = $period['execTimes'] ?? 0;

    // Build period description based on periodType
    if ($periodType === 'Y') {
        // Yearly: 每 1 年扣款
        $periodDesc = sprintf(__('charge every %s year(s)', 'woocommerce-omnipay'), $frequency);
    } elseif ($periodType === 'M') {
        // Monthly: 每 1 個月扣款
        $periodDesc = sprintf(__('charge every %s month(s)', 'woocommerce-omnipay'), $frequency);
    } else {
        // Daily: 每 2 天扣款
        $periodDesc = sprintf(__('charge every %s day(s)', 'woocommerce-omnipay'), $frequency);
    }

    // Build label: 金額 / 週期描述，共 次數 次
    $label = sprintf(
        __('%s / %s, %s times total', 'woocommerce-omnipay'),
        wc_price($total),
        esc_html($periodDesc),
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
