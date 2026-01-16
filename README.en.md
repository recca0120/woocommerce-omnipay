# WooCommerce Omnipay Gateway

English | [繁體中文](README.md)

A flexible WooCommerce payment gateway plugin that integrates multiple Taiwan-based payment processors through the [Omnipay](https://omnipay.thephpleague.com/) payment library abstraction layer.

![Tests](https://github.com/omnipay-taiwan/woocommerce-omnipay/actions/workflows/tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/omnipay-taiwan/woocommerce-omnipay/branch/main/graph/badge.svg)](https://codecov.io/gh/omnipay-taiwan/woocommerce-omnipay)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

- **Multiple Payment Gateways**: Support for ECPay, NewebPay, YiPay, and Bank Transfer
- **Omnipay Integration**: Built on the robust Omnipay payment abstraction library, using WordPress native HTTP API
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

- PHP 7.2 or higher
- WordPress 6.4 or higher
- WooCommerce 8.0 or higher

## Installation

### Download from Release (Recommended)

1. Go to the [Releases](https://github.com/omnipay-taiwan/woocommerce-omnipay/releases) page
2. Download the latest `woocommerce-omnipay.zip`
3. In WordPress admin → Plugins → Add New → Upload Plugin
4. Upload the zip file and activate

### Via Composer

```bash
composer require omnipay-taiwan/woocommerce-omnipay
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

### Shared Settings

Shared parameters (MerchantID, HashKey, HashIV, etc.) for each payment provider can be configured in WooCommerce → Settings → Omnipay. These settings are automatically applied to all payment methods using the same provider.

**Settings Priority**: Individual gateway settings > Shared settings > Omnipay defaults

### Plugin Configuration

Gateway configuration can be customized via the `woocommerce_omnipay_gateway_config` filter:

```php
add_filter('woocommerce_omnipay_gateway_config', function($config) {
    // Add a gateway
    $config['gateways'][] = [
        'gateway' => 'ECPay',           // Omnipay gateway name
        'gateway_id' => 'ecpay_credit', // WooCommerce gateway ID
        'title' => 'ECPay Credit Card',
        'description' => 'Pay with credit card',
        'override_settings' => true,    // Show Omnipay parameter fields (default false)
    ];
    return $config;
});
```

**Configuration Fields**:

| Field | Required | Description |
|-------|----------|-------------|
| `gateway` | Yes | Omnipay gateway name (e.g., ECPay, NewebPay) |
| `gateway_id` | Yes | WooCommerce payment method ID (auto-prefixed with `omnipay_`) |
| `title` | No | Display name (defaults to gateway name) |
| `description` | No | Payment method description |
| `override_settings` | No | Whether to show Omnipay parameter fields in individual gateway settings (default false, uses shared settings) |

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

### Architecture Overview

This plugin uses the **Feature Composition Pattern**, combining functionality through configuration rather than inheritance:

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ config/gateways │────▶│  GatewayRegistry │────▶│  OmnipayGateway │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                                            ┌─────────────▼─────────────┐
                                            │     Feature[] Composition │
                                            │  ┌─────────────────────┐  │
                                            │  │ • MinAmountFeature  │  │
                                            │  │ • MaxAmountFeature  │  │
                                            │  │ • InstallmentFeature│  │
                                            │  │ • ExpireDateFeature │  │
                                            │  │ • RecurringFeature  │  │
                                            │  └─────────────────────┘  │
                                            └───────────────────────────┘
```

#### Gateway Configuration Example

```php
// Configuration in woocommerce_omnipay_get_gateways() function
[
    'gateway' => 'ECPay',
    'gateway_id' => 'ecpay_atm',
    'title' => __('ECPay ATM', 'woocommerce-omnipay'),
    'payment_data' => ['ChoosePayment' => 'ATM'],
    'features' => [
        new MinAmountFeature,
        new MaxAmountFeature,
        new ExpireDateFeature('ExpireDate', 3, 1, 60),
    ],
],
```

#### Available Features

| Feature | Description | Parameters |
|---------|-------------|------------|
| `PaymentDataFeature` | Static payment data | Merges payment_data config into request data |
| `MinAmountFeature` | Minimum amount limit | Default $0 |
| `MaxAmountFeature` | Maximum amount limit | Default $30,000 |
| `InstallmentFeature` | Credit card installments | `fieldName` (required), `config` (options, defaults, periodRules) |
| `ExpireDateFeature` | Payment expiry settings | `fieldName`, `defaultDays`, `minDays`, `maxDays` |
| `FrequencyRecurringFeature` | Recurring payments (frequency-based) | For ECPay DCA |
| `ScheduledRecurringFeature` | Recurring payments (schedule-based) | For NewebPay DCA |

### Project Structure

```
woocommerce-omnipay/
├── src/
│   ├── Adapters/                     # Gateway Adapter layer
│   │   ├── Contracts/
│   │   │   └── GatewayAdapter.php    # Adapter interface
│   │   ├── Concerns/                 # Adapter Traits
│   │   │   ├── CreatesGateway.php    # Gateway instantiation
│   │   │   ├── FormatsCallbackResponse.php
│   │   │   ├── HandlesNotifications.php
│   │   │   ├── HandlesPurchases.php
│   │   │   └── HasPaymentInfo.php
│   │   ├── BankTransferAdapter.php   # Bank Transfer Adapter
│   │   ├── DefaultGatewayAdapter.php # Default Adapter
│   │   ├── ECPayAdapter.php          # ECPay Adapter
│   │   ├── NewebPayAdapter.php       # NewebPay Adapter
│   │   └── YiPayAdapter.php          # YiPay Adapter
│   ├── Gateways/
│   │   ├── Concerns/
│   │   │   └── DisplaysPaymentInfo.php  # Payment info display
│   │   ├── Features/                 # Feature components
│   │   │   ├── GatewayFeature.php    # Feature interface
│   │   │   ├── AbstractFeature.php   # Abstract base class
│   │   │   ├── AbstractRecurringFeature.php  # Recurring base class
│   │   │   ├── FeatureFactory.php    # Feature factory
│   │   │   ├── MinAmountFeature.php
│   │   │   ├── MaxAmountFeature.php
│   │   │   ├── PaymentDataFeature.php
│   │   │   ├── InstallmentFeature.php
│   │   │   ├── ExpireDateFeature.php
│   │   │   ├── RecurringFeature.php  # Recurring interface
│   │   │   ├── FrequencyRecurringFeature.php
│   │   │   └── ScheduledRecurringFeature.php
│   │   ├── BankTransferGateway.php   # Bank Transfer
│   │   ├── DummyGateway.php          # Testing
│   │   ├── ECPayGateway.php          # ECPay
│   │   ├── NewebPayGateway.php       # NewebPay
│   │   ├── YiPayGateway.php          # YiPay
│   │   ├── OmnipayGateway.php        # Universal gateway class
│   │   └── PaymentContext.php        # Payment context
│   ├── Exceptions/
│   │   ├── NetworkException.php      # Network exception
│   │   └── OrderNotFoundException.php
│   ├── Http/
│   │   ├── WordPressClient.php       # WordPress HTTP Client
│   │   ├── CurlClient.php            # cURL HTTP Client
│   │   └── StreamClient.php          # Stream HTTP Client
│   ├── Settings/
│   │   ├── Contracts/
│   │   │   └── SettingsSectionProvider.php  # Settings section interface
│   │   ├── BankTransferSettingsSection.php  # Bank Transfer settings
│   │   ├── GatewaySettingsSection.php       # Gateway shared settings
│   │   └── GeneralSettingsSection.php       # General settings
│   ├── WordPress/
│   │   ├── Logger.php                # PSR-3 logger
│   │   └── SettingsManager.php       # Settings manager
│   ├── Repositories/
│   │   └── OrderRepository.php       # Order data persistence
│   ├── Constants.php                 # Constants
│   ├── GatewayRegistry.php           # Gateway registration
│   ├── Helper.php                    # Helper functions
│   └── SharedSettingsPage.php        # Shared settings page
├── templates/
│   ├── admin/
│   │   ├── bank-accounts-table.php   # Bank accounts management
│   │   ├── dca-periods-table.php     # DCA periods table (base)
│   │   ├── frequency-recurring-periods-table.php  # Frequency recurring
│   │   ├── scheduled-recurring-periods-table.php  # Scheduled recurring
│   │   └── settings-sections.php     # Settings page sections
│   ├── checkout/
│   │   ├── bank-account-form.php     # Bank account selection
│   │   ├── credit-card-form.php      # Credit card form
│   │   ├── dca-form.php              # DCA form (base)
│   │   ├── frequency-recurring-form.php   # Frequency recurring
│   │   ├── installment-form.php      # Installment selection
│   │   ├── redirect-form.php         # Redirect form
│   │   └── scheduled-recurring-form.php   # Scheduled recurring
│   └── order/
│       ├── payment-info.php          # Payment info display
│       ├── payment-info-plain.php    # Plain text for emails
│       ├── payment-info-cartflows.php     # CartFlows compatible
│       ├── remittance-form.php       # Remittance info form
│       └── remittance-form-cartflows.php  # CartFlows remittance
├── assets/
│   ├── images/
│   │   └── payment-icons/            # Payment icons
│   └── js/
│       ├── admin.js                  # Admin scripts
│       ├── barcode.js                # Barcode rendering
│       ├── checkout.js               # Checkout scripts
│       └── vendor/
│           └── jsbarcode.min.js      # JsBarcode library
└── tests/                            # PHPUnit tests
```

### Creating a Custom Feature

Implement the `GatewayFeature` interface or extend `AbstractFeature`:

```php
namespace WooCommerceOmnipay\Gateways\Features;

class MyCustomFeature extends AbstractFeature
{
    public function initFormFields(array &$formFields): void
    {
        $formFields['my_option'] = [
            'title' => __('My Option', 'woocommerce-omnipay'),
            'type' => 'text',
            'default' => '',
        ];
    }

    public function isAvailable(\WC_Payment_Gateway $gateway): bool
    {
        // Custom availability check logic
        return true;
    }

    public function preparePaymentData(array $data, \WC_Order $order, \WC_Payment_Gateway $gateway): array
    {
        $data['myParam'] = $gateway->get_option('my_option');
        return $data;
    }
}
```

### Adding a New Gateway

1. Create an Adapter (encapsulates gateway logic):

```php
namespace WooCommerceOmnipay\Adapters;

class MyGatewayAdapter extends DefaultGatewayAdapter
{
    public function getGatewayName(): string
    {
        return 'MyGateway';
    }

    public function normalizePaymentInfo(array $data): array
    {
        return [
            'vAccount' => $data['account'] ?? '',
            'ExpireDate' => $data['expire'] ?? '',
        ];
    }
}
```

2. Create a Gateway (if special handling needed):

```php
namespace WooCommerceOmnipay\Gateways;

class MyGateway extends OmnipayGateway
{
    protected function getCallbackParameters()
    {
        return [
            'returnUrl' => WC()->api_request_url($this->id . '_complete'),
            'notifyUrl' => WC()->api_request_url($this->id . '_notify'),
        ];
    }
}
```

3. Register the gateway in the config:

```php
add_filter('woocommerce_omnipay_gateway_config', function($config) {
    $config['gateways'][] = [
        'gateway' => 'MyGateway',
        'gateway_id' => 'mygateway',
        'title' => 'My Gateway',
        'description' => 'Pay with My Gateway',
    ];
    return $config;
});
```

4. Install the corresponding Omnipay driver:

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

For bugs and feature requests, please use the [GitHub Issues](https://github.com/omnipay-taiwan/woocommerce-omnipay/issues).
