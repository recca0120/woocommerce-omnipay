<?php
/**
 * Bank Account Info Template (Single Account)
 *
 * @var array $account Bank account data
 */
defined('ABSPATH') || exit;

$bankCode = $account['bank_code'] ?? '';
$accountNumber = $account['account_number'] ?? '';

if (empty($bankCode) && empty($accountNumber)) {
    return;
}

// 格式: 銀行代碼-帳號 (例: 822-xxxxxxxx)
$label = $bankCode;
if ($accountNumber) {
    $label .= '-'.$accountNumber;
}
?>
<p class="form-row form-row-wide omnipay-bank-account-info">
    <strong><?php esc_html_e('Payment Account', 'woocommerce-omnipay'); ?>:</strong>
    <?php echo esc_html($label); ?>
</p>
