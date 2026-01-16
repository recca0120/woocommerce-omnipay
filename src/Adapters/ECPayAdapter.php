<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters;

/**
 * ECPay Adapter
 *
 * 封裝 ECPay 特有的邏輯
 */
class ECPayAdapter extends DefaultGatewayAdapter
{
    /**
     * ECPay 取號成功的 RtnCode
     */
    private const RTNCODE_ATM_SUCCESS = '2';

    private const RTNCODE_CVS_BARCODE_SUCCESS = '10100073';

    public function __construct()
    {
        parent::__construct('ECPay');
    }

    public function validateAmount(array $data, int $orderTotal): bool
    {
        return $this->validateAmountField($data, 'TradeAmt', $orderTotal);
    }

    public function normalizePaymentInfo(array $data): array
    {
        // ECPay 已經使用標準欄位名稱，直接回傳
        return array_filter([
            'BankCode' => $data['BankCode'] ?? null,
            'vAccount' => $data['vAccount'] ?? null,
            'PaymentNo' => $data['PaymentNo'] ?? null,
            'ExpireDate' => $data['ExpireDate'] ?? null,
            'Barcode1' => $data['Barcode1'] ?? null,
            'Barcode2' => $data['Barcode2'] ?? null,
            'Barcode3' => $data['Barcode3'] ?? null,
        ], function ($value) {
            return $value !== null;
        });
    }

    public function getPaymentInfoUrlSuffix(): string
    {
        // ECPay 的 PaymentInfoURL 與 notifyUrl 共用 _notify endpoint
        return '_notify';
    }

    /**
     * 判斷是否為付款資訊通知
     *
     * ECPay 的取號結果通知：RtnCode = 2 (ATM) 或 10100073 (CVS/BARCODE)
     */
    public function isPaymentInfoNotification(array $data): bool
    {
        $rtnCode = $data['RtnCode'] ?? '';

        return in_array($rtnCode, [self::RTNCODE_ATM_SUCCESS, self::RTNCODE_CVS_BARCODE_SUCCESS], true);
    }

    /**
     * 檢查是否為模擬付款
     */
    public function isSimulatedPayment(array $data): bool
    {
        return isset($data['SimulatePaid']) && $data['SimulatePaid'] === '1';
    }

    /**
     * 取得信用卡資訊
     */
    public function getCreditCardInfo(array $data): array
    {
        return array_filter([
            'card6no' => $data['card6no'] ?? null,
            'card4no' => $data['card4no'] ?? null,
        ], function ($value) {
            return $value !== null;
        });
    }

    public function getPaymentInfoNote(array $data): ?string
    {
        $paymentType = $data['PaymentType'] ?? '';

        return sprintf('ECPay 取號成功 (%s)，等待付款', $paymentType);
    }
}
