<?php

namespace WooCommerceOmnipay\Adapters;

use Omnipay\Common\GatewayInterface;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Omnipay;

/**
 * Gateway Operations Trait
 *
 * 提供 Omnipay Gateway 的共同操作實作
 */
trait GatewayOperationsTrait
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
        $this->gateway = null; // 重設 gateway，下次使用時重新建立

        return $this;
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

    public function createGateway(array $settings): GatewayInterface
    {
        $gateway = Omnipay::create($this->getGatewayName());
        $gateway->initialize($settings);

        return $gateway;
    }

    public function purchase(array $data): ResponseInterface
    {
        return $this->getGateway()->purchase($data)->send();
    }

    public function completePurchase(array $parameters = []): ResponseInterface
    {
        return $this->getGateway()->completePurchase($parameters)->send();
    }

    public function supportsAcceptNotification(): bool
    {
        return $this->getGateway()->supportsAcceptNotification();
    }

    public function acceptNotification(array $parameters = []): NotificationInterface
    {
        return $this->getGateway()->acceptNotification($parameters);
    }

    public function supportsGetPaymentInfo(): bool
    {
        return method_exists($this->getGateway(), 'getPaymentInfo');
    }

    public function getPaymentInfo(array $parameters = []): ResponseInterface
    {
        return $this->getGateway()->getPaymentInfo($parameters)->send();
    }

    public function getPaymentInfoEndpoint(): string
    {
        return '_payment_info';
    }

    public function getCallbackSuccessResponse(): string
    {
        return '1|OK';
    }

    public function getCallbackFailureResponse(string $message): string
    {
        return '0|'.$message;
    }

    public function isPaymentInfoNotification(array $data): bool
    {
        return false;
    }
}
