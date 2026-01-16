<?php

namespace OmnipayTaiwan\WooCommerce_Omnipay\Gateways;

use Omnipay\Common\Message\NotificationInterface;
use Psr\Log\LoggerInterface;
use OmnipayTaiwan\WooCommerce_Omnipay\Adapters\Contracts\GatewayAdapter;
use OmnipayTaiwan\WooCommerce_Omnipay\Exceptions\OrderNotFoundException;
use OmnipayTaiwan\WooCommerce_Omnipay\GatewayRegistry;
use OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Concerns\DisplaysPaymentInfo;
use OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features\FeatureFactory;
use OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features\GatewayFeature;
use OmnipayTaiwan\WooCommerce_Omnipay\Gateways\Features\RecurringFeature;
use OmnipayTaiwan\WooCommerce_Omnipay\Helper;
use OmnipayTaiwan\WooCommerce_Omnipay\Repositories\OrderRepository;
use OmnipayTaiwan\WooCommerce_Omnipay\WordPress\Logger;
use OmnipayTaiwan\WooCommerce_Omnipay\WordPress\SettingsManager;
use WC_Payment_Gateway;

/**
 * Omnipay Gateway
 *
 * 基礎類別，處理 Omnipay gateway 的通用邏輯
 * 可以直接實例化（傳入配置）或被繼承
 */
class OmnipayGateway extends WC_Payment_Gateway
{
    use DisplaysPaymentInfo;

    /**
     * 是否顯示 Omnipay 參數欄位以覆蓋共用設定
     *
     * @var bool
     */
    protected $overrideSettings = false;

    /**
     * @var SettingsManager
     */
    protected $settingsManager;

    /**
     * @var OrderRepository
     */
    protected $orders;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var GatewayAdapter
     */
    protected $adapter;

    /**
     * @var GatewayFeature[]
     */
    protected $features = [];

    /**
     * Constructor
     *
     * @param  array  $config  gateway 配置
     * @param  GatewayAdapter|null  $adapter  金流適配器
     */
    public function __construct(array $config, ?GatewayAdapter $adapter = null)
    {
        // gateway_id 自動加上 omnipay_ 前綴
        $this->id = 'omnipay_'.($config['gateway_id'] ?? '');
        $this->method_title = $config['title'] ?? '';
        $this->method_description = $config['description'] ?? '';
        $this->overrideSettings = $config['override_settings'] ?? false;
        $this->adapter = $adapter ?? (new GatewayRegistry())->resolveAdapter($config);
        $this->features = FeatureFactory::createFromConfig($config);

        // 載入 DCA 方案
        foreach ($this->features as $feature) {
            if ($feature instanceof RecurringFeature) {
                $feature->loadPeriods($this);
            }
        }

        // 設定 icon
        if (! empty($config['icon'])) {
            $this->icon = $config['icon'];
        }

        $this->settingsManager = new SettingsManager($this->adapter->getGatewayName());
        $this->orders = new OrderRepository();
        $this->logger = new Logger($this->id);

        // 如果有 features 需要顯示付款欄位，則啟用
        $this->has_fields = $this->hasPaymentFields();

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Hook to save settings
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);

        // 註冊 API callbacks（對應 Omnipay 方法命名）
        add_action('woocommerce_api_'.$this->id.'_notify', [$this, 'acceptNotification']);
        add_action('woocommerce_api_'.$this->id.'_payment_info', [$this, 'handlePaymentInfoCallback']);
        add_action('woocommerce_api_'.$this->id.'_complete', [$this, 'completePurchase']);

