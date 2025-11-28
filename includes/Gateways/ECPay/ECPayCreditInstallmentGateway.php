<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 信用卡分期 Gateway
 */
class ECPayCreditInstallmentGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_credit_installment';
        $config['title'] = $config['title'] ?? '綠界信用卡分期';
        $config['description'] = $config['description'] ?? '使用信用卡分期付款';

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

        $this->form_fields['installments'] = [
            'title' => __('分期期數', 'woocommerce-omnipay'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('選擇可用的分期期數', 'woocommerce-omnipay'),
            'default' => ['3', '6', '12', '18', '24'],
            'desc_tip' => true,
            'options' => [
                '3' => __('3 期', 'woocommerce-omnipay'),
                '6' => __('6 期', 'woocommerce-omnipay'),
                '12' => __('12 期', 'woocommerce-omnipay'),
                '18' => __('18 期', 'woocommerce-omnipay'),
                '24' => __('24 期', 'woocommerce-omnipay'),
                '30N' => __('30 期（圓夢分期）', 'woocommerce-omnipay'),
            ],
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
        $data['ChoosePayment'] = $this->paymentType;

        // 加入分期期數參數（multiselect 回傳陣列，需轉為逗號分隔字串）
        // 使用 WC_Payment_Gateway::get_option 繞過 OmnipayGateway 的 sanitize 處理
        $installments = \WC_Payment_Gateway::get_option('installments', ['3', '6', '12', '18', '24']);
        $data['CreditInstallment'] = is_array($installments) ? implode(',', $installments) : $installments;

        return $data;
    }
}
