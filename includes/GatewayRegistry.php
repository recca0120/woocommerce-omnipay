<?php

namespace WooCommerceOmnipay;

/**
 * Gateway Registry
 *
 * 從配置檔載入並註冊 Omnipay gateways
 *
 * 配置格式：純陣列，每個元素包含：
 * - omnipay_name: 必須指定的 Omnipay gateway 名稱
 * - gateway_id: 必須指定的 WooCommerce gateway ID
 * - title: 選填，預設使用 omnipay_name
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
     * @param  string  $gateway_name  Omnipay gateway 名稱
     * @return bool
     */
    public function isGatewayAvailable($gateway_name)
    {
        try {
            \Omnipay\Omnipay::create($gateway_name);

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

        foreach ($this->config['gateways'] as $gateway_config) {
            // 必須有 omnipay_name
            if (empty($gateway_config['omnipay_name'])) {
                continue;
            }

            // 必須有 gateway_id
            if (empty($gateway_config['gateway_id'])) {
                continue;
            }

            // 驗證 Omnipay gateway 是否可用
            if (! $this->isGatewayAvailable($gateway_config['omnipay_name'])) {
                continue;
            }

            // 補上預設值
            $gateways[] = $this->createGatewayInfo($gateway_config);
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
        $omnipay_name = $config['omnipay_name'];

        $defaults = [
            'title' => $omnipay_name,
            'description' => $this->generateDescription($config['title'] ?? $omnipay_name),
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
}
