<?php

namespace WooCommerceOmnipay\Gateways\Concerns;

/**
 * Trait HasAmountLimits
 *
 * Provides amount limit functionality for payment gateways.
 * Handles minimum and maximum order amount validation.
 *
 * Usage:
 * - Call initMinAmountField() in init_form_fields() to add minimum amount field
 * - Call initMaxAmountField() in init_form_fields() to add maximum amount field
 * - Override is_available() to call validateMinAmount() and/or validateMaxAmount()
 */
trait HasAmountLimits
{
    /**
     * Initialize minimum amount form field
     */
    protected function initMinAmountField()
    {
        $this->form_fields['min_amount'] = [
            'title' => __('Minimum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Minimum order amount required for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];
    }

    /**
     * Initialize maximum amount form field
     */
    protected function initMaxAmountField()
    {
        $this->form_fields['max_amount'] = [
            'title' => __('Maximum Amount', 'woocommerce-omnipay'),
            'type' => 'number',
            'description' => __('Maximum order amount for this payment method (0 = no limit)', 'woocommerce-omnipay'),
            'default' => '0',
            'desc_tip' => true,
            'custom_attributes' => ['min' => '0'],
        ];
    }

    /**
     * Validate minimum amount limit against current order total
     *
     * @return bool
     */
    protected function validateMinAmount()
    {
        $minAmount = (int) $this->get_option('min_amount', 0);
        if ($minAmount > 0) {
            $total = $this->get_order_total();
            if ($total < $minAmount) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate maximum amount limit against current order total
     *
     * @return bool
     */
    protected function validateMaxAmount()
    {
        $maxAmount = (int) $this->get_option('max_amount', 0);
        if ($maxAmount > 0) {
            $total = $this->get_order_total();
            if ($total > $maxAmount) {
                return false;
            }
        }

        return true;
    }
}
