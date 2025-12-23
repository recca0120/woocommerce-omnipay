<?php

namespace WooCommerceOmnipay\Adapters;

use WooCommerceOmnipay\Adapters\Concerns\CreatesGateway;
use WooCommerceOmnipay\Adapters\Concerns\FormatsCallbackResponse;
use WooCommerceOmnipay\Adapters\Concerns\HandlesNotifications;
use WooCommerceOmnipay\Adapters\Concerns\HandlesPurchases;
use WooCommerceOmnipay\Adapters\Concerns\HasPaymentInfo;
use WooCommerceOmnipay\Adapters\Contracts\GatewayAdapter;

/**
 * Default Gateway Adapter
 *
 * 通用的 Gateway Adapter，用於沒有特定實作的 Gateway
 */
class DefaultGatewayAdapter implements GatewayAdapter
{
    use CreatesGateway;
    use FormatsCallbackResponse;
    use HandlesNotifications;
    use HandlesPurchases;
    use HasPaymentInfo;

    /**
     * @var string
     */
    private $gatewayName;

    public function __construct(string $gatewayName)
    {
        $this->gatewayName = $gatewayName;
    }

    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }

    public function validateAmount(array $data, int $orderTotal): bool
    {
        // 預設不驗證金額
        return true;
    }

    public function normalizePaymentInfo(array $data): array
    {
        // 預設直接回傳原始資料
        return $data;
    }
}
