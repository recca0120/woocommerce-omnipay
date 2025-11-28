<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 定期定額 Gateway
 */
class NewebPayDCAGateway extends NewebPayGateway
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
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_dca';
        $config['title'] = $config['title'] ?? '藍新定期定額';
        $config['description'] = $config['description'] ?? '使用信用卡定期定額付款';

        parent::__construct($config);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['period_type'] = [
            'title' => __('週期類型', 'woocommerce-omnipay'),
            'type' => 'select',
            'description' => __('定期定額的週期類型', 'woocommerce-omnipay'),
            'default' => 'M',
            'options' => [
                'D' => __('日', 'woocommerce-omnipay'),
                'W' => __('週', 'woocommerce-omnipay'),
                'M' => __('月', 'woocommerce-omnipay'),
                'Y' => __('年', 'woocommerce-omnipay'),
            ],
            'desc_tip' => true,
        ];

        $this->form_fields['period_point'] = [
            'title' => __('扣款日/週期點', 'woocommerce-omnipay'),
            'type' => 'text',
            'description' => __('每月幾號扣款（1-28 或空白）', 'woocommerce-omnipay'),
            'default' => '',
            'desc_tip' => true,
        ];

        $this->form_fields['period_times'] = [
            'title' => __('授權期數', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('總共執行幾期', 'woocommerce-omnipay'),
            'default' => '12',
            'custom_attributes' => [
                'min' => '1',
            ],
            'desc_tip' => true,
        ];

        $this->form_fields['period_start_type'] = [
            'title' => __('交易模式', 'woocommerce-omnipay'),
            'type' => 'select',
            'description' => __('定期定額首次授權模式', 'woocommerce-omnipay'),
            'default' => '2',
            'options' => [
                '1' => __('立即執行十元授權', 'woocommerce-omnipay'),
                '2' => __('立即執行委託金額授權', 'woocommerce-omnipay'),
                '3' => __('不檢查信用卡資訊，不授權', 'woocommerce-omnipay'),
            ],
            'desc_tip' => true,
        ];
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

        // 加入定期定額參數
        $data['PeriodAmt'] = (int) $order->get_total();
        $data['PeriodType'] = $this->get_option('period_type', 'M');
        $data['PeriodPoint'] = $this->get_option('period_point', '');
        $data['PeriodTimes'] = (int) $this->get_option('period_times', '12');
        $data['PeriodStartType'] = (int) $this->get_option('period_start_type', '2');

        // PayerEmail 是定期定額的必填欄位
        $payerEmail = $order->get_billing_email();
        if (empty($payerEmail)) {
            // 如果訂單沒有 email，使用客戶 email 或網站管理員 email
            $payerEmail = $order->get_billing_email() ?: get_bloginfo('admin_email');
        }
        $data['PayerEmail'] = $payerEmail;

        return $data;
    }
}
