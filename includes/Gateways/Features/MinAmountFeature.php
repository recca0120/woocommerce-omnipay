<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Payment_Gateway;

/**
 * Minimum Amount Feature
 *
 * 最低金額限制功能
 */
class MinAmountFeature extends AbstractFeature
{
    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        $formFields['min_amount'] = [
            'title' => __('Minimum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Minimum order amount required for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(WC_Payment_Gateway $gateway): bool
    {
        $minAmount = (int) $gateway->get_option('min_amount', 0);

        if ($minAmount > 0) {
            $total = (float) WC()->cart->get_total('edit');

            if ($total < $minAmount) {
                return false;
            }
        }

        return true;
    }
}
