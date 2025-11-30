<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Constants;
use WooCommerceOmnipay\Gateways\ECPayGateway;
use WooCommerceOmnipay\Traits\HasAmountLimits;

/**
 * ECPay BNPL (無卡分期) Gateway
 */
class ECPayBNPLGateway extends ECPayGateway
{
    use HasAmountLimits;

    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'BNPL';

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
            'description' => sprintf(
                __('Maximum order amount for this payment method (max: %d)', 'woocommerce-omnipay'),
                Constants::BNPL_MAX_AMOUNT
            ),
            'default' => Constants::BNPL_MAX_AMOUNT,
            'desc_tip' => true,
            'custom_attributes' => [
                'min' => 0,
                'max' => Constants::BNPL_MAX_AMOUNT,
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
            $maxAmount = (int) $this->get_option('max_amount', 300000);

            if ($total > 0) {
                if ($minAmount > 0 && $total < $minAmount) {
                    return false;
                }
                // BNPL 最大金額限制為 300000
                if ($maxAmount > 0 && ($total > $maxAmount || $total > 300000)) {
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
