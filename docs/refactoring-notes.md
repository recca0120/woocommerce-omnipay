# 程式碼重構筆記

## 1. 重複的 foreach 循環（優先處理）

在 `OmnipayGateway.php` 中有多處使用相同模式遍歷 `$this->features`：

| 位置 | 方法 | 用途 |
|-----|------|-----|
| 第 79-83 行 | `__construct()` | 載入 DCA 方案 |
| 第 195-197 行 | `init_form_fields()` | 讓 Features 加入表單欄位 |
| 第 209-213 行 | `generate_periods_html()` | 生成 periods 欄位 HTML |
| 第 226-232 行 | `process_admin_options()` | 處理額外選項 |
| 第 249-253 行 | `is_available()` | 檢查可用性 |
| 第 267-269 行 | `payment_fields()` | 顯示付款欄位 |
| 第 279-283 行 | `validate_fields()` | 驗證付款欄位 |
| 第 294-299 行 | `hasPaymentFields()` | 檢查是否有付款欄位 |
| 第 410-412 行 | `preparePaymentData()` | 準備付款資料 |

---

## 2. 程式碼異味

### 2.1 過長的方法

| 方法 | 行數 | 位置 | 問題 |
|-----|-----|------|-----|
| `process_payment()` | 54 行 | 第 322-376 行 | 混合準備數據、執行付款、處理回應 |
| `handleNotification()` | 32 行 | 第 740-772 行 | 多重檢查和處理邏輯 |
| `preparePaymentData()` | 31 行 | 第 384-415 行 | 準備資料、建立卡片物件 |

### 2.2 過多的參數

```php
// 第 508 行 - 4 個參數
protected function onPaymentFailed($order, $errorMessage, $source = 'process_payment', $addNotice = true)

// 第 833 行 - 4 個參數
protected function handlePaymentResult($response, $order, $source, $addNotice = true)
```

### 2.3 使用反射檢查（第 294-299 行）

```php
protected function hasPaymentFields(): bool
{
    foreach ($this->features as $feature) {
        $reflection = new \ReflectionMethod($feature, 'paymentFields');
        if ($reflection->getDeclaringClass()->getName() !==
            'WooCommerceOmnipay\\Gateways\\Features\\AbstractFeature') {
            return true;
        }
    }
    return false;
}
```

**問題**：使用反射來判斷方法是否被覆寫，效能差且不直觀

**改進建議**：在 GatewayFeature 介面中加入 `hasPaymentFields(): bool` 方法

### 2.4 直接存取 $_POST（第 464-477 行）

```php
protected function getCardData()
{
    foreach ($fields as $field) {
        $postKey = 'omnipay_'.$field;
        if (isset($_POST[$postKey]) && ! empty($_POST[$postKey])) {
            $cardData[$field] = sanitize_text_field($_POST[$postKey]);
        }
    }
    return ! empty($cardData) ? $cardData : null;
}
```

**問題**：直接存取全域變數 `$_POST`，難以測試

---

## 3. 命名不一致

### 3.1 方法命名風格混用

| 風格 | 方法 | 說明 |
|-----|------|-----|
| snake_case | `process_payment()`, `init_form_fields()` | WC_Payment_Gateway 介面 |
| camelCase | `completePurchase()`, `acceptNotification()` | 自定義方法 |

### 3.2 動詞命名不統一

| 動詞 | 用途 |
|-----|------|
| `get*` | 取得資料，無副作用 |
| `handle*` | 處理邏輯，有副作用 |
| `on*` | 事件回調 |
| `process*` | 主要流程入口 |
| `accept*` | 接受/接收（與 handle 重疊）|

### 3.3 Endpoint vs Url 混用

```php
public function getPaymentInfoEndpoint(): string;  // Adapter
protected function getPaymentInfoUrl($order);       // OmnipayGateway
```

---

## 4. 過度設計

### 4.1 Traits 層級過多

`includes/Adapters/Concerns/*.php` - 5 個 Traits 每個只有 2-4 個簡單方法，可直接合併到 DefaultGatewayAdapter

### 4.2 RecurringFeature 重複程式碼

- `FrequencyRecurringFeature.php` (454行)
- `ScheduledRecurringFeature.php` (523行)

兩個類別有大量重複邏輯，可建立 AbstractRecurringFeature 基類

---

## 優先順序

| 優先度 | 項目 | 狀態 |
|-------|-----|------|
| 1 | foreach 循環重複 | 待處理 |
| 2 | 反射檢查改為介面方法 | 待處理 |
| 3 | 合併 Adapter Traits | 待處理 |
| 4 | 建立 AbstractRecurringFeature | 待處理 |
| 5 | 命名統一 | 待處理 |
