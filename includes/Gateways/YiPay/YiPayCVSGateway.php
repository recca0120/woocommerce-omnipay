<?php

namespace WooCommerceOmnipay\Gateways\YiPay;

use WooCommerceOmnipay\Gateways\YiPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * YiPay 超商代碼 Gateway
 */
class YiPayCVSGateway extends YiPayGateway
{
    use HasAmountLimits;

    /**
     * 付款類型
     * type=3: 超商代碼繳費
     *
     * @var string
     */
    protected $paymentType = '3';

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initMinAmountField();
        $this->initMaxAmountField();
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
        $data['type'] = $this->paymentType;

        return $data;
    }
}
