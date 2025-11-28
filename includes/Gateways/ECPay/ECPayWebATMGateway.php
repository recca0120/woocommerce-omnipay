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
