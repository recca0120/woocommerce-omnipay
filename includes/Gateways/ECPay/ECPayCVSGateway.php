<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 超商代碼 Gateway
 */
class ECPayCVSGateway extends ECPayGateway
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
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_cvs';
        $config['title'] = $config['title'] ?? '綠界超商代碼';
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
        $data['ChoosePayment'] = $this->paymentType;

        return $data;
    }
}
