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
        return true;
    }

    /**
     * 驗證指定欄位的金額是否與訂單金額相符
     */
    protected function validateAmountField(array $data, string $fieldName, int $orderTotal): bool
    {
        $amount = isset($data[$fieldName]) ? (int) $data[$fieldName] : 0;

        return $amount === $orderTotal;
    }

    public function normalizePaymentInfo(array $data): array
    {
        // 預設直接回傳原始資料
        return $data;
    }
}
