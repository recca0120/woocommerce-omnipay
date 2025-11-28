<?php

namespace WooCommerceOmnipay\Gateways\YiPay;

use WooCommerceOmnipay\Gateways\YiPayGateway;

/**
 * YiPay ATM Gateway
 */
class YiPayATMGateway extends YiPayGateway
{
    /**
     * 付款類型
     * type=4: ATM 虛擬帳號繳款
     *
     * @var string
     */
    protected $paymentType = '4';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'yipay_atm';
        $config['title'] = $config['title'] ?? '乙禾 ATM';
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
        $data['type'] = $this->paymentType;

        return $data;
    }
}
