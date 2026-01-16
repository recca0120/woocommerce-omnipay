<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways;

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
 */
class YiPayGateway extends OmnipayGateway
{
    /**
     * 取得 callback 參數
     *
     * YiPay 需要這些 URL 來驗證 checkCode 簽章
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
        $notification = $this->getAdapter()->acceptNotification($this->getCallbackParameters());
        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        $this->savePaymentInfo($order, $notification->getData());
        $this->sendNotificationResponse($notification);

        return null;
    }
}
