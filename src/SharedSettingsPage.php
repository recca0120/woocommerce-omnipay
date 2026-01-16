<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay;

use OmnipayTaiwan\WooCommerce_Omnipay\Settings\Contracts\SettingsSectionProvider;

/**
 * Shared Settings Page
 *
 * Coordinates settings sections for the Omnipay tab in WooCommerce settings.
 */
class SharedSettingsPage
{
    /**
     * @var SettingsSectionProvider[]
     */
    private $sections = [];

    /**
     * @param  SettingsSectionProvider[]  $sections
     */
    public function __construct(array $sections)
    {
        foreach ($sections as $section) {
            $this->sections[$section->getSectionKey()] = $section;
        }
    }

    public function register(): void
    {
        add_filter('woocommerce_settings_tabs_array', [$this, 'addTab'], 50);
        add_action('woocommerce_settings_omnipay', [$this, 'outputSettings']);
        add_action('woocommerce_update_options_omnipay', [$this, 'saveSettings']);
        add_action('woocommerce_sections_omnipay', [$this, 'outputSections']);

        foreach ($this->sections as $section) {
            $section->registerFieldHooks();
        }
    }

    public function addTab(array $tabs): array
    {
        $tabs['omnipay'] = 'Omni'.'pay';

        return $tabs;
    }

    public function getSections(): array
    {
        $result = [];
        foreach ($this->sections as $section) {
            $result[$section->getSectionKey()] = $section->getSectionLabel();
        }

        return $result;
    }

    public function getSettings(string $sectionKey): array
    {
        return isset($this->sections[$sectionKey])
            ? $this->sections[$sectionKey]->getSettings()
            : [];
    }

    public function outputSections(): void
    {
        echo woocommerce_omnipay_get_template('admin/settings-sections.php', [
            'sections' => $this->getSections(),
            'currentSection' => $this->getCurrentSection(),
        ]);
    }

    public function outputSettings(): void
    {
        $section = $this->getCurrentSection() ?: $this->getDefaultSection();
        $settings = $this->getSettings($section);

        \WC_Admin_Settings::output_fields($settings);
    }

    public function saveSettings(): void
    {
        $section = $this->getCurrentSection() ?: $this->getDefaultSection();
        $settings = $this->getSettings($section);

        $this->saveNestedFields($settings);
    }

    private function saveNestedFields(array $settings): void
    {
        foreach ($settings as $field) {
            if (! isset($field['id']) || ! isset($field['type'])) {
                continue;
            }

            if (in_array($field['type'], ['title', 'sectionend'], true)) {
                continue;
            }

            // Custom field types are handled by their registered hooks
            if ($this->isCustomFieldType($field['type'])) {
                do_action('woocommerce_update_option_'.$field['type'], $field);

                continue;
            }

            $this->saveField($field);
        }
    }

    private function isCustomFieldType(string $type): bool
    {
        return ! in_array($type, ['text', 'password', 'textarea', 'select', 'checkbox', 'number', 'email'], true);
    }

    private function saveField(array $field): void
    {
        $optionId = $field['id'];

        if (! preg_match('/^(.+)\[(.+)\]$/', $optionId, $matches)) {
            \WC_Admin_Settings::save_fields([$field]);

            return;
        }

        $optionName = $matches[1];
        $fieldKey = $matches[2];

        $value = $_POST[$optionName][$fieldKey] ?? null;

        if ($value === null) {
            return;
        }

        if ($field['type'] === 'checkbox') {
            $value = ($value === 'yes' || $value === '1') ? 'yes' : 'no';
        } else {
            $value = sanitize_text_field($value);
        }

        $existingSettings = get_option($optionName, []);
        $existingSettings[$fieldKey] = $value;
        update_option($optionName, $existingSettings);
    }

    private function getCurrentSection(): string
    {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    }

    private function getDefaultSection(): string
    {
        $sections = $this->getSections();
        reset($sections);

        return (string) key($sections);
    }
}
