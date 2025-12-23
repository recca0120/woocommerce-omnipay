<?php

namespace WooCommerceOmnipay\Adapters;

/**
 * NewebPay Adapter
 *
 * 封裝 NewebPay 特有的邏輯
 */
class NewebPayAdapter implements GatewayAdapterInterface
{
    use GatewayOperationsTrait;

    /**
     * NewebPay 付款類型
     */
    private const PAYMENT_TYPE_ATM = 'VACC';

    private const PAYMENT_TYPE_CVS = 'CVS';

    private const PAYMENT_TYPE_BARCODE = 'BARCODE';

    public function getGatewayName(): string
    {
        return 'NewebPay';
    }

    public function validateAmount(array $data, int $orderTotal): bool
    {
        $amt = isset($data['Amt']) ? (int) $data['Amt'] : 0;

        return $amt === $orderTotal;
    }

    public function normalizePaymentInfo(array $data): array
    {
        $normalized = [];
        $paymentType = $data['PaymentType'] ?? '';

        // BankCode 保持不變
        if (isset($data['BankCode'])) {
            $normalized['BankCode'] = $data['BankCode'];
        }

        // CodeNo 根據 PaymentType 轉換
        if (isset($data['CodeNo'])) {
            if ($paymentType === self::PAYMENT_TYPE_ATM) {
                // ATM: CodeNo -> vAccount (虛擬帳號)
                $normalized['vAccount'] = $data['CodeNo'];
            } else {
                // CVS/BARCODE: CodeNo -> PaymentNo (繳費代碼)
                $normalized['PaymentNo'] = $data['CodeNo'];
            }
        }

        // 合併 ExpireDate 和 ExpireTime
        if (isset($data['ExpireDate'])) {
            $expireDate = $data['ExpireDate'];
            if (isset($data['ExpireTime'])) {
                $expireDate .= ' '.$data['ExpireTime'];
            }
            $normalized['ExpireDate'] = $expireDate;
        }

        return $normalized;
    }

    public function getPaymentInfoNote(array $data): ?string
    {
        $paymentType = $data['PaymentType'] ?? '';

        return sprintf('藍新金流取號成功 (%s)，等待付款', $paymentType);
    }
}
