<?php

namespace WooCommerceOmnipay\Gateways;

/**
 * BankTransfer Gateway
 *
 * 處理銀行轉帳付款，顯示固定的銀行帳號資訊
 */
class BankTransferGateway extends OmnipayGateway
{
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
}
