<?php

namespace WooCommerceOmnipay\Gateways;

use WooCommerceOmnipay\Helper;

/**
 * YiPay Gateway
 *
 * 處理 YiPay 特有的邏輯，包含 ATM/CVS 付款資訊
 *
 * YiPay 流程：
 * - 信用卡 (type=1,2)：
 *   - returnURL → _complete endpoint → completePurchase()
 *   - backgroundURL → _notify endpoint → acceptNotification()
 * - ATM/CVS (type=3,4)：
 *   - returnURL → _notify endpoint → acceptNotification()（付款完成）
 *   - backgroundURL → _payment_info endpoint → get_payment_info()（取號通知）
 *
 * YiPay 付款類型：
 * - type=1: 信用卡付款
 * - type=2: 信用卡 3D 付款
 * - type=3: 超商代碼繳費
 * - type=4: ATM 虛擬帳號繳款
 */
class YiPayGateway extends OmnipayGateway
{
    /**
     * YiPay 付款類型常數
     */
    protected const TYPE_CVS = 3;

    protected const TYPE_ATM = 4;

    /**
     * 驗證回調金額是否與訂單金額相符
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  回調資料
     * @return bool
     */
    protected function validateAmount($order, array $data)
    {
        $amount = isset($data['amount']) ? (int) $data['amount'] : 0;

        return $amount === (int) $order->get_total();
    }

    /**
     * 取得 callback 參數
     *
     * YiPay 需要 returnUrl 和 notifyUrl 來驗證 checkCode 簽章
     *
     * @return array
     */
    protected function getCallbackParameters()
    {
        return [
            'returnUrl' => WC()->api_request_url($this->id.'_complete'),
            'cancelUrl' => WC()->api_request_url($this->id.'_complete'),
            'notifyUrl' => WC()->api_request_url($this->id.'_notify'),
            'paymentInfoUrl' => WC()->api_request_url($this->id.'_payment_info'),
        ];
    }

    /**
     * 處理付款資訊的核心邏輯
     *
     * YiPay 的 backgroundURL 使用背景 POST 通知（不同於使用者端導向）
     * 使用 acceptNotification() 解析回應，儲存付款資訊，回應金流
     *
     * @return null 背景通知不需 redirect
     */
    protected function handlePaymentInfo()
    {
        $gateway = $this->get_gateway();
        $notification = $gateway->acceptNotification($this->getCallbackParameters());

        $this->logger->info('getPaymentInfo: Parsed notification', [
            'transaction_id' => $notification->getTransactionId(),
            'data' => Helper::maskSensitiveData($notification->getData() ?? []),
        ]);

        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        $this->savePaymentInfo($order, $notification->getData());

        $this->logger->info('getPaymentInfo: Payment info saved', [
            'order_id' => $order->get_id(),
        ]);

        $this->sendNotificationResponse($notification);

        return null;
    }

    /**
     * 處理付款資訊通知
     *
     * 將 YiPay 的欄位名稱轉換為標準名稱
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        $normalizedData = $this->normalizePaymentInfo($data);

        parent::savePaymentInfo($order, $normalizedData);

        $type = (int) ($data['type'] ?? 0);
        $typeName = $type === self::TYPE_ATM ? 'ATM' : 'CVS';
        $this->orders->addNote($order, sprintf('YiPay 取號成功 (%s)，等待付款', $typeName));
    }

    /**
     * 將 YiPay 付款資訊欄位轉換為標準欄位
     *
     * YiPay 使用的欄位：
     * - bankCode: 銀行代碼 (ATM, type=4)
     * - account: 虛擬帳號 (ATM, type=4)
     * - expirationDate: 繳費期限 (ATM/CVS)
     * - pinCode: 繳費代碼 (CVS, type=3)
     *
     * @param  array  $data  YiPay 通知資料
     * @return array 標準化的付款資訊
     */
    protected function normalizePaymentInfo(array $data)
    {
        $normalized = [];
        $type = (int) ($data['type'] ?? 0);

        if ($type === self::TYPE_ATM) {
            // ATM: bankCode -> BankCode (銀行代碼)
            if (isset($data['bankCode'])) {
                $normalized['BankCode'] = $data['bankCode'];
            }
            // ATM: account -> vAccount (虛擬帳號)
            if (isset($data['account'])) {
                $normalized['vAccount'] = $data['account'];
            }
        }

        if ($type === self::TYPE_CVS && isset($data['pinCode'])) {
            // CVS: pinCode -> PaymentNo (繳費代碼)
            $normalized['PaymentNo'] = $data['pinCode'];
        }

        // 繳費期限
        if (isset($data['expirationDate'])) {
            $normalized['ExpireDate'] = $data['expirationDate'];
        }

        return $normalized;
    }
}
