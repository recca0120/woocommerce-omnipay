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
     * Order status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ON_HOLD = 'on-hold';

    public const STATUS_FAILED = 'failed';

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
     * @param  int|null  $orderId
     * @return \WC_Order|null
     */
    public function findById($orderId)
    {
        if (empty($orderId)) {
            return null;
        }

        $order = wc_get_order($orderId);

        return $order ?: null;
    }

    /**
     * 用 order ID 查詢訂單，找不到則丟出例外
     *
     * @param  int|null  $orderId
     * @return \WC_Order
     *
     * @throws OrderNotFoundException
     */
    public function findByIdOrFail($orderId)
    {
        $order = $this->findById($orderId);

        if (! $order) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    /**
     * 用 transactionId 查詢訂單
     *
     * @param  string|null  $transactionId
     * @return \WC_Order|null
     */
    public function findByTransactionId($transactionId)
    {
        if (empty($transactionId)) {
            return null;
        }

        $orders = wc_get_orders([
            'meta_key' => self::META_TRANSACTION_ID,
            'meta_value' => $transactionId,
            'limit' => 1,
        ]);

        return ! empty($orders) ? $orders[0] : null;
    }

    /**
     * 用 transactionId 查詢訂單，找不到則丟出例外
     *
     * @param  string|null  $transactionId
     * @return \WC_Order
     *
     * @throws OrderNotFoundException
     */
    public function findByTransactionIdOrFail($transactionId)
    {
        $order = $this->findByTransactionId($transactionId);

        if (! $order) {
            throw new OrderNotFoundException($transactionId);
        }

        return $order;
    }

    /**
     * 儲存 transactionId 到訂單
     *
     * @param  \WC_Order  $order
     * @param  string  $transactionId
     * @return void
     */
    public function saveTransactionId($order, $transactionId)
    {
        $order->update_meta_data(self::META_TRANSACTION_ID, $transactionId);
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

    /**
     * 新增訂單備註
     *
     * @param  \WC_Order  $order
     * @param  string  $note
     * @return void
     */
    public function addNote($order, $note)
    {
        $order->add_order_note($note);
    }

    /**
     * 將訂單標記為等待付款確認（on-hold）
     *
     * @param  \WC_Order  $order
     * @param  string  $message
     * @return void
     */
    public function markAsOnHold($order, $message = '')
    {
        $order->update_status('on-hold', $message);
    }

    /**
     * 將訂單標記為失敗
     *
     * @param  \WC_Order  $order
     * @param  string  $message
     * @return void
     */
    public function markAsFailed($order, $message = '')
    {
        $order->update_status('failed', $message);
    }

    /**
     * 完成訂單付款
     *
     * @param  \WC_Order  $order
     * @param  string|null  $transactionRef  交易參考碼
     * @param  string  $note  備註
     * @return void
     */
    public function markAsComplete($order, $transactionRef = null, $note = '')
    {
        $order->payment_complete($transactionRef);

        if (! empty($note)) {
            $order->add_order_note($note);
        }
    }

    /**
     * 儲存匯款帳號後5碼
     *
     * @param  \WC_Order  $order
     * @param  string  $last5
     * @return void
     */
    public function saveRemittanceLast5($order, $last5)
    {
        $order->update_meta_data(self::META_REMITTANCE_LAST5, $last5);
        $order->add_order_note(sprintf(__('客戶已填寫匯款帳號後5碼：%s', 'woocommerce-omnipay'), $last5));
        $order->save();
    }
}
