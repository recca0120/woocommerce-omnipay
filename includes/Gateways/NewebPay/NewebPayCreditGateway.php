<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * NewebPay 信用卡 Gateway
 */
class NewebPayCreditGateway extends NewebPayGateway
{
    use HasAmountLimits;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

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
        $data['CREDIT'] = '1';

        return $data;
    }
}
