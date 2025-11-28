<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 網路 ATM Gateway
 */
class NewebPayWebATMGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'WEBATM';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_webatm';
        $config['title'] = $config['title'] ?? '藍新網路 ATM';
        $config['description'] = $config['description'] ?? '使用網路 ATM 付款';

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
        $data['WEBATM'] = '1';

        return $data;
    }
}
