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
    protected function get_payment_info_url($order)
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
    protected function handle_notification($notification)
    {
        $data = $notification->getData();

        // 檢查是否為取號結果通知
        if ($this->is_payment_info_notification($data)) {
            $order = $this->order_repository->findByTransactionId($notification->getTransactionId());

            if ($order) {
                $this->save_payment_info($order, $data);
            }

            $this->send_notification_response($notification);

            return;
        }

        // 付款完成通知交給父類處理
        parent::handle_notification($notification);
    }

    /**
     * 判斷是否為付款資訊通知
     *
     * ECPay 的取號結果通知：RtnCode = 2 (ATM) 或 10100073 (CVS/BARCODE)
     *
     * @param  array  $data  通知資料
     * @return bool
     */
    protected function is_payment_info_notification(array $data)
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
    protected function save_payment_info($order, array $data)
    {
        parent::save_payment_info($order, $data);

        $paymentType = $data['PaymentType'] ?? '';
        $order->add_order_note(
            sprintf('ECPay 取號成功 (%s)，等待付款', $paymentType)
        );
    }
}
