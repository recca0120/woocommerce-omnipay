<?php

namespace WooCommerceOmnipay\Gateways\YiPay;

use WooCommerceOmnipay\Gateways\YiPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * YiPay 信用卡 Gateway
 */
class YiPayCreditGateway extends YiPayGateway
{
    use HasAmountLimits;

    /**
     * 付款類型
     * type=2: 信用卡 3D 付款
     *
     * @var string
     */
    protected $paymentType = '2';

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initMinAmountField();
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

        return $this->validateMinAmount();
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
