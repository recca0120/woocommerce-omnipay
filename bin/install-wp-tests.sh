#!/usr/bin/env bash

# WooCommerce Omnipay - WordPress Test Environment Setup
#
# Usage:
#   ./bin/install-wp-tests.sh [db_engine]
#
# Arguments:
#   db_engine: 'sqlite' (default) or 'mysql'
#
# Examples:
#   ./bin/install-wp-tests.sh          # Setup with SQLite (default)
#   ./bin/install-wp-tests.sh sqlite   # Setup with SQLite
#   ./bin/install-wp-tests.sh mysql    # Setup with MySQL

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_ENGINE="${1:-sqlite}"

WP_CORE_DIR="${PLUGIN_DIR}/.wordpress-test/wordpress"
WP_PLUGINS_DIR="${WP_CORE_DIR}/wp-content/plugins"

echo "=== WooCommerce Omnipay Test Environment Setup ==="
echo "Plugin directory: ${PLUGIN_DIR}"
echo "WordPress directory: ${WP_CORE_DIR}"
echo "Database engine: ${DB_ENGINE}"
echo ""

# Check if already installed
if [ -f "${WP_CORE_DIR}/wp-includes/version.php" ]; then
    echo "WordPress already installed. To reinstall, run:"
    echo "  rm -rf ${PLUGIN_DIR}/.wordpress-test"
    echo ""
    echo "To run tests:"
    echo "  composer test"
    exit 0
fi

# Create directories
mkdir -p "${WP_CORE_DIR}"
mkdir -p "${WP_PLUGINS_DIR}"

# Download WordPress
echo "Downloading WordPress..."
curl -sL https://wordpress.org/latest.tar.gz | tar xz --strip-components=1 -C "${WP_CORE_DIR}"

# Download WooCommerce
echo "Downloading WooCommerce..."
cd "${WP_PLUGINS_DIR}"

# Determine WooCommerce version based on PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR_MINOR=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")

if [ "$(php -r "echo version_compare('${PHP_MAJOR_MINOR}', '7.4', '<');")" = "1" ]; then
    # PHP < 7.4: Use WooCommerce 8.1.x (last version supporting PHP 7.3)
    WC_VERSION="8.1.1"
    echo "PHP ${PHP_VERSION} detected. Using WooCommerce ${WC_VERSION} (last version supporting PHP 7.3)"
    curl -sL "https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip" -o woocommerce.zip
else
    # PHP >= 7.4: Use latest stable
    echo "PHP ${PHP_VERSION} detected. Using latest stable WooCommerce"
    curl -sL https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip -o woocommerce.zip
fi

unzip -q woocommerce.zip
rm woocommerce.zip

# Download SQLite integration plugin (for SQLite mode)
if [ "${DB_ENGINE}" = "sqlite" ]; then
    echo "Downloading SQLite Database Integration plugin..."
    curl -sL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o sqlite.zip
    unzip -q sqlite.zip
    rm sqlite.zip
fi

# Create symlink to our plugin
echo "Creating symlink to plugin..."
if [ -L "${WP_PLUGINS_DIR}/woocommerce-omnipay" ]; then
    rm "${WP_PLUGINS_DIR}/woocommerce-omnipay"
fi
ln -s "${PLUGIN_DIR}" "${WP_PLUGINS_DIR}/woocommerce-omnipay"

# Install composer dependencies if not already installed
if [ ! -d "${PLUGIN_DIR}/vendor" ]; then
    echo "Installing composer dependencies..."
    cd "${PLUGIN_DIR}"
    composer install
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "To run tests:"
echo "  composer test"
echo ""
echo "Or with coverage:"
echo "  ./vendor/bin/phpunit --coverage-text"
echo ""
if [ "${DB_ENGINE}" = "mysql" ]; then
    echo "Note: Make sure MySQL is running and accessible."
    echo "Configure DB_PASSWORD environment variable if needed."
fi
