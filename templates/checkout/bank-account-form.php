<?php
/**
 * Bank Account Selection Form Template
 *
 * @var array $accounts Available bank accounts
 */
defined('ABSPATH') || exit;

if (empty($accounts)) {
    return;
}

$isSingleAccount = count($accounts) === 1;
?>
<p class="form-row form-row-wide">
    <label><?php echo esc_html($isSingleAccount ? __('Payment Account', 'woocommerce-omnipay') : __('Select Bank Account', 'woocommerce-omnipay')); ?></label>
    <select id="bank_account_index" name="bank_account_index" class="select">
    <?php foreach ($accounts as $index => $account) { ?>
        <?php
        $bankCode = $account['bank_code'] ?? '';
        $accountNumber = $account['account_number'] ?? '';

        // 格式: 銀行代碼-帳號 (例: 822-xxxxxxxx)
        $label = $bankCode;
        if ($accountNumber) {
            $label .= '-'.$accountNumber;
        }
        ?>
        <option value="<?php echo esc_attr($index); ?>"><?php echo esc_html($label); ?></option>
    <?php } ?>
    </select>
</p>
<div class="woocommerce-info">
    <?php esc_html_e('After completing the transfer, please enter the last 5 digits of your remittance account to help us verify the payment.', 'woocommerce-omnipay'); ?>
</div>
