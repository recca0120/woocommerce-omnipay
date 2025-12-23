<?php

namespace WooCommerceOmnipay\Gateways;

use Omnipay\Common\Message\NotificationInterface;
use WooCommerceOmnipay\Adapters\ECPayAdapter;

/**
 * ECPay Gateway
 *
 * 處理 ECPay 特有的邏輯，包含 ATM/CVS/BARCODE 付款資訊
 */
class ECPayGateway extends OmnipayGateway
{
    /**
     * @var ECPayAdapter
     */
    protected $adapter;

    public function __construct(array $config, ?ECPayAdapter $adapter = null)
    {
        parent::__construct($config, $adapter ?? new ECPayAdapter);
    }

    /**
     * 處理 AcceptNotification 回應
     *
     * ECPay 有額外的信用卡資訊儲存與模擬付款處理
     */
    protected function handleNotification($notification, array $data)
    {
        // 處理付款資訊通知
        if ($this->adapter->isPaymentInfoNotification($data)) {
            $order = $this->orders->findByTransactionId($notification->getTransactionId());

            if ($order) {
                $this->savePaymentInfo($order, $data);
            }

            $this->sendNotificationResponse($notification);

            return;
        }

        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        // 金額驗證
        if (! $this->adapter->validateAmount($data, (int) $order->get_total())) {
            $this->sendCallbackResponse(false, 'Amount mismatch');

            return;
        }

        // 儲存信用卡資訊
        $this->saveCreditCardInfo($order, $data);

        // 模擬付款處理：不改變訂單狀態
        if ($this->adapter->isSimulatedPayment($data)) {
            $this->orders->addNote($order, __('ECPay simulated payment (SimulatePaid=1)', 'woocommerce-omnipay'));
            $this->sendNotificationResponse($notification);

            return;
        }

        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        if ($notification->getTransactionStatus() !== NotificationInterface::STATUS_COMPLETED) {
            $errorMessage = $notification->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, 'callback', false);
            $this->sendCallbackResponse(false, $errorMessage);

            return;
        }

        $this->completeOrderPayment($order, $notification->getTransactionReference(), 'callback');
        $this->sendNotificationResponse($notification);
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