        // 註冊付款資訊顯示 hooks（ATM/CVS/BARCODE 等離線付款）
        $this->registerPaymentInfoHooks();
    }

    /**
     * 取得金流商名稱 (ECPay, NewebPay, YiPay 等品牌名稱)
     */
    public function getGatewayName(): string
    {
        return $this->adapter->getGatewayName();
    }

    /**
     * 取得有效的設定值（Gateway 設定 > 共用設定）
     *
     * 用於 transaction_id_prefix, allow_resubmit 等 Plugin 設定
     *
     * @param  string  $key  設定鍵
     * @param  mixed  $default  預設值
     * @return string
     */
    public function getEffectiveOption($key, $default = '')
    {
        // 優先使用 Gateway 自己的設定
        $value = $this->get_option($key);

        if (! empty($value)) {
            return $value;
        }

        // Fallback 到共用設定
        return $this->settingsManager->getSharedValue($key, $default);
    }

    /**
     * Initialize Gateway Settings Form Fields
     *
     * 自動從 Omnipay gateway 參數產生設定欄位
     */
    public function init_form_fields()
    {
        // 基本欄位
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-omnipay'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s', 'woocommerce-omnipay'), $this->method_title),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-omnipay'),
                'type' => 'text',
                'description' => __('Payment method title that users will see during checkout.', 'woocommerce-omnipay'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'woocommerce-omnipay'),
                'type' => 'textarea',
                'description' => __('Payment method description that users will see during checkout.', 'woocommerce-omnipay'),
                'default' => '',
                'desc_tip' => true,
            ],
        ];

        // 從 Omnipay gateway 取得參數並產生欄位（根據配置決定是否顯示）
        if ($this->overrideSettings) {
            $omnipayFields = $this->settingsManager->buildFormFields($this->adapter->getSettingsFields());
            $this->form_fields = array_merge($this->form_fields, $omnipayFields);
        }

        // 通用設定欄位
        $this->form_fields['allow_resubmit'] = [
            'title' => __('Allow Resubmit', 'woocommerce-omnipay'),
            'type' => 'checkbox',
            'label' => __('Allow users to resubmit payment', 'woocommerce-omnipay'),
            'description' => __('When enabled, use random transaction IDs to allow payment retry. Orders remain in Pending status. When disabled, use order IDs as transaction IDs and orders change to On-hold status.', 'woocommerce-omnipay'),
            'default' => 'no',
            'desc_tip' => true,
        ];

        $this->form_fields['transaction_id_prefix'] = [
            'title' => __('Transaction ID Prefix', 'woocommerce-omnipay'),
            'type' => 'text',
            'description' => __('Prefix added to transaction IDs to distinguish different sites or environments.', 'woocommerce-omnipay'),
            'default' => '',
            'desc_tip' => true,
        ];

        // 讓 Features 加入表單欄位
        foreach ($this->features as $feature) {
            $feature->initFormFields($this->form_fields);
        }
    }

    /**
     * 生成 periods 欄位 HTML（DCA 專用）
     *
     * @param  string  $key  欄位鍵
     * @param  array  $data  欄位資料
     * @return string
     */
    public function generate_periods_html($key, $data)
    {
        foreach ($this->features as $feature) {
            if ($feature instanceof RecurringFeature) {
                return $feature->generatePeriodsHtml($key, $data, $this);
            }
        }

        return '';
    }

    /**
     * 處理管理選項
     *
     * @return bool
     */
    public function process_admin_options()
    {
        // 讓 DCA Features 處理額外選項
        foreach ($this->features as $feature) {
            if ($feature instanceof RecurringFeature) {
                if (! $feature->processAdminOptions($this)) {
                    return false;
                }
            }
        }

        return parent::process_admin_options();
    }

    /**
     * 檢查付款方式是否可用
     *
     * @return bool
     */
    public function is_available()
    {
        if (! parent::is_available()) {
            return false;
        }

        // 檢查所有 Features 的可用性
        foreach ($this->features as $feature) {
            if (! $feature->isAvailable($this)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 顯示付款欄位
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo '<p>'.wp_kses_post($this->description).'</p>';
        }

        foreach ($this->features as $feature) {
            $feature->paymentFields($this);
        }
    }

    /**
     * 驗證付款欄位
     *
     * @return bool
     */
    public function validate_fields()
    {
        foreach ($this->features as $feature) {
            if (! $feature->validateFields()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process the payment
     *
     * @param  int  $orderId  Order ID
     * @return array
     */
    public function process_payment($orderId)
    {
        try {
            $order = $this->orders->findByIdOrFail($orderId);
            $response = $this->executePurchase($order);

            return $this->handlePurchaseResponse($order, $response);
        } catch (OrderNotFoundException $e) {
            return $this->handleOrderNotFound($orderId, $e);
        } catch (\Exception $e) {
            return $this->handlePaymentException($order ?? null, $orderId, $e);
        }
    }

    /**
     * 處理金流的回調通知（對應 Omnipay acceptNotification）
     */
    public function acceptNotification()
    {
        $this->logger->info('acceptNotification: Received callback', $this->getRequestData());

        try {
            $adapter = $this->getAdapter();
            $parameters = $this->getCallbackParameters();

            if (! $adapter->supportsAcceptNotification()) {
                $response = $adapter->completePurchase($parameters);

                $this->logger->info('acceptNotification: Fallback to completePurchase', [
                    'transaction_id' => $response->getTransactionId(),
                    'successful' => $response->isSuccessful(),
                    'message' => $response->getMessage(),
                ]);

                $this->handleCompletePurchaseCallback($response);

                return;
            }

            $notification = $adapter->acceptNotification($parameters);
            $data = $notification->getData() ?? [];

            $this->logger->info('acceptNotification: Parsed notification', [
                'transaction_id' => $notification->getTransactionId(),
                'transaction_reference' => $notification->getTransactionReference(),
                'status' => $notification->getTransactionStatus(),
                'message' => $notification->getMessage(),
            ]);

            $this->handleNotification($notification, $data);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('acceptNotification: '.$e->getMessage());
            $this->sendCallbackResponse(false, 'Order not found');
        } catch (\Exception $e) {
            $this->logger->error('acceptNotification: '.$e->getMessage());
            $this->sendCallbackResponse(false, $e->getMessage());
        }
    }

    /**
     * 處理付款資訊回調（接收 paymentInfoUrl 的背景 POST 通知）
     *
     * 預設行為：處理背景 POST 通知並回應金流
     * 子類可覆寫 handlePaymentInfo() 來改變處理邏輯
     *
     * @return string|void 測試時回傳 URL，正式環境 redirect 或 echo 後終止
     */
    public function handlePaymentInfoCallback()
    {
        $this->logger->info('handlePaymentInfoCallback: Received callback', $this->getRequestData());

        try {
            $redirectUrl = $this->handlePaymentInfo();

            // 如果回傳 null，表示是背景通知，已 echo 回應，不需 redirect
            if ($redirectUrl === null) {
                return;
            }

            return $this->redirect($redirectUrl);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('handlePaymentInfoCallback: '.$e->getMessage());
            wc_add_notice(__('Order not found.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('handlePaymentInfoCallback: '.$e->getMessage());
            wc_add_notice(__('Error processing payment information.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        }
    }

    /**
     * 處理用戶返回（對應 Omnipay completePurchase）
     *
     * @return string|void 測試時回傳 URL，正式環境 redirect 後終止
     */
    public function completePurchase()
    {
        $this->logger->info('completePurchase: User returned', $this->getRequestData());

        try {
            $response = $this->getAdapter()->completePurchase($this->getCallbackParameters());

            $this->logger->info('completePurchase: Gateway response', [
                'transaction_id' => $response->getTransactionId(),
                'successful' => $response->isSuccessful(),
                'message' => $response->getMessage(),
                'data' => Helper::maskSensitiveData($response->getData() ?? []),
            ]);

            $order = $this->orders->findByTransactionIdOrFail($response->getTransactionId());

            $result = $this->handlePaymentResult($response, $order, PaymentContext::fromReturnUrl());

            if (! $result['success']) {
                return $this->redirect(wc_get_checkout_url());
            }

            $this->logger->info('completePurchase: Payment completed', [
                'order_id' => $order->get_id(),
            ]);

            return $this->redirect($this->get_return_url($order));
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('completePurchase: '.$e->getMessage());
            wc_add_notice(__('Order not found.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('completePurchase: '.$e->getMessage());
            wc_add_notice(__('Error completing payment.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        }
    }

    /**
     * 檢查是否有付款欄位需要顯示
     */
    protected function hasPaymentFields(): bool
    {
        foreach ($this->features as $feature) {
            if ($feature->hasPaymentFields()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取得已初始化的 Adapter
     *
     * @return GatewayAdapter
     */
    protected function getAdapter()
    {
        $settings = $this->overrideSettings ? $this->settings : [];

        return $this->adapter->initializeFromSettings($this->settingsManager->getAllSettings($settings));
    }

    /**
     * 執行付款請求
     *
     * @param  \WC_Order  $order  訂單
     * @return \Omnipay\Common\Message\ResponseInterface
     */
    protected function executePurchase($order)
    {
        $paymentData = $this->preparePaymentData($order);

        $this->logger->info('process_payment: Initiating payment', [
            'order_id' => $order->get_id(),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'transaction_id' => $paymentData['transactionId'],
        ]);

        $response = $this->getAdapter()->purchase($paymentData);

        $this->logger->info('process_payment: Gateway response', [
            'order_id' => $order->get_id(),
            'successful' => $response->isSuccessful(),
            'redirect' => $response->isRedirect(),
            'message' => $response->getMessage(),
            'transaction_reference' => $response->getTransactionReference(),
            'data' => Helper::maskSensitiveData($response->getData() ?? []),
        ]);

        return $response;
    }

    /**
     * 處理付款回應
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\ResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function handlePurchaseResponse($order, $response)
    {
        if ($response->isSuccessful()) {
            return $this->onPaymentSuccess($order, $response);
        }

        if ($response->isRedirect()) {
            return $this->onPaymentRedirect($order, $response);
        }

        return $this->onPaymentFailed($order, $response->getMessage() ?: 'Payment failed');
    }

    /**
     * 處理訂單不存在的錯誤
     *
     * @param  int  $orderId  訂單 ID
     * @param  OrderNotFoundException  $e  例外
     * @return array
     */
    protected function handleOrderNotFound($orderId, OrderNotFoundException $e)
    {
        $this->logger->error('process_payment: '.$e->getMessage(), ['order_id' => $orderId]);
        wc_add_notice(__('Order not found.', 'woocommerce-omnipay'), 'error');

        return ['result' => 'failure'];
    }

    /**
     * 處理付款過程中的例外
     *
     * @param  \WC_Order|null  $order  訂單
     * @param  int  $orderId  訂單 ID
     * @param  \Exception  $e  例外
     * @return array
     */
    protected function handlePaymentException($order, $orderId, \Exception $e)
    {
        $this->logger->error('process_payment: '.$e->getMessage(), ['order_id' => $orderId]);

        if ($order) {
            $this->orders->addNote($order, sprintf('Payment error: %s', $e->getMessage()));
        }

        wc_add_notice(__('Payment processing error. Please try again later.', 'woocommerce-omnipay'), 'error');

        return ['result' => 'failure'];
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function preparePaymentData($order)
    {
        $transactionId = $this->generateTransactionId($order);

        $data = [
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'description' => sprintf('Order #%s', $order->get_order_number()),
            'transactionId' => $transactionId,
            'returnUrl' => WC()->api_request_url($this->id.'_complete'),
            'cancelUrl' => WC()->api_request_url($this->id.'_complete'),
            'notifyUrl' => WC()->api_request_url($this->id.'_notify'),
        ];

        // 加入 paymentInfoUrl（付款資訊通知 URL）
        $data['paymentInfoUrl'] = $this->getPaymentInfoUrl($order);

        // 取得卡片資料並建立 CreditCard 物件
        $cardData = $this->getCardData();

        if (! empty($cardData)) {
            // 建立 Omnipay CreditCard 物件
            $data['card'] = new \Omnipay\Common\CreditCard($cardData);
        }

        // 讓 Features 處理付款資料
        foreach ($this->features as $feature) {
            $data = $feature->preparePaymentData($data, $order, $this);
        }

        return $data;
    }

    /**
     * 取得付款資訊通知 URL
     *
     * 預設回傳 payment_info endpoint
     * 付款資訊會透過背景 POST 通知到此 URL
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function getPaymentInfoUrl($order)
    {
        return WC()->api_request_url($this->id.$this->adapter->getPaymentInfoUrlSuffix());
    }

    /**
     * 取得 callback 參數
     *
     * 某些金流（如 YiPay）需要額外參數來驗證簽章
     * 子類可覆寫此方法提供特定參數
     *
     * @return array
     */
    protected function getCallbackParameters()
    {
        return [];
    }

    /**
     * 產生 transactionId
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function generateTransactionId($order)
    {
        return $this->orders->createTransactionId(
            $order,
            $this->getEffectiveOption('transaction_id_prefix'),
            $this->getEffectiveOption('allow_resubmit') === 'yes'
        );
    }

    /**
     * 取得卡片資訊
     *
     * @return array|null
     */
    protected function getCardData()
    {
        $fields = ['number', 'expiryMonth', 'expiryYear', 'cvv', 'firstName', 'lastName'];
        $cardData = [];

        foreach ($fields as $field) {
            $postKey = 'omnipay_'.$field;
            if (isset($_POST[$postKey]) && ! empty($_POST[$postKey])) {
                $cardData[$field] = sanitize_text_field($_POST[$postKey]);
            }
        }

        return ! empty($cardData) ? $cardData : null;
    }

    /**
     * 付款成功事件
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\ResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function onPaymentSuccess($order, $response)
    {
        $this->completeOrderPayment($order, $response->getTransactionReference(), 'process_payment');

        // 清空購物車
        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    /**
     * 付款失敗事件
     *
     * @param  \WC_Order  $order  訂單
     * @param  string  $errorMessage  錯誤訊息（技術訊息，記錄到訂單備註）
     * @param  PaymentContext|null  $context  付款上下文
     * @return array
     */
    protected function onPaymentFailed($order, $errorMessage, ?PaymentContext $context = null)
    {
        $context = $context ?? PaymentContext::fromProcessPayment();

        $this->orders->markAsFailed($order, $errorMessage);
        $this->orders->addNote($order, sprintf('Payment failed via %s: %s', $context->getSource(), $errorMessage));

        if ($context->shouldAddNotice()) {
            wc_add_notice(__('Payment failed. Please try again or choose another payment method.', 'woocommerce-omnipay'), 'error');
        }

        return [
            'result' => 'failure',
        ];
    }

    /**
     * 需要 redirect 付款事件
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\RedirectResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function onPaymentRedirect($order, $response)
    {
        // allow_resubmit = no 時，將訂單改為 on-hold 避免重複提交
        if ($this->getEffectiveOption('allow_resubmit') !== 'yes') {
            $this->orders->markAsOnHold($order, sprintf('Awaiting %s payment.', $this->method_title));
        } else {
            $this->orders->addNote($order, sprintf('Redirecting to %s for payment.', $this->method_title));
        }

        // 取得 redirect URL
        $redirectUrl = $response->getRedirectUrl();

        // 如果是 POST redirect，需要產生表單
        if ($response->getRedirectMethod() === 'POST') {
            $redirectUrl = $this->buildRedirectFormUrl($order, $response);
        }

        return [
            'result' => 'success',
            'redirect' => $redirectUrl,
        ];
    }

    /**
     * 建立 POST redirect 表單的 URL
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\RedirectResponseInterface  $response  Omnipay 回應
     * @return string
     */
    protected function buildRedirectFormUrl($order, $response)
    {
        // 儲存 redirect 資料到 session 或 transient
        $redirectData = [
            'url' => $response->getRedirectUrl(),
            'method' => $response->getRedirectMethod(),
            'data' => $response->getRedirectData(),
        ];

        set_transient(
            'omnipay_redirect_'.$order->get_id(),
            $redirectData,
            30 * MINUTE_IN_SECONDS
        );

        // 回傳一個會自動提交表單的頁面 URL
        return add_query_arg([
            'omnipay_redirect' => '1',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ], wc_get_checkout_url());
    }

    /**
     * 處理付款資訊的核心邏輯
     *
     * 預設行為：處理使用者端導向的付款資訊回傳（如 NewebPay 的 CustomerURL）
     * 使用 Adapter 的 getPaymentInfo() 解析回應，儲存付款資訊，並導向感謝頁
     * 子類可覆寫此方法改變處理邏輯（例如：ECPay 使用背景 POST 通知）
     *
     * @return string redirect URL
     */
    protected function handlePaymentInfo()
    {
        $response = $this->getAdapter()->getPaymentInfo();

        $this->logger->info('handlePaymentInfo: Gateway response', [
            'transaction_id' => $response->getTransactionId(),
            'data' => Helper::maskSensitiveData($response->getData() ?? []),
        ]);

        $order = $this->orders->findByTransactionIdOrFail($response->getTransactionId());

        $this->savePaymentInfo($order, $response->getData());

        $this->logger->info('handlePaymentInfo: Payment info saved', [
            'order_id' => $order->get_id(),
        ]);

        return $this->get_return_url($order);
    }

    /**
     * 處理 AcceptNotification 回應
     *
     * @param  NotificationInterface  $notification
     */
    protected function handleNotification($notification, array $data)
    {
        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        if (! $this->validateNotification($order, $notification, $data)) {
            return;
        }

        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        $this->processNotificationResult($order, $notification);
    }

    /**
     * 驗證通知
     *
     * @param  \WC_Order  $order  訂單
     * @param  NotificationInterface  $notification  通知
     * @param  array  $data  通知資料
     * @return bool 驗證通過返回 true
     */
    protected function validateNotification($order, $notification, array $data): bool
    {
        // 金額驗證
        if (! $this->adapter->validateAmount($data, (int) $order->get_total())) {
            $this->sendCallbackResponse(false, 'Amount mismatch');

            return false;
        }

        // Hook: 讓子類處理額外邏輯（如 ECPay 的信用卡資訊、模擬付款）
        return $this->onNotificationReceived($order, $notification, $data);
    }

    /**
     * 處理通知結果
     *
     * @param  \WC_Order  $order  訂單
     * @param  NotificationInterface  $notification  通知
     */
    protected function processNotificationResult($order, $notification): void
    {
        $context = PaymentContext::fromCallback();

        if ($notification->getTransactionStatus() !== NotificationInterface::STATUS_COMPLETED) {
            $errorMessage = $notification->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, $context);
            $this->sendCallbackResponse(false, $errorMessage);

            return;
        }

        $this->completeOrderPayment($order, $notification->getTransactionReference(), $context->getSource());
        $this->sendNotificationResponse($notification);
    }

    /**
     * 通知接收後的 hook
     *
     * 子類可覆寫此方法處理額外邏輯
     *
     * @return bool true 繼續處理，false 已處理完畢
     */
    protected function onNotificationReceived($order, $notification, array $data): bool
    {
        return true;
    }

    /**
     * 處理 completePurchase callback 回應
     *
     * 用於 gateway 不支援 acceptNotification 時的 fallback
     *
     * @param  mixed  $response
     */
    protected function handleCompletePurchaseCallback($response)
    {
        $order = $this->orders->findByTransactionIdOrFail($response->getTransactionId());

        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        $result = $this->handlePaymentResult($response, $order, PaymentContext::fromCallback());

        $this->sendCallbackResponse($result['success'], $result['message']);
    }

    /**
     * 儲存付款資訊
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        $this->orders->savePaymentInfo($order, $this->adapter->normalizePaymentInfo($data));

        $note = $this->adapter->getPaymentInfoNote($data);
        if ($note) {
            $this->orders->addNote($order, $note);
        }
    }

    /**
     * 處理付款結果
     *
     * @param  mixed  $response  Omnipay response
     * @param  \WC_Order  $order  訂單
     * @param  PaymentContext  $context  付款上下文
     * @return array ['success' => bool, 'message' => string]
     */
    protected function handlePaymentResult($response, $order, PaymentContext $context)
    {
        if (! $this->shouldProcessOrder($order)) {
            return ['success' => true, 'message' => ''];
        }

        if (! $response->isSuccessful()) {
            $errorMessage = $response->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, $context);

            return ['success' => false, 'message' => $errorMessage];
        }

        $this->completeOrderPayment($order, $response->getTransactionReference(), $context->getSource());

        return ['success' => true, 'message' => ''];
    }

    /**
     * 重導向並終止
     *
     * @param  string  $url  重導向 URL
     * @return string 測試時回傳 URL
     */
    protected function redirect($url)
    {
        if (apply_filters('woocommerce_omnipay_should_exit', true)) {
            wp_safe_redirect($url);
            exit;
        }

        return $url;
    }

    /**
     * 檢查訂單是否需要處理
     *
     * @param  \WC_Order  $order  訂單
     * @return bool
     */
    protected function shouldProcessOrder($order)
    {
        return $order->get_status() === $this->getPendingStatus();
    }

    /**
     * 取得待處理訂單狀態
     *
     * @return string
     */
    protected function getPendingStatus()
    {
        // allow_resubmit = no 時，訂單應該是 on-hold
        // allow_resubmit = yes 時，訂單應該是 pending
        return $this->getEffectiveOption('allow_resubmit') === 'yes'
            ? OrderRepository::STATUS_PENDING
            : OrderRepository::STATUS_ON_HOLD;
    }

    /**
     * 完成訂單付款
     *
     * @param  \WC_Order  $order  訂單
     * @param  string|null  $transactionRef  交易參考碼
     * @param  string  $source  來源 (callback, return URL)
     */
    protected function completeOrderPayment($order, $transactionRef, $source = 'callback')
    {
        $this->orders->markAsComplete(
            $order,
            $transactionRef,
            sprintf('Payment completed via %s. Transaction ID: %s', $source, $transactionRef ?: 'N/A')
        );
    }

    /**
     * 發送回調回應
     *
     * @param  bool  $success  是否成功
     * @param  string  $message  訊息
     */
    protected function sendCallbackResponse($success, $message = '')
    {
        echo $success
            ? $this->adapter->getCallbackSuccessResponse()
            : $this->adapter->getCallbackFailureResponse($message);
        Helper::terminate();
    }

    /**
     * 發送 Notification 回應
     *
     * 優先使用 gateway 提供的 getReply()，否則使用預設回應
     *
     * @param  NotificationInterface  $notification
     */
    protected function sendNotificationResponse($notification)
    {
        if (method_exists($notification, 'getReply')) {
            echo $notification->getReply();
            Helper::terminate();

            return;
        }
        $this->sendCallbackResponse(true);
    }

    /**
     * 記錄請求資料（隱藏敏感資訊）
     *
     * @return array
     */
    protected function getRequestData()
    {
        return Helper::maskSensitiveData($_POST);
    }
}
