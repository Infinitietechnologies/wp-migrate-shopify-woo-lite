<?php

namespace ShopifyWooImporter\Helpers;

/**
 * Encryption Helper for securing sensitive data
 * 
 * This class provides methods to encrypt and decrypt sensitive data such as
 * Shopify access tokens before storing them in the database.
 */
class WMSWL_EncryptionHelper
{
    /**
     * Encryption method to use
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Salt prefix for key derivation
     */
    const SALT_PREFIX = 'wmsw_encrypt_';

    /**
     * Get the encryption key
     *
     * Uses WordPress AUTH_KEY or generates a secure key if not available
     *
     * @return string The encryption key
     */
    private static function get_encryption_key()
    {
        // Try to use WordPress AUTH_KEY first
        if (defined('AUTH_KEY') && !empty(AUTH_KEY) && AUTH_KEY !== 'put your unique phrase here') {
            return hash('sha256', AUTH_KEY . self::SALT_PREFIX);
        }

        // Fallback: get or generate a plugin-specific key
        $stored_key = get_option('wmsw_encryption_key');
        if (empty($stored_key)) {
            // Generate a new random key
            $stored_key = wp_generate_password(64, true, true);
            update_option('wmsw_encryption_key', $stored_key, false); // Don't autoload
        }

        return hash('sha256', $stored_key . self::SALT_PREFIX);
    }

    /**
     * Encrypt sensitive data
     * 
     * @param string $data The data to encrypt
     * @return string|false The encrypted data (base64 encoded) or false on failure
     */
    public static function encrypt($data)
    {
        if (empty($data)) {
            return $data;
        }

        // Check if OpenSSL is available
        if (!function_exists('openssl_encrypt')) {
            if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                $logger->warning('SWI Encryption: OpenSSL not available, storing data unencrypted');
            }
            return $data;
        }

        try {
            $key = self::get_encryption_key();

            // Generate a random IV
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = openssl_random_pseudo_bytes($iv_length);

            // Encrypt the data
            $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);

            if ($encrypted === false) {
                if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                    $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                    $logger->error('SWI Encryption: Failed to encrypt data');
                }
                return false;
            }

            // Combine IV and encrypted data, then base64 encode
            $result = base64_encode($iv . $encrypted);

            return $result;
        } catch (Exception $e) {
            if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                $logger->error('SWI Encryption Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Decrypt sensitive data
     * 
     * @param string $encrypted_data The encrypted data (base64 encoded)
     * @return string|false The decrypted data or false on failure
     */
    public static function decrypt($encrypted_data)
    {
        if (empty($encrypted_data)) {
            return $encrypted_data;
        }

        // Check if OpenSSL is available
        if (!function_exists('openssl_decrypt')) {
            if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                $logger->warning('SWI Decryption: OpenSSL not available, returning data as-is');
            }
            return $encrypted_data;
        }

        // Check if data looks encrypted (base64 encoded)
        if (!self::is_encrypted($encrypted_data)) {
            // Data doesn't appear to be encrypted, return as-is for backward compatibility
            return $encrypted_data;
        }

        try {
            $key = self::get_encryption_key();

            // Decode from base64
            $data = base64_decode($encrypted_data);
            if ($data === false) {
                if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                    $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                    $logger->error('SWI Decryption: Failed to decode base64 data');
                }
                return false;
            }

            // Extract IV and encrypted data
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            // Decrypt the data
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);

            if ($decrypted === false) {
                if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                    $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                    $logger->error('SWI Decryption: Failed to decrypt data');
                }
                return false;
            }

            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if data appears to be encrypted by this class
     * 
     * @param string $data The data to check
     * @return bool True if data appears encrypted
     */
    public static function is_encrypted($data)
    {
        if (empty($data)) {
            return false;
        }

        // Check if it's valid base64 and has minimum length for IV + some encrypted data
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        return strlen($decoded) > $iv_length;
    }

    /**
     * Securely hash a value for comparison (e.g., for duplicate checking)
     * 
     * @param string $value The value to hash
     * @return string The hashed value
     */
    public static function secure_hash($value)
    {
        if (empty($value)) {
            return '';
        }

        return hash_hmac('sha256', $value, self::get_encryption_key());
    }

    /**
     * Migrate existing plaintext access tokens to encrypted format
     * 
     * This method should be called during plugin update to encrypt existing tokens
     * 
     * @return array Migration results
     */
    public static function migrate_existing_tokens()
    {
        global $wpdb;

        $table = $wpdb->prefix . WMSW_STORES_TABLE;
        $migrated = 0;
        $failed = 0;

        try {
            // Get all stores with potentially unencrypted tokens
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $stores = $wpdb->get_results(
                "SELECT id, access_token FROM " . esc_sql($wpdb->prefix . WMSW_STORES_TABLE) . " WHERE access_token IS NOT NULL AND access_token != ''",
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ($stores as $store) {
                $token = $store['access_token'];

                // Skip if already encrypted
                if (self::is_encrypted($token)) {
                    continue;
                }

                // Encrypt the token
                $encrypted_token = self::encrypt($token);
                if ($encrypted_token !== false && $encrypted_token !== $token) {
                    // Update the database with encrypted token
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $result = $wpdb->update(
                        $table,
                        ['access_token' => $encrypted_token],
                        ['id' => $store['id']],
                        ['%s'],
                        ['%d']
                    );
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

                    if ($result !== false) {
                        $migrated++;
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'migrated' => $migrated,
                'failed' => $failed
            ];
        }

        return [
            'success' => true,
            'migrated' => $migrated,
            'failed' => $failed,
            'message' => sprintf(
                'Migration completed: %d tokens encrypted, %d failed',
                $migrated,
                $failed
            )
        ];
    }

    /**
     * Validate that encryption/decryption is working properly
     * 
     * @return bool True if encryption is working
     */
    public static function test_encryption()
    {
        $test_data = 'test_access_token_' . wp_generate_password(32, false);

        $encrypted = self::encrypt($test_data);
        if ($encrypted === false || $encrypted === $test_data) {
            return false;
        }

        $decrypted = self::decrypt($encrypted);

        return $decrypted === $test_data;
    }
}
