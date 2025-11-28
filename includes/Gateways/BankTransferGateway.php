<?php

namespace WooCommerceOmnipay\Gateways;

use WooCommerceOmnipay\Repositories\OrderRepository;

/**
 * BankTransfer Gateway
 *
 * 處理銀行轉帳付款，顯示固定的銀行帳號資訊
 */
class BankTransferGateway extends OmnipayGateway
{
    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        // 註冊匯款帳號後5碼的 AJAX 處理
        add_action('woocommerce_api_'.$this->id.'_remittance', [$this, 'handleRemittance']);
    }

    /**
     * 取得付款資訊通知 URL
     *
     * BankTransfer 的 paymentInfoUrl 用於用戶 redirect
     * 因此回傳 thankyou 頁面 URL，付款資訊在 redirect 時儲存
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function getPaymentInfoUrl($order)
    {
        return $this->get_return_url($order);
    }

    /**
     * 需要 redirect 付款事件
     *
     * BankTransfer 在 redirect 時儲存銀行資訊到訂單
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\RedirectResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function onPaymentRedirect($order, $response)
    {
        $redirect_data = $response->getRedirectData();
        $this->savePaymentInfo($order, $redirect_data);

        return parent::onPaymentRedirect($order, $response);
    }

    /**
     * 儲存付款資訊
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        $this->orders->savePaymentInfo($order, [
            'BankCode' => $data['bank_code'] ?? '',
            'BankAccount' => $data['account_number'] ?? '',
        ]);
    }

    /**
     * 取得付款資訊輸出（含匯款帳號後5碼表單）
     *
     * @param  \WC_Order  $order
     * @param  bool  $plainText
     * @return string
     */
    public function getPaymentInfoOutput($order, $plainText = false)
    {
        $output = parent::getPaymentInfoOutput($order, $plainText);

        // 純文字模式或非此 gateway 的訂單不顯示表單
        if ($plainText || $order->get_payment_method() !== $this->id) {
            return $output;
        }

        // 加入匯款帳號後5碼表單
        $output .= $this->getRemittanceFormOutput($order);

        return $output;
    }

    /**
     * 取得匯款帳號後5碼表單輸出
     *
     * @param  \WC_Order  $order
     * @return string
     */
    protected function getRemittanceFormOutput($order)
    {
        return woocommerce_omnipay_get_template('order/remittance-form.php', [
            'order' => $order,
            'submitted_last5' => $order->get_meta(OrderRepository::META_REMITTANCE_LAST5),
            'submit_url' => WC()->api_request_url($this->id.'_remittance'),
        ]);
    }

    /**
     * 處理匯款帳號後5碼提交
     */
    public function handleRemittance()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $last5 = isset($_POST['remittance_last5']) ? sanitize_text_field($_POST['remittance_last5']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        // 驗證 nonce
        if (! wp_verify_nonce($nonce, 'omnipay_remittance_nonce')) {
            $this->sendJsonResponse(false, __('安全驗證失敗', 'woocommerce-omnipay'));

            return;
        }

        // 驗證訂單
        $order = $this->orders->findById($order_id);
        if (! $order || $order->get_order_key() !== $order_key) {
            $this->sendJsonResponse(false, __('訂單驗證失敗', 'woocommerce-omnipay'));

            return;
        }

        // 驗證格式（必須是5位數字）
        if (! preg_match('/^\d{5}$/', $last5)) {
            $this->sendJsonResponse(false, __('請輸入5位數字', 'woocommerce-omnipay'));

            return;
        }

        // 儲存
        $this->orders->saveRemittanceLast5($order, $last5);

        $this->sendJsonResponse(true, __('已成功送出', 'woocommerce-omnipay'));
    }

    /**
     * 發送 JSON 回應
     *
     * @param  bool  $success
     * @param  string  $message
     */
    protected function sendJsonResponse($success, $message)
    {
        if (! headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
        $this->terminate();
    }
}
