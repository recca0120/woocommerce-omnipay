# WooCommerce Omnipay Gateway

[English](README.en.md) | 繁體中文

一個彈性的 WooCommerce 付款閘道外掛，透過 [Omnipay](https://omnipay.thephpleague.com/) 付款抽象層整合多個台灣金流服務。

![Tests](https://github.com/recca0120/woocommerce-omnipay/actions/workflows/tests.yml/badge.svg)
[![codecov](https://codecov.io/gh/recca0120/woocommerce-omnipay/branch/main/graph/badge.svg)](https://codecov.io/gh/recca0120/woocommerce-omnipay)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## 功能特色

- **多金流支援**：支援綠界、藍新、乙禾及銀行轉帳
- **Omnipay 整合**：基於穩定的 Omnipay 付款抽象層
- **HPOS 相容**：完整支援 WooCommerce 高效能訂單儲存
- **離線付款支援**：ATM 轉帳、超商代碼、條碼繳費
- **自動導向處理**：支援 GET 和 POST 導向方式
- **付款資訊顯示**：在感謝頁、訂單記錄及 Email 中顯示付款資訊
- **條碼渲染**：使用 JsBarcode 自動產生條碼
- **可擴充架構**：輕鬆新增其他金流
- **完整日誌記錄**：PSR-3 相容的日誌功能，自動遮蔽敏感資料

## 支援的金流

| 金流 | 類型 | 地區 | 功能 |
|------|------|------|------|
| **ECPay (綠界)** | 導向式 | 台灣 | 信用卡、ATM、超商代碼、條碼 |
| **NewebPay (藍新)** | 導向式 | 台灣 | 信用卡、ATM、超商代碼、條碼 |
| **YiPay (乙禾)** | 導向式 | 台灣 | 信用卡、ATM、超商代碼 |
| **BankTransfer (銀行轉帳)** | 離線 | 台灣 | 手動銀行轉帳 |
| **Dummy** | 直接式 | 測試 | 開發用信用卡表單 |

## 系統需求

- PHP 7.4 或更高版本
- WordPress 6.4 或更高版本
- WooCommerce 8.0 或更高版本

## 安裝

### 從 Release 下載（建議）

1. 前往 [Releases](https://github.com/recca0120/woocommerce-omnipay/releases) 頁面
2. 下載最新版本的 `woocommerce-omnipay.zip`
3. 在 WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛
4. 上傳 zip 檔案並啟用

### 透過 Composer

```bash
composer require recca0120/woocommerce-omnipay
```

### 手動安裝（開發者）

1. Clone 或下載 source code
2. 上傳至 `/wp-content/plugins/woocommerce-omnipay/`
3. 在外掛目錄執行 `composer install --no-dev`
4. 透過 WordPress 後台啟用外掛

## 設定

### 金流設定

每個金流可在 WooCommerce → 設定 → 付款 中設定：

1. 啟用/停用金流
2. 設定顯示標題和說明
3. 設定金流專用憑證（MerchantID、HashKey、HashIV 等）
4. 可選：設定交易編號前綴（多站情境使用）
5. 可選：啟用重新提交允許客戶重試失敗的付款

### 外掛設定

可透過 `woocommerce_omnipay_gateway_config` filter 自訂金流設定：

```php
add_filter('woocommerce_omnipay_gateway_config', function($config) {
    $config['gateways']['ECPay']['enabled'] = true;
    $config['gateways']['ECPay']['title'] = '綠界金流';
    return $config;
});
```

## 付款流程

### 直接式金流（Dummy）

1. 客戶在結帳頁輸入卡號資料
2. 立即處理付款
3. 訂單標記為完成或失敗

### 導向式金流（ECPay、NewebPay、YiPay）

1. 客戶在結帳頁選擇付款方式
2. 導向至金流商付款頁面
3. 金流商發送回調通知
4. 自動更新訂單狀態

### 離線付款（銀行轉帳）

1. 客戶在結帳頁選擇銀行轉帳
2. 顯示虛擬帳號
3. 客戶完成銀行轉帳
4. 金流商在收到款項時發送通知

## 開發

### 環境設定

第一次 clone 專案後，執行以下指令設定測試環境：

```bash
# 安裝相依套件
composer install

# 設定 WordPress 測試環境（自動下載 WordPress + WooCommerce）
./bin/install-wp-tests.sh
```

這會在專案目錄下建立 `.wordpress-test/` 資料夾，包含：
- WordPress core
- WooCommerce 外掛
- SQLite Database Integration 外掛

### 執行測試

```bash
# 執行測試
composer test

# 執行測試並產生覆蓋率報告
./vendor/bin/phpunit --coverage-text
```

### 專案結構

```
woocommerce-omnipay/
├── includes/
│   ├── Gateways/
│   │   ├── OmnipayGateway.php      # 基礎金流類別
│   │   ├── ECPayGateway.php        # 綠界實作
│   │   ├── NewebPayGateway.php     # 藍新實作
│   │   ├── YiPayGateway.php        # 乙禾實作
│   │   ├── BankTransferGateway.php # 銀行轉帳實作
│   │   └── DummyGateway.php        # 測試用金流
│   ├── Services/
│   │   ├── OmnipayBridge.php       # Omnipay 轉接器
│   │   └── WooCommerceLogger.php   # PSR-3 日誌
│   ├── Repositories/
│   │   └── OrderRepository.php     # 訂單資料持久化
│   └── GatewayRegistry.php         # 金流註冊
├── templates/
│   ├── checkout/
│   │   └── credit-card-form.php    # 信用卡表單模板
│   └── order/
│       ├── payment-info.php        # 付款資訊顯示
│       └── payment-info-plain.php  # Email 純文字格式
├── assets/
│   ├── css/
│   │   └── payment-info.css        # 付款資訊樣式
│   └── js/
│       └── barcode.js              # 條碼渲染
└── tests/                          # PHPUnit 測試
```

### 新增金流

1. 建立繼承 `OmnipayGateway` 的新金流類別：

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

2. 在設定中註冊金流：

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

3. 安裝對應的 Omnipay 驅動：

```bash
composer require omnipay/my-gateway
```

## Hooks & Filters

### Filters

- `woocommerce_omnipay_gateway_config` - 自訂金流設定
- `woocommerce_omnipay_should_exit` - 控制結束行為（測試時使用）

### Actions

- `woocommerce_api_{gateway_id}_complete` - 處理從金流商返回
- `woocommerce_api_{gateway_id}_notify` - 處理伺服器對伺服器通知
- `woocommerce_api_{gateway_id}_payment_info` - 處理付款資訊通知

## 貢獻

歡迎貢獻！請隨時提交 Pull Request。

1. Fork 專案
2. 建立功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交變更 (`git commit -m 'Add some amazing feature'`)
4. 推送至分支 (`git push origin feature/amazing-feature`)
5. 開啟 Pull Request

## 授權

本專案採用 MIT 授權 - 詳見 [LICENSE](LICENSE) 檔案。

## 致謝

- [Omnipay](https://omnipay.thephpleague.com/) - 付款處理函式庫
- [WooCommerce](https://woocommerce.com/) - 電子商務平台
- [JsBarcode](https://github.com/lindell/JsBarcode) - 條碼產生

## 支援

如有錯誤或功能建議，請使用 [GitHub Issues](https://github.com/recca0120/woocommerce-omnipay/issues)。
