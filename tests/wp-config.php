<?php

/**
 * WordPress Test Configuration
 *
 * All settings can be overridden via environment variables
 */

// Database configuration (supports both MySQL and SQLite)
define('DB_NAME', getenv('DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// WordPress database table prefix
$table_prefix = getenv('WP_TABLE_PREFIX') ?: 'wptests_';

// Required constant for wp-phpunit
define('WP_PHP_BINARY', 'php');

// Test environment
define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'example.org');
define('WP_TESTS_EMAIL', getenv('WP_TESTS_EMAIL') ?: 'admin@example.org');
define('WP_TESTS_TITLE', getenv('WP_TESTS_TITLE') ?: 'Test Blog');

// Debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Absolute path to WordPress directory
if (! defined('ABSPATH')) {
    // Priority: 1) Environment variable, 2) Local .wordpress-test, 3) Parent WordPress installation
    $wp_core_dir = getenv('WP_CORE_DIR');

    if (! $wp_core_dir) {
        $plugin_dir = dirname(__DIR__);

        // Check for .wordpress-test in plugin directory
        $local_wp = $plugin_dir.'/.wordpress-test/wordpress';
        if (is_dir($local_wp)) {
            $wp_core_dir = $local_wp;
        } else {
            // Fallback: assume plugin is installed in WordPress plugins directory
            $wp_core_dir = dirname($plugin_dir, 3);
        }
    }

    define('ABSPATH', rtrim($wp_core_dir, '/').'/');
}

// Set up wp-content directory
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH.'wp-content');
}

// Database engine configuration
define('DB_ENGINE', getenv('DB_ENGINE') ?: 'mysql');

// SQLite configuration
if (DB_ENGINE === 'sqlite') {
    // Use file-based SQLite database (in-memory doesn't work with multiple connections)
    $db_file = getenv('DB_FILE') ?: 'test.sqlite';
    $db_dir = getenv('DB_DIR') ?: '/tmp/wp-phpunit-tests/';

    // Ensure directory exists
    if (! is_dir($db_dir)) {
        @mkdir($db_dir, 0755, true);
    }

    define('DB_FILE', $db_file);
    define('DB_DIR', $db_dir);

    // Setup SQLite db.php drop-in
    $db_dropin_source = WP_CONTENT_DIR.'/plugins/sqlite-database-integration/wp-includes/sqlite/db.php';
    $db_dropin_target = WP_CONTENT_DIR.'/db.php';

    if (! file_exists($db_dropin_target)) {
        @symlink($db_dropin_source, $db_dropin_target);
    }
}
