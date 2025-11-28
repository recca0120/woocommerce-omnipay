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
        $sections = [];

        foreach ($this->gateways as $gateway) {
            $name = $gateway['gateway'];
            $key = strtolower($name);
            $sections[$key] = $name;
        }

        return $sections;
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
        $name = $this->get_name_by_section($section);

        if (! $name) {
            return [];
        }

        $optionKey = OmnipayBridge::getOptionKey($name);
        $bridge = $this->get_bridge($name);

        $fields = [
            [
                'title' => sprintf('%s 共用設定', $name),
                'type' => 'title',
                'desc' => sprintf('設定 %s 的共用參數，這些設定會套用到所有 %s 付款方式。', $name, $name),
                'id' => 'omnipay_'.$section.'_options',
            ],
        ];

        // 加入 Omnipay 參數欄位
        foreach ($bridge->getDefaultParameters() as $key => $defaultValue) {
            $fields[] = $this->createField($optionKey, $key, $defaultValue);
        }

        // 加入 Plugin 通用設定
        $fields[] = [
            'title' => __('交易編號前綴', 'woocommerce-omnipay'),
            'type' => 'text',
            'desc' => __('加在交易編號前面的前綴，用於區分不同網站或環境。', 'woocommerce-omnipay'),
            'id' => $optionKey.'[transaction_id_prefix]',
            'default' => '',
            'desc_tip' => true,
        ];

        $fields[] = [
            'title' => __('允許重新提交', 'woocommerce-omnipay'),
            'type' => 'checkbox',
            'desc' => __('啟用時使用隨機交易編號，允許重新付款。', 'woocommerce-omnipay'),
            'id' => $optionKey.'[allow_resubmit]',
            'default' => 'no',
        ];

        $fields[] = [
            'type' => 'sectionend',
            'id' => 'omnipay_'.$section.'_options',
        ];

        return $fields;
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
