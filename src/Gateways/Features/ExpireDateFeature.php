<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Expire Date Feature
 *
 * 繳費期限功能（用於 ATM、CVS、BARCODE 等離線付款）
 */
class ExpireDateFeature extends AbstractFeature
{
    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var int
     */
    private $defaultDays;

    /**
     * @var int
     */
    private $minDays;

    /**
     * @var int
     */
    private $maxDays;

    /**
     * @param  string  $fieldName  付款資料中的欄位名稱
     * @param  int  $defaultDays  預設天數
     * @param  int  $minDays  最小天數
     * @param  int  $maxDays  最大天數
     */
    public function __construct(
        string $fieldName = 'ExpireDate',
        int $defaultDays = 3,
        int $minDays = 1,
        int $maxDays = 60
    ) {
        $this->fieldName = $fieldName;
        $this->defaultDays = $defaultDays;
        $this->minDays = $minDays;
        $this->maxDays = $maxDays;
    }

    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        $formFields['expire_date'] = [
            'title' => __('Payment Expiry Days', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => sprintf(
                __('Payment expiry period, range %d-%d days', 'woocommerce-omnipay'),
                $this->minDays,
                $this->maxDays
            ),
            'default' => (string) $this->defaultDays,
            'desc_tip' => true,
            'custom_attributes' => [
                'min' => (string) $this->minDays,
                'max' => (string) $this->maxDays,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        $expireDays = (int) $gateway->get_option('expire_date', $this->defaultDays);

        // 確保在範圍內
        $expireDays = max($this->minDays, min($this->maxDays, $expireDays));

        $data[$this->fieldName] = $expireDays;

        return $data;
    }
}
