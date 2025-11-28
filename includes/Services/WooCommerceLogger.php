<?php

namespace WooCommerceOmnipay\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * WooCommerce Logger PSR-3 Adapter
 *
 * 將 WC_Logger 包裝成 PSR-3 LoggerInterface
 */
class WooCommerceLogger implements LoggerInterface
{
    /**
     * @var \WC_Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $source;

    /**
     * @param  string  $source  Log source identifier (e.g., gateway ID)
     */
    public function __construct(string $source)
    {
        $this->source = $source;
        $this->logger = wc_get_logger();
    }

    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $formattedMessage = $this->formatMessage($message, $context);
        $this->logger->log($level, $formattedMessage, ['source' => $this->source]);
    }

    /**
     * Format message with context data
     */
    protected function formatMessage(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        return $message.' | '.wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
