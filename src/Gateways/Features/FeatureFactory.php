<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features;

/**
 * Feature Factory
 *
 * 從配置建立 Feature 物件
 */
class FeatureFactory
{
    /**
     * 從配置建立 Features 陣列
     *
     * @param  array  $config  Gateway 配置
     * @return GatewayFeature[]
     */
    public static function createFromConfig(array $config): array
    {
        $features = [];

        if (! empty($config['payment_data'])) {
            $features[] = new PaymentDataFeature($config['payment_data']);
        }

        return ! empty($config['features'])
            ? array_merge($features, $config['features'])
            : $features;
    }
}
