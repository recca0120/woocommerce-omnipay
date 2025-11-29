<?php

namespace WooCommerceOmnipay\Gateways\ECPay;

use WooCommerceOmnipay\Gateways\ECPayGateway;

/**
 * ECPay 定期定額 Gateway
 */
class ECPayDCAGateway extends ECPayGateway
{
    /**
     * 付款方式
     *
     * @var string
     */
    protected $paymentType = 'Credit';

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
        $config['gateway_id'] = $config['gateway_id'] ?? 'ecpay_dca';
        $config['title'] = $config['title'] ?? '綠界定期定額';
        $config['description'] = $config['description'] ?? '使用信用卡定期定額付款';

        parent::__construct($config);

        // Load DCA periods from option
        $this->dca_periods = get_option('woocommerce_omnipay_ecpay_dca_periods', []);
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
                'D' => __('Day', 'woocommerce-omnipay'),
            ],
        ];

        $this->form_fields['dca_frequency'] = [
            'title' => __('Frequency', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 1,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 1,
                'step' => 1,
            ],
        ];

        $this->form_fields['dca_execTimes'] = [
            'title' => __('Execute Times', 'woocommerce-omnipay'),
            'type' => 'number',
            'default' => 2,
            'description' => __('Support WooCommerce checkout blocks', 'woocommerce-omnipay'),
            'custom_attributes' => [
                'min' => 2,
                'step' => 1,
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
            'type' => 'dca_periods',
            'default' => '',
            'description' => '',
        ];
    }

    /**
     * 生成 DCA 設定表格 HTML
     */
    public function generate_dca_periods_html($key, $data)
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
                <table class="widefat wc_input_table sortable" cellspacing="0" style="width: 600px;">
                    <thead>
                        <tr>
                            <th class="sort">&nbsp;</th>
                            <th><?php esc_html_e('Period Type (Y/M/D)', 'woocommerce-omnipay'); ?></th>
                            <th><?php esc_html_e('Frequency', 'woocommerce-omnipay'); ?></th>
                            <th><?php esc_html_e('Execute Times', 'woocommerce-omnipay'); ?></th>
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
                                    <td><input type="text" value="'.esc_attr($period['periodType']).'" name="dca_periodType['.$i.']" maxlength="1" required /></td>
                                    <td><input type="number" value="'.esc_attr($period['frequency']).'" name="dca_frequency['.$i.']" min="1" max="365" required /></td>
                                    <td><input type="number" value="'.esc_attr($period['execTimes']).'" name="dca_execTimes['.$i.']" min="2" max="999" required /></td>
                                </tr>';
            }
        } else {
            // Default periods
            echo '<tr class="account">
                                <td class="sort"></td>
                                <td><input type="text" value="M" name="dca_periodType[0]" maxlength="1" required /></td>
                                <td><input type="number" value="1" name="dca_frequency[0]" min="1" max="365" required /></td>
                                <td><input type="number" value="12" name="dca_execTimes[0]" min="2" max="999" required /></td>
                            </tr>';
        }
        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">
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
                                <td><input type="text" value="M" name="dca_periodType[' + size + ']" maxlength="1" required /></td>\
                                <td><input type="number" value="1" name="dca_frequency[' + size + ']" min="1" max="365" required /></td>\
                                <td><input type="number" value="12" name="dca_execTimes[' + size + ']" min="2" max="999" required /></td>\
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
        if (isset($_POST['dca_periodType'])) {
            $periodTypes = array_map('sanitize_text_field', $_POST['dca_periodType']);
            $frequencies = array_map('absint', $_POST['dca_frequency']);
            $execTimes = array_map('absint', $_POST['dca_execTimes']);

            foreach ($periodTypes as $i => $periodType) {
                if (! empty($periodType)) {
                    $dca_periods[] = [
                        'periodType' => $periodType,
                        'frequency' => $frequencies[$i],
                        'execTimes' => $execTimes[$i],
                    ];
                }
            }
        }
        update_option('woocommerce_omnipay_ecpay_dca_periods', $dca_periods);

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
        if (function_exists('is_checkout') && is_checkout()) {
            if (function_exists('has_block') && has_block('woocommerce/checkout')) {
                // 新版 WooCommerce Blocks - 檢查單一方案設定
                $periodType = $this->get_option('dca_periodType');
                $frequency = $this->get_option('dca_frequency');
                $execTimes = $this->get_option('dca_execTimes');

                if (empty($periodType) || empty($frequency) || empty($execTimes)) {
                    return false;
                }
            } else {
                // 舊版傳統結帳 - 檢查多組方案設定
                if (empty($this->dca_periods)) {
                    return false;
                }
            }
        }

        return true;
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
                'Y' => ' '.__('year', 'woocommerce-omnipay'),
                'M' => ' '.__('month', 'woocommerce-omnipay'),
                'D' => ' '.__('day', 'woocommerce-omnipay'),
            ];

            echo '<select id="omnipay_dca_period" name="omnipay_dca_period">';

            foreach ($this->dca_periods as $period) {
                $value = $period['periodType'].'_'.$period['frequency'].'_'.$period['execTimes'];
                $label = sprintf(
                    __('NT$ %d / %s %s, up to a maximum of %s', 'woocommerce-omnipay'),
                    $total,
                    $period['frequency'],
                    $periodTypeLabels[$period['periodType']] ?? $period['periodType'],
                    $period['execTimes']
                );
                echo '<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';
            }

            echo '</select>';
            echo '<div id="omnipay_dca_show"></div>';
            echo '<hr style="margin: 12px 0px;background-color: #eeeeee;">';
            echo '<p style="font-size: 0.8em;color: #c9302c;">';
            echo esc_html__('You will use ECPay recurring credit card payment. Please note that the products you purchased are non-single payment products.', 'woocommerce-omnipay');
            echo '</p>';
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
        $data['ChoosePayment'] = $this->paymentType;

        if (! isset($_POST['omnipay_dca_period'])) {
            // Blocks 模式：從設定讀取單一方案
            $data['PeriodType'] = $this->get_option('dca_periodType', 'M');
            $data['Frequency'] = (int) $this->get_option('dca_frequency', 1);
            $data['ExecTimes'] = (int) $this->get_option('dca_execTimes', 2);
        } else {
            // Shortcode 模式：從 POST 讀取用戶選擇
            $selectedPeriod = sanitize_text_field($_POST['omnipay_dca_period']);
            $parts = explode('_', $selectedPeriod);
            if (count($parts) === 3) {
                [$periodType, $frequency, $execTimes] = $parts;
                $data['PeriodType'] = $periodType;
                $data['Frequency'] = (int) $frequency;
                $data['ExecTimes'] = (int) $execTimes;
            }
        }

        $data['PeriodAmount'] = (int) $order->get_total();

        return $data;
    }
}
