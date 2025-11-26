<?php

namespace WooCommerceOmnipay;

/**
 * Gateway Registry
 *
 * 從配置檔載入並註冊 Omnipay gateways
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
     * 取得啟用的 gateways
     *
     * 從配置檔讀取並驗證 gateway 是否可用
     *
     * @return array
     */
    public function getEnabledGateways()
    {
        $gateways = [];

        foreach ($this->config['gateways'] as $gateway_name => $gateway_config) {
            // 檢查是否啟用
            if (empty($gateway_config['enabled'])) {
                continue;
            }

            // 驗證 gateway 是否真的可用
            if (! $this->isGatewayAvailable($gateway_name)) {
                continue;
            }

            // 建立 gateway 資訊（使用配置或預設值）
            $gateways[$gateway_name] = $this->createGatewayInfo($gateway_name, $gateway_config);
        }

        return $gateways;
    }

    /**
     * 建立 gateway 資訊
     *
     * @param  string  $omnipay_name  Omnipay gateway 名稱
     * @param  array  $config  配置（可選）
     * @return array
     */
    protected function createGatewayInfo($omnipay_name, array $config = [])
    {
        $defaults = [
            'omnipay_name' => $omnipay_name,
            'gateway_id' => $this->generateGatewayId($omnipay_name),
            'title' => $this->generateTitle($omnipay_name),
            'description' => $this->generateDescription($omnipay_name),
        ];

        // 合併配置，配置優先
        return array_merge($defaults, $config, [
            'omnipay_name' => $omnipay_name, // omnipay_name 不可覆寫
        ]);
    }

    /**
     * 產生 WooCommerce gateway ID
     *
     * 格式：omnipay_{lowercase_name_with_underscores}
     *
     * @param  string  $omnipay_name
     * @return string
     */
    protected function generateGatewayId($omnipay_name)
    {
        // 將 PayPal_Express 轉換為 paypal_express
        $id = strtolower($omnipay_name);
        // 確保只包含字母、數字和底線
        $id = preg_replace('/[^a-z0-9_]/', '_', $id);

        return 'omnipay_'.$id;
    }

    /**
     * 產生預設 title
     *
     * @param  string  $omnipay_name
     * @return string
     */
    protected function generateTitle($omnipay_name)
    {
        // PayPal_Express -> PayPal Express
        return str_replace('_', ' ', $omnipay_name);
    }

    /**
     * 產生預設 description
     *
     * @param  string  $omnipay_name
     * @return string
     */
    protected function generateDescription($omnipay_name)
    {
        return sprintf('Pay with %s', $this->generateTitle($omnipay_name));
    }
}
