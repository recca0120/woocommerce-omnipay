<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Settings;

use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Contracts\GatewayAdapter;

/**
 * BankTransfer Settings Section
 *
 * Provides settings for BankTransfer gateway with special handling
 * for bank_accounts_table and selection_mode fields.
 */
class BankTransferSettingsSection extends GatewaySettingsSection
{
    public function __construct(GatewayAdapter $adapter)
    {
        parent::__construct($adapter);
    }

    public function getSectionLabel(): string
    {
        return __('Bank Transfer', 'woocommerce-omnipay');
    }

    public function registerFieldHooks(): void
    {
        add_action('woocommerce_admin_field_bank_accounts_table', [$this, 'outputBankAccountsTable']);
        add_action('woocommerce_update_option_bank_accounts_table', [$this, 'saveBankAccountsTable']);
    }

    // =========================================================================
    // Bank Accounts Table Field Type Handlers
    // =========================================================================

    public function outputBankAccountsTable(array $field): void
    {
        $accounts = $this->getAccounts($field);

        echo woocommerce_omnipay_get_template('admin/bank-accounts-table.php', [
            'value' => $field,
            'accounts' => $accounts,
            'fieldName' => esc_attr($field['id']),
            'fieldId' => sanitize_title($field['id']),
        ]);
    }

    public function saveBankAccountsTable(array $field): void
    {
        $parsed = $this->parseOptionId($field['id']);

        if ($parsed === null) {
            return;
        }

        [$optionName, $fieldKey] = $parsed;

        $accounts = $this->sanitizeAccounts($optionName, $fieldKey);

        $existingSettings = get_option($optionName, []);
        $existingSettings[$fieldKey] = $accounts;
        update_option($optionName, $existingSettings);
    }

    protected function createField(string $key, $defaultValue): array
    {
        if ($key === 'selection_mode') {
            return $this->createSelectionModeField($defaultValue);
        }

        if ($key === 'bank_accounts') {
            return $this->createBankAccountsField($defaultValue);
        }

        return parent::createField($key, $defaultValue);
    }

    private function createSelectionModeField($defaultValue): array
    {
        $savedSettings = get_option($this->optionKey, []);
        $savedValue = $savedSettings['selection_mode'] ?? null;

        return [
            'title' => __('Selection Mode', 'woocommerce-omnipay'),
            'id' => $this->optionKey.'[selection_mode]',
            'type' => 'select',
            'default' => $defaultValue,
            'value' => $savedValue ?? $defaultValue,
            'desc' => __('How to select bank account when multiple accounts are configured', 'woocommerce-omnipay'),
            'desc_tip' => true,
            'options' => [
                'random' => __('Random', 'woocommerce-omnipay'),
                'round_robin' => __('Round Robin', 'woocommerce-omnipay'),
                'user_choice' => __('User Choice', 'woocommerce-omnipay'),
            ],
        ];
    }

    private function createBankAccountsField($defaultValue): array
    {
        return [
            'title' => __('Bank Accounts', 'woocommerce-omnipay'),
            'id' => $this->optionKey.'[bank_accounts]',
            'type' => 'bank_accounts_table',
            'default' => is_array($defaultValue) ? $defaultValue : [],
            'desc_tip' => true,
        ];
    }

    private function getAccounts(array $field): array
    {
        $parsed = $this->parseOptionId($field['id']);

        if ($parsed === null) {
            return [];
        }

        [$optionName, $fieldKey] = $parsed;

        $settings = get_option($optionName, []);
        $accounts = $settings[$fieldKey] ?? [];

        // Handle JSON string format
        if (is_string($accounts) && ! empty($accounts)) {
            $accounts = json_decode($accounts, true) ?: [];
        }

        return is_array($accounts) ? $accounts : [];
    }

    private function parseOptionId(string $optionId): ?array
    {
        if (! preg_match('/^(.+)\[(.+)\]$/', $optionId, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    private function sanitizeAccounts(string $optionName, string $fieldKey): array
    {
        $accounts = [];

        if (! isset($_POST[$optionName][$fieldKey]) || ! is_array($_POST[$optionName][$fieldKey])) {
            return $accounts;
        }

        foreach ($_POST[$optionName][$fieldKey] as $account) {
            if (empty($account['bank_code']) && empty($account['account_number'])) {
                continue;
            }

            $accounts[] = [
                'bank_code' => sanitize_text_field($account['bank_code'] ?? ''),
                'account_number' => sanitize_text_field($account['account_number'] ?? ''),
                'secret' => sanitize_text_field($account['secret'] ?? ''),
            ];
        }

        return $accounts;
    }
}
