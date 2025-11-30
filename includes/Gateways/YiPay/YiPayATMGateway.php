<?php

namespace WooCommerceOmnipay\Gateways\YiPay;

use WooCommerceOmnipay\Gateways\YiPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * YiPay ATM Gateway
 */
class YiPayATMGateway extends YiPayGateway
{
    use HasAmountLimits;

    /**
     * 付款類型
     * type=4: ATM 虛擬帳號繳款
     *
     * @var string
     */
    protected $paymentType = '4';

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
