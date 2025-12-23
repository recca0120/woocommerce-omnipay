<?php

namespace WooCommerceOmnipay\Gateways;

use WooCommerceOmnipay\Adapters\NewebPayAdapter;

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
     * @var NewebPayAdapter
     */
    protected $adapter;

    public function __construct(array $config, ?NewebPayAdapter $adapter = null)
    {
        parent::__construct($config, $adapter ?? new NewebPayAdapter);
    }
}
