<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Concerns;

use OmnipayTaiwan\WooCommerce_Omnipay\Repositories\OrderRepository;

/**
 * 付款資訊顯示 Trait
 *
 * 處理 ATM/CVS/BARCODE 等離線付款資訊的顯示
 */
trait DisplaysPaymentInfo
{
    /**
     * 透過訂單 ID 顯示付款資訊
     *
     * @param  int  $order_id
     */
    public function display_payment_info_by_order_id($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order) {
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
     * 顯示付款資訊（前台）
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info($order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        echo $this->getPaymentInfoOutput($order);
    }

    /**
     * 顯示付款資訊（管理後台）
     *
     * @param  \WC_Order  $order
     */
    public function display_payment_info_admin($order)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        echo $this->getPaymentInfoOutput($order);
    }

    /**
     * 在 Email 通知顯示付款資訊
     *
     * @param  \WC_Order  $order
     * @param  bool  $sentToAdmin
     * @param  bool  $plainText
     */
    public function display_payment_info_on_email($order, $sentToAdmin, $plainText)
    {
        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        echo $this->getPaymentInfoOutput($order, $plainText);
    }

    /**
     * 取得付款資訊輸出
     *
     * @param  \WC_Order  $order
     * @param  bool  $plainText  是否為純文字格式
     * @return string
     */
    public function getPaymentInfoOutput($order, $plainText = false)
    {
        $paymentInfo = $this->orders->getPaymentInfo($order);
        $template = $this->getPaymentInfoTemplate($plainText);

        return woocommerce_omnipay_get_template($template, [
            'payment_info' => $paymentInfo,
            'labels' => OrderRepository::getPaymentInfoLabels(),
        ]);
    }
    /**
     * 註冊付款資訊顯示 hooks
     */
    protected function registerPaymentInfoHooks()
    {
        // WooCommerce 感謝頁 + CartFlows 一般模式（載入 WC thankyou.php）
        add_action('woocommerce_thankyou_'.$this->id, [$this, 'display_payment_info_by_order_id']);

        // CartFlows Instant 模式
        add_action('woocommerce_receipt_'.$this->id, [$this, 'display_payment_info_by_order_id']);

        // view-order 頁面
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_payment_info_on_view_order']);

        // 管理後台
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_payment_info_admin']);

        // Email
        add_action('woocommerce_email_after_order_table', [$this, 'display_payment_info_on_email'], 10, 3);
    }

    /**
     * 取得付款資訊 template 路徑
     *
     * @param  bool  $plainText  是否為純文字格式
     * @return string
     */
    protected function getPaymentInfoTemplate($plainText = false)
    {
        if ($plainText) {
            return 'order/payment-info-plain.php';
        }

        if ($this->isCartFlowsThankYouPage()) {
            return 'order/payment-info-cartflows.php';
        }

        return 'order/payment-info.php';
    }

    /**
     * 判斷是否為 CartFlows 感謝頁
     *
     * @return bool
     */
    protected function isCartFlowsThankYouPage()
    {
        return function_exists('_is_wcf_thankyou_type') && _is_wcf_thankyou_type();
    }
}
