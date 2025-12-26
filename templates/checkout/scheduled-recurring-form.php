<?php
/**
 * NewebPay DCA (定期定額) Form Template
 *
 * Fields: periodType, periodPoint, periodTimes, periodStartType
 * Display format: 金額 / 每 {periodPoint解析} {periodType}，共 {periodTimes} 次
 *
 * @var array $periods Available DCA periods
 * @var float $total Order total amount
 * @var array $periodFields Period field names (e.g., ['periodType', 'periodPoint', 'periodTimes', 'periodStartType'])
 * @var string $warningMessage Warning message to display
 */
defined('ABSPATH') || exit;

// Period type labels
$periodTypeLabels = [
    'Y' => __('year', 'woocommerce-omnipay'),
    'M' => __('month', 'woocommerce-omnipay'),
    'W' => __('week', 'woocommerce-omnipay'),
    'D' => __('day', 'woocommerce-omnipay'),
];

// Weekday labels
$weekdayLabels = [
    '1' => __('Monday', 'woocommerce-omnipay'),
    '2' => __('Tuesday', 'woocommerce-omnipay'),
    '3' => __('Wednesday', 'woocommerce-omnipay'),
    '4' => __('Thursday', 'woocommerce-omnipay'),
    '5' => __('Friday', 'woocommerce-omnipay'),
    '6' => __('Saturday', 'woocommerce-omnipay'),
    '7' => __('Sunday', 'woocommerce-omnipay'),
];

/**
 * Parse periodPoint for display
 */
$parsePeriodPoint = function ($periodType, $periodPoint) use ($weekdayLabels) {
    if ($periodType === 'Y') {
        // MMDD format -> "3/15"
        if (preg_match('/^(\d{2})(\d{2})$/', $periodPoint, $matches)) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];

            return sprintf(__('%d/%d', 'woocommerce-omnipay'), $month, $day);
        }

        return $periodPoint;
    }

    if ($periodType === 'M') {
        // Day of month -> "15"
        return sprintf(__('%d', 'woocommerce-omnipay'), (int) $periodPoint);
    }

    if ($periodType === 'W') {
        // Weekday -> "Monday"
        return $weekdayLabels[$periodPoint] ?? $periodPoint;
    }

    if ($periodType === 'D') {
        // Day interval -> "every 2 days"
        return sprintf(__('every %d days', 'woocommerce-omnipay'), (int) $periodPoint);
    }

    return $periodPoint;
};
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

    // NewebPay format
    $periodType = $period['periodType'] ?? '';
    $periodPoint = $period['periodPoint'] ?? '';
    $periodTimes = $period['periodTimes'] ?? 0;
    $periodStartType = $period['periodStartType'] ?? '2';

    // Parse periodPoint for display
    $pointDisplay = $parsePeriodPoint($periodType, $periodPoint);

    // Build period description
    if ($periodType === 'D') {
        // Daily: 每 2 天扣款
        $periodDesc = $pointDisplay;
    } elseif ($periodType === 'Y') {
        // Yearly: 每年 3/15 扣款
        $periodDesc = sprintf(__('charge on %s every year', 'woocommerce-omnipay'), $pointDisplay);
    } elseif ($periodType === 'M') {
        // Monthly: 每月 15 日扣款
        $periodDesc = sprintf(__('charge on day %s every month', 'woocommerce-omnipay'), $pointDisplay);
    } else {
        // Weekly: 每週一扣款
        $periodDesc = sprintf(__('charge every %s', 'woocommerce-omnipay'), $pointDisplay);
    }

    // Final label: 金額 / 週期描述，共 次數 次
    $label = sprintf(
        __('%s / %s, %s times total', 'woocommerce-omnipay'),
        wc_price($total),
        esc_html($periodDesc),
        esc_html($periodTimes)
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
