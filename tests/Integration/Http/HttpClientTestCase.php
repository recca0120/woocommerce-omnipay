<?php

namespace Recca0120\WooCommerce_Omnipay\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;

abstract class HttpClientTestCase extends TestCase
{
    protected static $serverProcess;

    /** @var int */
    protected static $serverPort = 8989;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $serverDir = __DIR__.'/../../server';
        $command = sprintf(
            'php -S localhost:%d -t %s > /dev/null 2>&1 & echo $!',
            self::$serverPort,
            escapeshellarg($serverDir)
        );

        $pid = exec($command);
        self::$serverProcess = (int) $pid;

        // Wait for server to start
        usleep(100000);

        // Verify server is running
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $connection = @fsockopen('localhost', self::$serverPort, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);

                return;
            }
            usleep(100000);
        }

        throw new \RuntimeException('Failed to start test server');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess) {
            exec('kill '.self::$serverProcess.' 2>/dev/null');
        }

        parent::tearDownAfterClass();
    }

    protected function getServerUrl(string $path = ''): string
    {
        return 'http://localhost:'.self::$serverPort.$path;
    }
}
