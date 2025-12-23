<?php

namespace WooCommerceOmnipay\Adapters;

use Omnipay\Common\GatewayInterface;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Gateway Adapter Interface
 *
 * 封裝特定金流的邏輯，包含：
 * - Gateway 建立與配置
 * - 付款操作（purchase, completePurchase, acceptNotification, getPaymentInfo）
 * - 金額驗證
 * - 付款資訊欄位正規化
 * - Callback URL 處理
 */
interface GatewayAdapterInterface
{
    /**
     * 取得 Omnipay Gateway 名稱
     */
    public function getGatewayName(): string;

    /**
     * 建立 Omnipay Gateway
     */
    public function createGateway(array $settings): GatewayInterface;

    /**
     * 執行付款
     */
    public function purchase(array $data): ResponseInterface;

    /**
     * 完成付款（處理用戶返回）
     */
    public function completePurchase(array $parameters = []): ResponseInterface;

    /**
     * 是否支援接收通知
     */
    public function supportsAcceptNotification(): bool;

    /**
     * 接收金流通知
     */
    public function acceptNotification(array $parameters = []): NotificationInterface;

    /**
     * 是否支援取得付款資訊
     */
    public function supportsGetPaymentInfo(): bool;

    /**
     * 取得付款資訊
     */
    public function getPaymentInfo(array $parameters = []): ResponseInterface;

    /**
     * 驗證金額
     *
     * @param  array  $data  回調資料
     * @param  int  $orderTotal  訂單總金額
     */
    public function validateAmount(array $data, int $orderTotal): bool;

    /**
     * 正規化付款資訊欄位
     *
     * 將各金流的欄位名稱轉換為統一格式：
     * - BankCode: 銀行代碼
     * - vAccount: 虛擬帳號
     * - PaymentNo: 繳費代碼
     * - ExpireDate: 繳費期限
     * - Barcode1, Barcode2, Barcode3: 條碼
     */
    public function normalizePaymentInfo(array $data): array;

    /**
     * 取得付款資訊 URL endpoint
     *
     * 預設為 _payment_info，ECPay 為 _notify
     */
    public function getPaymentInfoEndpoint(): string;

    /**
     * 取得回調成功回應
     *
     * 不同金流可能有不同的回應格式
     */
    public function getCallbackSuccessResponse(): string;

    /**
     * 取得回調失敗回應
     *
     * @param  string  $message  錯誤訊息
     */
    public function getCallbackFailureResponse(string $message): string;

    /**
     * 是否為付款資訊通知（非付款完成通知）
     *
     * 某些金流（如 ECPay）的付款資訊通知與付款完成通知共用同一個 endpoint
     *
     * @param  array  $data  通知資料
     */
    public function isPaymentInfoNotification(array $data): bool;

    /**
     * 取得付款資訊通知的訂單備註
     *
     * @param  array  $data  通知資料
     * @return string|null 備註訊息，null 表示不加備註
     */
    public function getPaymentInfoNote(array $data): ?string;

    /**
     * 初始化設定
     */
    public function initialize(array $settings);
}
