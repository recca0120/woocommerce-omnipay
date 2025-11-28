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

        $this->form_fields['max_amount'] = [
            'title' => __('最大訂單金額', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('訂單金額高於此值時不顯示此付款方式（0 = 無限制）', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];

        $this->form_fields['expire_date'] = [
            'title' => __('付款期限（分鐘）', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('超商代碼的付款期限，範圍 1-43200 分鐘（預設 10080 分鐘 = 7 天）', 'woocommerce-omnipay'),
            'default' => '10080',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '1', 'max' => '43200'],
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

        $total = $this->get_order_total();
        $minAmount = (int) $this->get_option('min_amount', 0);
        $maxAmount = (int) $this->get_option('max_amount', 0);

        if ($minAmount > 0 && $total < $minAmount) {
            return false;
        }

        if ($maxAmount > 0 && $total > $maxAmount) {
            return false;
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
        $data['StoreExpireDate'] = (int) $this->get_option('expire_date', 10080);

        return $data;
    }
}
