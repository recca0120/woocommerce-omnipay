<?php
/**
 * Bank Account Selection Form Template
 *
 * @var array $accounts Available bank accounts
 * @var int $last_digits Number of digits for remittance confirmation
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
    <?php echo esc_html(sprintf(__('Please enter the last %d digits of your remittance account after payment to help us confirm the transaction.', 'woocommerce-omnipay'), $last_digits)); ?>
</div>
