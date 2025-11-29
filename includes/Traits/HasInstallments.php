<?php

namespace WooCommerceOmnipay\Traits;

/**
 * Trait HasInstallments
 *
 * Provides installment functionality for credit card payment gateways.
 * Handles installment periods selection and payment data preparation.
 *
 * Usage:
 * - Call initInstallmentsField() in init_form_fields() to add installments field
 * - Call displayInstallmentFields() in payment_fields() to show installment selection
 * - Override getInstallmentFieldName() to specify the API parameter name
 * - Override requiresDreamInstallment() to enable ECPay Dream Installment (30N) handling
 */
trait HasInstallments
{
    /**
     * Get the installment field name for API
     * Override in child class (e.g., 'CreditInstallment' for ECPay, 'InstFlag' for NewebPay)
     */
    abstract protected function getInstallmentFieldName(): string;

    /**
     * Whether this gateway requires ECPay Dream Installment (30N) handling
     * When enabled:
     * - Converts '30' to '30N' when sending to API
     * - Validates minimum amount (20000) for 30 period installment
     */
    protected function requiresDreamInstallment(): bool
    {
        return false;
    }

    /**
     * Get available installment options
     * Override to customize options per provider
     */
    protected function getInstallmentOptions(): array
    {
        return [
            '3' => __('3 installments', 'woocommerce-omnipay'),
            '6' => __('6 installments', 'woocommerce-omnipay'),
            '12' => __('12 installments', 'woocommerce-omnipay'),
            '18' => __('18 installments', 'woocommerce-omnipay'),
            '24' => __('24 installments', 'woocommerce-omnipay'),
            '30' => __('30 installments', 'woocommerce-omnipay'),
        ];
    }

    /**
     * Get default installments
     */
    protected function getDefaultInstallments(): array
    {
        return ['3', '6', '12', '18', '24'];
    }

    /**
     * Initialize installments form field
     */
    protected function initInstallmentsField()
    {
        $this->form_fields['installments'] = [
            'title' => __('Installment Periods', 'woocommerce-omnipay'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('Select available installment periods', 'woocommerce-omnipay'),
            'default' => $this->getDefaultInstallments(),
            'desc_tip' => true,
            'options' => $this->getInstallmentOptions(),
        ];
    }

    /**
     * Display installment payment fields
     */
    protected function displayInstallmentFields()
    {
        $installments = $this->get_option('installments', $this->getDefaultInstallments());

        // Ensure installments is an array
        if (! is_array($installments)) {
            $installments = $this->getDefaultInstallments();
        }

        echo woocommerce_omnipay_get_template('checkout/installment-form.php', [
            'installments' => $installments,
            'total' => $this->get_order_total(),
            'validate_30_min_amount' => $this->requiresDreamInstallment(),
        ]);
    }

    /**
     * Get selected installment from POST data
     */
    protected function getSelectedInstallment(): string
    {
        return isset($_POST['omnipay_installment'])
            ? sanitize_text_field($_POST['omnipay_installment'])
            : '';
    }

    /**
     * Convert installment value for API
     * ECPay Dream Installment: converts '30' to '30N'
     * Other gateways: keeps original value
     */
    protected function convertInstallmentValue(string $value): string
    {
        if ($this->requiresDreamInstallment() && $value === '30') {
            return '30N';
        }

        return $value;
    }

    /**
     * Get installments as comma-separated string
     */
    protected function getInstallmentsString(): string
    {
        $installments = $this->get_option('installments', $this->getDefaultInstallments());

        if (! is_array($installments)) {
            return $this->convertInstallmentValue($installments);
        }

        // Convert each installment value
        $converted = array_map(function ($value) {
            return $this->convertInstallmentValue($value);
        }, $installments);

        return implode(',', $converted);
    }

    /**
     * Prepare installment data for payment
     */
    protected function prepareInstallmentData(): array
    {
        $selectedInstallment = $this->getSelectedInstallment();
        $fieldName = $this->getInstallmentFieldName();

        if (! empty($selectedInstallment)) {
            return [$fieldName => $this->convertInstallmentValue($selectedInstallment)];
        }

        return [$fieldName => $this->getInstallmentsString()];
    }
}
