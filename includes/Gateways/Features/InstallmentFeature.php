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
     * @var bool 是否需要 ECPay 圓夢分期 (30N) 處理
     */
    private $requiresDreamInstallment;

    /**
     * @param  string  $fieldName  API 欄位名稱 (如 'CreditInstallment' 或 'InstFlag')
     * @param  array  $options  可用的分期選項
     * @param  array  $defaults  預設啟用的分期
     * @param  bool  $requiresDreamInstallment  是否需要圓夢分期處理
     */
    public function __construct(
        string $fieldName = 'CreditInstallment',
        array $options = [],
        array $defaults = [],
        bool $requiresDreamInstallment = false
    ) {
        $this->fieldName = $fieldName;
        $this->options = $options ?: $this->getDefaultOptions();
        $this->defaults = $defaults ?: ['3', '6', '12', '18', '24'];
        $this->requiresDreamInstallment = $requiresDreamInstallment;
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
            'validate_30_min_amount' => $this->requiresDreamInstallment,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        $selectedInstallment = $this->getSelectedInstallment();

        if (! empty($selectedInstallment)) {
            $data[$this->fieldName] = $this->convertInstallmentValue($selectedInstallment);
        } else {
            $data[$this->fieldName] = $this->getInstallmentsString($gateway);
        }

        return $data;
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
     * 轉換分期值（ECPay 圓夢分期）
     */
    private function convertInstallmentValue(string $value): string
    {
        if ($this->requiresDreamInstallment && $value === '30') {
            return '30N';
        }

        return $value;
    }

    /**
     * 取得分期字串（逗號分隔）
     */
    private function getInstallmentsString(WC_Payment_Gateway $gateway): string
    {
        $installments = $gateway->get_option('installments', $this->defaults);

        if (! is_array($installments)) {
            return $this->convertInstallmentValue($installments);
        }

        $converted = array_map(function ($value) {
            return $this->convertInstallmentValue($value);
        }, $installments);

        return implode(',', $converted);
    }
}
