<?php

namespace WooCommerceOmnipay\Exceptions;

use Exception;

class OrderNotFoundException extends Exception
{
    public function __construct($transactionId = null)
    {
        $message = $transactionId
            ? sprintf('Order not found for transaction ID: %s', $transactionId)
            : 'Order not found';

        parent::__construct($message);
    }
}
