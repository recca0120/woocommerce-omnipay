<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns;

use Omnipay\Common\GatewayInterface;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Omnipay;
use OmnipayTaiwan\WooCommerce_Omnipay\Helper;

/**
 * Creates Gateway
 *
 * 提供 Omnipay Gateway 的建立與初始化
 */
trait CreatesGateway
{
    /**
     * @var GatewayInterface|null
     */
    protected $gateway;

    /**
     * @var array
     */
    protected $settings = [];

    /**
     * @var ClientInterface|null
     */
    protected $httpClient;

    /**
     * 設定 HTTP Client
     *
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * 設定 Gateway 參數
     */
    public function initialize(array $settings): self
    {
        $this->settings = $settings;
        $this->gateway = null;

        return $this;
    }

    /**
     * 從所有設定中初始化（自動過濾並轉換型別）
     *
     * @param  array  $settings  所有設定（已合併優先順序）
     */
    public function initializeFromSettings(array $settings): self
    {
        $parameters = [];

        foreach ($this->getDefaultParameters() as $key => $defaultValue) {
            if (isset($settings[$key]) && $settings[$key] !== '') {
                $parameters[$key] = Helper::convertOptionValue($settings[$key], $defaultValue);
            }
        }

        return $this->initialize($parameters);
    }

    public function getDefaultParameters(): array
    {
        return Omnipay::create($this->getGatewayName(), $this->getHttpClient())->getDefaultParameters();
    }

    public function getSettingsFields(): array
    {
        return $this->getDefaultParameters();
    }

    public function createGateway(array $settings): GatewayInterface
    {
        $gateway = Omnipay::create($this->getGatewayName(), $this->getHttpClient());
        $gateway->initialize($settings);

        return $gateway;
    }

    /**
     * 取得 Gateway 實例
     */
    protected function getGateway(): GatewayInterface
    {
        if ($this->gateway === null) {
            $this->gateway = $this->createGateway($this->settings);
        }

        return $this->gateway;
    }

    /**
     * 取得 HTTP Client
     */
    protected function getHttpClient(): ?ClientInterface
    {
        return $this->httpClient;
    }
}
