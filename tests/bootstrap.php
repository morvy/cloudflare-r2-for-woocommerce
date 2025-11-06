<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package CloudflareR2WC
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Mock WordPress functions that are used in the plugin
if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'test-salt-for-phpunit-testing-do-not-use-in-production';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $_test_options;
        return $_test_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $_test_options;
        $_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $_test_options;
        unset($_test_options[$option]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $_test_transients;
        $data = $_test_transients[$transient] ?? false;
        if ($data && isset($data['expiration']) && $data['expiration'] < time()) {
            unset($_test_transients[$transient]);
            return false;
        }
        return $data['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $_test_transients;
        $_test_transients[$transient] = [
            'value' => $value,
            'expiration' => time() + $expiration
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $_test_transients;
        unset($_test_transients[$transient]);
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename, $mimes = null) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $type = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            default => false
        };
        return ['ext' => $ext, 'type' => $type];
    }
}

if (!function_exists('get_allowed_mime_types')) {
    function get_allowed_mime_types() {
        return [
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor];
    }
}

// Initialize global test variables
global $_test_options, $_test_transients;
$_test_options = [];
$_test_transients = [];

// WordPress function polyfills for testing
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// Load plugin classes
require_once dirname(__DIR__) . '/includes/class-cfr2wc-encryption.php';
require_once dirname(__DIR__) . '/includes/class-cfr2wc-logger.php';
