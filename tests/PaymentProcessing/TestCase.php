<?php

namespace WooCommerceOmnipay\Tests\PaymentProcessing;

use WP_UnitTestCase;

/**
 * Payment Processing 測試基類
 */
abstract class TestCase extends WP_UnitTestCase
{
    protected $gateway;

    protected $configCallback;

    protected $gatewayId;

    protected $gatewayName;

    protected $settings = [];

    protected function setUp(): void
    {
        parent::setUp();

        add_filter('woocommerce_omnipay_should_exit', '__return_false');

        wp_cache_delete('woocommerce_omnipay_'.$this->gatewayId.'_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        $gatewayId = $this->gatewayId;
        $gatewayName = $this->gatewayName;
        $this->configCallback = function () use ($gatewayId, $gatewayName) {
            return [
                'gateways' => [[
                    'gateway' => $gatewayName,
                    'gateway_id' => $gatewayId,
                    'title' => $gatewayName,
                ]],
            ];
        };
        add_filter('woocommerce_omnipay_gateway_config', $this->configCallback);

        update_option('woocommerce_omnipay_'.$this->gatewayId.'_settings', array_merge(
            ['enabled' => 'yes'],
            $this->settings
        ));

        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();

        $this->gateway = WC()->payment_gateways->payment_gateways()['omnipay_'.$this->gatewayId];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        delete_option('woocommerce_omnipay_'.$this->gatewayId.'_settings');
        wp_cache_delete('woocommerce_omnipay_'.$this->gatewayId.'_settings', 'options');
        wp_cache_delete('alloptions', 'options');

        if ($this->configCallback) {
            remove_filter('woocommerce_omnipay_gateway_config', $this->configCallback);
        }
        remove_filter('woocommerce_omnipay_should_exit', '__return_false');
        WC()->payment_gateways()->payment_gateways = [];

        parent::tearDown();
    }

    /**
     * 建立測試訂單（含產品）
     */
    protected function createOrder($amount = 100, $currency = 'TWD', $virtual = false, $downloadable = false)
    {
        $product = new \WC_Product_Simple;
        $product->set_name('Test Product');
        $product->set_regular_price($amount);
        $product->set_virtual($virtual);
        $product->set_downloadable($downloadable);
        $product->save();

        $order = wc_create_order();
        $order->set_currency($currency);
        $order->add_product($product, 1);
        $order->set_payment_method($this->gateway->id);
        $order->calculate_totals();
        $order->save();

        return $order;
    }

    /**
     * 建立簡單訂單（不含產品）
     */
    protected function createSimpleOrder($amount = 100, $currency = 'TWD')
    {
        $order = wc_create_order();
        $order->set_currency($currency);
        $order->set_total($amount);
        $order->set_payment_method($this->gateway->id);
        $order->save();

        return $order;
    }

    /**
     * 模擬 callback POST 資料
     */
    protected function simulateCallback(array $data)
    {
        $_POST = $data;
        $_REQUEST = $data;
    }

    /**
     * 建立測試產品
     */
    protected function createProduct($price = 100)
    {
        $product = new \WC_Product_Simple;
        $product->set_name('Test Product');
        $product->set_regular_price($price);
        $product->save();

        return $product;
    }
}
