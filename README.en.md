# WooCommerce Omnipay Gateway

English | [繁體中文](README.md)

A flexible WooCommerce payment gateway plugin that integrates multiple Taiwan-based payment processors through the [Omnipay](https://omnipay.thephpleague.com/) payment library abstraction layer.

![Tests](https://github.com/recca0120/woocommerce-omnipay/actions/workflows/tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/recca0120/woocommerce-omnipay/branch/main/graph/badge.svg)](https://codecov.io/gh/recca0120/woocommerce-omnipay)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

- **Multiple Payment Gateways**: Support for ECPay, NewebPay, YiPay, and Bank Transfer
- **Omnipay Integration**: Built on the robust Omnipay payment abstraction library
- **HPOS Compatible**: Full support for WooCommerce High-Performance Order Storage
- **Offline Payment Support**: ATM transfers, CVS codes, and barcode payments
- **Automatic Redirect Handling**: Supports both GET and POST redirect methods
- **Payment Info Display**: Shows payment information on thank you page, order history, and emails
- **Barcode Rendering**: Automatic barcode generation using JsBarcode
- **Extensible Architecture**: Easy to add new payment gateways
- **Comprehensive Logging**: PSR-3 compatible logging with sensitive data masking

## Supported Payment Gateways

| Gateway | Type | Region | Features |
|---------|------|--------|----------|
| **ECPay (綠界)** | Redirect | Taiwan | Credit Card, ATM, CVS, Barcode |
| **NewebPay (藍新)** | Redirect | Taiwan | Credit Card, ATM, CVS, Barcode |
| **YiPay (乙禾)** | Redirect | Taiwan | Credit Card, ATM, CVS |
| **BankTransfer** | Offline | Taiwan | Manual bank transfers |
| **Dummy** | Direct | Testing | Credit card form for development |

## Requirements

- PHP 7.4 or higher
- WordPress 6.4 or higher
- WooCommerce 8.0 or higher

## Installation

### Download from Release (Recommended)

1. Go to the [Releases](https://github.com/recca0120/woocommerce-omnipay/releases) page
2. Download the latest `woocommerce-omnipay.zip`
3. In WordPress admin → Plugins → Add New → Upload Plugin
4. Upload the zip file and activate

### Via Composer

```bash
composer require recca0120/woocommerce-omnipay
```

### Manual Installation (Developers)

1. Clone or download the source code
2. Upload to `/wp-content/plugins/woocommerce-omnipay/`
3. Run `composer install --no-dev` in the plugin directory
4. Activate the plugin through WordPress admin

## Configuration

### Gateway Configuration

Each gateway can be configured in WooCommerce → Settings → Payments:

1. Enable/Disable the gateway
2. Set display title and description
3. Configure gateway-specific credentials (MerchantID, HashKey, HashIV, etc.)
4. Optional: Set transaction ID prefix for multi-site scenarios
5. Optional: Enable resubmit to allow customers to retry failed payments

### Plugin Configuration

Gateway configuration can be customized via the `woocommerce_omnipay_gateway_config` filter:

```php
add_filter('woocommerce_omnipay_gateway_config', function($config) {
    $config['gateways']['ECPay']['enabled'] = true;
    $config['gateways']['ECPay']['title'] = '綠界金流';
    return $config;
});
```

## Payment Flow

### Direct Gateways (Dummy)

1. Customer enters card details on checkout
2. Payment processed immediately
3. Order marked as complete or failed

### Redirect Gateways (ECPay, NewebPay, YiPay)

1. Customer selects payment method at checkout
2. Redirected to payment provider's page
3. Payment provider sends callback notification
4. Order status updated automatically

### Offline Payment (Bank Transfer)

1. Customer selects bank transfer at checkout
2. Virtual account number displayed
3. Customer makes bank transfer
4. Payment provider sends notification when payment received

## Development

### Environment Setup

After cloning the project, run the following commands to set up the test environment:

```bash
# Install dependencies
composer install

# Setup WordPress test environment (auto-downloads WordPress + WooCommerce)
./bin/install-wp-tests.sh
```

This creates a `.wordpress-test/` directory containing:
- WordPress core
- WooCommerce plugin
- SQLite Database Integration plugin

### Running Tests

```bash
# Run tests
composer test

# Run tests with coverage
./vendor/bin/phpunit --coverage-text
```

### Project Structure

```
woocommerce-omnipay/
├── includes/
│   ├── Gateways/
│   │   ├── OmnipayGateway.php      # Base gateway class
│   │   ├── ECPayGateway.php        # ECPay implementation
│   │   ├── NewebPayGateway.php     # NewebPay implementation
│   │   ├── YiPayGateway.php        # YiPay implementation
│   │   ├── BankTransferGateway.php # Bank transfer implementation
│   │   └── DummyGateway.php        # Testing gateway
│   ├── Services/
│   │   ├── OmnipayBridge.php       # Omnipay adapter
│   │   └── WooCommerceLogger.php   # PSR-3 logger
│   ├── Repositories/
│   │   └── OrderRepository.php     # Order data persistence
│   └── GatewayRegistry.php         # Gateway registration
├── templates/
│   ├── checkout/
│   │   └── credit-card-form.php    # Credit card form template
│   └── order/
│       ├── payment-info.php        # Payment info display
│       └── payment-info-plain.php  # Plain text for emails
├── assets/
│   ├── css/
│   │   └── payment-info.css        # Payment info styles
│   └── js/
│       └── barcode.js              # Barcode rendering
└── tests/                          # PHPUnit tests
```

### Adding a New Gateway

1. Create a new gateway class extending `OmnipayGateway`:

```php
namespace WooCommerceOmnipay\Gateways;

class MyGateway extends OmnipayGateway
{
    protected function get_callback_parameters()
    {
        return [
            'returnUrl' => WC()->api_request_url($this->id . '_complete'),
            'notifyUrl' => WC()->api_request_url($this->id . '_notify'),
        ];
    }
}
```

2. Register the gateway in the config:

```php
add_filter('woocommerce_omnipay_gateway_config', function($config) {
    $config['gateways']['MyGateway'] = [
        'enabled' => true,
        'title' => 'My Gateway',
        'description' => 'Pay with My Gateway',
    ];
    return $config;
});
```

3. Install the corresponding Omnipay driver:

```bash
composer require omnipay/my-gateway
```

## Hooks & Filters

### Filters

- `woocommerce_omnipay_gateway_config` - Customize gateway configuration
- `woocommerce_omnipay_should_exit` - Control exit behavior (useful for testing)

### Actions

- `woocommerce_api_{gateway_id}_complete` - Handle return from payment provider
- `woocommerce_api_{gateway_id}_notify` - Handle server-to-server notification
- `woocommerce_api_{gateway_id}_payment_info` - Handle payment info notification

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

- [Omnipay](https://omnipay.thephpleague.com/) - Payment processing library
- [WooCommerce](https://woocommerce.com/) - E-commerce platform
- [JsBarcode](https://github.com/lindell/JsBarcode) - Barcode generation

## Support

For bugs and feature requests, please use the [GitHub Issues](https://github.com/recca0120/woocommerce-omnipay/issues).
