<?php

namespace WooCommerceOmnipay\WordPress;

use Omnipay\Common\Http\Exception\NetworkException as OmnipayNetworkException;

/**
 * Network Exception
 *
 * WordPress HTTP 請求失敗時拋出
 */
class NetworkException extends OmnipayNetworkException {}
