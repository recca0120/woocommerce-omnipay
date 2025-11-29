<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 網路 ATM Gateway
 */
class ECPayWebATMGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'WebATM';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_webatm';
        $config['title'] = $config['title'] ?? '綠界網路 ATM';
        $config['description'] = $config['description'] ?? '使用網路 ATM 付款';

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

        $this->form_fields['max_amount'] = [
            'title' => __('Maximum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Maximum order amount for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
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

        $total = $this->get_order_total();
        $minAmount = (int) $this->get_option('min_amount', 0);
        $maxAmount = (int) $this->get_option('max_amount', 0);

        if ($minAmount > 0 && $total < $minAmount) {
            return false;
        }

        if ($maxAmount > 0 && $total > $maxAmount) {
            return false;
        }

        return true;
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

        return $data;
    }
}
