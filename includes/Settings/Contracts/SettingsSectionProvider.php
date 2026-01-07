<?php

namespace Recca0120\WooCommerce_Omnipay\Settings\Contracts;

/**
 * Settings Section Provider Interface
 *
 * Defines a settings section for the Omnipay settings page.
 * Each provider is responsible for its own section key, label, fields, and field hooks.
 */
interface SettingsSectionProvider
{
    /**
     * Get the section key (used in URL)
     *
     * @return string Empty string for default section, or lowercase identifier (e.g., 'ecpay')
     */
    public function getSectionKey(): string;

    /**
     * Get the translated section label for display
     */
    public function getSectionLabel(): string;

    /**
     * Get the settings fields for this section
     *
     * @return array WooCommerce settings fields
     */
    public function getSettings(): array;

    /**
     * Register custom field type hooks for this section
     */
    public function registerFieldHooks(): void;
}
