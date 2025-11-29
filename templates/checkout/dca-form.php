<?php
/**
 * DCA (定期定額) Form Template
 *
 * @var array $periods Available DCA periods
 * @var float $total Order total amount
 * @var string $warning_message Warning message to display
 */
defined('ABSPATH') || exit;

$period_type_labels = [
    'Y' => ' '.__('year', 'woocommerce-omnipay'),
    'M' => ' '.__('month', 'woocommerce-omnipay'),
    'D' => ' '.__('day', 'woocommerce-omnipay'),
];
?>
<select id="omnipay_dca_period" name="omnipay_dca_period">
<?php foreach ($periods as $period) { ?>
    <?php
    $value = $period['periodType'].'_'.$period['frequency'].'_'.$period['execTimes'];
    $label = sprintf(
        __('%s / %s %s, up to a maximum of %s', 'woocommerce-omnipay'),
        wc_price($total),
        $period['frequency'],
        $period_type_labels[$period['periodType']] ?? $period['periodType'],
        $period['execTimes']
    );
    ?>
    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
<?php } ?>
</select>
<div id="omnipay_dca_show"></div>
<hr style="margin: 12px 0px;background-color: #eeeeee;">
<p style="font-size: 0.8em;color: #c9302c;">
    <?php echo wp_kses_post($warning_message); ?>
</p>
