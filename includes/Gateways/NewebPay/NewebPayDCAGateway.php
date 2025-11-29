<?php

namespace WooCommerceOmnipay\Gateways\NewebPay;

use WooCommerceOmnipay\Gateways\NewebPayGateway;

/**
 * NewebPay 定期定額 Gateway
 */
class NewebPayDCAGateway extends NewebPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'CREDIT';

    /**
     * 定期定額方案
     *
     * @var array
     */
    protected $dca_periods = [];

    /**
     * Constructor
     *
     * @param  array  $config  Gateway 配置
     */
    public function __construct(array $config)
    {
        $config['gateway_id'] = $config['gateway_id'] ?? 'newebpay_dca';
        $config['title'] = $config['title'] ?? __('NewebPay Recurring Payment', 'woocommerce-omnipay');
        $config['description'] = $config['description'] ?? __('Pay with credit card recurring payment', 'woocommerce-omnipay');

        parent::__construct($config);

        // Load DCA periods from option
        $this->dca_periods = get_option('woocommerce_omnipay_newebpay_dca_periods', []);
    }

    /**
     * 初始化表單欄位
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        // Blocks mode settings (single period)
        $this->form_fields['dca_blocks_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_blocks_caption'] = [
            'title' => '',
            'type' => 'title',
            'description' => __('There are two section fields for DCA settings: WooCommerce Blocks and Woocommerce Shortcode. Please fill out the section that matches your current page configuration. If you are uncertain about which page configuration you are using, input the identical setting in both sections.', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_blocks_title'] = [
            'title' => __('DCA (Support WooCommerce Blocks)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the WooCommerce Blocks checkout page and do not support the use of the traditional shortcode-based checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periodType'] = [
            'title' => __('Period Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 'M',
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'options' => [
                'Y' => __('Year', 'woocommerce-omnipay'),
                'M' => __('Month', 'woocommerce-omnipay'),
                'W' => __('Week', 'woocommerce-omnipay'),
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['dca_periodPoint'] = [
            'title' => __('Period Point', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '',
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periodTimes'] = [
            'title' => __('Period Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 12,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->form_fields['dca_periodStartType'] = [
            'title' => __('Period Start Type', 'woocommerce-omnipay'),
            'type' => 'select',
            'default' => 2,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'options' => [
                '1' => __('1 - Authorize and start immediately', 'woocommerce-omnipay'),
                '2' => __('2 - Authorize only, start manually', 'woocommerce-omnipay'),
                '3' => __('3 - Delegate to merchant', 'woocommerce-omnipay'),
            ],
        ];

        // Shortcode mode settings (multiple periods table)
        $this->form_fields['dca_shortcode_line'] = [
            'title' => '<hr>',
            'type' => 'title',
        ];

        $this->form_fields['dca_shortcode_title'] = [
            'title' => __('DCA (Support WooCommerce Shortcode)', 'woocommerce-omnipay'),
            'type' => 'title',
            'description' => __('The following settings support the traditional shortcode-based checkout page and do not support the use of the WooCommerce Blocks checkout. Please configure carefully', 'woocommerce-omnipay'),
        ];

        $this->form_fields['dca_periods'] = [
            'title' => __('DCA Periods', 'woocommerce-omnipay'),
            'type' => 'dca_periods_newebpay',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * 生成 DCA 設定表格 HTML
     */
    public function generate_dca_periods_newebpay_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'class' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php echo wp_kses_post($data['title']); ?></th>
            <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                <table class="widefat wc_input_table sortable" cellspacing="0" style="width: 700px;">
                    <thead>
                        <tr>
                            <th class="sort">&nbsp;</th>
                            <th><?php esc_html_e('Period Type (Y/M/W/D)', 'woocommerce-omnipay'); ?></th>
                            <th><?php esc_html_e('Period Point', 'woocommerce-omnipay'); ?></th>
                            <th><?php esc_html_e('Period Times', 'woocommerce-omnipay'); ?></th>
                            <th><?php esc_html_e('Start Type (1/2/3)', 'woocommerce-omnipay'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="accounts">
                        <?php
                        $i = -1;
        if (! empty($this->dca_periods) && is_array($this->dca_periods)) {
            foreach ($this->dca_periods as $period) {
                $i++;
                echo '<tr class="account">
                                    <td class="sort"></td>
                                    <td><input type="text" value="'.esc_attr($period['periodType']).'" name="newebpay_dca_periodType['.$i.']" maxlength="1" required /></td>
                                    <td><input type="text" value="'.esc_attr($period['periodPoint']).'" name="newebpay_dca_periodPoint['.$i.']" /></td>
                                    <td><input type="number" value="'.esc_attr($period['periodTimes']).'" name="newebpay_dca_periodTimes['.$i.']" min="1" required /></td>
                                    <td><input type="number" value="'.esc_attr($period['periodStartType']).'" name="newebpay_dca_periodStartType['.$i.']" min="1" max="3" required /></td>
                                </tr>';
            }
        } else {
            // Default period
            echo '<tr class="account">
                                <td class="sort"></td>
                                <td><input type="text" value="M" name="newebpay_dca_periodType[0]" maxlength="1" required /></td>
                                <td><input type="text" value="" name="newebpay_dca_periodPoint[0]" /></td>
                                <td><input type="number" value="12" name="newebpay_dca_periodTimes[0]" min="1" required /></td>
                                <td><input type="number" value="2" name="newebpay_dca_periodStartType[0]" min="1" max="3" required /></td>
                            </tr>';
        }
        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5">
                                <a href="#" class="add button"><?php esc_html_e('Add Period', 'woocommerce-omnipay'); ?></a>
                                <a href="#" class="remove_rows button"><?php esc_html_e('Remove Selected', 'woocommerce-omnipay'); ?></a>
                            </th>
                        </tr>
                    </tfoot>
                </table>
                <script type="text/javascript">
                    jQuery(function($) {
                        $('#<?php echo esc_js($field_key); ?>').on('click', '.add', function(e) {
                            e.preventDefault();
                            var size = $('#<?php echo esc_js($field_key); ?> tbody .account').length;
                            $('<tr class="account">\
                                <td class="sort"></td>\
                                <td><input type="text" value="M" name="newebpay_dca_periodType[' + size + ']" maxlength="1" required /></td>\
                                <td><input type="text" value="" name="newebpay_dca_periodPoint[' + size + ']" /></td>\
                                <td><input type="number" value="12" name="newebpay_dca_periodTimes[' + size + ']" min="1" required /></td>\
                                <td><input type="number" value="2" name="newebpay_dca_periodStartType[' + size + ']" min="1" max="3" required /></td>\
                            </tr>').appendTo('#<?php echo esc_js($field_key); ?> table tbody');
                        });

                        $('#<?php echo esc_js($field_key); ?>').on('click', '.remove_rows', function(e) {
                            e.preventDefault();
                            $('#<?php echo esc_js($field_key); ?> tbody tr').remove();
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * 處理管理選項更新
     */
    public function process_admin_options()
    {
        // Save DCA periods
        $dca_periods = [];
        if (isset($_POST['newebpay_dca_periodType']) && is_array($_POST['newebpay_dca_periodType'])) {
            $periodTypes = array_map('sanitize_text_field', $_POST['newebpay_dca_periodType']);
            $periodPoints = isset($_POST['newebpay_dca_periodPoint']) && is_array($_POST['newebpay_dca_periodPoint'])
                ? array_map('sanitize_text_field', $_POST['newebpay_dca_periodPoint'])
                : [];
            $periodTimes = isset($_POST['newebpay_dca_periodTimes']) && is_array($_POST['newebpay_dca_periodTimes'])
                ? array_map('absint', $_POST['newebpay_dca_periodTimes'])
                : [];
            $periodStartTypes = isset($_POST['newebpay_dca_periodStartType']) && is_array($_POST['newebpay_dca_periodStartType'])
                ? array_map('absint', $_POST['newebpay_dca_periodStartType'])
                : [];

            foreach ($periodTypes as $i => $periodType) {
                if (! empty($periodType)) {
                    $dca_periods[] = [
                        'periodType' => $periodType,
                        'periodPoint' => $periodPoints[$i] ?? '',
                        'periodTimes' => $periodTimes[$i] ?? 0,
                        'periodStartType' => $periodStartTypes[$i] ?? 0,
                    ];
                }
            }
        }
        update_option('woocommerce_omnipay_newebpay_dca_periods', $dca_periods);

        return parent::process_admin_options();
    }

    /**
     * 檢查付款方式是否可用
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        // 未設定定期定額選項時，不開放此付款方式
        if (! (function_exists('is_checkout') && is_checkout())) {
            return true;
        }

        // 新版 WooCommerce Blocks - 檢查單一方案設定
        if (function_exists('has_block') && has_block('woocommerce/checkout')) {
            return ! (empty($this->get_option('dca_periodType'))
                || empty($this->get_option('dca_periodTimes'))
                || empty($this->get_option('dca_periodStartType')));
        }

        // 舊版傳統結帳 - 檢查多組方案設定
        return ! empty($this->dca_periods);
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo '<p>'.wp_kses_post($this->description).'</p>';
        }

        // 只有 Shortcode 版本才顯示下拉選單
        // Blocks 版本不需要顯示（直接使用設定的方案）
        if (is_checkout() && ! is_wc_endpoint_url('order-pay')) {
            $total = WC()->cart ? WC()->cart->total : 0;

            $periodTypeLabels = [
                'Y' => __('year', 'woocommerce-omnipay'),
                'M' => __('month', 'woocommerce-omnipay'),
                'W' => __('week', 'woocommerce-omnipay'),
                'D' => __('day', 'woocommerce-omnipay'),
            ];

            echo '<p><select id="omnipay_dca_period" name="omnipay_dca_period">';

            foreach ($this->dca_periods as $period) {
                $value = $period['periodType'].'_'.$period['periodPoint'].'_'.$period['periodTimes'].'_'.$period['periodStartType'];
                $label = sprintf(
                    __('%s / %s, up to a maximum of %s', 'woocommerce-omnipay'),
                    wc_price($total),
                    $periodTypeLabels[$period['periodType']] ?? $period['periodType'],
                    $period['periodTimes']
                );
                echo '<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';
            }

            echo '</select></p>';
        }
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function preparePaymentData($order)
    {
        $data = parent::preparePaymentData($order);
        $data['CREDIT'] = '1';

        if (! isset($_POST['omnipay_dca_period'])) {
            // Blocks 模式：從設定讀取單一方案
            $data['PeriodType'] = $this->get_option('dca_periodType', 'M');
            $data['PeriodPoint'] = $this->get_option('dca_periodPoint', '');
            $data['PeriodTimes'] = (int) $this->get_option('dca_periodTimes', 12);
            $data['PeriodStartType'] = (int) $this->get_option('dca_periodStartType', 2);
        } else {
            // Shortcode 模式：從 POST 讀取用戶選擇
            $selectedPeriod = sanitize_text_field($_POST['omnipay_dca_period']);
            $parts = explode('_', $selectedPeriod);
            if (count($parts) === 4) {
                [$periodType, $periodPoint, $periodTimes, $periodStartType] = $parts;
                $data['PeriodType'] = $periodType;
                $data['PeriodPoint'] = $periodPoint;
                $data['PeriodTimes'] = (int) $periodTimes;
                $data['PeriodStartType'] = (int) $periodStartType;
            } else {
                // Fallback to default values if format is invalid
                $data['PeriodType'] = 'M';
                $data['PeriodPoint'] = '';
                $data['PeriodTimes'] = 12;
                $data['PeriodStartType'] = 2;
            }
        }

        $data['PeriodAmt'] = (int) $order->get_total();

        // PayerEmail 是定期定額的必填欄位
        $payerEmail = $order->get_billing_email();
        if (empty($payerEmail)) {
            // 如果訂單沒有 email，使用客戶 email 或網站管理員 email
            $payerEmail = $order->get_billing_email() ?: get_bloginfo('admin_email');
        }
        $data['PayerEmail'] = $payerEmail;

        return $data;
    }
}
