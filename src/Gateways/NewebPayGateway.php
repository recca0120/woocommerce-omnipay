<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways;

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
}
