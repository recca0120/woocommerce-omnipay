<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 定期定額 Gateway
 */
class ECPayDCAGateway extends ECPayGateway
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
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_dca';
        $config['title'] = $config['title'] ?? '綠界定期定額';
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
                'M' => __('月', 'woocommerce-omnipay'),
                'Y' => __('年', 'woocommerce-omnipay'),
            ],
            'desc_tip' => true,
        ];

        $this->form_fields['frequency'] = [
            'title' => __('執行頻率', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('多久執行一次（依週期類型）', 'woocommerce-omnipay'),
            'default' => '1',
            'custom_attributes' => [
                'min' => '1',
            ],
            'desc_tip' => true,
        ];

        $this->form_fields['exec_times'] = [
            'title' => __('執行次數', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('總共執行幾次', 'woocommerce-omnipay'),
            'default' => '12',
            'custom_attributes' => [
                'min' => '1',
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
        $data['ChoosePayment'] = $this->paymentType;

        // 加入定期定額參數
        $data['PeriodType'] = $this->get_option('period_type', 'M');
        $data['Frequency'] = (int) $this->get_option('frequency', '1');
        $data['ExecTimes'] = (int) $this->get_option('exec_times', '12');
        $data['PeriodAmount'] = (int) $order->get_total();

        return $data;
    }
}
