<?php
/**
 * Plugin Name: Cloudflare R2 for WooCommerce
 * Plugin URI: https://github.com/morvy/cloudflare-r2-for-woocommerce
 * Description: Simple WooCommerce integration to offload downloadable product files to Cloudflare R2
 * Version: 0.1.0
 * Author: Peter Morvay
 * Author URI: https://moped.jepan.sk
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: cfr2wc
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 10.3
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CFR2WC_VERSION', '0.1.0');
define('CFR2WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFR2WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CFR2WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloader
if (file_exists(CFR2WC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once CFR2WC_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-logger.php';
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-encryption.php';
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-database.php';
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-file-cache.php';
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-file-cache-manager.php';
require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-main.php';

/**
 * Declare compatibility with WooCommerce features
 */
function cfr2wc_declare_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('orders_cache', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'cfr2wc_declare_compatibility');

/**
 * Initialize the plugin
 */
function cfr2wc_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'cfr2wc_woocommerce_missing_notice');
        return;
    }

    // Initialize database
    CFR2WC_Database::check_database_version();

    // Initialize main plugin class
    CFR2WC_Main::instance();
}
add_action('plugins_loaded', 'cfr2wc_init');

/**
 * WooCommerce missing notice
 */
function cfr2wc_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Cloudflare R2 for WooCommerce requires WooCommerce to be installed and active.', 'cfr2wc'); ?></p>
    </div>
    <?php
}

/**
 * Activation hook
 */
function cfr2wc_activate() {
    // Create options with defaults
    add_option('cfr2wc_settings', [
        'endpoint' => '',
        'access_key_id' => '',
        'secret_access_key' => '',
        'bucket_name' => '',
        'custom_domain' => '',
    ]);

    // Create database tables
    CFR2WC_Database::create_tables();
}
register_activation_hook(__FILE__, 'cfr2wc_activate');

/**
 * Deactivation hook
 */
function cfr2wc_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'cfr2wc_deactivate');
