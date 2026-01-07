<?php

namespace Recca0120\WooCommerce_Omnipay\Settings;

use Recca0120\WooCommerce_Omnipay\Adapters\Contracts\GatewayAdapter;
use Recca0120\WooCommerce_Omnipay\Settings\Contracts\SettingsSectionProvider;
use Recca0120\WooCommerce_Omnipay\WordPress\SettingsManager;

/**
 * Gateway Settings Section
 *
 * Provides settings for a specific gateway.
 */
class GatewaySettingsSection implements SettingsSectionProvider
{
    /**
     * @var GatewayAdapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $optionKey;

    /**
     * Fields to exclude from gateway settings (handled in General Settings)
     */
    private const GENERAL_FIELDS = ['testMode', 'transaction_id_prefix', 'allow_resubmit'];

    public function __construct(GatewayAdapter $adapter)
    {
        $this->adapter = $adapter;
        $this->name = $adapter->getGatewayName();
        $this->optionKey = SettingsManager::getOptionKey($this->name);
    }

    public function getSectionKey(): string
    {
        return strtolower($this->name);
    }

    public function getSectionLabel(): string
    {
        // translators: %s is the gateway name (e.g., ECPay, NewebPay, YiPay, Dummy)
        return __($this->name, 'woocommerce-omnipay');
    }

    public function getSettings(): array
    {
        $fields = [
            [
                'title' => sprintf(__('%s Shared Settings', 'woocommerce-omnipay'), $this->name),
                'type' => 'title',
                'desc' => sprintf(
                    __('Configure %s shared parameters. These settings apply to all %s payment methods.', 'woocommerce-omnipay'),
                    $this->name,
                    $this->name
                ),
                'id' => 'omnipay_'.$this->getSectionKey().'_options',
            ],
        ];

        $parameters = $this->getParameters();

        foreach ($parameters as $key => $defaultValue) {
            if (in_array($key, self::GENERAL_FIELDS, true)) {
                continue;
            }

            $fields[] = $this->createField($key, $defaultValue);
        }

        $fields[] = [
            'type' => 'sectionend',
            'id' => 'omnipay_'.$this->getSectionKey().'_options',
        ];

        return $fields;
    }

    public function registerFieldHooks(): void
    {
        // No custom field types
    }

    /**
     * Get parameters from adapter
     */
    protected function getParameters(): array
    {
        return $this->adapter->getSettingsFields();
    }

    /**
     * Create a field configuration
     */
    protected function createField(string $key, $defaultValue): array
    {
        $savedSettings = get_option($this->optionKey, []);
        $savedValue = $savedSettings[$key] ?? null;

        $field = [
            'title' => ucwords(str_replace('_', ' ', $key)),
            'id' => $this->optionKey.'['.$key.']',
            'desc_tip' => true,
        ];

        // Handle boolean
        if (is_bool($defaultValue)) {
            $field['type'] = 'checkbox';
            $field['default'] = $defaultValue ? 'yes' : 'no';
            $field['value'] = $savedValue ?? ($defaultValue ? 'yes' : 'no');
            $field['desc'] = sprintf('Omnipay parameter: %s', $key);

            return $field;
        }

        // Handle array (textarea with JSON)
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

        // Text field (default)
        $field['type'] = 'text';
        $field['default'] = (string) $defaultValue;
        $field['value'] = $savedValue ?? (string) $defaultValue;
        $field['desc'] = sprintf('Omnipay parameter: %s', $key);

        // Password field for sensitive keys
        if ($this->isSensitiveField($key)) {
            $field['type'] = 'password';
        }

        return $field;
    }

    /**
     * Check if field should be a password field
     */
    protected function isSensitiveField(string $key): bool
    {
        return stripos($key, 'key') !== false
            || stripos($key, 'iv') !== false
            || stripos($key, 'secret') !== false;
    }
}
