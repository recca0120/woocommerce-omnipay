<?php

namespace WooCommerceOmnipay\Gateways;

use Omnipay\Common\Message\NotificationInterface;
use Psr\Log\LoggerInterface;
use WC_Payment_Gateway;
use WooCommerceOmnipay\Exceptions\OrderNotFoundException;
use WooCommerceOmnipay\Gateways\Traits\DisplaysPaymentInfo;
use WooCommerceOmnipay\Repositories\OrderRepository;
use WooCommerceOmnipay\Services\OmnipayBridge;
use WooCommerceOmnipay\Services\WooCommerceLogger;

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
     * 訂單狀態常數
     */
    protected const STATUS_PENDING = 'pending';

    protected const STATUS_ON_HOLD = 'on-hold';

    protected const STATUS_FAILED = 'failed';

    /**
     * Omnipay gateway 名稱（例如：'Dummy', 'PayPal_Express'）
     *
     * @var string
     */
    protected $omnipay_gateway_name;

    /**
     * @var OmnipayBridge
     */
    protected $omnipay_bridge;

    /**
     * @var OrderRepository
     */
    protected $order_repository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param  array  $gateway_config  可選的 gateway 配置
     */
    public function __construct(array $gateway_config = [])
    {
        // 如果提供了配置，使用配置初始化
        if (! empty($gateway_config)) {
            // gateway_id 自動加上 omnipay_ 前綴
            $gateway_id = $gateway_config['gateway_id'] ?? '';
            $this->id = 'omnipay_'.$gateway_id;
            $this->method_title = $gateway_config['title'] ?? '';
            $this->method_description = $gateway_config['description'] ?? '';
            $this->omnipay_gateway_name = $gateway_config['omnipay_name'] ?? '';
        }

        $this->omnipay_bridge = new OmnipayBridge($this->omnipay_gateway_name);
        $this->order_repository = new OrderRepository;
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
        add_action('woocommerce_api_'.$this->id.'_notify', [$this, 'accept_notification']);
        add_action('woocommerce_api_'.$this->id.'_payment_info', [$this, 'get_payment_info']);
        add_action('woocommerce_api_'.$this->id.'_complete', [$this, 'complete_purchase']);

        // 註冊付款資訊顯示 hooks（ATM/CVS/BARCODE 等離線付款）
        $this->register_payment_info_hooks();
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

        return OmnipayBridge::sanitizeOptionValue($value);
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

        // 從 Omnipay gateway 取得參數並產生欄位
        $omnipay_fields = $this->omnipay_bridge->buildFormFields();
        $this->form_fields = array_merge($this->form_fields, $omnipay_fields);

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
    public function get_omnipay_gateway()
    {
        return $this->omnipay_bridge->createGateway($this->get_omnipay_parameters());
    }

    /**
     * 從 WooCommerce 設定取得 Omnipay 參數
     *
     * @return array
     */
    protected function get_omnipay_parameters()
    {
        $parameters = [];

        foreach ($this->omnipay_bridge->getDefaultParameters() as $key => $default_value) {
            $setting_value = $this->get_option($key);

            if (! empty($setting_value)) {
                $parameters[$key] = OmnipayBridge::convertOptionValue($setting_value, $default_value);
            }
        }

        return $parameters;
    }

    /**
     * Process the payment
     *
     * @param  int  $order_id  Order ID
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = $this->order_repository->findById($order_id);

        if (! $order) {
            wc_add_notice('Invalid order.', 'error');

            return [
                'result' => 'failure',
            ];
        }

        try {
            // 建立 Omnipay gateway
            $gateway = $this->get_omnipay_gateway();

            // 準備付款參數
            $payment_data = $this->prepare_payment_data($order);

            $this->logger->info('process_payment: Initiating payment', [
                'order_id' => $order_id,
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'transaction_id' => $payment_data['transactionId'],
            ]);

            // 執行付款
            $response = $gateway->purchase($payment_data)->send();

            $this->logger->info('process_payment: Gateway response', [
                'order_id' => $order_id,
                'successful' => $response->isSuccessful(),
                'redirect' => $response->isRedirect(),
                'message' => $response->getMessage(),
                'transaction_reference' => $response->getTransactionReference(),
                'data' => $this->mask_sensitive_data($response->getData() ?? []),
            ]);

            // 處理回應
            if ($response->isSuccessful()) {
                // 付款成功（Direct Gateway）
                return $this->on_payment_success($order, $response);
            } elseif ($response->isRedirect()) {
                // 需要 redirect（Redirect Gateway）
                return $this->on_payment_redirect($order, $response);
            } else {
                // 付款失敗
                $error_message = $response->getMessage() ?: 'Payment failed';

                return $this->on_payment_failed($order, $error_message);
            }
        } catch (\Exception $e) {
            $this->logger->error('process_payment: Exception', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ]);

            // 例外處理
            $order->add_order_note(
                sprintf('Payment error: %s', $e->getMessage())
            );
            wc_add_notice('Payment error: '.$e->getMessage(), 'error');

            return [
                'result' => 'failure',
            ];
        }
    }

    /**
     * 準備付款資料
     *
     * @param  \WC_Order  $order  訂單
     * @return array
     */
    protected function prepare_payment_data($order)
    {
        $transaction_id = $this->generate_transaction_id($order);

        $data = [
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'description' => sprintf('Order #%s', $order->get_order_number()),
            'transactionId' => $transaction_id,
            'returnUrl' => WC()->api_request_url($this->id.'_complete'),
            'cancelUrl' => WC()->api_request_url($this->id.'_complete'),
            'notifyUrl' => WC()->api_request_url($this->id.'_notify'),
        ];

        // 加入 paymentInfoUrl（付款資訊通知 URL）
        $data['paymentInfoUrl'] = $this->get_payment_info_url($order);

        // 取得卡片資料並建立 CreditCard 物件
        $card_data = $this->get_card_data();

        if (! empty($card_data)) {
            // 建立 Omnipay CreditCard 物件
            $data['card'] = new \Omnipay\Common\CreditCard($card_data);
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
    protected function get_payment_info_url($order)
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
    protected function get_callback_parameters()
    {
        return [];
    }

    /**
     * 產生 transactionId
     *
     * @param  \WC_Order  $order  訂單
     * @return string
     */
    protected function generate_transaction_id($order)
    {
        return $this->order_repository->createTransactionId(
            $order,
            $this->get_option('transaction_id_prefix'),
            $this->get_option('allow_resubmit') === 'yes'
        );
    }

    /**
     * 取得卡片資訊
     *
     * @return array|null
     */
    protected function get_card_data()
    {
        $fields = ['number', 'expiryMonth', 'expiryYear', 'cvv', 'firstName', 'lastName'];
        $card_data = [];

        foreach ($fields as $field) {
            $post_key = 'omnipay_'.$field;
            if (isset($_POST[$post_key]) && ! empty($_POST[$post_key])) {
                $card_data[$field] = sanitize_text_field($_POST[$post_key]);
            }
        }

        return ! empty($card_data) ? $card_data : null;
    }

    /**
     * 付款成功事件
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\ResponseInterface  $response  Omnipay 回應
     * @return array
     */
    protected function on_payment_success($order, $response)
    {
        $this->complete_order_payment($order, $response->getTransactionReference(), 'process_payment');

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
     * @param  string  $error_message  錯誤訊息
     * @param  string  $source  來源 (process_payment, callback, return URL)
     * @param  bool  $add_notice  是否顯示錯誤訊息給使用者
     * @return array
     */
    protected function on_payment_failed($order, $error_message, $source = 'process_payment', $add_notice = true)
    {
        $order->update_status(self::STATUS_FAILED, $error_message);
        $order->add_order_note(
            sprintf('Payment failed via %s: %s', $source, $error_message)
        );

        if ($add_notice) {
            wc_add_notice($error_message, 'error');
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
    protected function on_payment_redirect($order, $response)
    {
        // allow_resubmit = no 時，將訂單改為 on-hold 避免重複提交
        if ($this->get_option('allow_resubmit') !== 'yes') {
            $order->update_status(self::STATUS_ON_HOLD, sprintf('Awaiting %s payment.', $this->method_title));
        } else {
            $order->add_order_note(
                sprintf('Redirecting to %s for payment.', $this->method_title)
            );
        }

        // 取得 redirect URL
        $redirect_url = $response->getRedirectUrl();

        // 如果是 POST redirect，需要產生表單
        if ($response->getRedirectMethod() === 'POST') {
            $redirect_url = $this->build_redirect_form_url($order, $response);
        }

        return [
            'result' => 'success',
            'redirect' => $redirect_url,
        ];
    }

    /**
     * 建立 POST redirect 表單的 URL
     *
     * @param  \WC_Order  $order  訂單
     * @param  \Omnipay\Common\Message\RedirectResponseInterface  $response  Omnipay 回應
     * @return string
     */
    protected function build_redirect_form_url($order, $response)
    {
        // 儲存 redirect 資料到 session 或 transient
        $redirect_data = [
            'url' => $response->getRedirectUrl(),
            'method' => $response->getRedirectMethod(),
            'data' => $response->getRedirectData(),
        ];

        set_transient(
            'omnipay_redirect_'.$order->get_id(),
            $redirect_data,
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
    public function accept_notification()
    {
        $this->logger->info('accept_notification: Received callback', $this->get_request_data());

        try {
            $gateway = $this->get_omnipay_gateway();

            if ($gateway->supportsAcceptNotification()) {
                $notification = $gateway->acceptNotification($this->get_callback_parameters());

                $this->logger->info('accept_notification: Parsed notification', [
                    'transaction_id' => $notification->getTransactionId(),
                    'transaction_reference' => $notification->getTransactionReference(),
                    'status' => $notification->getTransactionStatus(),
                    'message' => $notification->getMessage(),
                ]);

                $this->handle_notification($notification);

                return;
            }

            $response = $gateway->completePurchase($this->get_callback_parameters())->send();

            $this->logger->info('accept_notification: Fallback response', [
                'transaction_id' => $response->getTransactionId(),
                'successful' => $response->isSuccessful(),
                'message' => $response->getMessage(),
                'data' => $this->mask_sensitive_data($response->getData() ?? []),
            ]);

            $this->handle_complete_purchase_callback($response);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('accept_notification: '.$e->getMessage());
            $this->send_callback_response(false, 'Order not found');
        } catch (\Exception $e) {
            $this->logger->error('accept_notification: Exception', [
                'error' => $e->getMessage(),
            ]);
            $this->send_callback_response(false, $e->getMessage());
        }
    }

    /**
     * 處理付款資訊通知（接收 paymentInfoUrl 的背景 POST 通知）
     *
     * 預設行為：處理背景 POST 通知並回應金流
     * 子類可覆寫 handle_payment_info() 來改變處理邏輯
     *
     * @return string|void 測試時回傳 URL，正式環境 redirect 或 echo 後終止
     */
    public function get_payment_info()
    {
        $this->logger->info('get_payment_info: Received callback', $this->get_request_data());

        try {
            $redirect_url = $this->handle_payment_info();

            // 如果回傳 null，表示是背景通知，已 echo 回應，不需 redirect
            if ($redirect_url === null) {
                return;
            }

            return $this->redirect($redirect_url);
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('get_payment_info: '.$e->getMessage());
            wc_add_notice(__('Order not found.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('get_payment_info: Exception', [
                'error' => $e->getMessage(),
            ]);
            wc_add_notice($e->getMessage(), 'error');

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
    protected function handle_payment_info()
    {
        $gateway = $this->get_omnipay_gateway();
        $notification = $gateway->acceptNotification($this->get_callback_parameters());

        $this->logger->info('get_payment_info: Parsed notification', [
            'transaction_id' => $notification->getTransactionId(),
            'data' => $this->mask_sensitive_data($notification->getData() ?? []),
        ]);

        $order = $this->order_repository->findByTransactionIdOrFail($notification->getTransactionId());

        $this->save_payment_info($order, $notification->getData());

        $this->logger->info('get_payment_info: Payment info saved', [
            'order_id' => $order->get_id(),
        ]);

        $this->send_notification_response($notification);

        return null;
    }

    /**
     * 處理用戶返回（對應 Omnipay completePurchase）
     *
     * @return string|void 測試時回傳 URL，正式環境 redirect 後終止
     */
    public function complete_purchase()
    {
        $this->logger->info('complete_purchase: User returned', $this->get_request_data());

        try {
            $gateway = $this->get_omnipay_gateway();
            $response = $gateway->completePurchase($this->get_callback_parameters())->send();

            $this->logger->info('complete_purchase: Gateway response', [
                'transaction_id' => $response->getTransactionId(),
                'successful' => $response->isSuccessful(),
                'message' => $response->getMessage(),
                'data' => $this->mask_sensitive_data($response->getData() ?? []),
            ]);

            $order = $this->order_repository->findByTransactionIdOrFail($response->getTransactionId());

            $result = $this->handle_payment_result($response, $order, 'return URL');

            if (! $result['success']) {
                return $this->redirect(wc_get_checkout_url());
            }

            $this->logger->info('complete_purchase: Payment completed', [
                'order_id' => $order->get_id(),
            ]);

            return $this->redirect($this->get_return_url($order));
        } catch (OrderNotFoundException $e) {
            $this->logger->warning('complete_purchase: '.$e->getMessage());
            wc_add_notice(__('Order not found.', 'woocommerce-omnipay'), 'error');

            return $this->redirect(wc_get_checkout_url());
        } catch (\Exception $e) {
            $this->logger->error('complete_purchase: Exception', [
                'error' => $e->getMessage(),
            ]);
            wc_add_notice($e->getMessage(), 'error');

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
    protected function handle_notification($notification)
    {
        $order = $this->order_repository->findByTransactionIdOrFail($notification->getTransactionId());

        if (! $this->should_process_order($order)) {
            $this->send_callback_response(true);

            return;
        }

        $status = $notification->getTransactionStatus();

        if ($status !== NotificationInterface::STATUS_COMPLETED) {
            $error_message = $notification->getMessage() ?: 'Payment failed';
            $this->on_payment_failed($order, $error_message, 'callback', false);
            $this->send_callback_response(false, $error_message);

            return;
        }

        $this->complete_order_payment($order, $notification->getTransactionReference(), 'callback');

        $this->send_notification_response($notification);
    }

    /**
     * 處理 completePurchase callback 回應的核心邏輯
     *
     * 用於 gateway 不支援 acceptNotification 時的 fallback
     * 子類可覆寫此方法來自訂處理邏輯
     *
     * @param  mixed  $response
     */
    protected function handle_complete_purchase_callback($response)
    {
        $order = $this->order_repository->findByTransactionIdOrFail($response->getTransactionId());

        if (! $this->should_process_order($order)) {
            $this->send_callback_response(true);

            return;
        }

        $result = $this->handle_payment_result($response, $order, 'callback', false);

        $this->send_callback_response($result['success'], $result['message']);
    }

    /**
     * 儲存付款資訊
     *
     * 子類可覆寫此方法來自訂付款資訊的儲存邏輯
     *
     * @param  \WC_Order  $order  訂單
     * @param  array  $data  通知資料
     */
    protected function save_payment_info($order, array $data)
    {
        $this->order_repository->savePaymentInfo($order, $data);
    }

    /**
     * 處理付款結果
     *
     * @param  mixed  $response  Omnipay response
     * @param  \WC_Order  $order  訂單
     * @param  string  $source  來源
     * @param  bool  $add_notice  是否顯示通知
     * @return array ['success' => bool, 'message' => string]
     */
    protected function handle_payment_result($response, $order, $source, $add_notice = true)
    {
        if (! $this->should_process_order($order)) {
            return ['success' => true, 'message' => ''];
        }

        if (! $response->isSuccessful()) {
            $error_message = $response->getMessage() ?: 'Payment failed';
            $this->on_payment_failed($order, $error_message, $source, $add_notice);

            return ['success' => false, 'message' => $error_message];
        }

        $this->complete_order_payment($order, $response->getTransactionReference(), $source);

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
    protected function should_process_order($order)
    {
        return $order->get_status() === $this->get_pending_status();
    }

    /**
     * 取得待處理訂單狀態
     *
     * @return string
     */
    protected function get_pending_status()
    {
        // allow_resubmit = no 時，訂單應該是 on-hold
        // allow_resubmit = yes 時，訂單應該是 pending
        return $this->get_option('allow_resubmit') === 'yes' ? self::STATUS_PENDING : self::STATUS_ON_HOLD;
    }

    /**
     * 完成訂單付款
     *
     * @param  \WC_Order  $order  訂單
     * @param  string|null  $transaction_ref  交易參考碼
     * @param  string  $source  來源 (callback, return URL)
     */
    protected function complete_order_payment($order, $transaction_ref, $source = 'callback')
    {
        $order->payment_complete($transaction_ref);
        $order->add_order_note(
            sprintf('Payment completed via %s. Transaction ID: %s', $source, $transaction_ref ?: 'N/A')
        );
    }

    /**
     * 發送回調回應
     *
     * @param  bool  $success  是否成功
     * @param  string  $message  訊息
     */
    protected function send_callback_response($success, $message = '')
    {
        if ($success) {
            echo '1|OK';
        } else {
            echo '0|'.$message;
        }
        $this->terminate();
    }

    /**
     * 發送 Notification 回應
     *
     * 優先使用 gateway 提供的 getReply()，否則使用預設回應
     *
     * @param  NotificationInterface  $notification
     */
    protected function send_notification_response($notification)
    {
        if (method_exists($notification, 'getReply')) {
            echo $notification->getReply();
            $this->terminate();

            return;
        }
        $this->send_callback_response(true);
    }

    /**
     * 終止請求
     *
     * 在測試環境中可透過 filter 禁用 exit
     *
     * @codeCoverageIgnore
     */
    protected function terminate()
    {
        if (apply_filters('woocommerce_omnipay_should_exit', true)) {
            exit;
        }
    }

    /**
     * 記錄請求資料（隱藏敏感資訊）
     *
     * @return array
     */
    protected function get_request_data()
    {
        return $this->mask_sensitive_data($_POST);
    }

    /**
     * 遮蔽敏感資料
     *
     * @param  array  $data  原始資料
     * @return array 遮蔽後的資料
     */
    protected function mask_sensitive_data(array $data)
    {
        $sensitive_keys = ['HashKey', 'HashIV', 'cvv', 'number', 'card_number', 'password', 'secret'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mask_sensitive_data($value);
            } elseif (in_array(strtolower($key), array_map('strtolower', $sensitive_keys), true)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
