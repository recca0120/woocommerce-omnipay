<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters;

use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns\CreatesGateway;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns\FormatsCallbackResponse;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns\HandlesNotifications;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns\HandlesPurchases;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns\HasPaymentInfo;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Contracts\GatewayAdapter;

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

    public function normalizePaymentInfo(array $data): array
    {
        // 預設直接回傳原始資料
        return $data;
    }

    /**
     * 驗證指定欄位的金額是否與訂單金額相符
     */
    protected function validateAmountField(array $data, string $fieldName, int $orderTotal): bool
    {
        $amount = isset($data[$fieldName]) ? (int) $data[$fieldName] : 0;

        return $amount === $orderTotal;
    }
}
