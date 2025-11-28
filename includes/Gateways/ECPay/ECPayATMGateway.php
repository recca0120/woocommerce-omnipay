<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay ATM Gateway
 */
class ECPayATMGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'ATM';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_atm';
        $config['title'] = $config['title'] ?? '綠界 ATM';
        $config['description'] = $config['description'] ?? '使用 ATM 虛擬帳號付款';

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
