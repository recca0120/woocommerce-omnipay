<?php

namespace WooCommerceOmnipay\Gateways\Features;

use WC_Payment_Gateway;

/**
 * Frequency-based Recurring Payment Feature
 *
 * 頻率式定期付款（每隔 N 天/月/年執行一次）
 * 使用 periodType + frequency + execTimes 參數
 */
class FrequencyRecurringFeature extends AbstractRecurringFeature
{
    /**
     * {@inheritdoc}
     */
    protected function getFieldConfigs(): array
    {
        return [
            [
                'name' => 'periodType',
                'type' => 'text',
                'default' => 'M',
                'attributes' => ['maxlength' => '1', 'required' => 'required'],
            ],
            [
                'name' => 'frequency',
                'type' => 'number',
                'default' => 1,
                'attributes' => ['min' => '1', 'max' => '365', 'required' => 'required'],
            ],
            [
                'name' => 'execTimes',
                'type' => 'number',
                'default' => 12,
                'attributes' => ['min' => '1', 'max' => '999', 'required' => 'required'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPeriod(): array
    {
        return ['periodType' => 'M', 'frequency' => 1, 'execTimes' => 12];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormTemplate(): string
    {
        return 'checkout/frequency-recurring-form.php';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAdminTemplate(): string
    {
        return 'admin/frequency-recurring-periods-table.php';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAmountFieldName(): string
    {
        return 'PeriodAmount';
    }

    /**
     * {@inheritdoc}
     */
    protected function getBlocksModeDcaData(WC_Payment_Gateway $gateway): array
    {
        return [
            'PeriodType' => $gateway->get_option('periodType', 'M'),
            'Frequency' => (int) $gateway->get_option('frequency', 1),
            'ExecTimes' => (int) $gateway->get_option('execTimes', 2),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period'] ?? '');
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 3) {
            [$periodType, $frequency, $execTimes] = $parts;

            return [
                'PeriodType' => $periodType,
                'Frequency' => (int) $frequency,
                'ExecTimes' => (int) $execTimes,
            ];
        }

        return [
            'PeriodType' => 'M',
            'Frequency' => 1,
            'ExecTimes' => 2,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initFormFields(array &$formFields): void
    {
        // Blocks 與 Shortcode 說明區塊
        $formFields['blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $formFields['blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('Configure settings for both WooCommerce Blocks and Shortcode checkout. Fill in the section matching your checkout page type.', 'woocommerce-omnipay'),
        ];

        $formFields['blocks_title'] = [
            'title' => __('WooCommerce Blocks Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using WooCommerce Blocks checkout page.', 'woocommerce-omnipay'),
        ];

        // Blocks 模式欄位
        $formFields['periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'M',
            'description' => '',
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $formFields['frequency'] = [
            'title' => __('Frequency', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 1,
            'description' => '',
            'custom_attributes' => ['min' => 1, 'step' => 1],
        ];

        $formFields['execTimes'] = [
            'title' => __('Execute Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => ['min' => 1, 'step' => 1],
        ];

        // Shortcode 模式欄位
        $formFields['shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $formFields['shortcode_title'] = [
            'title' => __('Shortcode Checkout', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('These settings apply when using traditional shortcode-based checkout page.', 'woocommerce-omnipay'),
        ];

        $formFields['periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'periods',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function validatePeriodConstraints(array $values): string
    {
        $periodType = $values['periodType'] ?? '';
        $frequency = $values['frequency'] ?? 0;
        $execTimes = $values['execTimes'] ?? 0;

        $constraints = [
            'Y' => [
                'frequency' => [1, 1],
                'execTimes' => [1, 9],
                'messages' => [
                    'frequency' => __('When the periodType field is set to year, the execution frequency field can only be set to 1.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to year, The execTimes field can only be between 1 and 9.', 'woocommerce-omnipay'),
                ],
            ],
            'M' => [
                'frequency' => [1, 12],
                'execTimes' => [1, 99],
                'messages' => [
                    'frequency' => __('When the periodType field is set to month, The frequency field can only be between 1 and 12.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to month, The execTimes field can only be between 1 and 99.', 'woocommerce-omnipay'),
                ],
            ],
            'D' => [
                'frequency' => [1, 365],
                'execTimes' => [1, 999],
                'messages' => [
                    'frequency' => __('When the periodType field is set to day, The frequency field can only be between 1 and 365.', 'woocommerce-omnipay'),
                    'execTimes' => __('When the periodType field is set to day, The execTimes field can only be between 1 and 999.', 'woocommerce-omnipay'),
                ],
            ],
        ];

        if (! isset($constraints[$periodType])) {
            return '';
        }

        $config = $constraints[$periodType];
        $errors = [];

        [$minFreq, $maxFreq] = $config['frequency'];
        if ($frequency < $minFreq || $frequency > $maxFreq) {
            $errors[] = $config['messages']['frequency'];
        }

        [$minExec, $maxExec] = $config['execTimes'];
        if ($execTimes < $minExec || $execTimes > $maxExec) {
            $errors[] = $config['messages']['execTimes'];
        }

        return implode(' ', $errors);
    }
}
