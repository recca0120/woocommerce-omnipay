<?php

namespace WooCommerceOmnipay\Gateways\Features;

/**
 * Feature Factory
 *
 * 從配置建立 Feature 物件
 */
class FeatureFactory
{
    /**
     * @var array Feature 名稱對應的類別
     */
    private static $featureMap = [
        'min_amount' => MinAmountFeature::class,
        'max_amount' => MaxAmountFeature::class,
        'expire_date' => ExpireDateFeature::class,
    ];

    /**
     * 從配置建立 Features 陣列
     *
     * @param  array  $config  Gateway 配置
     * @return GatewayFeature[]
     */
    public static function createFromConfig(array $config): array
    {
        $features = [];

        // 處理 payment_data
        if (! empty($config['payment_data'])) {
            $features[] = new PaymentDataFeature($config['payment_data']);
        }

        // 處理 features 陣列
        if (! empty($config['features'])) {
            foreach ($config['features'] as $feature) {
                $resolved = self::resolve($feature);
                if ($resolved !== null) {
                    $features[] = $resolved;
                }
            }
        }

        return $features;
    }

    /**
     * 解析 Feature
     *
     * @param  mixed  $feature  Feature 名稱或物件
     */
    public static function resolve($feature): ?GatewayFeature
    {
        // 已經是 Feature 物件
        if ($feature instanceof GatewayFeature) {
            return $feature;
        }

        // 字串名稱
        if (is_string($feature) && isset(self::$featureMap[$feature])) {
            $class = self::$featureMap[$feature];

            return new $class;
        }

        return null;
    }

    /**
     * 註冊自訂 Feature
     *
     * @param  string  $name  Feature 名稱
     * @param  string  $class  Feature 類別
     */
    public static function register(string $name, string $class): void
    {
        self::$featureMap[$name] = $class;
    }
}
