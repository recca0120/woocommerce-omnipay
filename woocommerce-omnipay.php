<?php
/**
 * Plugin Name: WooCommerce Omnipay Gateway
 * Plugin URI: https://github.com/recca0120/woocommerce-omnipay
 * Description: WooCommerce payment gateway integration using Omnipay library
 * Version: 0.0.1
 * Author: Recca Tsai
 * Author URI: https://github.com/recca0120
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: woocommerce-omnipay
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */
defined('ABSPATH') || exit;

// Define plugin constants
define('WOOCOMMERCE_OMNIPAY_VERSION', '0.0.1');
define('WOOCOMMERCE_OMNIPAY_PLUGIN_FILE', __FILE__);
define('WOOCOMMERCE_OMNIPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOCOMMERCE_OMNIPAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require_once __DIR__.'/vendor/autoload.php';
}

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Load plugin textdomain for translations
 */
function woocommerce_omnipay_load_textdomain()
{
    load_plugin_textdomain('woocommerce-omnipay', false, dirname(plugin_basename(__FILE__)).'/languages');
}
add_action('init', 'woocommerce_omnipay_load_textdomain');

/**
 * Initialize the plugin
 */
function woocommerce_omnipay_init()
{
    // Check if WooCommerce is active
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', 'woocommerce_omnipay_missing_wc_notice');

        return;
    }

    // Register payment gateways
    add_filter('woocommerce_payment_gateways', 'woocommerce_omnipay_add_gateways');

    // Register shared settings pages
    woocommerce_omnipay_register_shared_settings();

    // Handle redirect form rendering
    add_action('template_redirect', 'woocommerce_omnipay_maybe_render_redirect_form');

    // Register barcode scripts
    add_action('wp_enqueue_scripts', 'woocommerce_omnipay_register_scripts');
}
add_action('plugins_loaded', 'woocommerce_omnipay_init');

/**
 * Register shared settings page
 */
function woocommerce_omnipay_register_shared_settings()
{
    $config = woocommerce_omnipay_get_config();

    $page = new \WooCommerceOmnipay\SharedSettingsPage($config['gateways']);
    $page->register();
}

/**
 * Register plugin scripts and styles
 */
function woocommerce_omnipay_register_scripts()
{
    // Only load on order-related pages
    if (! is_checkout() && ! is_wc_endpoint_url('order-received') && ! is_wc_endpoint_url('view-order')) {
        return;
    }

    // Register and enqueue payment info styles
    wp_enqueue_style(
        'woocommerce-omnipay-payment-info',
        WOOCOMMERCE_OMNIPAY_PLUGIN_URL.'assets/css/payment-info.css',
        [],
        WOOCOMMERCE_OMNIPAY_VERSION
    );

    // Register JsBarcode from CDN
    wp_register_script(
        'jsbarcode',
        'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js',
        [],
        '3.11.6',
        true
    );

    // Register barcode initialization script
    wp_register_script(
        'woocommerce-omnipay-barcode',
        WOOCOMMERCE_OMNIPAY_PLUGIN_URL.'assets/js/barcode.js',
        ['jsbarcode'],
        WOOCOMMERCE_OMNIPAY_VERSION,
        true
    );

    // Enqueue scripts
    wp_enqueue_script('woocommerce-omnipay-barcode');
}

/**
 * Render redirect form for POST redirect gateways
 */
function woocommerce_omnipay_maybe_render_redirect_form()
{
    if (empty($_GET['omnipay_redirect']) || $_GET['omnipay_redirect'] !== '1') {
        return;
    }

    $orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $orderKey = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

    if (! $orderId) {
        return;
    }

    $order = wc_get_order($orderId);

    if (! $order || $order->get_order_key() !== $orderKey) {
        return;
    }

    $redirectData = get_transient('omnipay_redirect_'.$orderId);

    if (! $redirectData) {
        return;
    }

    delete_transient('omnipay_redirect_'.$orderId);

    echo woocommerce_omnipay_get_template('checkout/redirect-form.php', [
        'url' => $redirectData['url'],
        'method' => $redirectData['method'],
        'data' => $redirectData['data'],
    ]);

    \WooCommerceOmnipay\Helper::terminate();
}

/**
 * Add Omnipay gateways to WooCommerce
 *
 * @param  array  $gateways  Existing gateways
 * @return array Modified gateways
 */
function woocommerce_omnipay_add_gateways($gateways)
{
    $registry = new \WooCommerceOmnipay\Services\GatewayRegistry(
        woocommerce_omnipay_get_config()
    );

    foreach ($registry->getGateways() as $gatewayInfo) {
        $gatewayClass = $registry->resolveGatewayClass($gatewayInfo);
        $gateways[] = new $gatewayClass($gatewayInfo);
    }

    return $gateways;
}

/**
 * Get gateway discovery configuration
 *
 * @return array
 */
function woocommerce_omnipay_get_config()
{
    $gateways = require WOOCOMMERCE_OMNIPAY_PLUGIN_DIR.'config/gateways.php';

    return apply_filters('woocommerce_omnipay_gateway_config', [
        'gateways' => $gateways,
    ]);
}

/**
 * Display notice if WooCommerce is not active
 */
function woocommerce_omnipay_missing_wc_notice()
{
    ?>
    <div class="error">
        <p><?php esc_html_e('WooCommerce Omnipay Gateway requires WooCommerce to be installed and active.', 'woocommerce-omnipay'); ?></p>
    </div>
    <?php
}

/**
 * Load and render a template
 *
 * Look for template in theme first, then fallback to plugin templates
 *
 * @param  string  $template_name  Template name (e.g., 'payment-info.php')
 * @param  array  $args  Variables to pass to the template
 * @return string Rendered template content
 */
function woocommerce_omnipay_get_template($template_name, $args = [])
{
    // Look in theme/child-theme first
    $template_path = locate_template(['woocommerce-omnipay/'.$template_name]);

    // Fallback to plugin templates
    if (! $template_path) {
        $template_path = WOOCOMMERCE_OMNIPAY_PLUGIN_DIR.'templates/'.$template_name;
    }

    if (! file_exists($template_path)) {
        return '';
    }

    if (! empty($args)) {
        extract($args);
    }

    ob_start();
    include $template_path;

    return ob_get_clean();
}
