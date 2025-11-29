<?php
/**
 * DCA Periods Table Template (Admin)
 *
 * @var string $field_key Field key for the setting
 * @var array $data Field data
 * @var array $periods Existing DCA periods
 */
defined('ABSPATH') || exit;

$defaults = [
    'title' => '',
    'class' => '',
];

$data = wp_parse_args($data, $defaults);

// Helper function to render a single row
$render_row = function ($index, $period_type, $frequency, $exec_times) {
    // Set constraints based on period type
    // Year (Y): frequency=1, execTimes=1-9
    // Month (M): frequency=1-12, execTimes=1-99
    // Day (D): frequency=1-365, execTimes=1-999
    $freq_min = 1;
    $freq_max = 365;
    $exec_min = 1;
    $exec_max = 999;

    if ($period_type === 'Y') {
        $freq_max = 1;  // Year: frequency must be 1
        $exec_max = 9;  // Year: execTimes 1-9
    } elseif ($period_type === 'M') {
        $freq_max = 12; // Month: frequency 1-12
        $exec_max = 99; // Month: execTimes 1-99
    }
    ?>
    <tr class="account">
        <td class="sort"></td>
        <td><input type="text" value="<?php echo esc_attr($period_type); ?>" name="dca_periodType[<?php echo esc_attr($index); ?>]" maxlength="1" required /></td>
        <td><input type="number" value="<?php echo esc_attr($frequency); ?>" name="dca_frequency[<?php echo esc_attr($index); ?>]" min="<?php echo esc_attr($freq_min); ?>" max="<?php echo esc_attr($freq_max); ?>" required /></td>
        <td><input type="number" value="<?php echo esc_attr($exec_times); ?>" name="dca_execTimes[<?php echo esc_attr($index); ?>]" min="<?php echo esc_attr($exec_min); ?>" max="<?php echo esc_attr($exec_max); ?>" required /></td>
    </tr>
    <?php
};

// Prepare periods data
$periods_to_render = [];
if (! empty($periods) && is_array($periods)) {
    $periods_to_render = $periods;
} else {
    // Default periods (same as ECPay official)
    $periods_to_render = [
        ['periodType' => 'Y', 'frequency' => 1, 'execTimes' => 6],
        ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12],
    ];
}
?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php echo wp_kses_post($data['title']); ?></th>
    <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: 600px;">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <th><?php esc_html_e('Period Type (Y/M/D)', 'woocommerce-omnipay'); ?></th>
                    <th><?php esc_html_e('Frequency', 'woocommerce-omnipay'); ?></th>
                    <th><?php esc_html_e('Execute Times', 'woocommerce-omnipay'); ?></th>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php foreach ($periods_to_render as $i => $period) { ?>
                    <?php $render_row($i, $period['periodType'], $period['frequency'], $period['execTimes']); ?>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">
                        <a href="#" class="add button"><?php esc_html_e('Add Period', 'woocommerce-omnipay'); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>

        <!-- JavaScript template for new rows -->
        <script type="text/template" id="<?php echo esc_attr($field_key); ?>-row-template">
            <?php $render_row('{{INDEX}}', 'M', 1, 12); ?>
        </script>
    </td>
</tr>
