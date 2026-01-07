<?php

namespace Recca0120\WooCommerce_Omnipay\Adapters\Concerns;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Has Payment Info
 *
 * 提供付款資訊相關操作
 */
trait HasPaymentInfo
{
    public function supportsGetPaymentInfo(): bool
    {
        return method_exists($this->getGateway(), 'getPaymentInfo');
    }

    public function getPaymentInfo(array $parameters = []): ResponseInterface
    {
        return $this->getGateway()->getPaymentInfo($parameters)->send();
    }

    public function getPaymentInfoUrlSuffix(): string
    {
        return '_payment_info';
    }

    public function getPaymentInfoNote(array $data): ?string
    {
        return null;
    }
}
