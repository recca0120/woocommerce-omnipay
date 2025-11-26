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
    public function __construct(array $gateway_config = [])
    {
        parent::__construct($gateway_config);

        // 註冊匯款帳號後5碼的 AJAX 處理
        add_action('woocommerce_api_'.$this->id.'_remittance', [$this, 'handle_remittance']);
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
    protected function get_payment_info_url($order)
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
    protected function on_payment_redirect($order, $response)
    {
        $redirect_data = $response->getRedirectData();
        $this->save_payment_info($order, $redirect_data);

        return parent::on_payment_redirect($order, $response);
    }

    /**
     * 儲存付款資訊
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function save_payment_info($order, array $data)
    {
        if (! empty($data['bank_code'])) {
            $order->update_meta_data('_omnipay_bank_code', $data['bank_code']);
        }
        if (! empty($data['account_number'])) {
            $order->update_meta_data('_omnipay_bank_account', $data['account_number']);
        }
        $order->save();
    }

    /**
     * 取得付款資訊輸出（含匯款帳號後5碼表單）
     *
     * @param  \WC_Order  $order
     * @param  bool  $plain_text
     * @return string
     */
    public function get_payment_info_output($order, $plain_text = false)
    {
        $output = parent::get_payment_info_output($order, $plain_text);

        // 純文字模式或非此 gateway 的訂單不顯示表單
        if ($plain_text || $order->get_payment_method() !== $this->id) {
            return $output;
        }

        // 加入匯款帳號後5碼表單
        $output .= $this->get_remittance_form_output($order);

        return $output;
    }

    /**
     * 取得匯款帳號後5碼表單輸出
     *
     * @param  \WC_Order  $order
     * @return string
     */
    protected function get_remittance_form_output($order)
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
    public function handle_remittance()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';
        $last5 = isset($_POST['remittance_last5']) ? sanitize_text_field($_POST['remittance_last5']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        // 驗證 nonce
        if (! wp_verify_nonce($nonce, 'omnipay_remittance_nonce')) {
            $this->send_json_response(false, __('安全驗證失敗', 'woocommerce-omnipay'));

            return;
        }

        // 驗證訂單
        $order = $this->order_repository->findById($order_id);
        if (! $order || $order->get_order_key() !== $order_key) {
            $this->send_json_response(false, __('訂單驗證失敗', 'woocommerce-omnipay'));

            return;
        }

        // 驗證格式（必須是5位數字）
        if (! preg_match('/^\d{5}$/', $last5)) {
            $this->send_json_response(false, __('請輸入5位數字', 'woocommerce-omnipay'));

            return;
        }

        // 儲存
        $order->update_meta_data(OrderRepository::META_REMITTANCE_LAST5, $last5);
        $order->add_order_note(sprintf(__('客戶已填寫匯款帳號後5碼：%s', 'woocommerce-omnipay'), $last5));
        $order->save();

        $this->send_json_response(true, __('已成功送出', 'woocommerce-omnipay'));
    }

    /**
     * 發送 JSON 回應
     *
     * @param  bool  $success
     * @param  string  $message
     */
    protected function send_json_response($success, $message)
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
