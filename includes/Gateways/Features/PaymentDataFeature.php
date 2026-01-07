<?php

namespace Recca0120\WooCommerce_Omnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Payment Data Feature
 *
 * 將指定的付款資料合併到請求中
 * 例如：['ChoosePayment' => 'Credit'] 或 ['VACC' => 1]
 */
class PaymentDataFeature extends AbstractFeature
{
    /**
     * @var array
     */
    private $paymentData;

    /**
     * @param  array  $paymentData  要合併的付款資料
     */
    public function __construct(array $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        return array_merge($data, $this->paymentData);
    }
}
