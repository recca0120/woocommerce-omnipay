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
