<?php
/**
 * Plugin Name: WooCommerce Omnipay Gateway
 * Plugin URI: https://github.com/recca0120/woocommerce-omnipay
 * Description: WooCommerce payment gateway integration using Omnipay library
 * Version: 1.0.0
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
define('WOOCOMMERCE_OMNIPAY_VERSION', '1.0.0');
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

    // Handle redirect form rendering
    add_action('template_redirect', 'woocommerce_omnipay_maybe_render_redirect_form');

    // Register barcode scripts
    add_action('wp_enqueue_scripts', 'woocommerce_omnipay_register_scripts');
}
add_action('plugins_loaded', 'woocommerce_omnipay_init');

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

    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

    if (! $order_id) {
        return;
    }

    $order = wc_get_order($order_id);

    if (! $order || $order->get_order_key() !== $order_key) {
        return;
    }

    $redirect_data = get_transient('omnipay_redirect_'.$order_id);

    if (! $redirect_data) {
        return;
    }

    // 刪除 transient，避免重複使用
    delete_transient('omnipay_redirect_'.$order_id);

    // 渲染自動提交表單
    woocommerce_omnipay_render_redirect_form($redirect_data);

    // 在測試環境不 exit
    if (! defined('WP_TESTS_DOMAIN')) {
        exit;
    }
}

/**
 * Render auto-submit redirect form
 */
function woocommerce_omnipay_render_redirect_form(array $redirect_data)
{
    $url = esc_url($redirect_data['url']);
    $method = esc_attr($redirect_data['method']);
    $data = $redirect_data['data'];

    echo '<form id="omnipay-redirect-form" action="'.$url.'" method="'.$method.'">';

    foreach ($data as $name => $value) {
        echo '<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($value).'" />';
    }

    echo '</form>';
    echo '<script>document.getElementById("omnipay-redirect-form").submit();</script>';
}

/**
 * Add Omnipay gateways to WooCommerce
 *
 * 使用 GatewayRegistry 載入已配置的 Omnipay gateways
 * 如果有對應的具體 Gateway 類別，使用該類別
 * 否則使用 OmnipayGateway 動態建立
 *
 * @param  array  $gateways  Existing gateways
 * @return array Modified gateways
 */
function woocommerce_omnipay_add_gateways($gateways)
{
    // 使用 GatewayRegistry 載入 gateways
    $registry = new \WooCommerceOmnipay\GatewayRegistry(
        woocommerce_omnipay_get_config()
    );

    // 為每個已配置的 gateway 建立實例並註冊
    foreach ($registry->getGateways() as $gateway_info) {
        $omnipay_name = $gateway_info['omnipay_name'] ?? '';
        $gateway_class = "\\WooCommerceOmnipay\\Gateways\\{$omnipay_name}Gateway";

        if (! class_exists($gateway_class)) {
            $gateway_class = \WooCommerceOmnipay\Gateways\OmnipayGateway::class;
        }

        $gateways[] = new $gateway_class($gateway_info);
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
    // 預設 gateways 配置
    // 純陣列格式，必須指定 omnipay_name 和 gateway_id
    $default_config = [
        'gateways' => [
            [
                'omnipay_name' => 'BankTransfer',
                'gateway_id' => 'banktransfer',
                'title' => '銀行轉帳',
                'description' => '使用銀行轉帳付款',
            ],
            [
                'omnipay_name' => 'Dummy',
                'gateway_id' => 'dummy',
                'title' => 'Dummy Gateway',
                'description' => 'Dummy payment gateway for testing',
            ],
            [
                'omnipay_name' => 'ECPay',
                'gateway_id' => 'ecpay',
                'title' => '綠界金流',
                'description' => '使用綠界金流付款',
            ],
            [
                'omnipay_name' => 'NewebPay',
                'gateway_id' => 'newebpay',
                'title' => '藍新金流',
                'description' => '使用藍新金流付款',
            ],
            [
                'omnipay_name' => 'YiPay',
                'gateway_id' => 'yipay',
                'title' => 'YiPay 乙禾金流',
                'description' => '使用 YiPay 乙禾金流付款',
            ],
        ],
    ];

    // 允許透過 filter 自訂配置
    return apply_filters('woocommerce_omnipay_gateway_config', $default_config);
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
 * Get template file path
 *
 * Look for template in theme first, then fallback to plugin templates
 *
 * @param  string  $template_name  Template name (e.g., 'payment-info.php')
 * @return string Template file path
 */
function woocommerce_omnipay_get_template_path($template_name)
{
    // Look in theme/child-theme first
    $theme_template = locate_template([
        'woocommerce-omnipay/'.$template_name,
    ]);

    if ($theme_template) {
        return $theme_template;
    }

    // Fallback to plugin templates
    return WOOCOMMERCE_OMNIPAY_PLUGIN_DIR.'templates/'.$template_name;
}

/**
 * Load and render a template
 *
 * @param  string  $template_name  Template name (e.g., 'payment-info.php')
 * @param  array  $args  Variables to pass to the template
 * @return string Rendered template content
 */
function woocommerce_omnipay_get_template($template_name, $args = [])
{
    $template_path = woocommerce_omnipay_get_template_path($template_name);

    if (! file_exists($template_path)) {
        return '';
    }

    // Extract args to make them available in template
    if (! empty($args)) {
        extract($args);
    }

    ob_start();
    include $template_path;

    return ob_get_clean();
}
