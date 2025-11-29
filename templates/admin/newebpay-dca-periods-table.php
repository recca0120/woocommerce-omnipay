<?php
/**
 * NewebPay DCA Periods Table Template (Admin)
 *
 * Fields: periodType, periodPoint, periodTimes, periodStartType
 *
 * @var string $fieldKey Field key for the setting
 * @var array $data Field data
 * @var array $periods Existing DCA periods
 * @var array $fieldConfigs Field configuration array with field names and attributes
 * @var array $defaultPeriod Default period data for new rows
 */
defined('ABSPATH') || exit;

$defaults = [
    'title' => '',
    'class' => '',
];

$data = wp_parse_args($data, $defaults);

// Build headers from field configs
$fieldLabels = [
    'periodType' => __('Period Type', 'woocommerce-omnipay'),
    'periodPoint' => __('Period Point', 'woocommerce-omnipay'),
    'periodTimes' => __('Period Times', 'woocommerce-omnipay'),
    'periodStartType' => __('Period Start Type', 'woocommerce-omnipay'),
];

$headers = [];
foreach ($fieldConfigs as $config) {
    $headers[] = $fieldLabels[$config['name']] ?? $config['name'];
}

// Calculate table width based on number of fields
$tableWidth = 700;

/**
 * Render a single row
 */
$renderRow = function ($index, $period) use ($fieldConfigs) {
    ?>
    <tr class="account">
        <td class="sort"></td>
        <?php foreach ($fieldConfigs as $config) { ?>
            <td>
                <input
                    type="<?php echo esc_attr($config['type']); ?>"
                    value="<?php echo esc_attr($period[$config['name']] ?? $config['default']); ?>"
                    name="<?php echo esc_attr($config['name']); ?>[<?php echo esc_attr($index); ?>]"
                    <?php foreach ($config['attributes'] as $attr => $value) { ?>
                        <?php echo esc_attr($attr); ?>="<?php echo esc_attr($value); ?>"
                    <?php } ?>
                />
            </td>
        <?php } ?>
    </tr>
    <?php
};

// Prepare periods data
$periodsToRender = ! empty($periods) && is_array($periods) ? $periods : [$defaultPeriod];
$colspan = count($headers) + 1; // +1 for sort column
?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php echo wp_kses_post($data['title']); ?></th>
    <td class="forminp" id="<?php echo esc_attr($fieldKey); ?>">
        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: <?php echo esc_attr($tableWidth); ?>px;">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <?php foreach ($headers as $header) { ?>
                        <th><?php echo esc_html($header); ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php foreach ($periodsToRender as $i => $period) { ?>
                    <?php $renderRow($i, $period); ?>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="<?php echo esc_attr($colspan); ?>">
                        <a href="#" class="add button"><?php esc_html_e('Add Period', 'woocommerce-omnipay'); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>

        <!-- JavaScript template for new rows -->
        <script type="text/template" id="<?php echo esc_attr($fieldKey); ?>-row-template">
            <?php $renderRow('{{INDEX}}', $defaultPeriod); ?>
        </script>
    </td>
</tr>
