<?php

namespace Recca0120\WooCommerce_Omnipay\Adapters\Concerns;

use Omnipay\Common\Message\NotificationInterface;

/**
 * Handles Notifications
 *
 * 提供通知處理操作
 */
trait HandlesNotifications
{
    public function supportsAcceptNotification(): bool
    {
        return $this->getGateway()->supportsAcceptNotification();
    }

    public function acceptNotification(array $parameters = []): NotificationInterface
    {
        return $this->getGateway()->acceptNotification($parameters);
    }
}
