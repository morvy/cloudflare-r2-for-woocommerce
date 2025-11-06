<?php
/**
 * Tests for file validation functionality
 *
 * @package CloudflareR2WC
 */

use PHPUnit\Framework\TestCase;

class FileValidationTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Reset globals
        global $_test_options;
        $_test_options = [];
    }

    /**
     * Test valid file upload
     */
    public function test_valid_file(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 1024 * 1024, // 1MB
            'tmp_name' => '/tmp/phpupload',
            'error' => UPLOAD_ERR_OK
        ];

        $result = $this->validate_upload($file);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test file with disallowed extension
     */
    public function test_disallowed_file_type(): void {
        $file = [
            'name' => 'test.exe',
            'type' => 'application/x-msdownload',
            'size' => 1024,
            'tmp_name' => '/tmp/phpupload',
            'error' => UPLOAD_ERR_OK
        ];

        $result = $this->validate_upload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not allowed', $result['message']);
    }

    /**
     * Test file too large
     */
    public function test_file_too_large(): void {
        $file = [
            'name' => 'large.jpg',
            'type' => 'image/jpeg',
            'size' => 150 * 1024 * 1024, // 150MB (over default 100MB limit)
            'tmp_name' => '/tmp/phpupload',
            'error' => UPLOAD_ERR_OK
        ];

        $result = $this->validate_upload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too large', $result['message']);
    }

    /**
     * Test upload error handling
     */
    public function test_upload_error_ini_size(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 0,
            'tmp_name' => '',
            'error' => UPLOAD_ERR_INI_SIZE
        ];

        $result = $this->validate_upload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds', $result['message']);
    }

    /**
     * Test partial upload error
     */
    public function test_upload_error_partial(): void {
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'tmp_name' => '/tmp/phpupload',
            'error' => UPLOAD_ERR_PARTIAL
        ];

        $result = $this->validate_upload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('partially uploaded', $result['message']);
    }

    /**
     * Test no file uploaded error
     */
    public function test_upload_error_no_file(): void {
        $file = [
            'name' => '',
            'type' => '',
            'size' => 0,
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE
        ];

        $result = $this->validate_upload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('No file', $result['message']);
    }

    /**
     * Test various allowed MIME types
     */
    public function test_allowed_mime_types(): void {
        $allowed_files = [
            ['name' => 'image.jpg', 'type' => 'image/jpeg'],
            ['name' => 'image.png', 'type' => 'image/png'],
            ['name' => 'document.pdf', 'type' => 'application/pdf'],
            ['name' => 'archive.zip', 'type' => 'application/zip'],
        ];

        foreach ($allowed_files as $file_data) {
            $file = array_merge([
                'size' => 1024,
                'tmp_name' => '/tmp/phpupload',
                'error' => UPLOAD_ERR_OK
            ], $file_data);

            $result = $this->validate_upload($file);

            $this->assertTrue(
                $result['valid'],
                "File {$file_data['name']} should be allowed but was rejected"
            );
        }
    }

    /**
     * Test rate limiting for uploads
     */
    public function test_rate_limiting(): void {
        // Should succeed first time
        $result = $this->check_rate_limit('upload', 3, 3600);
        $this->assertTrue($result);

        // Should succeed second time
        $result = $this->check_rate_limit('upload', 3, 3600);
        $this->assertTrue($result);

        // Should succeed third time
        $result = $this->check_rate_limit('upload', 3, 3600);
        $this->assertTrue($result);

        // Should fail fourth time (exceeded limit of 3)
        $result = $this->check_rate_limit('upload', 3, 3600);
        $this->assertFalse($result);
    }

    /**
     * Test rate limit reset after expiration
     */
    public function test_rate_limit_expiration(): void {
        global $_test_transients;

        // Set rate limit with expired time
        $key = 'cfr2wc_rate_upload_1';
        $_test_transients[$key] = [
            'value' => 5,
            'expiration' => time() - 100 // Expired 100 seconds ago
        ];

        // Should succeed because transient expired
        $result = $this->check_rate_limit('upload', 3, 3600);
        $this->assertTrue($result);

        // New transient should be created with count of 1
        $this->assertEquals(1, get_transient($key));
    }

    /**
     * Helper method to validate upload (mimics private method from product integration class)
     */
    private function validate_upload(array $file): array {
        // Check for upload errors
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => $this->get_upload_error_message($file['error'])
            ];
        }

        // Validate file type
        $allowed_types = get_allowed_mime_types();
        $file_type = wp_check_filetype($file['name'], $allowed_types);

        if (!$file_type['type']) {
            return [
                'valid' => false,
                'message' => 'File type not allowed'
            ];
        }

        // Validate file size (default 100MB)
        $max_size = 100 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return [
                'valid' => false,
                'message' => sprintf('File too large. Maximum size: %s', size_format($max_size))
            ];
        }

        return ['valid' => true];
    }

    /**
     * Helper method to check rate limit (mimics private method from product integration class)
     */
    private function check_rate_limit(string $operation, int $limit, int $window): bool {
        $key = 'cfr2wc_rate_' . $operation . '_' . get_current_user_id();
        $current = get_transient($key);

        if ($current === false) {
            set_transient($key, 1, $window);
            return true;
        }

        if ($current >= $limit) {
            return false;
        }

        set_transient($key, $current + 1, $window);
        return true;
    }

    /**
     * Helper method to get upload error messages
     */
    private function get_upload_error_message(int $error_code): string {
        return match ($error_code) {
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server upload_max_filesize setting',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension',
            default => 'Unknown upload error'
        };
    }
}
