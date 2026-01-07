<?php

namespace Recca0120\WooCommerce_Omnipay\Gateways;

/**
 * Dummy Gateway
 *
 * Direct payment gateway for testing with credit card form
 */
class DummyGateway extends OmnipayGateway
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        // Direct Gateway 需要顯示表單
        $this->has_fields = true;

        // 載入前端腳本
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * 顯示付款欄位（信用卡表單）
     */
    public function payment_fields()
    {
        // 顯示 description
        if ($this->description) {
            echo '<p>'.wp_kses_post($this->description).'</p>';
        }

        echo woocommerce_omnipay_get_template('checkout/credit-card-form.php', [
            'gateway_id' => $this->id,
            'billing_data' => $this->get_billing_data(),
        ]);
    }

    /**
     * 驗證信用卡欄位
     *
     * @return bool
     */
    public function validate_fields()
    {
        $requiredFields = [
            'omnipay_number' => __('Card number', 'woocommerce-omnipay'),
            'omnipay_expiryMonth' => __('Expiry month', 'woocommerce-omnipay'),
            'omnipay_expiryYear' => __('Expiry year', 'woocommerce-omnipay'),
            'omnipay_cvv' => __('CVV', 'woocommerce-omnipay'),
            'omnipay_firstName' => __('First name', 'woocommerce-omnipay'),
            'omnipay_lastName' => __('Last name', 'woocommerce-omnipay'),
        ];

        $valid = true;

        foreach ($requiredFields as $field => $label) {
            if (empty($_POST[$field])) {
                wc_add_notice(
                    sprintf(__('%s is required.', 'woocommerce-omnipay'), $label),
                    'error'
                );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * 載入前端腳本
     */
    public function enqueue_scripts()
    {
        // 只在結帳頁面載入
        if (! is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'woocommerce-omnipay-checkout',
            WOOCOMMERCE_OMNIPAY_PLUGIN_URL.'assets/js/checkout.js',
            [], // 不依賴 jQuery，使用原生 JavaScript
            WOOCOMMERCE_OMNIPAY_VERSION,
            true
        );
    }

    /**
     * 取得帳單資訊用於預填
     *
     * @return array
     */
    protected function get_billing_data()
    {
        $data = [
            'firstName' => '',
            'lastName' => '',
        ];

        // 嘗試從當前訂單取得帳單資訊
        // 1. 檢查是否有待付款訂單（結帳流程）
        if (WC()->session && WC()->session->get('order_awaiting_payment')) {
            $order = $this->orders->findById(WC()->session->get('order_awaiting_payment'));
            if ($order) {
                $data['firstName'] = $order->get_billing_first_name();
                $data['lastName'] = $order->get_billing_last_name();

                return $data;
            }
        }

        // 2. 檢查當前使用者（已登入使用者）
        if (is_user_logged_in()) {
            $customer = WC()->customer;
            if ($customer) {
                $data['firstName'] = $customer->get_billing_first_name();
                $data['lastName'] = $customer->get_billing_last_name();
            }
        }

        return $data;
    }
}
