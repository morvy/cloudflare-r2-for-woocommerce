<?php
/**
 * Tests for CFR2WC_Encryption class
 *
 * @package CloudflareR2WC
 */

use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        // Reset globals before each test
        global $_test_options;
        $_test_options = [];
    }

    /**
     * Test basic encryption and decryption
     */
    public function test_encrypt_decrypt_basic(): void {
        $original = 'test-secret-value';

        $encrypted = CFR2WC_Encryption::encrypt($original);

        // Encrypted value should be different from original
        $this->assertNotEquals($original, $encrypted);

        // Encrypted value should have version marker
        $this->assertStringStartsWith('v1::', $encrypted);

        // Decrypt should return original value
        $decrypted = CFR2WC_Encryption::decrypt($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    /**
     * Test encryption with special characters
     */
    public function test_encrypt_decrypt_special_characters(): void {
        $original = 'Test!@#$%^&*()_+-=[]{}|;:,.<>?/~`';

        $encrypted = CFR2WC_Encryption::encrypt($original);
        $decrypted = CFR2WC_Encryption::decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
    }

    /**
     * Test encryption with UTF-8 characters
     */
    public function test_encrypt_decrypt_utf8(): void {
        $original = 'Ťest údaje s háčkami a čiarkami';

        $encrypted = CFR2WC_Encryption::encrypt($original);
        $decrypted = CFR2WC_Encryption::decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
    }

    /**
     * Test encryption with empty string
     */
    public function test_encrypt_empty_string(): void {
        $encrypted = CFR2WC_Encryption::encrypt('');

        $this->assertEquals('', $encrypted);
    }

    /**
     * Test decryption with empty string
     */
    public function test_decrypt_empty_string(): void {
        $decrypted = CFR2WC_Encryption::decrypt('');

        $this->assertEquals('', $decrypted);
    }

    /**
     * Test decryption with plain text (backward compatibility)
     */
    public function test_decrypt_plain_text(): void {
        $plain = 'plain-text-value';

        $decrypted = CFR2WC_Encryption::decrypt($plain);

        // Should return original plain text
        $this->assertEquals($plain, $decrypted);
    }

    /**
     * Test is_encrypted method
     */
    public function test_is_encrypted(): void {
        $plain = 'plain-text';
        $encrypted = CFR2WC_Encryption::encrypt('secret');

        $this->assertFalse(CFR2WC_Encryption::is_encrypted($plain));
        $this->assertTrue(CFR2WC_Encryption::is_encrypted($encrypted));
    }

    /**
     * Test sanitize_credential with plain text
     */
    public function test_sanitize_credential_plain(): void {
        $value = 'plain-credential';

        $sanitized = CFR2WC_Encryption::sanitize_credential($value);

        // Should be encrypted
        $this->assertStringStartsWith('v1::', $sanitized);
    }

    /**
     * Test sanitize_credential with already encrypted value
     */
    public function test_sanitize_credential_already_encrypted(): void {
        $encrypted = CFR2WC_Encryption::encrypt('secret');

        $sanitized = CFR2WC_Encryption::sanitize_credential($encrypted);

        // Should not re-encrypt
        $this->assertEquals($encrypted, $sanitized);
    }

    /**
     * Test sanitize_credential with empty value
     */
    public function test_sanitize_credential_empty(): void {
        $sanitized = CFR2WC_Encryption::sanitize_credential('');

        $this->assertEquals('', $sanitized);
    }

    /**
     * Test encryption produces different ciphertext for same plaintext
     */
    public function test_encryption_randomness(): void {
        $original = 'test-value';

        $encrypted1 = CFR2WC_Encryption::encrypt($original);
        $encrypted2 = CFR2WC_Encryption::encrypt($original);

        // Different IVs should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to same value
        $this->assertEquals($original, CFR2WC_Encryption::decrypt($encrypted1));
        $this->assertEquals($original, CFR2WC_Encryption::decrypt($encrypted2));
    }

    /**
     * Test decryption with malformed data returns original
     */
    public function test_decrypt_malformed_data(): void {
        $malformed = 'v1::invalid-base64-!@#$%';

        $decrypted = CFR2WC_Encryption::decrypt($malformed);

        // Should return original when decryption fails
        $this->assertEquals($malformed, $decrypted);
    }

    /**
     * Test decryption with truncated data returns original
     */
    public function test_decrypt_truncated_data(): void {
        $encrypted = CFR2WC_Encryption::encrypt('test');

        // Truncate the encrypted data
        $truncated = 'v1::' . substr(substr($encrypted, 4), 0, 10);

        $decrypted = CFR2WC_Encryption::decrypt($truncated);

        // Should return original when data is malformed
        $this->assertEquals($truncated, $decrypted);
    }

    /**
     * Test encryption with whitespace-only string
     */
    public function test_encrypt_whitespace_only(): void {
        $whitespace = '   ';

        $encrypted = CFR2WC_Encryption::encrypt($whitespace);

        // Should return empty string
        $this->assertEquals('', $encrypted);
    }
}
