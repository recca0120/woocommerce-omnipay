<?php

namespace WooCommerceOmnipay\Gateways\Traits;

use WooCommerceOmnipay\Repositories\OrderRepository;

/**
 * 付款資訊顯示 Trait
 *
 * 處理 ATM/CVS/BARCODE 等離線付款資訊的顯示
 */
trait DisplaysPaymentInfo
{
    /**
     * 註冊付款資訊顯示 hooks
     */
    protected function register_payment_info_hooks()
    {
        // 感謝頁：付款資訊在訂單詳情之前
        add_action('woocommerce_order_details_before_order_table', [$this, 'display_payment_info_on_thankyou']);
        // view-order：付款資訊在訂單詳情之後
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_payment_info_on_view_order']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_payment_info']);
        add_action('woocommerce_email_after_order_table', [$this, 'display_payment_info_on_email'], 10, 3);
    }

    /**
     * 在感謝頁顯示付款資訊（訂單詳情之前）
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info_on_thankyou($order)
    {
        if (! is_wc_endpoint_url('order-received')) {
            return;
        }

        $this->display_payment_info($order);
    }

    /**
     * 在 view-order 頁顯示付款資訊（訂單詳情之後）
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info_on_view_order($order)
    {
        if (! is_wc_endpoint_url('view-order')) {
            return;
        }

        $this->display_payment_info($order);
    }

    /**
     * 顯示付款資訊（管理後台）
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info($order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        echo $this->get_payment_info_output($order);
    }

    /**
     * 在 Email 通知顯示付款資訊
     *
     * @param  \WC_Order  $order
     * @param  bool  $sent_to_admin
     * @param  bool  $plain_text
     */
    public function display_payment_info_on_email($order, $sent_to_admin, $plain_text)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        echo $this->get_payment_info_output($order, $plain_text);
    }

    /**
     * 取得付款資訊輸出
     *
     * @param  \WC_Order  $order
     * @param  bool  $plain_text  是否為純文字格式
     * @return string
     */
    public function get_payment_info_output($order, $plain_text = false)
    {
        $payment_info = $this->order_repository->getPaymentInfo($order);
        $template = $plain_text ? 'order/payment-info-plain.php' : 'order/payment-info.php';

        return woocommerce_omnipay_get_template($template, [
            'payment_info' => $payment_info,
            'labels' => OrderRepository::getPaymentInfoLabels(),
        ]);
    }
}
