<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 超商代碼 Gateway
 */
class NewebPayCVSGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CVS';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_cvs';
        $config['title'] = $config['title'] ?? '藍新超商代碼';
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
        $data['CVS'] = '1';

        return $data;
    }
}
