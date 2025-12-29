<?php

namespace WooCommerceOmnipay;

use WooCommerceOmnipay\WordPress\SettingsManager;

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
     * @var array 已建立的 Adapter 實例快取
     */
    private $adapters = [];

    /**
     * @var GatewayRegistry
     */
    private $registry;

    /**
     * @param  array  $gateways  Gateway 配置列表
     * @param  GatewayRegistry|null  $registry  Gateway Registry
     */
    public function __construct(array $gateways, ?GatewayRegistry $registry = null)
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

        $this->registry = $registry ?? new GatewayRegistry;
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

        // 自定義欄位類型 - 輸出和儲存
        add_action('woocommerce_admin_field_bank_accounts_table', [$this, 'output_bank_accounts_table']);
        add_action('woocommerce_update_option_bank_accounts_table', [$this, 'save_bank_accounts_table']);
    }

    /**
     * 儲存銀行帳號表格欄位
     *
     * @param  array  $value  欄位設定
     */
    public function save_bank_accounts_table($value)
    {
        // 解析 option ID: woocommerce_omnipay_xxx_shared_settings[bank_accounts]
        // POST 結構: $_POST['woocommerce_omnipay_xxx_shared_settings']['bank_accounts']
        $optionId = $value['id'];

        if (! preg_match('/^(.+)\[(.+)\]$/', $optionId, $matches)) {
            return;
        }

        $optionName = $matches[1];  // woocommerce_omnipay_xxx_shared_settings
        $fieldKey = $matches[2];    // bank_accounts

        $accounts = [];

        // 從 POST 取得資料
        if (isset($_POST[$optionName][$fieldKey]) && is_array($_POST[$optionName][$fieldKey])) {
            foreach ($_POST[$optionName][$fieldKey] as $account) {
                // 過濾空白帳號
                if (empty($account['bank_code']) && empty($account['account_number'])) {
                    continue;
                }

                $accounts[] = [
                    'bank_code' => sanitize_text_field($account['bank_code'] ?? ''),
                    'account_number' => sanitize_text_field($account['account_number'] ?? ''),
                    'secret' => sanitize_text_field($account['secret'] ?? ''),
                ];
            }
        }

        // 儲存到對應的 shared settings option
        $existingSettings = get_option($optionName, []);
        $existingSettings[$fieldKey] = $accounts;
        update_option($optionName, $existingSettings);
    }

    /**
     * 輸出銀行帳號表格欄位
     *
     * @param  array  $value  欄位設定
     */
    public function output_bank_accounts_table($value)
    {
        $optionId = $value['id'];
        $accounts = [];

        // 解析 option ID: woocommerce_omnipay_xxx_shared_settings[bank_accounts]
        if (preg_match('/^(.+)\[(.+)\]$/', $optionId, $matches)) {
            $optionName = $matches[1];
            $fieldKey = $matches[2];
            $settings = get_option($optionName, []);
            $accounts = $settings[$fieldKey] ?? [];
        }

        // 如果是 JSON 字串，解析為陣列
        if (is_string($accounts) && ! empty($accounts)) {
            $accounts = json_decode($accounts, true) ?: [];
        }

        if (! is_array($accounts)) {
            $accounts = [];
        }

        echo woocommerce_omnipay_get_template('admin/bank-accounts-table.php', [
            'value' => $value,
            'accounts' => $accounts,
            'fieldName' => esc_attr($value['id']),
            'fieldId' => sanitize_title($value['id']),
        ]);
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
        $currentSection = $this->getCurrentSection();

        $sections = $this->get_sections();

        if (empty($sections)) {
            return;
        }

        echo '<ul class="subsubsub">';

        $links = [];
        foreach ($sections as $id => $label) {
            $url = admin_url('admin.php?page=wc-settings&tab=omnipay&section='.$id);
            $class = ($currentSection === $id || (empty($currentSection) && $id === array_key_first($sections))) ? 'current' : '';
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
        $currentSection = $this->getCurrentSection();

        $section = $currentSection ?: $this->get_default_section();
        $settings = $this->get_settings($section);

        \WC_Admin_Settings::output_fields($settings);
    }

    /**
     * 儲存設定
     */
    public function save_settings()
    {
        $currentSection = $this->getCurrentSection();

        $section = $currentSection ?: $this->get_default_section();
        $settings = $this->get_settings($section);

        // WC_Admin_Settings::save_fields() 無法處理 option[field] 格式
        // 所以我們需要手動處理嵌套格式的欄位
        $this->saveNestedFields($settings);
    }

    /**
     * 儲存嵌套格式的欄位
     *
     * @param  array  $settings  設定欄位
     */
    private function saveNestedFields(array $settings)
    {
        foreach ($settings as $field) {
            if (! isset($field['id']) || ! isset($field['type'])) {
                continue;
            }

            // 跳過 title 和 sectionend
            if (in_array($field['type'], ['title', 'sectionend'], true)) {
                continue;
            }

            // bank_accounts_table 需要直接呼叫處理方法
            if ($field['type'] === 'bank_accounts_table') {
                $this->save_bank_accounts_table($field);

                continue;
            }

            $optionId = $field['id'];

            // 解析嵌套格式: option_name[field_key]
            if (! preg_match('/^(.+)\[(.+)\]$/', $optionId, $matches)) {
                // 非嵌套格式，使用 WooCommerce 標準處理
                \WC_Admin_Settings::save_fields([$field]);

                continue;
            }

            $optionName = $matches[1];
            $fieldKey = $matches[2];

            // 從 POST 讀取值
            $value = $_POST[$optionName][$fieldKey] ?? null;

            if ($value === null) {
                continue;
            }

            // 處理 checkbox 類型
            if ($field['type'] === 'checkbox') {
                $value = ($value === 'yes' || $value === '1') ? 'yes' : 'no';
            } else {
                $value = sanitize_text_field($value);
            }

            // 儲存到對應的 option
            $existingSettings = get_option($optionName, []);
            $existingSettings[$fieldKey] = $value;
            update_option($optionName, $existingSettings);
        }
    }

    /**
     * 取得當前 section
     *
     * @return string
     */
    private function getCurrentSection()
    {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
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

        $optionKey = SettingsManager::getOptionKey($name);
        $adapter = $this->get_adapter($name);

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

        // 優先使用 getSettingsFields()（如果有），否則使用 getDefaultParameters()
        $parameters = method_exists($adapter, 'getSettingsFields')
            ? $adapter->getSettingsFields()
            : $adapter->getDefaultParameters();

        foreach ($parameters as $key => $defaultValue) {
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
     * 取得 Adapter 實例
     *
     * @param  string  $name
     * @return \WooCommerceOmnipay\Adapters\Contracts\GatewayAdapter
     */
    private function get_adapter($name)
    {
        if (! isset($this->adapters[$name])) {
            $this->adapters[$name] = $this->registry->resolveAdapter(['gateway' => $name]);
        }

        return $this->adapters[$name];
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
        // 讀取已儲存的值
        $savedSettings = get_option($optionKey, []);
        $savedValue = $savedSettings[$key] ?? null;

        $field = [
            'title' => ucwords(str_replace('_', ' ', $key)),
            'id' => $optionKey.'['.$key.']',
            'desc_tip' => true,
        ];

        // 處理特殊欄位
        if ($key === 'selection_mode') {
            return $this->createSelectionModeField($field, $defaultValue, $savedValue);
        }

        if ($key === 'bank_accounts') {
            return $this->createBankAccountsField($field, $defaultValue);
        }

        // 處理布林值
        if (is_bool($defaultValue)) {
            $field['type'] = 'checkbox';
            $field['default'] = $defaultValue ? 'yes' : 'no';
            $field['value'] = $savedValue ?? ($defaultValue ? 'yes' : 'no');
            $field['desc'] = sprintf('Omnipay parameter: %s', $key);

            return $field;
        }

        // 處理陣列（fallback 用 textarea + JSON）
        if (is_array($defaultValue)) {
            $field['type'] = 'textarea';
            $field['default'] = json_encode($defaultValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $field['value'] = $savedValue !== null
                ? (is_array($savedValue) ? json_encode($savedValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $savedValue)
                : $field['default'];
            $field['desc'] = sprintf('Omnipay parameter: %s (JSON format)', $key);
            $field['css'] = 'min-height: 100px;';

            return $field;
        }

        // 一般文字欄位
        $field['type'] = 'text';
        $field['default'] = (string) $defaultValue;
        $field['value'] = $savedValue ?? (string) $defaultValue;
        $field['desc'] = sprintf('Omnipay parameter: %s', $key);

        // 密碼欄位
        if (stripos($key, 'key') !== false || stripos($key, 'iv') !== false || stripos($key, 'secret') !== false) {
            $field['type'] = 'password';
        }

        return $field;
    }

    /**
     * 建立帳號選擇模式欄位
     */
    private function createSelectionModeField(array $field, $defaultValue, $savedValue = null)
    {
        $field['type'] = 'select';
        $field['default'] = $defaultValue;
        $field['value'] = $savedValue ?? $defaultValue;
        $field['desc'] = __('How to select bank account when multiple accounts are configured', 'woocommerce-omnipay');
        $field['options'] = [
            'random' => __('Random', 'woocommerce-omnipay'),
            'round_robin' => __('Round Robin', 'woocommerce-omnipay'),
            'user_choice' => __('User Choice', 'woocommerce-omnipay'),
        ];

        return $field;
    }

    /**
     * 建立銀行帳號池欄位（表格式 UI）
     */
    private function createBankAccountsField(array $field, $defaultValue)
    {
        $field['type'] = 'bank_accounts_table';
        $field['default'] = is_array($defaultValue) ? $defaultValue : [];

        return $field;
    }
}
