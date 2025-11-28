# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-11-28

### Added
- Initial release of WooCommerce Omnipay Gateway plugin
- Support for ECPay, NewebPay, YiPay, BankTransfer, and Dummy gateways
- Shared settings page for common gateway parameters (MerchantID, HashKey, HashIV)
- Settings priority: Gateway settings > Shared settings > Omnipay defaults
- `override_settings` config option to show Omnipay fields in individual gateway settings
- Payment info display for ATM, CVS, and Barcode payments
- Barcode rendering using JsBarcode
- Bank transfer confirmation with last 5 digits validation
- HPOS (High-Performance Order Storage) compatibility
- PSR-3 compatible logging with sensitive data masking
- Comprehensive test suite with 88 tests

### Configuration Format
Gateway configuration uses array format:
```php
[
    'gateway' => 'ECPay',           // Omnipay gateway name
    'gateway_id' => 'ecpay',        // WooCommerce gateway ID (auto-prefixed with omnipay_)
    'title' => 'ECPay',             // Display title
    'description' => 'Pay with ECPay',
    'override_settings' => false,   // Show Omnipay fields in gateway settings
]
```

### Supported Payment Types
- **ECPay**: Credit Card, ATM, CVS (Convenience Store), Barcode
- **NewebPay**: Credit Card, ATM, CVS, Barcode
- **YiPay**: Credit Card, ATM, CVS
- **BankTransfer**: Manual bank transfer with virtual account
- **Dummy**: Test gateway for development

### Requirements
- PHP 7.4+
- WordPress 6.4+
- WooCommerce 8.0+
