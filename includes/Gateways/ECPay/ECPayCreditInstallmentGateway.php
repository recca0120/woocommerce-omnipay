<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 信用卡分期 Gateway
 */
class ECPayCreditInstallmentGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

    /**
     * 預設分期期數
     *
     * @var string
     */
    protected $defaultInstallments = '3,6,12,18,24';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_credit_installment';
        $config['title'] = $config['title'] ?? '綠界信用卡分期';
        $config['description'] = $config['description'] ?? '使用信用卡分期付款';

        parent::__construct($config);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['installments'] = [
            'title' => __('分期期數', 'woocommerce-omnipay'),
            'type' => 'text',
            'description' => __('可用的分期期數，以逗號分隔（例如：3,6,12,18,24）', 'woocommerce-omnipay'),
            'default' => $this->defaultInstallments,
            'desc_tip' => true,
        ];
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

        // 加入分期期數參數
        $installments = $this->get_option('installments', $this->defaultInstallments);
        $data['CreditInstallment'] = $installments;

        return $data;
    }
}
