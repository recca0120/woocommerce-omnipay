<?php

namespace WooCommerceOmnipay\Gateways;

use Omnipay\Common\Message\NotificationInterface;

/**
 * ECPay Gateway
 *
 * 處理 ECPay 特有的邏輯，包含 ATM/CVS/BARCODE 付款資訊
 */
class ECPayGateway extends OmnipayGateway
{
    /**
     * ECPay 取號成功的 RtnCode
     */
    protected const RTNCODE_ATM_SUCCESS = '2';

    protected const RTNCODE_CVS_BARCODE_SUCCESS = '10100073';

    /**
     * 取得付款資訊通知 URL
     *
     * ECPay 的 PaymentInfoURL 與 notifyUrl 指向同一個 _notify endpoint
     * 透過 RtnCode 區分是取號通知還是付款完成通知
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function getPaymentInfoUrl($order)
    {
        return WC()->api_request_url($this->id.'_notify');
    }

    /**
     * 處理 AcceptNotification 回應的核心邏輯
     *
     * ECPay 的 PaymentInfoURL 與 notifyUrl 指向同一個 _notify endpoint
     * 需要透過 RtnCode 區分是取號通知還是付款完成通知
     *
     * @param  NotificationInterface  $notification
     */
    protected function handleNotification($notification)
    {
        $data = $notification->getData();

        // 檢查是否為取號結果通知
        if ($this->isPaymentInfoNotification($data)) {
            $order = $this->orders->findByTransactionId($notification->getTransactionId());

            if ($order) {
                $this->savePaymentInfo($order, $data);
            }

            $this->sendNotificationResponse($notification);

            return;
        }

        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        // 金額驗證
        if (! $this->validateAmount($order, $data)) {
            $this->sendCallbackResponse(false, 'Amount mismatch');

            return;
        }

        // 儲存信用卡資訊
        $this->saveCreditCardInfo($order, $data);

        // 模擬付款處理
        if ($this->isSimulatedPayment($data)) {
            $this->orders->addNote($order, __('ECPay simulated payment (SimulatePaid=1)', 'woocommerce-omnipay'));
            $this->sendNotificationResponse($notification);

            return;
        }

        // 檢查訂單是否需要處理
        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        $status = $notification->getTransactionStatus();

        if ($status !== NotificationInterface::STATUS_COMPLETED) {
            $errorMessage = $notification->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, 'callback', false);
            $this->sendCallbackResponse(false, $errorMessage);

            return;
        }

        $this->completeOrderPayment($order, $notification->getTransactionReference(), 'callback');
        $this->sendNotificationResponse($notification);
    }

    /**
     * 驗證金額是否正確
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     * @return bool
     */
    protected function validateAmount($order, array $data)
    {
        $tradeAmt = isset($data['TradeAmt']) ? (int) $data['TradeAmt'] : 0;
        $orderTotal = (int) $order->get_total();

        return $tradeAmt === $orderTotal;
    }

    /**
     * 檢查是否為模擬付款
     *
     * @param  array  $data  通知資料
     * @return bool
     */
    protected function isSimulatedPayment(array $data)
    {
        return isset($data['SimulatePaid']) && $data['SimulatePaid'] === '1';
    }

    /**
     * 儲存信用卡資訊
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function saveCreditCardInfo($order, array $data)
    {
        $hasCardInfo = false;

        if (! empty($data['card6no'])) {
            $order->update_meta_data('_omnipay_card6no', $data['card6no']);
            $hasCardInfo = true;
        }

        if (! empty($data['card4no'])) {
            $order->update_meta_data('_omnipay_card4no', $data['card4no']);
            $hasCardInfo = true;
        }

        if ($hasCardInfo) {
            $order->save();
        }
    }

    /**
     * 判斷是否為付款資訊通知
     *
     * ECPay 的取號結果通知：RtnCode = 2 (ATM) 或 10100073 (CVS/BARCODE)
     *
     * @param  array  $data  通知資料
     * @return bool
     */
    protected function isPaymentInfoNotification(array $data)
    {
        $rtnCode = $data['RtnCode'] ?? '';

        return in_array($rtnCode, [self::RTNCODE_ATM_SUCCESS, self::RTNCODE_CVS_BARCODE_SUCCESS], true);
    }

    /**
     * 處理付款資訊通知
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        parent::savePaymentInfo($order, $data);

        $paymentType = $data['PaymentType'] ?? '';
        $this->orders->addNote($order, sprintf('ECPay 取號成功 (%s)，等待付款', $paymentType));
    }
}
