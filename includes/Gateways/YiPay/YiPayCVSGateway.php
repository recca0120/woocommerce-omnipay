<?php

namespace WooCommerceOmnipay\Gateways\YiPay;

use WooCommerceOmnipay\Gateways\YiPayGateway;

/**
 * YiPay 超商代碼 Gateway
 */
class YiPayCVSGateway extends YiPayGateway
{
    /**
     * 付款類型
     * type=3: 超商代碼繳費
     *
     * @var string
     */
    protected $paymentType = '3';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'yipay_cvs';
        $config['title'] = $config['title'] ?? '乙禾超商代碼';
        $config['description'] = $config['description'] ?? '使用超商代碼付款';

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
        $data['type'] = $this->paymentType;

        return $data;
    }
}
