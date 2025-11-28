<?php

namespace WooCommerceOmnipay\Gateways;

use WooCommerceOmnipay\Helper;

/**
 * NewebPay Gateway
 *
 * 處理 NewebPay 特有的邏輯，包含 ATM/CVS 付款資訊
 *
 * NewebPay 流程：
 * - CustomerURL (paymentInfoUrl) → _payment_info endpoint → getPaymentInfo()
 * - NotifyURL (notifyUrl) → _notify endpoint → acceptNotification()
 * - ReturnURL (returnUrl) → _complete endpoint → completePurchase()
 */
class NewebPayGateway extends OmnipayGateway
{
    /**
     * NewebPay 取號的 PaymentType
     */
    protected const PAYMENT_TYPE_ATM = 'VACC';

    protected const PAYMENT_TYPE_CVS = 'CVS';

    protected const PAYMENT_TYPE_BARCODE = 'BARCODE';

    /**
     * 驗證回調金額是否與訂單金額相符
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  回調資料
     * @return bool
     */
    protected function validateAmount($order, array $data)
    {
        $amt = isset($data['Amt']) ? (int) $data['Amt'] : 0;

        return $amt === (int) $order->get_total();
    }

    /**
     * 處理付款資訊的核心邏輯
     *
     * NewebPay 的 CustomerURL 是使用者端導向（不同於背景 POST），需要：
     * 1. 使用 getPaymentInfo() 解析回應（不是 acceptNotification）
     * 2. 儲存付款資訊
     * 3. 回傳感謝頁 URL 讓使用者重導向
     *
     * @return string redirect URL
     */
    protected function handlePaymentInfo()
    {
        $gateway = $this->get_gateway();
        $response = $gateway->getPaymentInfo()->send();

        $this->logger->info('getPaymentInfo: Gateway response', [
            'transaction_id' => $response->getTransactionId(),
            'data' => Helper::maskSensitiveData($response->getData() ?? []),
        ]);

        $order = $this->orders->findByTransactionIdOrFail($response->getTransactionId());

        $this->savePaymentInfo($order, $response->getData());

        $this->logger->info('getPaymentInfo: Payment info saved', [
            'order_id' => $order->get_id(),
        ]);

        return $this->get_return_url($order);
    }

    /**
     * 處理付款資訊通知
     *
     * 將 NewebPay 的欄位名稱轉換為標準名稱
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        // 轉換 NewebPay 的欄位名稱為標準名稱
        $normalizedData = $this->normalizePaymentInfo($data);

        parent::savePaymentInfo($order, $normalizedData);

        $paymentType = $data['PaymentType'] ?? '';
        $this->orders->addNote($order, sprintf('藍新金流取號成功 (%s)，等待付款', $paymentType));
    }

    /**
     * 將 NewebPay 付款資訊欄位轉換為標準欄位
     *
     * NewebPay 使用的欄位：
     * - BankCode: 銀行代碼 (ATM)
     * - CodeNo: 虛擬帳號 (ATM) 或繳費代碼 (CVS/BARCODE)
     * - ExpireDate: 繳費期限
     * - ExpireTime: 繳費期限時間
     *
     * @param  array  $data  NewebPay 通知資料
     * @return array 標準化的付款資訊
     */
    protected function normalizePaymentInfo(array $data)
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
}
