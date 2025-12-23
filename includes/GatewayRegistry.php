<?php

namespace WooCommerceOmnipay;

use Omnipay\Common\Http\ClientInterface;
use WooCommerceOmnipay\Adapters\Contracts\GatewayAdapter;
use WooCommerceOmnipay\Adapters\DefaultGatewayAdapter;
use WooCommerceOmnipay\WordPress\HttpClient;

/**
 * Gateway Registry
 *
 * 從配置檔載入並註冊 Omnipay gateways
 *
 * 配置格式：純陣列，每個元素包含：
 * - gateway: 必須指定的 Omnipay gateway 名稱
 * - gateway_id: 必須指定的 WooCommerce gateway ID
 * - class: 選填，指定的 Gateway 類別（完整命名空間）
 * - adapter: 選填，指定的 Adapter 類別（完整命名空間）
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
     * Gateway 可用性快取
     *
     * @var array
     */
    protected $availabilityCache = [];

    /**
     * HTTP Client
     *
     * @var ClientInterface|null
     */
    protected $httpClient;

    /**
     * Constructor
     *
     * @param  array  $config  配置選項
     * @param  ClientInterface|null  $httpClient  HTTP Client
     */
    public function __construct(array $config = [], ?ClientInterface $httpClient = null)
    {
        $this->config = array_merge([
            'gateways' => [],
        ], $config);
        $this->httpClient = $httpClient ?? new HttpClient;
    }

    /**
     * 取得 HTTP Client
     */
    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    /**
     * 驗證 gateway 是否已安裝且可用
     *
     * @param  string  $name  Omnipay gateway 名稱
     * @return bool
     */
    public function isAvailable($name)
    {
        if (isset($this->availabilityCache[$name])) {
            return $this->availabilityCache[$name];
        }

        try {
            \Omnipay\Omnipay::create($name, $this->getHttpClient());
            $this->availabilityCache[$name] = true;
        } catch (\Exception $e) {
            $this->availabilityCache[$name] = false;
        }

        return $this->availabilityCache[$name];
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
            if (! $this->isValidConfig($config)) {
                continue;
            }

            $gateways[] = $this->createGatewayInfo($config);
        }

        return $gateways;
    }

    /**
     * 驗證配置是否有效
     *
     * @param  array  $config  配置
     * @return bool
     */
    protected function isValidConfig(array $config)
    {
        if (empty($config['gateway']) || empty($config['gateway_id'])) {
            return false;
        }

        return $this->isAvailable($config['gateway']);
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
            'description' => '',
        ];

        return array_merge($defaults, $config);
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

    /**
     * 解析 Gateway Adapter
     *
     * 優先順序：
     * 1. 配置中指定的 adapter
     * 2. 自動偵測的具體 Adapter 類別
     * 3. 使用 DefaultGatewayAdapter
     *
     * @param  array  $gatewayInfo  Gateway 配置資訊
     */
    public function resolveAdapter(array $gatewayInfo): GatewayAdapter
    {
        $adapter = $this->createAdapter($gatewayInfo);
        $adapter->setHttpClient($this->getHttpClient());

        return $adapter;
    }

    /**
     * 建立 Adapter 實例
     */
    protected function createAdapter(array $gatewayInfo): GatewayAdapter
    {
        // 1. 優先使用配置中指定的 adapter
        if (! empty($gatewayInfo['adapter']) && class_exists($gatewayInfo['adapter'])) {
            return new $gatewayInfo['adapter'];
        }

        $name = $gatewayInfo['gateway'] ?? '';

        // 2. 嘗試自動偵測具體 Adapter 類別
        $adapterClass = "\\WooCommerceOmnipay\\Adapters\\{$name}Adapter";
        if (class_exists($adapterClass)) {
            return new $adapterClass;
        }

        // 3. 使用 DefaultGatewayAdapter
        return new DefaultGatewayAdapter($name);
    }
}
