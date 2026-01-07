<?php

namespace Recca0120\WooCommerce_Omnipay\Settings;

use Recca0120\WooCommerce_Omnipay\Settings\Contracts\SettingsSectionProvider;

/**
 * General Settings Section
 *
 * Provides general settings that apply to all Omnipay payment methods.
 */
class GeneralSettingsSection implements SettingsSectionProvider
{
    private const OPTION_KEY = 'woocommerce_omnipay_general_settings';

    public function getSectionKey(): string
    {
        return '';
    }

    public function getSectionLabel(): string
    {
        return __('General Settings', 'woocommerce-omnipay');
    }

    public function getSettings(): array
    {
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
                'id' => self::OPTION_KEY.'[testMode]',
                'default' => 'no',
            ],
            [
                'title' => __('Transaction ID Prefix', 'woocommerce-omnipay'),
                'type' => 'text',
                'desc' => __('Prefix added to transaction IDs to distinguish different sites or environments.', 'woocommerce-omnipay'),
                'id' => self::OPTION_KEY.'[transaction_id_prefix]',
                'default' => '',
                'desc_tip' => true,
            ],
            [
                'title' => __('Allow Resubmit', 'woocommerce-omnipay'),
                'type' => 'checkbox',
                'desc' => __('When enabled, use random transaction IDs to allow payment retry.', 'woocommerce-omnipay'),
                'id' => self::OPTION_KEY.'[allow_resubmit]',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id' => 'omnipay_general_options',
            ],
        ];
    }

    public function registerFieldHooks(): void
    {
        // No custom field types
    }
}
