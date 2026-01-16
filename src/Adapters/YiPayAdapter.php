<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters;

/**
 * YiPay Adapter
 *
 * 封裝 YiPay 特有的邏輯
 */
class YiPayAdapter extends DefaultGatewayAdapter
{
    /**
     * YiPay 付款類型
     */
    private const TYPE_CVS = 3;

    private const TYPE_ATM = 4;

    public function __construct()
    {
        parent::__construct('YiPay');
    }

    public function validateAmount(array $data, int $orderTotal): bool
    {
        return $this->validateAmountField($data, 'amount', $orderTotal);
    }

    public function normalizePaymentInfo(array $data): array
    {
        $normalized = [];
        $type = (int) ($data['type'] ?? 0);

        if ($type === self::TYPE_ATM) {
            // ATM: bankCode -> BankCode
            if (isset($data['bankCode'])) {
                $normalized['BankCode'] = $data['bankCode'];
            }
            // ATM: account -> vAccount
            if (isset($data['account'])) {
                $normalized['vAccount'] = $data['account'];
            }
        }

        if ($type === self::TYPE_CVS && isset($data['pinCode'])) {
            // CVS: pinCode -> PaymentNo
            $normalized['PaymentNo'] = $data['pinCode'];
        }

        // 繳費期限
        if (isset($data['expirationDate'])) {
            $normalized['ExpireDate'] = $data['expirationDate'];
        }

        return $normalized;
    }

    /**
     * 取得付款類型名稱
     */
    public function getPaymentTypeName(array $data): string
    {
        $type = (int) ($data['type'] ?? 0);

        return $type === self::TYPE_ATM ? 'ATM' : 'CVS';
    }

    public function getPaymentInfoNote(array $data): ?string
    {
        return sprintf('YiPay 取號成功 (%s)，等待付款', $this->getPaymentTypeName($data));
    }
}
