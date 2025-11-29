<?php

namespace WooCommerceOmnipay;

use WooCommerceOmnipay\Services\OmnipayBridge;

/**
 * 共用設定頁面
 *
 * 在 WooCommerce > 設定 下新增 Omnipay tab，並以 sub-tab 區分各 Gateway
 */
class SharedSettingsPage
{
    /**
     * @var array Gateway 配置列表
     */
    private $gateways;

    /**
     * @var array 已建立的 OmnipayBridge 實例快取
     */
    private $bridges = [];

    /**
     * @param  array  $gateways  Gateway 配置列表
     */
    public function __construct(array $gateways)
    {
        // 取得不重複的 gateway 列表
        $seen = [];
        $this->gateways = [];

        foreach ($gateways as $gateway) {
            $name = $gateway['gateway'] ?? '';
            if (! empty($name) && ! isset($seen[$name])) {
                $this->gateways[] = $gateway;
                $seen[$name] = true;
            }
        }
    }

    /**
     * 註冊 hooks
     */
    public function register()
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_tab'], 50);
        add_action('woocommerce_settings_omnipay', [$this, 'output_settings']);
        add_action('woocommerce_update_options_omnipay', [$this, 'save_settings']);
        add_action('woocommerce_sections_omnipay', [$this, 'output_sections']);
    }

    /**
     * 新增 Omnipay tab
     *
     * @param  array  $tabs
     * @return array
     */
    public function add_tab($tabs)
    {
        $tabs['omnipay'] = 'Omnipay';

        return $tabs;
    }

    /**
     * 取得所有 sections（sub-tabs）
     *
     * @return array
     */
    public function get_sections()
    {
        $sections = [
            '' => __('General Settings', 'woocommerce-omnipay'),
        ];

        foreach ($this->gateways as $gateway) {
            $name = $gateway['gateway'];
            $key = strtolower($name);
            // Translate gateway name for display
            $sections[$key] = $this->translateGatewayName($name);
        }

        return $sections;
    }

    /**
     * Translate gateway name for display
     *
     * @param  string  $name  Gateway name
     * @return string
     */
    private function translateGatewayName($name)
    {
        $translations = [
            'ECPay' => __('ECPay', 'woocommerce-omnipay'),
            'NewebPay' => __('NewebPay', 'woocommerce-omnipay'),
            'YiPay' => __('YiPay', 'woocommerce-omnipay'),
            'BankTransfer' => __('Bank Transfer', 'woocommerce-omnipay'),
            'Dummy' => __('Dummy Gateway', 'woocommerce-omnipay'),
        ];

        return $translations[$name] ?? $name;
    }

    /**
     * 輸出 sections 導航
     */
    public function output_sections()
    {
        global $current_section;

        $sections = $this->get_sections();

        if (empty($sections)) {
            return;
        }

        echo '<ul class="subsubsub">';

        $links = [];
        foreach ($sections as $id => $label) {
            $url = admin_url('admin.php?page=wc-settings&tab=omnipay&section='.$id);
            $class = ($current_section === $id || (empty($current_section) && $id === array_key_first($sections))) ? 'current' : '';
            $links[] = '<li><a href="'.esc_url($url).'" class="'.esc_attr($class).'">'.esc_html($label).'</a></li>';
        }

        echo implode(' | ', $links);
        echo '</ul><br class="clear" />';
    }

    /**
     * 輸出設定頁面
     */
    public function output_settings()
    {
        global $current_section;

        $section = $current_section ?: $this->get_default_section();
        $settings = $this->get_settings($section);

        \WC_Admin_Settings::output_fields($settings);
    }

    /**
     * 儲存設定
     */
    public function save_settings()
    {
        global $current_section;

        $section = $current_section ?: $this->get_default_section();
        $settings = $this->get_settings($section);

        \WC_Admin_Settings::save_fields($settings);
    }

    /**
     * 取得預設 section
     *
     * @return string
     */
    private function get_default_section()
    {
        $sections = $this->get_sections();

        return array_key_first($sections) ?: '';
    }

    /**
     * 取得設定欄位
     *
     * @param  string  $section
     * @return array
     */
    public function get_settings($section)
    {
        // 通用設定
        if ($section === '') {
            return $this->get_general_settings();
        }

        $name = $this->get_name_by_section($section);

        if (! $name) {
            return [];
        }

        $optionKey = OmnipayBridge::getOptionKey($name);
        $bridge = $this->get_bridge($name);

        $fields = [
            [
                'title' => sprintf(__('%s Shared Settings', 'woocommerce-omnipay'), $name),
                'type' => 'title',
                'desc' => sprintf(__('Configure %s shared parameters. These settings apply to all %s payment methods.', 'woocommerce-omnipay'), $name, $name),
                'id' => 'omnipay_'.$section.'_options',
            ],
        ];

        // 加入 Omnipay 參數欄位（排除通用設定中的欄位）
        $generalFields = ['testMode', 'transaction_id_prefix', 'allow_resubmit'];

        foreach ($bridge->getDefaultParameters() as $key => $defaultValue) {
            // 跳過通用設定中的欄位
            if (in_array($key, $generalFields, true)) {
                continue;
            }

            $fields[] = $this->createField($optionKey, $key, $defaultValue);
        }

        $fields[] = [
            'type' => 'sectionend',
            'id' => 'omnipay_'.$section.'_options',
        ];

        return $fields;
    }

    /**
     * 取得通用設定欄位
     *
     * @return array
     */
    private function get_general_settings()
    {
        $optionKey = 'woocommerce_omnipay_general_settings';

        return [
            [
                'title' => __('Omnipay General Settings', 'woocommerce-omnipay'),
                'type' => 'title',
                'desc' => __('These settings apply to all Omnipay payment methods.', 'woocommerce-omnipay'),
                'id' => 'omnipay_general_options',
            ],
            [
                'title' => __('Test Mode', 'woocommerce-omnipay'),
                'type' => 'checkbox',
                'desc' => __('Enable test mode for development and testing.', 'woocommerce-omnipay'),
                'id' => $optionKey.'[testMode]',
                'default' => 'no',
            ],
            [
                'title' => __('Transaction ID Prefix', 'woocommerce-omnipay'),
                'type' => 'text',
                'desc' => __('Prefix added to transaction IDs to distinguish different sites or environments.', 'woocommerce-omnipay'),
                'id' => $optionKey.'[transaction_id_prefix]',
                'default' => '',
                'desc_tip' => true,
            ],
            [
                'title' => __('Allow Resubmit', 'woocommerce-omnipay'),
                'type' => 'checkbox',
                'desc' => __('When enabled, use random transaction IDs to allow payment retry.', 'woocommerce-omnipay'),
                'id' => $optionKey.'[allow_resubmit]',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id' => 'omnipay_general_options',
            ],
        ];
    }

    /**
     * 根據 section 取得 gateway name
     *
     * @param  string  $section
     * @return string|null
     */
    private function get_name_by_section($section)
    {
        foreach ($this->gateways as $gateway) {
            $name = $gateway['gateway'];
            if (strtolower($name) === $section) {
                return $name;
            }
        }

        return null;
    }

    /**
     * 取得 OmnipayBridge 實例
     *
     * @param  string  $name
     * @return OmnipayBridge
     */
    private function get_bridge($name)
    {
        if (! isset($this->bridges[$name])) {
            $this->bridges[$name] = new OmnipayBridge($name);
        }

        return $this->bridges[$name];
    }

    /**
     * 建立欄位
     *
     * @param  string  $optionKey
     * @param  string  $key
     * @param  mixed  $defaultValue
     * @return array
     */
    private function createField($optionKey, $key, $defaultValue)
    {
        $field = [
            'title' => ucwords(str_replace('_', ' ', $key)),
            'id' => $optionKey.'['.$key.']',
            'default' => is_bool($defaultValue) ? ($defaultValue ? 'yes' : 'no') : (string) $defaultValue,
            'desc_tip' => true,
        ];

        if (is_bool($defaultValue)) {
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
