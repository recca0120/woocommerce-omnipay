<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;
use WooCommerceOmnipay\Traits\HasInstallments;

/**
 * NewebPay 信用卡分期 Gateway
 */
class NewebPayCreditInstallmentGateway extends NewebPayGateway
{
    use HasAmountLimits;
    use HasInstallments;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_credit_installment';
        $config['title'] = $config['title'] ?? __('NewebPay Credit Card Installment', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with credit card installment', 'woocommerce-omnipay');

        parent::__construct($config);
    }

    /**
     * Get the installment field name for API
     */
    protected function getInstallmentFieldName(): string
    {
        return 'InstFlag';
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
        $data['CREDIT'] = '1';
        $data = array_merge($data, $this->prepareInstallmentData());

        return $data;
    }
}
