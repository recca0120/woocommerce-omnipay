<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Concerns;

/**
 * Formats Callback Response
 *
 * 提供回調回應格式
 */
trait FormatsCallbackResponse
{
    public function getCallbackSuccessResponse(): string
    {
        return '1|OK';
    }

    public function getCallbackFailureResponse(string $message): string
    {
        return '0|'.$message;
    }
}
