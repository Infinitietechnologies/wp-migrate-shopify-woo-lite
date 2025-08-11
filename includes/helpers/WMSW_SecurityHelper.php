<?php

/**
 * Security Helper Class
 * 
 * This class provides helper methods for security-related operations
 * like nonce verification and permission checks in AJAX requests.
 * 
 * Usage:
 * 1. Import the class: use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
 * 2. Call the method: WMSW_SecurityHelper::verifyAdminRequest();
 * 
 * This will check both nonce and admin capabilities in one go and
 * automatically return wp_send_json_error() if verification fails.
 * 
 * @package ShopifyWooImporter
 */

namespace ShopifyWooImporter\Helpers;

// WordPress functions are in global namespace
use function wp_verify_nonce;
use function wp_unslash;
use function sanitize_text_field;
use function wp_send_json_error;
use function esc_html__;
use function current_user_can;
use function sanitize_email;
use function sanitize_textarea_field;
use function absint;
use function intval;
use function floatval;
use function is_email;
use function esc_url_raw;
use function wp_die;

/**
 * Security Helper Functions
 *
 * Provides helper functions for security operations like nonce verification
 */
class WMSW_SecurityHelper
{
    /**
     * Verifies the admin nonce for AJAX requests
     *
     * @param string $nonceField The name of the POST field containing the nonce (default: 'nonce')
     * @param string $nonceName The name of the nonce (default: 'swi-admin-nonce')
     * @param boolean $returnResult Whether to return the result instead of sending JSON error (default: false)
     * @return boolean|void Returns boolean if $returnResult is true, otherwise may exit script by sending JSON error
     */
    public static function verifyAdminNonce($nonceField = 'nonce', $nonceName = 'swi-admin-nonce', $returnResult = false)
    {
        $verified = isset($_POST[$nonceField]) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceField])), $nonceName);

        if (!$verified && !$returnResult) {
            wp_send_json_error([
                'message'    => esc_html__('Security check failed', 'wp-migrate-shopify-woo'),
                'error_type' => 'security',
            ]);
        }

        return $verified;
    }

    /**
     * Verifies the admin capabilities
     *
     * @param string $capability The capability to check (default: 'manage_options')
     * @param boolean $returnResult Whether to return the result instead of sending JSON error (default: false)
     * @return boolean|void Returns boolean if $returnResult is true, otherwise may exit script by sending JSON error
     */
    public static function verifyAdminCapability($capability = 'manage_options', $returnResult = false)
    {
        $hasCapability = current_user_can($capability);

        if (!$hasCapability && !$returnResult) {
            wp_send_json_error([
                'message'    => esc_html__('You do not have permission to perform this action', 'wp-migrate-shopify-woo'),
                'error_type' => 'permission',
            ]);
        }

        return $hasCapability;
    }

    /**
     * Verifies both nonce and admin capability in one go
     *
     * @param string $nonceField The name of the POST field containing the nonce (default: 'nonce')
     * @param string $nonceName The name of the nonce (default: 'swi-admin-nonce')
     * @param string $capability The capability to check (default: 'manage_options')
     * @return boolean True if verification passes, otherwise exits script by sending JSON error
     */
    public static function verifyAdminRequest($nonceField = 'nonce', $nonceName = 'swi-admin-nonce', $capability = 'manage_options')
    {
        self::verifyAdminNonce($nonceField, $nonceName);
        self::verifyAdminCapability($capability);

        return true;
    }

    /**
     * Simple nonce verification method
     * 
     * @param string $nonceName The name of the nonce to verify
     * @param string $nonceField The name of the POST field containing the nonce (default: 'nonce')
     * @return boolean True if nonce is valid, false otherwise
     */
    public static function verifyNonce($nonceName, $nonceField = 'nonce')
    {
        return isset($_POST[$nonceField]) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonceField])), $nonceName);
    }

    /**
     * Verify admin request for view pages (non-AJAX)
     * 
     * @param string $capability The capability to check (default: 'manage_options')
     * @return void Dies if verification fails
     */
    public static function verifyAdminPage($capability = 'manage_options')
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-migrate-shopify-woo'));
        }
    }

    /**
     * Sanitize store ID from request
     * 
     * @param string $field The field name to get store ID from (default: 'store_id')
     * @return int Sanitized store ID
     */
    public static function sanitizeStoreId($field = 'store_id')
    {
        return isset($_POST[$field]) ? intval($_POST[$field]) : 0;
    }

    /**
     * Validate required fields in POST request
     * 
     * @param array $requiredFields Array of required field names
     * @return void Sends JSON error if validation fails
     */
    public static function validateRequiredFields($requiredFields)
    {
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error([
                    'message' => sprintf(
                        /* translators: %s: field name */
                        __('Required field "%s" is missing', 'wp-migrate-shopify-woo'), 
                        $field
                    ),
                    'error_type' => 'validation',
                    'field' => $field
                ]);
            }
        }
    }

    /**
     * Get sanitized POST data
     * 
     * @param string $field The field name
     * @param mixed $default Default value if field is not set
     * @param string $sanitizer The sanitization function to use
     * @return mixed Sanitized value
     */
    public static function getPostData($field, $default = '', $sanitizer = 'sanitize_text_field')
    {
        if (!isset($_POST[$field])) {
            return $default;
        }

        $value = wp_unslash($_POST[$field]);

        switch ($sanitizer) {
            case 'sanitize_email':
                return sanitize_email($value);
            case 'sanitize_textarea_field':
                return sanitize_textarea_field($value);
            case 'absint':
                return absint($value);
            case 'intval':
                return intval($value);
            case 'floatval':
                return floatval($value);
            case 'rest_sanitize_boolean':
                return rest_sanitize_boolean($value);
            case 'array':
                return is_array($value) ? array_map('sanitize_text_field', $value) : $default;
            case 'sanitize_text_field':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Validate and sanitize complex array inputs
     *
     * @param array $rules Array with fields as keys and sanitizers as values
     * @param array $data The data to be validated and sanitized
     * @return array Sanitized data or error messages
     */
    public static function validateAndSanitizeArray($rules, $data)
    {
        $sanitizedData = [];
        foreach ($rules as $field => $sanitizer) {
            if (!array_key_exists($field, $data)) {
                return ['error' => __('Missing field: ', 'wp-migrate-shopify-woo') . $field];
            }
            $sanitizedData[$field] = self::getPostData($data[$field], '', $sanitizer);
        }
        return $sanitizedData;
    }

    /**
     * Output escaping for different contexts
     */
    public static function escapeHtml($data) {
        return esc_html($data);
    }

    public static function escapeAttr($data) {
        return esc_attr($data);
    }

    public static function escapeJs($data) {
        return esc_js($data);
    }

    public static function escapeUrl($data) {
        return esc_url($data);
    }

    /**
     * Advanced validator methods
     */
    public static function validateEmail($email) {
        return is_email($email) ? $email : false;
    }

    public static function validateUrl($url) {
        return wp_http_validate_url($url) ? $url : false;
    }

    public static function validateNumericRange($value, $min, $max) {
        $num = floatval($value);
        return ($num >= $min && $num <= $max) ? $num : false;
    }

    public static function validateStringLength($string, $minLength, $maxLength) {
        $length = strlen($string);
        return ($length >= $minLength && $length <= $maxLength) ? $string : false;
    }

    public static function validateArrayValues($array, $allowedValues) {
        if (!is_array($array)) {
            return false;
        }
        foreach ($array as $value) {
            if (!in_array($value, $allowedValues, true)) {
                return false;
            }
        }
        return $array;
    }

    /**
     * Check if debug mode is enabled
     * Centralized debug mode checking
     * 
     * @return bool True if debug mode is enabled
     */
    public static function isDebugModeEnabled(): bool
    {
        // Use the Logger's static method if available
        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger')) {
            return \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled();
        }

        // Fallback to WordPress debug mode
        return (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Debug log message only when debug mode is enabled
     * 
     * @param string $message The message to log
     * @param string $prefix Optional prefix for the log message
     */
    public static function debugLog($message, $prefix = '[SWI]'): void
    {
        if (!self::isDebugModeEnabled()) {
            return;
        }

        if (is_array($message) || is_object($message)) {
            error_log($prefix . ' ' . json_encode($message, JSON_PRETTY_PRINT));
        } else {
            error_log($prefix . ' ' . $message);
        }
    }
}
