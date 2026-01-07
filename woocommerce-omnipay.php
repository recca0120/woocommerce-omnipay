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
 *
 * WordPress 6.7+ requires textdomain to be loaded at 'init' or later.
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

    // Register shared settings pages (after init to ensure textdomain is loaded)
    add_action('init', 'woocommerce_omnipay_register_shared_settings', 20);

    // Handle redirect form rendering
    add_action('template_redirect', 'woocommerce_omnipay_maybe_render_redirect_form');

    // Register frontend scripts
    add_action('wp_enqueue_scripts', 'woocommerce_omnipay_register_scripts');

    // Register admin scripts
    add_action('admin_enqueue_scripts', 'woocommerce_omnipay_register_admin_scripts');
}
add_action('plugins_loaded', 'woocommerce_omnipay_init');

/**
 * Register shared settings page
 */
function woocommerce_omnipay_register_shared_settings()
{
    $config = woocommerce_omnipay_get_config();
    $registry = new \Recca0120\WooCommerce_Omnipay\GatewayRegistry($config);

    $sections = [new \Recca0120\WooCommerce_Omnipay\Settings\GeneralSettingsSection];

    // 取得不重複的 gateway 配置
    $seen = [];
    foreach ($config['gateways'] as $gatewayConfig) {
        $name = $gatewayConfig['gateway'] ?? '';
        if (empty($name) || isset($seen[$name]) || ! $registry->isAvailable($name)) {
            continue;
        }
        $seen[$name] = true;

        $adapter = $registry->resolveAdapter($gatewayConfig);
        $sections[] = $name === 'BankTransfer'
            ? new \Recca0120\WooCommerce_Omnipay\Settings\BankTransferSettingsSection($adapter)
            : new \Recca0120\WooCommerce_Omnipay\Settings\GatewaySettingsSection($adapter);
    }

    $page = new \Recca0120\WooCommerce_Omnipay\SharedSettingsPage($sections);
    $page->register();
}

/**
 * Register frontend scripts and styles
 */
