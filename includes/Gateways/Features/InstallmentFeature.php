<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Installment Feature
 *
 * 信用卡分期功能
 */
class InstallmentFeature extends AbstractFeature
{
    /**
     * @var string API 欄位名稱
     */
    private $fieldName;

    /**
     * @var array 可用的分期選項
     */
    private $options;

    /**
     * @var array 預設啟用的分期
     */
    private $defaults;

    /**
     * @var array 期數規則 (如 ['30' => ['min_amount' => 20000]])
     */
    private $periodRules;

    /**
     * @param  string  $fieldName  API 欄位名稱（必填）
     * @param  array  $config  配置選項
     *                         - options: 可用的分期選項
     *                         - defaults: 預設啟用的分期
     *                         - periodRules: 期數規則 (如 ['30' => ['min_amount' => 20000]])
     */
    public function __construct(string $fieldName, array $config = [])
    {
        $this->fieldName = $fieldName;
        $this->options = $config['options'] ?? $this->getDefaultOptions();
        $this->defaults = $config['defaults'] ?? ['3', '6', '12', '18', '24'];
        $this->periodRules = $config['periodRules'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        $formFields['installments'] = [
            'title' => __('Installment Periods', 'woocommerce-omnipay'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'description' => __('Select available installment periods', 'woocommerce-omnipay'),
            'default' => $this->defaults,
            'desc_tip' => true,
            'options' => $this->options,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function paymentFields(WC_Payment_Gateway $gateway): void
    {
        $installments = $gateway->get_option('installments', $this->defaults);

        if (! is_array($installments)) {
            $installments = $this->defaults;
        }

        echo woocommerce_omnipay_get_template('checkout/installment-form.php', [
            'installments' => $installments,
            'total' => WC()->cart->get_total('edit'),
            'period_rules' => $this->periodRules,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        $selectedInstallment = $this->getSelectedInstallment();

        if (! empty($selectedInstallment)) {
            $data[$this->fieldName] = $selectedInstallment;
        } else {
            $data[$this->fieldName] = $this->getInstallmentsString($gateway);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPaymentFields(): bool
    {
        return true;
    }

    /**
     * 取得預設分期選項
     */
    private function getDefaultOptions(): array
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
     * 取得使用者選擇的分期
     */
    private function getSelectedInstallment(): string
    {
        return isset($_POST['omnipay_installment'])
            ? sanitize_text_field($_POST['omnipay_installment'])
            : '';
    }

    /**
     * 取得分期字串（逗號分隔）
     */
    private function getInstallmentsString(WC_Payment_Gateway $gateway): string
    {
        $installments = $gateway->get_option('installments', $this->defaults);

        if (! is_array($installments)) {
            return $installments;
        }

        return implode(',', $installments);
    }
}
