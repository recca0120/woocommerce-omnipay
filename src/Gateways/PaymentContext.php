<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways;

/**
 * Payment Context
 *
 * 封裝付款處理的上下文資訊
 */
class PaymentContext
{
    /**
     * @var string 來源識別
     */
    private $source;

    /**
     * @var bool 是否顯示錯誤訊息給使用者
     */
    private $addNotice;

    public function __construct(string $source, bool $addNotice = true)
    {
        $this->source = $source;
        $this->addNotice = $addNotice;
    }

    /**
     * 從 process_payment 建立上下文
     */
    public static function fromProcessPayment(): self
    {
        return new self('process_payment', true);
    }

    /**
     * 從 callback 建立上下文（背景通知，不顯示訊息）
     */
    public static function fromCallback(): self
    {
        return new self('callback', false);
    }

    /**
     * 從 return URL 建立上下文（使用者返回，顯示訊息）
     */
    public static function fromReturnUrl(): self
    {
        return new self('return URL', true);
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function shouldAddNotice(): bool
    {
        return $this->addNotice;
    }
}
