<?php

namespace WooCommerceOmnipay;

use WooCommerceOmnipay\Services\OmnipayBridge;

/**
 * 共用設定頁面
 *
 * 在 WooCommerce > 設定 > 付款 下新增 Omnipay gateway 的共用設定區塊
 */
class SharedSettingsPage
{
    /**
     * @var string Omnipay gateway 名稱
     */
    private $omnipay_name;

    /**
     * @var string section ID
     */
    private $section_id;

    /**
     * @var OmnipayBridge
     */
    private $omnipay_bridge;

    /**
     * @param  string  $omnipay_name  Omnipay gateway 名稱（如 ECPay）
     * @param  string  $section_id  section ID（如 ecpay）
     */
    public function __construct($omnipay_name, $section_id = null)
    {
        $this->omnipay_name = $omnipay_name;
        $this->section_id = 'omnipay_'.($section_id ?: strtolower($omnipay_name));
        $this->omnipay_bridge = new OmnipayBridge($omnipay_name);
    }

    /**
     * 註冊 hooks
     */
    public function register()
    {
        add_filter('woocommerce_get_sections_checkout', [$this, 'add_section']);
        add_filter('woocommerce_get_settings_checkout', [$this, 'get_settings'], 10, 2);
    }

    /**
     * 新增設定區塊
     *
     * @param  array  $sections
     * @return array
     */
    public function add_section($sections)
    {
        $sections[$this->section_id] = $this->omnipay_name;

        return $sections;
    }

    /**
     * 取得設定欄位
     *
     * @param  array  $settings
     * @param  string  $current_section
     * @return array
     */
    public function get_settings($settings, $current_section)
    {
        if ($current_section !== $this->section_id) {
            return $settings;
        }

        $option_key = SharedSettings::getOptionKey($this->omnipay_name);

        $fields = [
            [
                'title' => sprintf('%s 共用設定', $this->omnipay_name),
                'type' => 'title',
                'desc' => sprintf('設定 %s 的共用參數，這些設定會套用到所有 %s 付款方式。', $this->omnipay_name, $this->omnipay_name),
                'id' => $this->section_id.'_options',
            ],
        ];

        // 加入 Omnipay 參數欄位
        foreach ($this->omnipay_bridge->getDefaultParameters() as $key => $default_value) {
            $fields[] = $this->create_field($option_key, $key, $default_value);
        }

        // 加入 Plugin 通用設定
        $fields[] = [
            'title' => __('交易編號前綴', 'woocommerce-omnipay'),
            'type' => 'text',
            'desc' => __('加在交易編號前面的前綴，用於區分不同網站或環境。', 'woocommerce-omnipay'),
            'id' => $option_key.'[transaction_id_prefix]',
            'default' => '',
            'desc_tip' => true,
        ];

        $fields[] = [
            'title' => __('允許重新提交', 'woocommerce-omnipay'),
            'type' => 'checkbox',
            'desc' => __('啟用時使用隨機交易編號，允許重新付款。', 'woocommerce-omnipay'),
            'id' => $option_key.'[allow_resubmit]',
            'default' => 'no',
        ];

        $fields[] = [
            'type' => 'sectionend',
            'id' => $this->section_id.'_options',
        ];

        return $fields;
    }

    /**
     * 建立欄位
     *
     * @param  string  $option_key
     * @param  string  $key
     * @param  mixed  $default_value
     * @return array
     */
    private function create_field($option_key, $key, $default_value)
    {
        $field = [
            'title' => ucwords(str_replace('_', ' ', $key)),
            'id' => $option_key.'['.$key.']',
            'default' => is_bool($default_value) ? ($default_value ? 'yes' : 'no') : (string) $default_value,
            'desc_tip' => true,
        ];

        if (is_bool($default_value)) {
            $field['type'] = 'checkbox';
            $field['desc'] = sprintf('Omnipay parameter: %s', $key);
        } else {
            $field['type'] = 'text';
            $field['desc'] = sprintf('Omnipay parameter: %s', $key);

            // 密碼欄位
            if (stripos($key, 'key') !== false || stripos($key, 'iv') !== false || stripos($key, 'secret') !== false) {
                $field['type'] = 'password';
            }
        }

        return $field;
    }
}