function woocommerce_omnipay_register_scripts()
{
    // Only load on order-related pages
    if (! is_checkout() && ! is_wc_endpoint_url('order-received') && ! is_wc_endpoint_url('view-order')) {
        return;
    }

    // Register JsBarcode
    wp_register_script(
        'jsbarcode',
        WOOCOMMERCE_OMNIPAY_PLUGIN_URL.'assets/js/vendor/jsbarcode.min.js',
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
 * Register admin scripts
 */
function woocommerce_omnipay_register_admin_scripts($hook)
{
    // Only load on WooCommerce settings pages
    if ($hook !== 'woocommerce_page_wc-settings') {
        return;
    }

    // Register and enqueue admin scripts
    wp_enqueue_script(
        'woocommerce-omnipay-admin',
        WOOCOMMERCE_OMNIPAY_PLUGIN_URL.'assets/js/admin.js',
        [],
        WOOCOMMERCE_OMNIPAY_VERSION,
        true
    );
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

    \Recca0120\WooCommerce_Omnipay\Helper::terminate();
}

/**
 * Add Omnipay gateways to WooCommerce
 *
 * @param  array  $gateways  Existing gateways
 * @return array Modified gateways
 */
function woocommerce_omnipay_add_gateways($gateways)
{
    $registry = new \Recca0120\WooCommerce_Omnipay\GatewayRegistry(
        woocommerce_omnipay_get_config()
    );

    foreach ($registry->getGateways() as $gatewayInfo) {
        $gatewayClass = $registry->resolveGatewayClass($gatewayInfo);
        $adapter = $registry->resolveAdapter($gatewayInfo);
        $gateways[] = new $gatewayClass($gatewayInfo, $adapter);
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
    return apply_filters('woocommerce_omnipay_gateway_config', [
        'gateways' => woocommerce_omnipay_get_gateways(),
    ]);
}

/**
 * Get gateway configurations
 *
 * This function is called via woocommerce_payment_gateways filter,
 * which runs after 'init' hook when translations are available.
 *
 * @return array
 */
function woocommerce_omnipay_get_gateways()
{
    // Icon URLs
    $ecpayIcon = plugins_url('assets/images/payment-icons/ecpay.png', __FILE__);
    $newebpayIcon = plugins_url('assets/images/payment-icons/newebpay.png', __FILE__);
    $yipayIcon = plugins_url('assets/images/payment-icons/yipay.png', __FILE__);

    return [
        // Dummy (for testing)
        [
            'gateway' => 'Dummy',
            'gateway_id' => 'dummy',
            'title' => __('Dummy Gateway', 'woocommerce-omnipay'),
        ],
        // Bank Transfer
        [
            'gateway' => 'BankTransfer',
            'gateway_id' => 'banktransfer',
            'title' => __('Bank Transfer', 'woocommerce-omnipay'),
        ],
        // ECPay (All-in-one)
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay',
            'title' => __('ECPay', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
        ],
        // ECPay Sub-Gateways
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_credit',
            'title' => __('ECPay Credit Card', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_credit_installment',
            'title' => __('ECPay Credit Card Installment', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\InstallmentFeature('CreditInstallment', ['periodRules' => ['30' => ['min_amount' => 20000]]]),
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_dca',
            'title' => __('ECPay Recurring Payment', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'Credit'],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\FrequencyRecurringFeature],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_bnpl',
            'title' => __('ECPay BNPL', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'BNPL'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_webatm',
            'title' => __('ECPay WebATM', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'WebATM'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_atm',
            'title' => __('ECPay ATM', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'ATM'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\ExpireDateFeature('ExpireDate', 3, 1, 60),
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_cvs',
            'title' => __('ECPay CVS', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'CVS'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\ExpireDateFeature('StoreExpireDate', 10080, 1, 43200),
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_barcode',
            'title' => __('ECPay Barcode', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'BARCODE'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\ExpireDateFeature('StoreExpireDate', 7, 1, 30),
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_applepay',
            'title' => __('ECPay Apple Pay', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'ApplePay'],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_twqr',
            'title' => __('ECPay Taiwan Pay', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'TWQR'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'ECPay',
            'gateway_id' => 'ecpay_weixin',
            'title' => __('ECPay WeChat Pay', 'woocommerce-omnipay'),
            'icon' => $ecpayIcon,
            'payment_data' => ['ChoosePayment' => 'WeiXin'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        // NewebPay (All-in-one)
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay',
            'title' => __('NewebPay', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
        ],
        // NewebPay Sub-Gateways
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_credit',
            'title' => __('NewebPay Credit Card', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['CREDIT' => 1],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_credit_installment',
            'title' => __('NewebPay Credit Card Installment', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['CREDIT' => 1],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\InstallmentFeature('InstFlag'),
            ],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_dca',
            'title' => __('NewebPay Recurring Payment', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['CREDIT' => 1],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\ScheduledRecurringFeature],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_webatm',
            'title' => __('NewebPay WebATM', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['WEBATM' => 1],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_atm',
            'title' => __('NewebPay ATM', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['VACC' => 1],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_cvs',
            'title' => __('NewebPay CVS', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['CVS' => 1],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'NewebPay',
            'gateway_id' => 'newebpay_barcode',
            'title' => __('NewebPay Barcode', 'woocommerce-omnipay'),
            'icon' => $newebpayIcon,
            'payment_data' => ['BARCODE' => 1],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        // YiPay (All-in-one)
        [
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay',
            'title' => __('YiPay', 'woocommerce-omnipay'),
            'icon' => $yipayIcon,
        ],
        // YiPay Sub-Gateways
        [
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_credit',
            'title' => __('YiPay Credit Card', 'woocommerce-omnipay'),
            'icon' => $yipayIcon,
            'payment_data' => ['type' => '2'],
            'features' => [new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature],
        ],
        [
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_atm',
            'title' => __('YiPay ATM', 'woocommerce-omnipay'),
            'icon' => $yipayIcon,
            'payment_data' => ['type' => '4'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
        [
            'gateway' => 'YiPay',
            'gateway_id' => 'yipay_cvs',
            'title' => __('YiPay CVS', 'woocommerce-omnipay'),
            'icon' => $yipayIcon,
            'payment_data' => ['type' => '3'],
            'features' => [
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MinAmountFeature,
                new \Recca0120\WooCommerce_Omnipay\Gateways\Features\MaxAmountFeature,
            ],
        ],
    ];
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
