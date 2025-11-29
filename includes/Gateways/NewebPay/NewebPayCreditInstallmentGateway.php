<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 信用卡分期 Gateway
 */
class NewebPayCreditInstallmentGateway extends NewebPayGateway
{
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
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['min_amount'] = [
            'title' => __('Minimum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Minimum order amount required for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];

        $this->form_fields['installments'] = [
            'title' => __('Installment Periods', 'woocommerce-omnipay'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('Select available installment periods', 'woocommerce-omnipay'),
            'default' => ['3', '6', '12', '18', '24'],
            'desc_tip' => true,
            'options' => [
                '3' => __('3 installments', 'woocommerce-omnipay'),
                '6' => __('6 installments', 'woocommerce-omnipay'),
                '12' => __('12 installments', 'woocommerce-omnipay'),
                '18' => __('18 installments', 'woocommerce-omnipay'),
                '24' => __('24 installments', 'woocommerce-omnipay'),
                '30' => __('30 installments', 'woocommerce-omnipay'),
            ],
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

        $minAmount = (int) $this->get_option('min_amount', 0);
        if ($minAmount > 0 && $this->get_order_total() < $minAmount) {
            return false;
        }

        return true;
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        parent::payment_fields();

        $installments = $this->get_option('installments', ['3', '6', '12', '18', '24']);

        // Ensure installments is an array
        if (! is_array($installments)) {
            $installments = ['3', '6', '12', '18', '24'];
        }

        echo woocommerce_omnipay_get_template('checkout/installment-form.php', [
            'installments' => $installments,
            'total' => $this->get_order_total(),
            'has_30n_validation' => false,
        ]);
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

        $selectedInstallment = isset($_POST['omnipay_installment']) ? sanitize_text_field($_POST['omnipay_installment']) : '';

        if (! empty($selectedInstallment)) {
            $data['InstFlag'] = $selectedInstallment;

            return $data;
        }

        $installments = $this->get_option('installments', ['3', '6', '12', '18', '24']);
        $data['InstFlag'] = is_array($installments) ? implode(',', $installments) : $installments;

        return $data;
    }
}
