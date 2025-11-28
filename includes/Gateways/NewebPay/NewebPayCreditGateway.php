<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 信用卡 Gateway
 */
class NewebPayCreditGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_credit';
        $config['title'] = $config['title'] ?? '藍新信用卡';
        $config['description'] = $config['description'] ?? '使用信用卡付款';

        parent::__construct($config);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['min_amount'] = [
            'title' => __('最小訂單金額', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('訂單金額低於此值時不顯示此付款方式（0 = 無限制）', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];
    }

    /**
     * 檢查付款方式是否可用
     *
     * @return bool
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        $minAmount = (int) $this->get_option('min_amount', 0);
        if ($minAmount > 0) {
            $total = $this->get_order_total();
            if ($total < $minAmount) {
                return false;
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
        $data['CREDIT'] = '1';

        return $data;
    }
}
