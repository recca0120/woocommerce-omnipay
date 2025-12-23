<?php

namespace WooCommerceOmnipay\Tests\Unit\Adapters;

use PHPUnit\Framework\TestCase;
use WooCommerceOmnipay\Adapters\ECPayAdapter;
use WooCommerceOmnipay\Adapters\GatewayAdapterInterface;
use WooCommerceOmnipay\Adapters\NewebPayAdapter;
use WooCommerceOmnipay\Adapters\YiPayAdapter;

/**
 * GatewayAdapter Test
 *
 * 測試各金流 Adapter 的行為
 */
class GatewayAdapterTest extends TestCase
{
    /**
     * @dataProvider adapterProvider
     */
    public function test_adapter_implements_interface(string $adapterClass): void
    {
        $adapter = new $adapterClass;

        $this->assertInstanceOf(GatewayAdapterInterface::class, $adapter);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function test_adapter_returns_gateway_name(string $adapterClass, string $expectedName): void
    {
        $adapter = new $adapterClass;

        $this->assertEquals($expectedName, $adapter->getGatewayName());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function test_adapter_can_create_gateway(string $adapterClass): void
    {
        $adapter = new $adapterClass;
        $gateway = $adapter->createGateway([]);

        $this->assertNotNull($gateway);
    }

    /**
     * @dataProvider amountValidationProvider
     */
    public function test_adapter_validates_amount(
        string $adapterClass,
        array $data,
        int $orderTotal,
        bool $expected
    ): void {
        $adapter = new $adapterClass;

        $this->assertEquals($expected, $adapter->validateAmount($data, $orderTotal));
    }

    /**
     * @dataProvider paymentInfoNormalizationProvider
     */
    public function test_adapter_normalizes_payment_info(
        string $adapterClass,
        array $input,
        array $expectedKeys
    ): void {
        $adapter = new $adapterClass;
        $normalized = $adapter->normalizePaymentInfo($input);

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $normalized);
        }
    }

    public function test_ecpay_adapter_identifies_payment_info_notification(): void
    {
        $adapter = new ECPayAdapter;

        // ATM 取號成功
        $this->assertTrue($adapter->isPaymentInfoNotification(['RtnCode' => '2']));

        // CVS/BARCODE 取號成功
        $this->assertTrue($adapter->isPaymentInfoNotification(['RtnCode' => '10100073']));

        // 付款成功
        $this->assertFalse($adapter->isPaymentInfoNotification(['RtnCode' => '1']));
    }

    public static function adapterProvider(): array
    {
        return [
            'ECPay' => [ECPayAdapter::class, 'ECPay'],
            'NewebPay' => [NewebPayAdapter::class, 'NewebPay'],
            'YiPay' => [YiPayAdapter::class, 'YiPay'],
        ];
    }

    public static function amountValidationProvider(): array
    {
        return [
            // ECPay
            'ECPay valid amount' => [ECPayAdapter::class, ['TradeAmt' => '100'], 100, true],
            'ECPay invalid amount' => [ECPayAdapter::class, ['TradeAmt' => '200'], 100, false],
            'ECPay missing amount' => [ECPayAdapter::class, [], 100, false],

            // NewebPay
            'NewebPay valid amount' => [NewebPayAdapter::class, ['Amt' => '100'], 100, true],
            'NewebPay invalid amount' => [NewebPayAdapter::class, ['Amt' => '200'], 100, false],

            // YiPay
            'YiPay valid amount' => [YiPayAdapter::class, ['amount' => '100'], 100, true],
            'YiPay invalid amount' => [YiPayAdapter::class, ['amount' => '200'], 100, false],
        ];
    }

    public static function paymentInfoNormalizationProvider(): array
    {
        return [
            // ECPay ATM - 已經是標準格式
            'ECPay ATM' => [
                ECPayAdapter::class,
                ['vAccount' => '12345678901234', 'BankCode' => '012', 'ExpireDate' => '2024/12/31'],
                ['vAccount', 'BankCode', 'ExpireDate'],
            ],

            // NewebPay ATM
            'NewebPay ATM' => [
                NewebPayAdapter::class,
                ['CodeNo' => '12345678901234', 'BankCode' => '012', 'PaymentType' => 'VACC', 'ExpireDate' => '2024-12-31'],
                ['vAccount', 'BankCode', 'ExpireDate'],
            ],

            // NewebPay CVS
            'NewebPay CVS' => [
                NewebPayAdapter::class,
                ['CodeNo' => 'ABC123', 'PaymentType' => 'CVS', 'ExpireDate' => '2024-12-31'],
                ['PaymentNo', 'ExpireDate'],
            ],

            // YiPay ATM
            'YiPay ATM' => [
                YiPayAdapter::class,
                ['account' => '12345678901234', 'bankCode' => '012', 'type' => 4, 'expirationDate' => '2024-12-31'],
                ['vAccount', 'BankCode', 'ExpireDate'],
            ],

            // YiPay CVS
            'YiPay CVS' => [
                YiPayAdapter::class,
                ['pinCode' => 'ABC123', 'type' => 3, 'expirationDate' => '2024-12-31'],
                ['PaymentNo', 'ExpireDate'],
            ],
        ];
    }
}
