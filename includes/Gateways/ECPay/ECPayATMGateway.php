<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * ECPay ATM Gateway
 */
class ECPayATMGateway extends ECPayGateway
{
    use HasAmountLimits;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'ATM';

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initMinAmountField();
        $this->initMaxAmountField();

        $this->form_fields['expire_date'] = [
            'title' => __('Payment Expiry Days', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Payment expiry period for ATM virtual account, range 1-60 days', 'woocommerce-omnipay'),
            'default' => '3',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '1', 'max' => '60'],
        ];
    }

    /**
     * 檢查付款方式是否可用
     *
     * @return bool
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        return $this->validateMinAmount() && $this->validateMaxAmount();
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function preparePaymentData($order)
    {
        $data = parent::preparePaymentData($order);
        $data['ChoosePayment'] = $this->paymentType;
        $data['ExpireDate'] = (int) $this->get_option('expire_date', 3);

        return $data;
    }
}
