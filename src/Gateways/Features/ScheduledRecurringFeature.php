<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features;

use WC_Order;
use WC_Payment_Gateway;

/**
 * Scheduled Recurring Payment Feature
 *
 * 排程式定期付款（指定日期/星期執行）
 * 使用 periodType + periodPoint + periodTimes + periodStartType 參數
 */
class ScheduledRecurringFeature extends AbstractRecurringFeature
{
    /**
     * {@inheritdoc}
     */
    public function preparePaymentData(array $data, WC_Order $order, WC_Payment_Gateway $gateway): array
    {
        $data = parent::preparePaymentData($data, $order, $gateway);

        // PayerEmail is required for recurring payment
        $data['PayerEmail'] = $order->get_billing_email() ?: get_bloginfo('admin_email');

        return $data;
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
                'W' => __('Week', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $formFields['periodPoint'] = [
            'title' => __('Period Point', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '01',
            'description' => __('Y: MMDD (e.g., 0315), M: 01-31, W: 1-7, D: 2-999', 'woocommerce-omnipay'),
        ];

        $formFields['periodTimes'] = [
            'title' => __('Period Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => '',
            'custom_attributes' => ['min' => 2, 'max' => 99, 'step' => 1],
        ];

        $formFields['periodStartType'] = [
            'title' => __('Period Start Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => '2',
            'description' => '',
            'options' => [
                '1' => __('1 - Authorize and start immediately', 'woocommerce-omnipay'),
                '2' => __('2 - Authorize only, start manually', 'woocommerce-omnipay'),
                '3' => __('3 - Delegate to merchant', 'woocommerce-omnipay'),
            ],
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
                'name' => 'periodPoint',
                'type' => 'text',
                'default' => '01',
                'attributes' => ['required' => 'required', 'placeholder' => 'Y:MMDD M:DD W:1-7 D:2-999'],
            ],
            [
                'name' => 'periodTimes',
                'type' => 'number',
                'default' => 2,
                'attributes' => ['min' => '2', 'max' => '99', 'required' => 'required'],
            ],
            [
                'name' => 'periodStartType',
                'type' => 'number',
                'default' => 2,
                'attributes' => ['min' => '1', 'max' => '3', 'required' => 'required'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPeriod(): array
    {
        return [
            'periodType' => 'M',
            'periodPoint' => '01',
            'periodTimes' => 2,
            'periodStartType' => '2',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormTemplate(): string
    {
        return 'checkout/scheduled-recurring-form.php';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAdminTemplate(): string
    {
        return 'admin/scheduled-recurring-periods-table.php';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAmountFieldName(): string
    {
        return 'PeriodAmt';
    }

    /**
     * {@inheritdoc}
     */
    protected function getBlocksModeDcaData(WC_Payment_Gateway $gateway): array
    {
        return [
            'PeriodType' => $gateway->get_option('periodType', 'M'),
            'PeriodPoint' => $gateway->get_option('periodPoint', '1'),
            'PeriodTimes' => (int) $gateway->get_option('periodTimes', 2),
            'PeriodStartType' => (int) $gateway->get_option('periodStartType', 2),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getShortcodeModeDcaData(): array
    {
        $selectedPeriod = sanitize_text_field($_POST['omnipay_period'] ?? '');
        $parts = explode('_', $selectedPeriod);

        if (count($parts) === 4) {
            [$periodType, $periodPoint, $periodTimes, $periodStartType] = $parts;

            return [
                'PeriodType' => $periodType,
                'PeriodPoint' => $periodPoint,
                'PeriodTimes' => (int) $periodTimes,
                'PeriodStartType' => (int) $periodStartType,
            ];
        }

        return [
            'PeriodType' => 'M',
            'PeriodPoint' => '1',
            'PeriodTimes' => 12,
            'PeriodStartType' => 2,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function validatePeriodConstraints(array $values): string
    {
        $periodType = $values['periodType'] ?? '';
        $periodPoint = $values['periodPoint'] ?? '';
        $periodTimes = $values['periodTimes'] ?? 0;

        // 驗證 PeriodPoint 格式
        $pointError = $this->validatePeriodPoint($periodType, $periodPoint);
        if ($pointError) {
            return $pointError;
        }

        $constraints = [
            'Y' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to year, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'M' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to month, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'W' => [
                'periodTimes' => [2, 99],
                'message' => __('When the periodType field is set to week, The periodTimes field can only be between 2 and 99.', 'woocommerce-omnipay'),
            ],
            'D' => [
                'periodTimes' => [2, 999],
                'message' => __('When the periodType field is set to day, The periodTimes field can only be between 2 and 999.', 'woocommerce-omnipay'),
            ],
        ];

        if (! isset($constraints[$periodType])) {
            return '';
        }

        $config = $constraints[$periodType];
        [$minTimes, $maxTimes] = $config['periodTimes'];

        if ($periodTimes < $minTimes || $periodTimes > $maxTimes) {
            return $config['message'].' ';
        }

        return '';
    }

    /**
     * 驗證 PeriodPoint 格式
     */
    private function validatePeriodPoint(string $periodType, string $periodPoint): string
    {
        if ($periodType === 'Y') {
            if (! preg_match('/^\d{4}$/', $periodPoint)) {
                return __('For yearly periods, PeriodPoint must be in MMDD format (e.g., 0315 for March 15th).', 'woocommerce-omnipay').' ';
            }
            $month = (int) substr($periodPoint, 0, 2);
            $day = (int) substr($periodPoint, 2, 2);
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return __('Invalid date in PeriodPoint. Month must be 01-12, day must be 01-31.', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'M') {
            $day = (int) $periodPoint;
            if ($day < 1 || $day > 31) {
                return __('For monthly periods, PeriodPoint must be 1-31 (day of month).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'W') {
            $weekday = (int) $periodPoint;
            if ($weekday < 1 || $weekday > 7) {
                return __('For weekly periods, PeriodPoint must be 1-7 (1=Monday, 7=Sunday).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        if ($periodType === 'D') {
            $interval = (int) $periodPoint;
            if ($interval < 2 || $interval > 999) {
                return __('For daily periods, PeriodPoint must be 2-999 (day interval).', 'woocommerce-omnipay').' ';
            }

            return '';
        }

        return '';
    }
}
