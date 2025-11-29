<?php

use WooCommerceOmnipay\Gateways\ECPay\ECPayATMGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayBarcodeGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayCreditGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayCreditInstallmentGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayCVSGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayDCAGateway;
use WooCommerceOmnipay\Gateways\ECPay\ECPayWebATMGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayATMGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayBarcodeGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayCreditGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayCreditInstallmentGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayCVSGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayDCAGateway;
use WooCommerceOmnipay\Gateways\NewebPay\NewebPayWebATMGateway;
use WooCommerceOmnipay\Gateways\YiPay\YiPayATMGateway;
use WooCommerceOmnipay\Gateways\YiPay\YiPayCreditGateway;
use WooCommerceOmnipay\Gateways\YiPay\YiPayCVSGateway;

// Icon URLs
$ecpayIcon = plugins_url('assets/images/payment-icons/ecpay.png', dirname(__DIR__).'/woocommerce-omnipay.php');
$newebpayIcon = plugins_url('assets/images/payment-icons/newebpay.png', dirname(__DIR__).'/woocommerce-omnipay.php');
$yipayIcon = plugins_url('assets/images/payment-icons/yipay.png', dirname(__DIR__).'/woocommerce-omnipay.php');

return [
    // Bank Transfer
    [
        'gateway' => 'BankTransfer',
        'gateway_id' => 'banktransfer',
        'title' => __('Bank Transfer', 'woocommerce-omnipay'),
        'description' => __('Pay via bank transfer', 'woocommerce-omnipay'),
    ],
    // Dummy (for testing)
    [
        'gateway' => 'Dummy',
        'gateway_id' => 'dummy',
        'title' => __('Dummy Gateway', 'woocommerce-omnipay'),
        'description' => __('Dummy payment gateway for testing', 'woocommerce-omnipay'),
    ],
    // ECPay (All-in-one)
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay',
        'title' => __('ECPay', 'woocommerce-omnipay'),
        'description' => __('Pay via ECPay payment gateway', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    // ECPay Sub-Gateways
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit',
        'class' => ECPayCreditGateway::class,
        'title' => __('ECPay Credit Card', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit_installment',
        'class' => ECPayCreditInstallmentGateway::class,
        'title' => __('ECPay Credit Card Installment', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card installment', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_dca',
        'class' => ECPayDCAGateway::class,
        'title' => __('ECPay Recurring Payment', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card recurring payment', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_webatm',
        'class' => ECPayWebATMGateway::class,
        'title' => __('ECPay WebATM', 'woocommerce-omnipay'),
        'description' => __('Pay via WebATM', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_atm',
        'class' => ECPayATMGateway::class,
        'title' => __('ECPay ATM', 'woocommerce-omnipay'),
        'description' => __('Pay via ATM virtual account', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_cvs',
        'class' => ECPayCVSGateway::class,
        'title' => __('ECPay CVS', 'woocommerce-omnipay'),
        'description' => __('Pay at convenience store', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_barcode',
        'class' => ECPayBarcodeGateway::class,
        'title' => __('ECPay Barcode', 'woocommerce-omnipay'),
        'description' => __('Pay with barcode at convenience store', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    // NewebPay (All-in-one)
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay',
        'title' => __('NewebPay', 'woocommerce-omnipay'),
        'description' => __('Pay via NewebPay payment gateway', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    // NewebPay Sub-Gateways
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit',
        'class' => NewebPayCreditGateway::class,
        'title' => __('NewebPay Credit Card', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit_installment',
        'class' => NewebPayCreditInstallmentGateway::class,
        'title' => __('NewebPay Credit Card Installment', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card installment', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_dca',
        'class' => NewebPayDCAGateway::class,
        'title' => __('NewebPay Recurring Payment', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card recurring payment', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_webatm',
        'class' => NewebPayWebATMGateway::class,
        'title' => __('NewebPay WebATM', 'woocommerce-omnipay'),
        'description' => __('Pay via WebATM', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_atm',
        'class' => NewebPayATMGateway::class,
        'title' => __('NewebPay ATM', 'woocommerce-omnipay'),
        'description' => __('Pay via ATM virtual account', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_cvs',
        'class' => NewebPayCVSGateway::class,
        'title' => __('NewebPay CVS', 'woocommerce-omnipay'),
        'description' => __('Pay at convenience store', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_barcode',
        'class' => NewebPayBarcodeGateway::class,
        'title' => __('NewebPay Barcode', 'woocommerce-omnipay'),
        'description' => __('Pay with barcode at convenience store', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    // YiPay (All-in-one)
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay',
        'title' => __('YiPay', 'woocommerce-omnipay'),
        'description' => __('Pay via YiPay payment gateway', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
    ],
    // YiPay Sub-Gateways
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_credit',
        'class' => YiPayCreditGateway::class,
        'title' => __('YiPay Credit Card', 'woocommerce-omnipay'),
        'description' => __('Pay with credit card', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_atm',
        'class' => YiPayATMGateway::class,
        'title' => __('YiPay ATM', 'woocommerce-omnipay'),
        'description' => __('Pay via ATM virtual account', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_cvs',
        'class' => YiPayCVSGateway::class,
        'title' => __('YiPay CVS', 'woocommerce-omnipay'),
        'description' => __('Pay at convenience store', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
    ],
];
