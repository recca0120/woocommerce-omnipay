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

return [
    // 銀行轉帳
    [
        'gateway' => 'BankTransfer',
        'gateway_id' => 'banktransfer',
        'title' => '銀行轉帳',
        'description' => '使用銀行轉帳付款',
    ],
    // Dummy（測試用）
    [
        'gateway' => 'Dummy',
        'gateway_id' => 'dummy',
        'title' => 'Dummy Gateway',
        'description' => 'Dummy payment gateway for testing',
    ],
    // ECPay（全功能）
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay',
        'title' => '綠界金流',
        'description' => '使用綠界金流付款',
    ],
    // ECPay 子 Gateway
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit',
        'class' => ECPayCreditGateway::class,
        'title' => '綠界信用卡',
        'description' => '使用信用卡付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_credit_installment',
        'class' => ECPayCreditInstallmentGateway::class,
        'title' => '綠界信用卡分期',
        'description' => '使用信用卡分期付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_dca',
        'class' => ECPayDCAGateway::class,
        'title' => '綠界定期定額',
        'description' => '使用信用卡定期定額付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_webatm',
        'class' => ECPayWebATMGateway::class,
        'title' => '綠界網路 ATM',
        'description' => '使用網路 ATM 付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_atm',
        'class' => ECPayATMGateway::class,
        'title' => '綠界 ATM',
        'description' => '使用 ATM 虛擬帳號付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_cvs',
        'class' => ECPayCVSGateway::class,
        'title' => '綠界超商代碼',
        'description' => '使用超商代碼付款',
    ],
    [
        'gateway' => 'ECPay',
        'gateway_id' => 'ecpay_barcode',
        'class' => ECPayBarcodeGateway::class,
        'title' => '綠界超商條碼',
        'description' => '使用超商條碼付款',
    ],
    // NewebPay（全功能）
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay',
        'title' => '藍新金流',
        'description' => '使用藍新金流付款',
    ],
    // NewebPay 子 Gateway
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit',
        'class' => NewebPayCreditGateway::class,
        'title' => '藍新信用卡',
        'description' => '使用信用卡付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_credit_installment',
        'class' => NewebPayCreditInstallmentGateway::class,
        'title' => '藍新信用卡分期',
        'description' => '使用信用卡分期付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_dca',
        'class' => NewebPayDCAGateway::class,
        'title' => '藍新定期定額',
        'description' => '使用信用卡定期定額付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_webatm',
        'class' => NewebPayWebATMGateway::class,
        'title' => '藍新網路 ATM',
        'description' => '使用網路 ATM 付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_atm',
        'class' => NewebPayATMGateway::class,
        'title' => '藍新 ATM',
        'description' => '使用 ATM 虛擬帳號付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_cvs',
        'class' => NewebPayCVSGateway::class,
        'title' => '藍新超商代碼',
        'description' => '使用超商代碼付款',
    ],
    [
        'gateway' => 'NewebPay',
        'gateway_id' => 'newebpay_barcode',
        'class' => NewebPayBarcodeGateway::class,
        'title' => '藍新超商條碼',
        'description' => '使用超商條碼付款',
    ],
    // YiPay（全功能）
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay',
        'title' => 'YiPay 乙禾金流',
        'description' => '使用 YiPay 乙禾金流付款',
    ],
    // YiPay 子 Gateway
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_credit',
        'class' => YiPayCreditGateway::class,
        'title' => '乙禾信用卡',
        'description' => '使用信用卡付款',
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_atm',
        'class' => YiPayATMGateway::class,
        'title' => '乙禾 ATM',
        'description' => '使用 ATM 虛擬帳號付款',
    ],
    [
        'gateway' => 'YiPay',
        'gateway_id' => 'yipay_cvs',
        'class' => YiPayCVSGateway::class,
        'title' => '乙禾超商代碼',
        'description' => '使用超商代碼付款',
    ],
];
