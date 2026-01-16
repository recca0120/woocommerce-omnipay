<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Abstract Feature
 *
 * 提供 GatewayFeature 的預設實作
 * 子類只需覆寫需要的方法
 */
abstract class AbstractFeature implements GatewayFeature
{
    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        // 預設不加欄位
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(WC_Payment_Gateway $gateway): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function paymentFields(WC_Payment_Gateway $gateway): void
    {
        // 預設不顯示欄位
    }

    /**
     * {@inheritdoc}
     */
    public function validateFields(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPaymentFields(): bool
    {
        return false;
    }
}
