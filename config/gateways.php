<?php

use WooCommerceOmnipay\Gateways\Features\ExpireDateFeature;
use WooCommerceOmnipay\Gateways\Features\FrequencyRecurringFeature;
use WooCommerceOmnipay\Gateways\Features\InstallmentFeature;
use WooCommerceOmnipay\Gateways\Features\MaxAmountFeature;
use WooCommerceOmnipay\Gateways\Features\MinAmountFeature;
use WooCommerceOmnipay\Gateways\Features\ScheduledRecurringFeature;

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
    ],
    // Dummy (for testing)
    [
        'gateway' => 'Dummy',
        'gateway_id' => 'dummy',
        'title' => __('Dummy Gateway', 'woocommerce-omnipay'),
    ],
    // ECPay (All-in-one)
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay',
        'title' => __('ECPay', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
    ],
    // ECPay Sub-Gateways
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit',
        'title' => __('ECPay Credit Card', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'Credit'],
        'features' => [new MinAmountFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit_installment',
        'title' => __('ECPay Credit Card Installment', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'Credit'],
        'features' => [
            new MinAmountFeature,
            new InstallmentFeature('CreditInstallment', ['periodRules' => ['30' => ['min_amount' => 20000]]]),
        ],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_dca',
        'title' => __('ECPay Recurring Payment', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'Credit'],
        'features' => [new FrequencyRecurringFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_bnpl',
        'title' => __('ECPay BNPL', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'BNPL'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_webatm',
        'title' => __('ECPay WebATM', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'WebATM'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_atm',
        'title' => __('ECPay ATM', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'ATM'],
        'features' => [new MinAmountFeature, new MaxAmountFeature, new ExpireDateFeature('ExpireDate', 3, 1, 60)],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_cvs',
        'title' => __('ECPay CVS', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'CVS'],
        'features' => [new MinAmountFeature, new MaxAmountFeature, new ExpireDateFeature('StoreExpireDate', 10080, 1, 43200)],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_barcode',
        'title' => __('ECPay Barcode', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'BARCODE'],
        'features' => [new MinAmountFeature, new MaxAmountFeature, new ExpireDateFeature('StoreExpireDate', 7, 1, 30)],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_applepay',
        'title' => __('ECPay Apple Pay', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'ApplePay'],
        'features' => [new MinAmountFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_twqr',
        'title' => __('ECPay Taiwan Pay', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'TWQR'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_weixin',
        'title' => __('ECPay WeChat Pay', 'woocommerce-omnipay'),
        'icon' => $ecpayIcon,
        'payment_data' => ['ChoosePayment' => 'WeiXin'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    // NewebPay (All-in-one)
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay',
        'title' => __('NewebPay', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
    ],
    // NewebPay Sub-Gateways
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit',
        'title' => __('NewebPay Credit Card', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['CREDIT' => 1],
        'features' => [new MinAmountFeature],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit_installment',
        'title' => __('NewebPay Credit Card Installment', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['CREDIT' => 1],
        'features' => [
            new MinAmountFeature,
            new InstallmentFeature('InstFlag'),
        ],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_dca',
        'title' => __('NewebPay Recurring Payment', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['CREDIT' => 1],
        'features' => [new ScheduledRecurringFeature],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_webatm',
        'title' => __('NewebPay WebATM', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['WEBATM' => 1],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_atm',
        'title' => __('NewebPay ATM', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['VACC' => 1],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_cvs',
        'title' => __('NewebPay CVS', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['CVS' => 1],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_barcode',
        'title' => __('NewebPay Barcode', 'woocommerce-omnipay'),
        'icon' => $newebpayIcon,
        'payment_data' => ['BARCODE' => 1],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    // YiPay (All-in-one)
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay',
        'title' => __('YiPay', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
    ],
    // YiPay Sub-Gateways
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_credit',
        'title' => __('YiPay Credit Card', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
        'payment_data' => ['type' => '2'],
        'features' => [new MinAmountFeature],
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_atm',
        'title' => __('YiPay ATM', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
        'payment_data' => ['type' => '4'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_cvs',
        'title' => __('YiPay CVS', 'woocommerce-omnipay'),
        'icon' => $yipayIcon,
        'payment_data' => ['type' => '3'],
        'features' => [new MinAmountFeature, new MaxAmountFeature],
    ],
];
