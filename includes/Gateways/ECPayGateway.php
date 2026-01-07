<?php

namespace Recca0120\WooCommerce_Omnipay\Gateways;

/**
 * ECPay Gateway
 *
 * 處理 ECPay 特有的邏輯，包含 ATM/CVS/BARCODE 付款資訊
 */
class ECPayGateway extends OmnipayGateway
{
    /**
     * 準備付款資料
     *
     * ECPay 特有：將分期 30 轉換為 30N（圓夢分期）
     */
    protected function preparePaymentData($order): array
    {
        $data = parent::preparePaymentData($order);

        // 處理圓夢分期：30 -> 30N
        if (! empty($data['CreditInstallment'])) {
            $data['CreditInstallment'] = $this->convertDreamInstallment($data['CreditInstallment']);
        }

        return $data;
    }

    /**
     * 轉換圓夢分期值
     */
    private function convertDreamInstallment(string $value): string
    {
        return str_replace('30', '30N', $value);
    }

    /**
     * 處理 AcceptNotification 回應
     *
     * ECPay 的付款資訊通知與付款完成通知共用同一個 endpoint
     */
    protected function handleNotification($notification, array $data)
    {
        // ECPay 專用：檢查付款資訊通知
        if ($this->adapter->isPaymentInfoNotification($data)) {
            $order = $this->orders->findByTransactionId($notification->getTransactionId());

            if ($order) {
                $this->savePaymentInfo($order, $data);
            }

            $this->sendNotificationResponse($notification);

            return;
        }

        parent::handleNotification($notification, $data);
    }

    /**
     * 通知接收後的 hook
     *
     * 處理 ECPay 的信用卡資訊儲存與模擬付款
     */
    protected function onNotificationReceived($order, $notification, array $data): bool
    {
        $this->saveCreditCardInfo($order, $data);

        // 模擬付款處理：不改變訂單狀態
        if ($this->adapter->isSimulatedPayment($data)) {
            $this->orders->addNote($order, __('ECPay simulated payment (SimulatePaid=1)', 'woocommerce-omnipay'));
            $this->sendNotificationResponse($notification);

            return false;
        }

        return true;
    }

    /**
     * 儲存信用卡資訊
     */
    protected function saveCreditCardInfo($order, array $data): void
    {
        $cardInfo = $this->adapter->getCreditCardInfo($data);

        if (empty($cardInfo)) {
            return;
        }

        foreach ($cardInfo as $key => $value) {
            $order->update_meta_data('_omnipay_'.$key, $value);
        }

        $order->save();
    }
}
