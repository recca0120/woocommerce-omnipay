<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters;

/**
 * NewebPay Adapter
 *
 * 封裝 NewebPay 特有的邏輯
 */
class NewebPayAdapter extends DefaultGatewayAdapter
{
    /**
     * NewebPay 付款類型
     */
    private const PAYMENT_TYPE_ATM = 'VACC';

    private const PAYMENT_TYPE_CVS = 'CVS';

    private const PAYMENT_TYPE_BARCODE = 'BARCODE';

    public function __construct()
    {
        parent::__construct('NewebPay');
    }

    public function validateAmount(array $data, int $orderTotal): bool
    {
        return $this->validateAmountField($data, 'Amt', $orderTotal);
    }

    public function normalizePaymentInfo(array $data): array
    {
        $normalized = [];
        $paymentType = $data['PaymentType'] ?? '';

        // BankCode 保持不變
        if (isset($data['BankCode'])) {
            $normalized['BankCode'] = $data['BankCode'];
        }

        // 根據 PaymentType 處理不同欄位
        if ($paymentType === self::PAYMENT_TYPE_ATM) {
            // ATM: CodeNo -> vAccount (虛擬帳號)
            if (isset($data['CodeNo'])) {
                $normalized['vAccount'] = $data['CodeNo'];
            }
        } elseif ($paymentType === self::PAYMENT_TYPE_BARCODE) {
            // BARCODE: Barcode_1, Barcode_2, Barcode_3 -> Barcode1, Barcode2, Barcode3
            if (isset($data['Barcode_1'])) {
                $normalized['Barcode1'] = $data['Barcode_1'];
            }
            if (isset($data['Barcode_2'])) {
                $normalized['Barcode2'] = $data['Barcode_2'];
            }
            if (isset($data['Barcode_3'])) {
                $normalized['Barcode3'] = $data['Barcode_3'];
            }
        } elseif ($paymentType === self::PAYMENT_TYPE_CVS) {
            // CVS: CodeNo -> PaymentNo (繳費代碼)
            if (isset($data['CodeNo'])) {
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
