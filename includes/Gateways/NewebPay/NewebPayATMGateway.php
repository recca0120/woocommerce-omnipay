<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay ATM Gateway
 */
class NewebPayATMGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'VACC';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_atm';
        $config['title'] = $config['title'] ?? '藍新 ATM';
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
        $data['VACC'] = '1';

        return $data;
    }
}
