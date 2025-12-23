<?php

namespace WooCommerceOmnipay\Adapters;

/**
 * Default Gateway Adapter
 *
 * 通用的 Gateway Adapter，用於沒有特定實作的 Gateway
 */
class DefaultGatewayAdapter implements GatewayAdapterInterface
{
    use GatewayOperationsTrait;

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
