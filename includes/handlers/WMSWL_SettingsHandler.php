<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Models\WMSWL_Settings;
use ShopifyWooImporter\Helpers\WMSWL_SecurityHelper;
use ShopifyWooImporter\Services\WMSWL_Logger;

// WordPress AJAX and sanitization functions
use function add_action;
use function wp_send_json_success;
use function wp_send_json_error;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_email;
use function absint;
use function update_option;
use function get_option;
use function wp_verify_nonce;
use function wp_unslash;
use function __;

/**
 * Settings Handler
 * Handles AJAX requests for saving and retrieving plugin settings
 */
class WMSWL_SettingsHandler
{
    /** @var WMSWL_Logger */
    private $logger;
    
    /** @var array */
    private $defaultOptions = [
        'import_batch_size' => 50,
        'enable_auto_sync' => false,
        'sync_interval' => 'hourly',
        'preserve_shopify_ids' => false,
        'log_retention_days' => 30,
        'enable_debug_mode' => false
    ];

    public function __construct()
    {
        $this->logger = new WMSWL_Logger();
        $this->initHooks();
    }

    private function initHooks()
    {
        add_action('wp_ajax_wmsw_save_settings', [$this, 'saveSettings']);
        add_action('wp_ajax_wmsw_get_setting', [$this, 'getSetting']);
        add_action('wp_ajax_wmsw_delete_setting', [$this, 'deleteSetting']);
    }

