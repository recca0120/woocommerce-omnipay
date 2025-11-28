<?php

namespace WooCommerceOmnipay\Gateways;

use Omnipay\Common\Message\NotificationInterface;
use Psr\Log\LoggerInterface;
use WC_Payment_Gateway;
use WooCommerceOmnipay\Exceptions\OrderNotFoundException;
use WooCommerceOmnipay\Helper;
use WooCommerceOmnipay\Repositories\OrderRepository;
use WooCommerceOmnipay\Services\OmnipayBridge;
use WooCommerceOmnipay\Services\WooCommerceLogger;
use WooCommerceOmnipay\Traits\DisplaysPaymentInfo;

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
     * Omnipay gateway 名稱（例如：'Dummy', 'PayPal_Express'）
     *
     * @var string
     */
    protected $name;

    /**
     * 是否顯示 Omnipay 參數欄位以覆蓋共用設定
     *
     * @var bool
     */
    protected $overrideSettings = false;

    /**
     * @var OmnipayBridge
     */
    protected $bridge;

    /**
     * @var OrderRepository
     */
    protected $orders;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param  array  $config  gateway 配置
     */
    public function __construct(array $config)
    {
        // gateway_id 自動加上 omnipay_ 前綴
        $this->id = 'omnipay_'.($config['gateway_id'] ?? '');
        $this->method_title = $config['title'] ?? '';
        $this->method_description = $config['description'] ?? '';
        $this->name = $config['gateway'] ?? '';
        $this->overrideSettings = $config['override_settings'] ?? false;

        $this->bridge = new OmnipayBridge($this->name);
        $this->orders = new OrderRepository;
        $this->logger = new WooCommerceLogger($this->id);

        // 預設不啟用付款欄位（子類可以覆寫）
        $this->has_fields = false;

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
        add_action('woocommerce_api_'.$this->id.'_payment_info', [$this, 'getPaymentInfo']);
        add_action('woocommerce_api_'.$this->id.'_complete', [$this, 'completePurchase']);

        // 註冊付款資訊顯示 hooks（ATM/CVS/BARCODE 等離線付款）
        $this->registerPaymentInfoHooks();
    }

    /**
     * Override get_option to ensure no array values are returned
     *
     * @param  string  $key  Option key
     * @param  mixed  $empty_value  Default value if option is empty
     * @return string
     */
    public function get_option($key, $empty_value = null)
    {
        $value = parent::get_option($key, $empty_value);

        return Helper::sanitizeOptionValue($value);
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
        return $this->bridge->getSharedValue($key, $default);
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
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => sprintf('Enable %s', $this->method_title),
                'default' => 'no',
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'description' => 'Payment method title that users will see during checkout.',
                'default' => $this->method_title,
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'Payment method description that users will see during checkout.',
                'default' => sprintf('Pay with %s', $this->method_title),
                'desc_tip' => true,
            ],
        ];

        // 從 Omnipay gateway 取得參數並產生欄位（根據配置決定是否顯示）
        if ($this->overrideSettings) {
            $omnipayFields = $this->bridge->buildFormFields();
            $this->form_fields = array_merge($this->form_fields, $omnipayFields);
        }

        // 通用設定欄位
        $this->form_fields['allow_resubmit'] = [
            'title' => __('允許重新提交', 'woocommerce-omnipay'),
            'type' => 'checkbox',
            'label' => __('允許用戶重新提交付款', 'woocommerce-omnipay'),
            'description' => __('啟用時使用隨機交易編號，訂單維持 Pending 狀態，允許重新付款。停用時使用訂單編號作為交易編號，訂單改為 On-hold 狀態。', 'woocommerce-omnipay'),
            'default' => 'no',
            'desc_tip' => true,
        ];

        $this->form_fields['transaction_id_prefix'] = [
            'title' => __('交易編號前綴', 'woocommerce-omnipay'),
            'type' => 'text',
            'description' => __('加在交易編號前面的前綴，用於區分不同網站或環境。', 'woocommerce-omnipay'),
            'default' => '',
            'desc_tip' => true,
        ];
    }

    /**
     * Get Omnipay gateway instance
     *
     * @return \Omnipay\Common\GatewayInterface
     */
    public function get_gateway()
    {
        return $this->bridge->createGateway($this->settings);
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
            // 建立 Omnipay gateway
            $gateway = $this->get_gateway();

            // 準備付款參數
            $paymentData = $this->preparePaymentData($order);

            $this->logger->info('process_payment: Initiating payment', [
                'order_id' => $orderId,
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'transaction_id' => $paymentData['transactionId'],
            ]);

            // 執行付款
            $response = $gateway->purchase($paymentData)->send();

            $this->logger->info('process_payment: Gateway response', [
                'order_id' => $orderId,
                'successful' => $response->isSuccessful(),
                'redirect' => $response->isRedirect(),
                'message' => $response->getMessage(),
                'transaction_reference' => $response->getTransactionReference(),
                'data' => Helper::maskSensitiveData($response->getData() ?? []),
            ]);

            // 處理回應
            if ($response->isSuccessful()) {
                // 付款成功（Direct Gateway）
                return $this->onPaymentSuccess($order, $response);
            } elseif ($response->isRedirect()) {
                // 需要 redirect（Redirect Gateway）
                return $this->onPaymentRedirect($order, $response);
            } else {
                // 付款失敗
                $errorMessage = $response->getMessage() ?: 'Payment failed';

                return $this->onPaymentFailed($order, $errorMessage);
            }
        } catch (OrderNotFoundException $e) {
            $this->logger->error('process_payment: '.$e->getMessage(), ['order_id' => $orderId]);
            wc_add_notice(__('找不到訂單資料。', 'woocommerce-omnipay'), 'error');

            return ['result' => 'failure'];
        } catch (\Exception $e) {
            $this->logger->error('process_payment: '.$e->getMessage(), ['order_id' => $orderId]);
            if (isset($order) && $order) {
                $this->orders->addNote($order, sprintf('Payment error: %s', $e->getMessage()));
            }
            wc_add_notice(__('付款處理發生錯誤，請稍後再試。', 'woocommerce-omnipay'), 'error');

            return ['result' => 'failure'];
        }
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
        return WC()->api_request_url($this->id.'_payment_info');
    }

    /**
     * 取得 callback 參數
     *
     * 子類可覆寫此方法提供額外參數給 acceptNotification / completePurchase
     * 例如：YiPay 需要 returnUrl 和 notifyUrl 來驗證簽章
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
     * @param  string  $source  來源 (process_payment, callback, return URL)
     * @param  bool  $addNotice  是否顯示錯誤訊息給使用者
     * @return array
     */
    protected function onPaymentFailed($order, $errorMessage, $source = 'process_payment', $addNotice = true)
    {
        $this->orders->markAsFailed($order, $errorMessage);
        $this->orders->addNote($order, sprintf('Payment failed via %s: %s', $source, $errorMessage));

        if ($addNotice) {
            wc_add_notice(__('付款失敗，請重新嘗試或選擇其他付款方式。', 'woocommerce-omnipay'), 'error');
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
     * 處理金流的回調通知（對應 Omnipay acceptNotification）
     */
    public function acceptNotification()
    {
        $this->logger->info('acceptNotification: Received callback', $this->getRequestData());

        try {
            $gateway = $this->get_gateway();

            if ($gateway->supportsAcceptNotification()) {
                $notification = $gateway->acceptNotification($this->getCallbackParameters());

                $this->logger->info('acceptNotification: Parsed notification', [
                    'transaction_id' => $notification->getTransactionId(),
                    'transaction_reference' => $notification->getTransactionReference(),
                    'status' => $notification->getTransactionStatus(),
                    'message' => $notification->getMessage(),
                ]);

                $this->handleNotification($notification);

                return;
            }

            $response = $gateway->completePurchase($this->getCallbackParameters())->send();

            $this->logger->info('acceptNotification: Fallback response', [
                'transaction_id' => $response->getTransactionId(),
                'successful' => $response->isSuccessful(),
                'message' => $response->getMessage(),
                'data' => Helper::maskSensitiveData($response->getData() ?? []),
            ]);

            $this->handleCompletePurchaseCallback($response);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('acceptNotification: '.$e->getMessage());
            $this->sendCallbackResponse(false, 'Order not found');
        } catch (\Exception $e) {
            $this->logger->error('acceptNotification: '.$e->getMessage());
            $this->sendCallbackResponse(false, $e->getMessage());
        }
    }

    /**
     * 處理付款資訊通知（接收 paymentInfoUrl 的背景 POST 通知）
     *
     * 預設行為：處理背景 POST 通知並回應金流
     * 子類可覆寫 handlePaymentInfo() 來改變處理邏輯
     *
     * @return string|void 測試時回傳 URL，正式環境 redirect 或 echo 後終止
     */
    public function getPaymentInfo()
    {
        $this->logger->info('getPaymentInfo: Received callback', $this->getRequestData());

        try {
            $redirectUrl = $this->handlePaymentInfo();

            // 如果回傳 null，表示是背景通知，已 echo 回應，不需 redirect
            if ($redirectUrl === null) {
                return;
            }

            return $this->redirect($redirectUrl);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('getPaymentInfo: '.$e->getMessage());
            wc_add_notice(__('找不到訂單資料。', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('getPaymentInfo: '.$e->getMessage());
            wc_add_notice(__('處理付款資訊時發生錯誤。', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        }
    }

    /**
     * 處理付款資訊的核心邏輯
     *
     * 預設行為：接收背景 POST 通知，儲存付款資訊，回應金流
     * 子類可覆寫此方法改變處理邏輯（例如：NewebPay 是使用者導向的 CustomerURL）
     *
     * @return string|null redirect URL 或 null（背景通知不需 redirect）
     */
    protected function handlePaymentInfo()
    {
        $gateway = $this->get_gateway();
        $notification = $gateway->acceptNotification($this->getCallbackParameters());

        $this->logger->info('getPaymentInfo: Parsed notification', [
            'transaction_id' => $notification->getTransactionId(),
            'data' => Helper::maskSensitiveData($notification->getData() ?? []),
        ]);

        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        $this->savePaymentInfo($order, $notification->getData());

        $this->logger->info('getPaymentInfo: Payment info saved', [
            'order_id' => $order->get_id(),
        ]);

        $this->sendNotificationResponse($notification);

        return null;
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
            $gateway = $this->get_gateway();
            $response = $gateway->completePurchase($this->getCallbackParameters())->send();

            $this->logger->info('completePurchase: Gateway response', [
                'transaction_id' => $response->getTransactionId(),
                'successful' => $response->isSuccessful(),
                'message' => $response->getMessage(),
                'data' => Helper::maskSensitiveData($response->getData() ?? []),
            ]);

            $order = $this->orders->findByTransactionIdOrFail($response->getTransactionId());

            $result = $this->handlePaymentResult($response, $order, 'return URL');

            if (! $result['success']) {
                return $this->redirect(wc_get_checkout_url());
            }

            $this->logger->info('completePurchase: Payment completed', [
                'order_id' => $order->get_id(),
            ]);

            return $this->redirect($this->get_return_url($order));
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('completePurchase: '.$e->getMessage());
            wc_add_notice(__('找不到訂單資料。', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('completePurchase: '.$e->getMessage());
            wc_add_notice(__('完成付款時發生錯誤。', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        }
    }

    /**
     * 處理 AcceptNotification 回應的核心邏輯
     *
     * 子類可覆寫此方法來自訂 notification 處理邏輯
     *
     * @param  NotificationInterface  $notification
     */
    protected function handleNotification($notification)
    {
        $order = $this->orders->findByTransactionIdOrFail($notification->getTransactionId());

        // 金額驗證
        if (! $this->validateAmount($order, $notification->getData())) {
            $this->sendCallbackResponse(false, 'Amount mismatch');

            return;
        }

        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        $status = $notification->getTransactionStatus();

        if ($status !== NotificationInterface::STATUS_COMPLETED) {
            $errorMessage = $notification->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, 'callback', false);
            $this->sendCallbackResponse(false, $errorMessage);

            return;
        }

        $this->completeOrderPayment($order, $notification->getTransactionReference(), 'callback');

        $this->sendNotificationResponse($notification);
    }

    /**
     * 驗證回調金額是否與訂單金額相符
     *
     * 子類應覆寫此方法實作金額驗證邏輯
     * 預設不驗證（回傳 true）
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  回調資料
     * @return bool
     */
    protected function validateAmount($order, array $data)
    {
        return true;
    }

    /**
     * 處理 completePurchase callback 回應的核心邏輯
     *
     * 用於 gateway 不支援 acceptNotification 時的 fallback
     * 子類可覆寫此方法來自訂處理邏輯
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

        $result = $this->handlePaymentResult($response, $order, 'callback', false);

        $this->sendCallbackResponse($result['success'], $result['message']);
    }

    /**
     * 儲存付款資訊
     *
     * 子類可覆寫此方法來自訂付款資訊的儲存邏輯
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function savePaymentInfo($order, array $data)
    {
        $this->orders->savePaymentInfo($order, $data);
    }

    /**
     * 處理付款結果
     *
     * @param  mixed  $response  Omnipay response
     * @param  \WC_Order  $order  訂單
     * @param  string  $source  來源
     * @param  bool  $addNotice  是否顯示通知
     * @return array ['success' => bool, 'message' => string]
     */
    protected function handlePaymentResult($response, $order, $source, $addNotice = true)
    {
        if (! $this->shouldProcessOrder($order)) {
            return ['success' => true, 'message' => ''];
        }

        if (! $response->isSuccessful()) {
            $errorMessage = $response->getMessage() ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, $source, $addNotice);

            return ['success' => false, 'message' => $errorMessage];
        }

        $this->completeOrderPayment($order, $response->getTransactionReference(), $source);

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
        if ($success) {
            echo '1|OK';
        } else {
            echo '0|'.$message;
        }
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
