<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Payment_Gateway;

/**
 * Maximum Amount Feature
 *
 * 最高金額限制功能
 */
class MaxAmountFeature extends AbstractFeature
{
    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        $formFields['max_amount'] = [
            'title' => __('Maximum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Maximum order amount for this payment method (0 = no limit)', 'woocommerce-omnipay'),
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
        $maxAmount = (int) $gateway->get_option('max_amount', 0);

        if ($maxAmount > 0) {
            $total = (float) WC()->cart->get_total('edit');

            if ($total > $maxAmount) {
                return false;
            }
        }

        return true;
    }
}
