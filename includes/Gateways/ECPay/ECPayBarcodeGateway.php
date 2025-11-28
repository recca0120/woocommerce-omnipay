<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 超商條碼 Gateway
 */
class ECPayBarcodeGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'BARCODE';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_barcode';
        $config['title'] = $config['title'] ?? '綠界超商條碼';
        $config['description'] = $config['description'] ?? '使用超商條碼付款';

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
