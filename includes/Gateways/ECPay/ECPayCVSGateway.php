<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * ECPay 超商代碼 Gateway
 */
class ECPayCVSGateway extends ECPayGateway
{
    use HasAmountLimits;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CVS';

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initMinAmountField();
        $this->initMaxAmountField();

        $this->form_fields['expire_date'] = [
            'title' => __('Payment Expiry Minutes', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Payment expiry period for CVS code, range 1-43200 minutes (default 10080 minutes = 7 days)', 'woocommerce-omnipay'),
            'default' => '10080',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '1', 'max' => '43200'],
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
        $data['StoreExpireDate'] = (int) $this->get_option('expire_date', 10080);

        return $data;
    }
}
