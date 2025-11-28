<?php

namespace WooCommerceOmnipay\Repositories;

use WooCommerceOmnipay\Exceptions\OrderNotFoundException;

/**
 * Order Repository
 *
 * 處理訂單的查詢與持久化邏輯
 */
class OrderRepository
{
    /**
     * Meta keys
     */
    public const META_TRANSACTION_ID = '_omnipay_transaction_id';

    public const META_BANK_CODE = '_omnipay_bank_code';

    public const META_BANK_ACCOUNT = '_omnipay_bank_account';

    public const META_VIRTUAL_ACCOUNT = '_omnipay_virtual_account';

    public const META_PAYMENT_NO = '_omnipay_payment_no';

    public const META_BARCODE_1 = '_omnipay_barcode_1';

    public const META_BARCODE_2 = '_omnipay_barcode_2';

    public const META_BARCODE_3 = '_omnipay_barcode_3';

    public const META_EXPIRE_DATE = '_omnipay_expire_date';

    public const META_REMITTANCE_LAST5 = '_omnipay_remittance_last5';

    /**
     * 付款資訊欄位對應表
     */
    protected const PAYMENT_INFO_FIELDS = [
        'BankCode' => self::META_BANK_CODE,
        'BankAccount' => self::META_BANK_ACCOUNT,
        'vAccount' => self::META_VIRTUAL_ACCOUNT,
        'PaymentNo' => self::META_PAYMENT_NO,
        'Barcode1' => self::META_BARCODE_1,
        'Barcode2' => self::META_BARCODE_2,
        'Barcode3' => self::META_BARCODE_3,
        'ExpireDate' => self::META_EXPIRE_DATE,
    ];

    /**
     * 用 order ID 查詢訂單
     *
     * @param  int|null  $order_id
     * @return \WC_Order|null
     */
    public function findById($order_id)
    {
        if (empty($order_id)) {
            return null;
        }

        $order = wc_get_order($order_id);

        return $order ?: null;
    }

    /**
     * 用 order ID 查詢訂單，找不到則丟出例外
     *
     * @param  int|null  $order_id
     * @return \WC_Order
     *
     * @throws OrderNotFoundException
     */
    public function findByIdOrFail($order_id)
    {
        $order = $this->findById($order_id);

        if (! $order) {
            throw new OrderNotFoundException($order_id);
        }

        return $order;
    }

    /**
     * 用 transactionId 查詢訂單
     *
     * @param  string|null  $transaction_id
     * @return \WC_Order|null
     */
    public function findByTransactionId($transaction_id)
    {
        if (empty($transaction_id)) {
            return null;
        }

        $orders = wc_get_orders([
            'meta_key' => self::META_TRANSACTION_ID,
            'meta_value' => $transaction_id,
            'limit' => 1,
        ]);

        return ! empty($orders) ? $orders[0] : null;
    }

    /**
     * 用 transactionId 查詢訂單，找不到則丟出例外
     *
     * @param  string|null  $transaction_id
     * @return \WC_Order
     *
     * @throws OrderNotFoundException
     */
    public function findByTransactionIdOrFail($transaction_id)
    {
        $order = $this->findByTransactionId($transaction_id);

        if (! $order) {
            throw new OrderNotFoundException($transaction_id);
        }

        return $order;
    }

    /**
     * 儲存 transactionId 到訂單
     *
     * @param  \WC_Order  $order
     * @param  string  $transaction_id
     * @return void
     */
    public function saveTransactionId($order, $transaction_id)
    {
        $order->update_meta_data(self::META_TRANSACTION_ID, $transaction_id);
        $order->save();
    }

    /**
     * 產生並儲存 transactionId
     *
     * @param  \WC_Order  $order
     * @param  string  $prefix  前綴
     * @param  bool  $allowResubmit  是否允許重新提交（true: 隨機 ID，false: 固定 ID）
     * @return string
     */
    public function createTransactionId($order, $prefix = '', $allowResubmit = true)
    {
        $baseId = $prefix.$order->get_id();

        $transactionId = $allowResubmit
            ? $this->generateRandomTransactionId($baseId)
            : $baseId;

        $this->saveTransactionId($order, $transactionId);

        return $transactionId;
    }

    /**
     * 儲存付款資訊（ATM/CVS/BARCODE）
     *
     * @param  \WC_Order  $order
     * @param  array  $data  付款資訊（使用標準 key: BankCode, vAccount, PaymentNo, Barcode1-3, ExpireDate）
     * @return void
     */
    public function savePaymentInfo($order, array $data)
    {
        foreach (self::PAYMENT_INFO_FIELDS as $dataKey => $metaKey) {
            if (isset($data[$dataKey])) {
                $order->update_meta_data($metaKey, $data[$dataKey]);
            }
        }

        $order->save();
    }

    /**
     * 取得付款資訊
     *
     * @param  \WC_Order  $order
     * @return array 付款資訊（key 為 meta key 常數）
     */
    public function getPaymentInfo($order)
    {
        $info = [];

        foreach (self::PAYMENT_INFO_FIELDS as $dataKey => $metaKey) {
            $value = $order->get_meta($metaKey);
            if (! empty($value)) {
                $info[$metaKey] = $value;
            }
        }

        return $info;
    }

    /**
     * 取得付款資訊欄位的標籤（支援 i18n）
     *
     * @return array meta_key => label
     */
    public static function getPaymentInfoLabels()
    {
        return [
            self::META_BANK_CODE => __('銀行代碼', 'woocommerce-omnipay'),
            self::META_BANK_ACCOUNT => __('銀行帳號', 'woocommerce-omnipay'),
            self::META_VIRTUAL_ACCOUNT => __('虛擬帳號', 'woocommerce-omnipay'),
            self::META_PAYMENT_NO => __('繳費代碼', 'woocommerce-omnipay'),
            self::META_BARCODE_1 => __('條碼一', 'woocommerce-omnipay'),
            self::META_BARCODE_2 => __('條碼二', 'woocommerce-omnipay'),
            self::META_BARCODE_3 => __('條碼三', 'woocommerce-omnipay'),
            self::META_EXPIRE_DATE => __('繳費期限', 'woocommerce-omnipay'),
            self::META_REMITTANCE_LAST5 => __('匯款帳號後5碼', 'woocommerce-omnipay'),
        ];
    }

    /**
     * 產生隨機的 transactionId
     *
     * 格式：{base_id}T{random}，總長度不超過 20 字元
     *
     * @param  string  $baseId  基礎 ID（prefix + order_id）
     * @return string
     */
    private function generateRandomTransactionId($baseId)
    {
        $maxRandomLength = 20 - strlen($baseId) - 1; // -1 for 'T' separator

        $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $maxRandomLength);

        return $baseId.'T'.$random;
    }
}
