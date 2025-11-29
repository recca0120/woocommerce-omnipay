<?php

/**
 * PHPUnit Bootstrap File for WordPress Plugin Testing
 *
 * Supports both local development and CI environments
 */

// Load Composer autoloader
require_once dirname(__DIR__).'/vendor/autoload.php';

// Load WordPress test framework functions
require_once dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

/**
 * Get WooCommerce plugin path
 *
 * @return string|null
 */
function _get_woocommerce_path()
{
    // Check environment variable first (CI environment)
    $wp_core_dir = getenv('WP_CORE_DIR');

    if ($wp_core_dir) {
        $wc_path = rtrim($wp_core_dir, '/').'/wp-content/plugins/woocommerce/woocommerce.php';
        if (file_exists($wc_path)) {
            return $wc_path;
        }
    }

    // Local development: check sibling plugins directory
    $plugins_dir = dirname(__DIR__, 2);
    $wc_path = $plugins_dir.'/woocommerce/woocommerce.php';
    if (file_exists($wc_path)) {
        return $wc_path;
    }

    return null;
}

/**
 * Manually load plugins before WordPress initializes
 */
function _manually_load_plugins()
{
    // Load WooCommerce
    $woocommerce_path = _get_woocommerce_path();
    if ($woocommerce_path) {
        require $woocommerce_path;
    } else {
        echo "Warning: WooCommerce not found. Some tests may fail.\n";
    }

    // Load our plugin
    require dirname(__DIR__).'/woocommerce-omnipay.php';
}

/**
 * Install WooCommerce tables before WordPress init
 */
function _install_woocommerce()
{
    // Get WooCommerce path
    $woocommerce_path = _get_woocommerce_path();
    if (! $woocommerce_path) {
        return;
    }

    // Define WooCommerce constants for testing
    if (! defined('WC_ABSPATH')) {
        define('WC_ABSPATH', dirname($woocommerce_path).'/');
    }

    // Include WooCommerce install class
    if (file_exists(WC_ABSPATH.'includes/class-wc-install.php')) {
        include_once WC_ABSPATH.'includes/class-wc-install.php';

        // Suppress database errors for PHP 7.2 with WooCommerce 6.4
        // SQLite doesn't support FOREIGN KEY constraints syntax used in WooCommerce 6.4
        global $wpdb;
        $shouldSuppressErrors = version_compare(PHP_VERSION, '7.3', '<');
        if ($shouldSuppressErrors) {
            $wpdb->suppress_errors = true;
        }

        WC_Install::install();

        if ($shouldSuppressErrors) {
            $wpdb->suppress_errors = false;
        }
    }
}

// Register plugin loader hook
tests_add_filter('muplugins_loaded', '_manually_load_plugins');

// Install WooCommerce tables early (before init hook)
tests_add_filter('setup_theme', '_install_woocommerce');

// Start up the WordPress testing environment
require dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
