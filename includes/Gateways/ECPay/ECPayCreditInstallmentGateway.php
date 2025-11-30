<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;
use WooCommerceOmnipay\Traits\HasInstallments;

/**
 * ECPay 信用卡分期 Gateway
 */
class ECPayCreditInstallmentGateway extends ECPayGateway
{
    use HasAmountLimits;
    use HasInstallments;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

    /**
     * Get the installment field name for API
     */
    protected function getInstallmentFieldName(): string
    {
        return 'CreditInstallment';
    }

    /**
     * Enable ECPay Dream Installment (30N) handling
     */
    protected function requiresDreamInstallment(): bool
    {
        return true;
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->initMinAmountField();
        $this->initInstallmentsField();
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
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        parent::payment_fields();
        $this->displayInstallmentFields();
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
        $data = array_merge($data, $this->prepareInstallmentData());

        return $data;
    }
}
