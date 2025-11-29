<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay TWQR (台灣 Pay) Gateway
 */
class ECPayTWQRGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'TWQR';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_twqr';
        $config['title'] = $config['title'] ?? __('ECPay Taiwan Pay', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with Taiwan Pay (TWQR)', 'woocommerce-omnipay');

        parent::__construct($config);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        // 添加最小金額設定
        $this->form_fields['min_amount'] = [
            'title' => __('Minimum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Minimum order amount required for this payment method', 'woocommerce-omnipay'),
            'default' => 0,
            'desc_tip' => true,
            'custom_attributes' => [
                'min' => 0,
                'step' => 1,
            ],
        ];

        // 添加最大金額設定
        $this->form_fields['max_amount'] = [
            'title' => __('Maximum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Maximum order amount for this payment method', 'woocommerce-omnipay'),
            'default' => 0,
            'desc_tip' => true,
            'custom_attributes' => [
                'min' => 0,
                'step' => 1,
            ],
        ];
    }

    /**
     * 檢查付款方式是否可用
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        if (WC()->cart) {
            $total = $this->get_order_total();
            $minAmount = (int) $this->get_option('min_amount', 0);
            $maxAmount = (int) $this->get_option('max_amount', 0);

            if ($total > 0) {
                if ($minAmount > 0 && $total < $minAmount) {
                    return false;
                }
                if ($maxAmount > 0 && $total > $maxAmount) {
                    return false;
                }
            }
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
        $data['ChoosePayment'] = $this->paymentType;

        return $data;
    }
}
