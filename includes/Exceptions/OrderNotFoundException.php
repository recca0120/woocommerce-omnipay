<?php

namespace WooCommerceOmnipay\Exceptions;

use Exception;

class OrderNotFoundException extends Exception
{
    public function __construct($transaction_id = null)
    {
        $message = $transaction_id
            ? sprintf('Order not found for transaction ID: %s', $transaction_id)
            : 'Order not found';

        parent::__construct($message);
    }
}
