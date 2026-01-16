<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Handles Purchases
 *
 * 提供購買相關操作
 */
trait HandlesPurchases
{
    public function purchase(array $data): ResponseInterface
    {
        return $this->getGateway()->purchase($data)->send();
    }

    public function completePurchase(array $parameters = []): ResponseInterface
    {
        return $this->getGateway()->completePurchase($parameters)->send();
    }
}
