<?php
/**
 * Tests for CFR2WC_Logger class
 *
 * @package CloudflareR2WC
 */

use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {

    private $logged_messages = [];

    protected function setUp(): void {
        parent::setUp();

        // Reset globals and logged messages
        global $_test_options;
        $_test_options = [];
        $this->logged_messages = [];

        // Mock wc_get_logger function
        if (!function_exists('wc_get_logger')) {
            eval('function wc_get_logger() {
                return new class {
                    public function log($level, $message, $context) {
                        global $test_logged_messages;
                        $test_logged_messages[] = [
                            "level" => $level,
                            "message" => $message,
                            "context" => $context
                        ];
                    }
                };
            }');
        }

        global $test_logged_messages;
        $test_logged_messages = [];
    }

    /**
     * Test logging is disabled by default
     */
    public function test_logging_disabled_by_default(): void {
        global $test_logged_messages;

        CFR2WC_Logger::error('Test error message');

        $this->assertEmpty($test_logged_messages);
    }

    /**
     * Test logging when debug mode is enabled
     */
    public function test_logging_when_enabled(): void {
        global $test_logged_messages;

        // Enable debug mode with error level
        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        CFR2WC_Logger::error('Test error message');

        $this->assertCount(1, $test_logged_messages);
        $this->assertEquals('error', $test_logged_messages[0]['level']);
        $this->assertStringContainsString('Test error message', $test_logged_messages[0]['message']);
    }

    /**
     * Test log level filtering
     */
    public function test_log_level_filtering(): void {
        global $test_logged_messages;

        // Enable debug mode with error level (priority 4)
        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        // These should NOT be logged (priority < 4)
        CFR2WC_Logger::debug('Debug message');
        CFR2WC_Logger::info('Info message');
        CFR2WC_Logger::notice('Notice message');
        CFR2WC_Logger::warning('Warning message');

        // These SHOULD be logged (priority >= 4)
        CFR2WC_Logger::error('Error message');
        CFR2WC_Logger::critical('Critical message');
        CFR2WC_Logger::alert('Alert message');
        CFR2WC_Logger::emergency('Emergency message');

        $this->assertCount(4, $test_logged_messages);
        $this->assertEquals('error', $test_logged_messages[0]['level']);
        $this->assertEquals('critical', $test_logged_messages[1]['level']);
        $this->assertEquals('alert', $test_logged_messages[2]['level']);
        $this->assertEquals('emergency', $test_logged_messages[3]['level']);
    }

    /**
     * Test debug level allows all messages
     */
    public function test_debug_level_logs_all(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'debug'
        ]);

        CFR2WC_Logger::debug('Debug message');
        CFR2WC_Logger::info('Info message');
        CFR2WC_Logger::error('Error message');

        $this->assertCount(3, $test_logged_messages);
    }

    /**
     * Test context sanitization for sensitive keys
     */
    public function test_context_sanitization(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        CFR2WC_Logger::error('Test message', [
            'user' => 'john',
            'password' => 'secret123',
            'access_key' => 'AKIAIOSFODNN7EXAMPLE',
            'api_key' => 'sk-1234567890',
            'secret' => 'hidden',
            'token' => 'xyz123',
            'safe_data' => 'visible'
        ]);

        $this->assertCount(1, $test_logged_messages);

        $message = $test_logged_messages[0]['message'];

        // Sensitive keys should be redacted
        $this->assertStringContainsString('[REDACTED]', $message);

        // Safe data should be visible
        $this->assertStringContainsString('visible', $message);

        // Actual sensitive values should NOT appear
        $this->assertStringNotContainsString('secret123', $message);
        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $message);
        $this->assertStringNotContainsString('sk-1234567890', $message);
    }

    /**
     * Test nested context sanitization
     */
    public function test_nested_context_sanitization(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        CFR2WC_Logger::error('Test message', [
            'credentials' => [
                'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
                'secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
            ],
            'config' => [
                'endpoint' => 'https://example.com',
                'api_key' => 'secret-key'
            ]
        ]);

        $this->assertCount(1, $test_logged_messages);

        $message = $test_logged_messages[0]['message'];

        // Nested sensitive data should be redacted
        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $message);
        $this->assertStringNotContainsString('wJalrXUtnFEMI', $message);
        $this->assertStringNotContainsString('secret-key', $message);

        // Non-sensitive data should be visible (JSON encoded)
        $this->assertStringContainsString('https:\/\/example.com', $message);
    }

    /**
     * Test all log level methods exist
     */
    public function test_all_log_levels(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'debug'
        ]);

        CFR2WC_Logger::emergency('Emergency');
        CFR2WC_Logger::alert('Alert');
        CFR2WC_Logger::critical('Critical');
        CFR2WC_Logger::error('Error');
        CFR2WC_Logger::warning('Warning');
        CFR2WC_Logger::notice('Notice');
        CFR2WC_Logger::info('Info');
        CFR2WC_Logger::debug('Debug');

        $this->assertCount(8, $test_logged_messages);

        $levels = array_column($test_logged_messages, 'level');
        $this->assertEquals([
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug'
        ], $levels);
    }

    /**
     * Test context with empty array
     */
    public function test_empty_context(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        CFR2WC_Logger::error('Test message', []);

        $this->assertCount(1, $test_logged_messages);

        // Message should not contain "Context:" when empty
        $message = $test_logged_messages[0]['message'];
        $this->assertStringNotContainsString('Context:', $message);
    }

    /**
     * Test case-insensitive sensitive key detection
     */
    public function test_case_insensitive_sanitization(): void {
        global $test_logged_messages;

        update_option('cfr2wc_settings', [
            'debug_mode' => 'yes',
            'debug_level' => 'error'
        ]);

        CFR2WC_Logger::error('Test message', [
            'Password' => 'secret',
            'ACCESS_KEY' => 'key123',
            'ApiKey' => 'api123',
            'Secret' => 'hidden'
        ]);

        $this->assertCount(1, $test_logged_messages);

        $message = $test_logged_messages[0]['message'];

        // All variations should be redacted
        $this->assertStringNotContainsString('secret', $message);
        $this->assertStringNotContainsString('key123', $message);
        $this->assertStringNotContainsString('api123', $message);
        $this->assertStringNotContainsString('hidden', $message);
    }
}
