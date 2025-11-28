<?php

namespace WooCommerceOmnipay\Services;

/**
 * Gateway Registry
 *
 * 從配置檔載入並註冊 Omnipay gateways
 *
 * 配置格式：純陣列，每個元素包含：
 * - gateway: 必須指定的 Omnipay gateway 名稱
 * - gateway_id: 必須指定的 WooCommerce gateway ID
 * - class: 選填，指定的 Gateway 類別（完整命名空間）
 * - title: 選填，預設使用 gateway
 * - description: 選填，自動產生
 */
class GatewayRegistry
{
    /**
     * 配置
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param  array  $config  配置選項
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'gateways' => [],
        ], $config);
    }

    /**
     * 驗證 gateway 是否已安裝且可用
     *
     * @param  string  $name  Omnipay gateway 名稱
     * @return bool
     */
    public function isGatewayAvailable($name)
    {
        try {
            \Omnipay\Omnipay::create($name);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 取得所有 gateways
     *
     * 從配置檔讀取並驗證 gateway 是否可用
     *
     * @return array
     */
    public function getGateways()
    {
        $gateways = [];

        foreach ($this->config['gateways'] as $config) {
            // 必須有 gateway
            if (empty($config['gateway'])) {
                continue;
            }

            // 必須有 gateway_id
            if (empty($config['gateway_id'])) {
                continue;
            }

            // 驗證 Omnipay gateway 是否可用
            if (! $this->isGatewayAvailable($config['gateway'])) {
                continue;
            }

            // 補上預設值
            $gateways[] = $this->createGatewayInfo($config);
        }

        return $gateways;
    }

    /**
     * 取得啟用的 gateways（已棄用，回傳同 getGateways）
     *
     * @return array
     *
     * @deprecated 使用 getGateways() 代替
     */
    public function getEnabledGateways()
    {
        return $this->getGateways();
    }

    /**
     * 建立 gateway 資訊
     *
     * @param  array  $config  配置
     * @return array
     */
    protected function createGatewayInfo(array $config)
    {
        $name = $config['gateway'];

        $defaults = [
            'title' => $name,
            'description' => $this->generateDescription($config['title'] ?? $name),
        ];

        return array_merge($defaults, $config);
    }

    /**
     * 產生預設 description
     *
     * @param  string  $title
     * @return string
     */
    protected function generateDescription($title)
    {
        return sprintf('Pay with %s', $title);
    }

    /**
     * 解析 Gateway 類別
     *
     * 優先順序：
     * 1. 配置中指定的 class
     * 2. 自動偵測的具體 Gateway 類別
     * 3. 使用 OmnipayGateway 動態建立
     *
     * @param  array  $gatewayInfo  Gateway 配置資訊
     * @return string Gateway 類別名稱
     */
    public function resolveGatewayClass(array $gatewayInfo)
    {
        // 1. 優先使用配置中指定的 class
        if (! empty($gatewayInfo['class']) && class_exists($gatewayInfo['class'])) {
            return $gatewayInfo['class'];
        }

        $name = $gatewayInfo['gateway'] ?? '';

        // 2. 嘗試自動偵測具體 Gateway 類別
        $gatewayClass = "\\WooCommerceOmnipay\\Gateways\\{$name}Gateway";
        if (class_exists($gatewayClass)) {
            return $gatewayClass;
        }

        // 3. 使用 OmnipayGateway 動態建立
        return \WooCommerceOmnipay\Gateways\OmnipayGateway::class;
    }
}
