<?php
/**
 * Bank Accounts Table Template (Admin)
 *
 * Fields: bank_code, account_number
 *
 * @var array $value Field configuration from WooCommerce settings
 * @var array $accounts Existing bank accounts
 * @var string $fieldName Full field name for form submission
 * @var string $fieldId Sanitized field ID for JavaScript
 */
defined('ABSPATH') || exit;

$fieldConfigs = [
    [
        'name' => 'bank_code',
        'label' => __('Bank Code', 'woocommerce-omnipay'),
        'type' => 'text',
        'style' => 'width: 80px;',
        'required' => true,
    ],
    [
        'name' => 'account_number',
        'label' => __('Account Number', 'woocommerce-omnipay'),
        'type' => 'text',
        'style' => '',
        'required' => true,
    ],
    [
        'name' => 'secret',
        'label' => __('Secret', 'woocommerce-omnipay'),
        'type' => 'text',
        'style' => 'width: 80px;',
        'required' => false,
    ],
];

$colspan = count($fieldConfigs) + 1; // +1 for sort column
$tableWidth = 600;

/**
 * Render a single row
 */
$renderRow = function ($index, $account) use ($fieldConfigs, $fieldName) {
    ?>
    <tr class="account">
        <td class="sort"></td>
        <?php foreach ($fieldConfigs as $config) { ?>
            <td>
                <input
                    type="<?php echo esc_attr($config['type']); ?>"
                    name="<?php echo esc_attr($fieldName); ?>[<?php echo esc_attr($index); ?>][<?php echo esc_attr($config['name']); ?>]"
                    value="<?php echo esc_attr($account[$config['name']] ?? ''); ?>"
                    <?php if (! empty($config['style'])) { ?>
                        style="<?php echo esc_attr($config['style']); ?>"
                    <?php } ?>
                    <?php if (! empty($config['required'])) { ?>
                        required
                    <?php } ?>
                />
            </td>
        <?php } ?>
    </tr>
    <?php
};

$defaultAccount = [
    'bank_code' => '',
    'account_number' => '',
    'secret' => '',
];
?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php echo wp_kses_post($value['title']); ?></th>
    <td class="forminp" id="<?php echo esc_attr($fieldId); ?>">
        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: <?php echo esc_attr($tableWidth); ?>px;">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <?php foreach ($fieldConfigs as $config) { ?>
                        <th><?php echo esc_html($config['label']); ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php foreach ($accounts as $index => $account) { ?>
                    <?php $renderRow($index, $account); ?>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="<?php echo esc_attr($colspan); ?>">
                        <a href="#" class="add button"><?php esc_html_e('Add Account', 'woocommerce-omnipay'); ?></a>
                        <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>

        <!-- JavaScript template for new rows -->
        <script type="text/template" id="<?php echo esc_attr($fieldId); ?>-row-template">
            <?php $renderRow('{{INDEX}}', $defaultAccount); ?>
        </script>
    </td>
</tr>