    /**
     * Validate and sanitize AJAX request
     *
     * @param string $nonceKey
     * @return array|false Sanitized POST data or false if validation fails
     */
    private function validateAjaxRequest($nonceKey = 'swi-admin-nonce')
    {
        if (!isset($_POST['nonce']) || empty($_POST['nonce'])) {
            wp_send_json_error(['message' => __('Missing nonce', 'wp-migrate-shopify-woo-lite')]);
            return false;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $nonceKey)) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
            return false;
        }

        return $_POST;
    }

    /**
     * Sanitize setting value based on type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed Sanitized value
     */
    private function sanitizeSettingValue($value, $type = 'string')
    {
        switch ($type) {
            case 'integer':
                return absint($value);
            case 'boolean':
                return (bool) $value;
            case 'email':
                return sanitize_email($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'string':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * AJAX: Get a setting value
     */
    public function getSetting()
    {
        $postData = $this->validateAjaxRequest();
        if (!$postData) {
            return;
        }

        $key = sanitize_text_field(wp_unslash($postData['key'] ?? ''));
        $storeId = isset($postData['store_id']) ? absint(wp_unslash($postData['store_id'])) : null;
        $isGlobal = !empty(sanitize_text_field(wp_unslash($postData['is_global'] ?? '')));

        if (!$key) {
            wp_send_json_error(['message' => __('Missing setting key.', 'wp-migrate-shopify-woo-lite')]);
            return;
        }

        try {
            $setting = WMSWL_Settings::get($key, $storeId, $isGlobal);
            
            if ($setting) {
                wp_send_json_success([
                    'key' => $setting->getSettingKey(),
                    'value' => $setting->getSettingValue(),
                    'type' => $setting->getSettingType(),
                    'is_global' => $setting->getIsGlobal(),
                    'store_id' => $setting->getStoreId(),
                ]);
            } else {
                wp_send_json_error(['message' => __('Setting not found.', 'wp-migrate-shopify-woo-lite')]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error getting setting', [
                'key' => $key,
                'store_id' => $storeId,
                'is_global' => $isGlobal,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error(['message' => __('Error retrieving setting.', 'wp-migrate-shopify-woo-lite')]);
        }
    }

    /**
     * AJAX: Delete a setting
     */
    public function deleteSetting()
    {
        $postData = $this->validateAjaxRequest();
        if (!$postData) {
            return;
        }

        $key = sanitize_text_field(wp_unslash($postData['key'] ?? ''));
        $storeId = isset($postData['store_id']) ? absint(wp_unslash($postData['store_id'])) : null;
        $isGlobal = !empty(sanitize_text_field(wp_unslash($postData['is_global'] ?? '')));

        if (!$key) {
            wp_send_json_error(['message' => __('Missing setting key.', 'wp-migrate-shopify-woo-lite')]);
            return;
        }

        try {
            $result = WMSWL_Settings::delete($key, $storeId, $isGlobal);
            
            if ($result) {
                wp_send_json_success(['message' => __('Setting deleted successfully.', 'wp-migrate-shopify-woo-lite')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete setting.', 'wp-migrate-shopify-woo-lite')]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting setting', [
                'key' => $key,
                'store_id' => $storeId,
                'is_global' => $isGlobal,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error(['message' => __('Error deleting setting.', 'wp-migrate-shopify-woo-lite')]);
        }
    }

    /**
     * AJAX: Save multiple settings (for the settings form)
     */
    public function saveSettings()
    {
        $postData = $this->validateAjaxRequest();
        if (!$postData) {
            return;
        }

        try {
            // Remove WordPress specific fields
            unset($postData['action'], $postData['nonce']);

            // Sanitize and prepare options array
            $options = $this->prepareOptions($postData);

            // Save to WordPress options
            update_option('wmsw_options', $options);

            // Save each setting to the custom table as global
            $failedSettings = $this->saveSettingsToCustomTable($options);

            if (empty($failedSettings)) {
                wp_send_json_success([
                    'message' => __('All settings have been saved successfully and are now active.', 'wp-migrate-shopify-woo-lite'),
                    'options' => $options
                ]);
            } else {
                $errorMsg = __('Some settings could not be saved to the database.', 'wp-migrate-shopify-woo-lite');
                if (!empty($failedSettings)) {
                    $errorMsg .= ' ' . __('See details below.', 'wp-migrate-shopify-woo-lite');
                }
                wp_send_json_error([
                    'message' => $errorMsg,
                    'failed_settings' => $failedSettings,
                    'options' => $options
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Settings save error', [
                'exception' => $e->getMessage(),
                'context' => $postData ?? []
            ]);
            wp_send_json_error([
                'message' => __('An error occurred while saving settings.', 'wp-migrate-shopify-woo-lite'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prepare and sanitize options from form data
     *
     * @param array $formData
     * @return array
     */
    private function prepareOptions(array $formData): array
    {
        $options = [];
        
        foreach ($this->defaultOptions as $key => $defaultValue) {
            $value = $formData[$key] ?? $defaultValue;
            
            switch ($key) {
                case 'import_batch_size':
                case 'log_retention_days':
                    $options[$key] = absint($value);
                    break;
                case 'enable_auto_sync':
                case 'preserve_shopify_ids':
                case 'enable_debug_mode':
                    $options[$key] = isset($formData[$key]);
                    break;
                case 'sync_interval':
                    $options[$key] = sanitize_text_field($value);
                    break;
                default:
                    $options[$key] = $this->sanitizeSettingValue($value);
            }
        }
        
        return $options;
    }

    /**
     * Save settings to custom table
     *
     * @param array $options
     * @return array Array of failed settings
     */
    private function saveSettingsToCustomTable(array $options): array
    {
        $failedSettings = [];
        global $wpdb;

        foreach ($options as $key => $value) {
            try {
                $this->logger->debug('Saving setting to custom table', [
                    'key' => $key,
                    'value' => $value,
                    'global' => true
                ]);

                $success = WMSWL_Settings::update($key, $value, null, true, 'string');
                
                if (!$success) {
                    $failedSettings[] = [
                        'key' => $key,
                        'value' => $value,
                        'reason' => sprintf(
                            /* translators: %s: SQL error message */
                            __('Custom table update failed. Last SQL error: %s', 'wp-migrate-shopify-woo-lite'),
                            $wpdb->last_error
                        )
                    ];
                    
                    $this->logger->error('Failed to save setting to custom table', [
                        'key' => $key,
                        'value' => $value,
                        'sql_error' => $wpdb->last_error
                    ]);
                }
            } catch (\Throwable $e) {
                $failedSettings[] = [
                    'key' => $key,
                    'value' => $value,
                    'reason' => $e->getMessage()
                ];
                
                $this->logger->error('Exception while saving setting to custom table', [
                    'key' => $key,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $failedSettings;
    }
}
