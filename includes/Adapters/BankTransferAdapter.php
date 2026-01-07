<?php

namespace Recca0120\WooCommerce_Omnipay\Adapters;

/**
 * BankTransfer Gateway Adapter
 *
 * 擴展 DefaultGatewayAdapter，加入多帳號支援
 */
class BankTransferAdapter extends DefaultGatewayAdapter
{
    public function __construct()
    {
        parent::__construct('BankTransfer');
    }

    public function getGatewayName(): string
    {
        return 'BankTransfer';
    }

    /**
     * 取得預設參數
     *
     * 包含 Omnipay 需要的參數（由 BankTransferGateway 從 bank_accounts 選擇後填入）
     */
    public function getDefaultParameters(): array
    {
        return [
            'bank_accounts' => [],
            'selection_mode' => 'random',
            // Omnipay 需要這些參數，但不在設定頁面顯示（由 bank_accounts 選擇後填入）
            'bank_code' => '',
            'account_number' => '',
            'secret' => '',
        ];
    }

    /**
     * 取得設定頁面要顯示的欄位（排除 Omnipay 內部使用的欄位）
     */
    public function getSettingsFields(): array
    {
        return [
            'bank_accounts' => [],
            'selection_mode' => 'random',
        ];
    }
}
