<?php

namespace WooCommerceOmnipay\Adapters\Concerns;

use Omnipay\Common\GatewayInterface;
use Omnipay\Omnipay;
use WooCommerceOmnipay\Helper;

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
     * @param  array  $allSettings  所有設定（已合併優先順序）
     */
    public function initializeFromSettings(array $allSettings): self
    {
        $parameters = [];

        foreach ($this->getDefaultParameters() as $key => $defaultValue) {
            if (isset($allSettings[$key]) && $allSettings[$key] !== '') {
                $parameters[$key] = Helper::convertOptionValue($allSettings[$key], $defaultValue);
            }
        }

        return $this->initialize($parameters);
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

    public function getDefaultParameters(): array
    {
        return Omnipay::create($this->getGatewayName())->getDefaultParameters();
    }

    public function createGateway(array $settings): GatewayInterface
    {
        $gateway = Omnipay::create($this->getGatewayName());
        $gateway->initialize($settings);

        return $gateway;
    }
}
