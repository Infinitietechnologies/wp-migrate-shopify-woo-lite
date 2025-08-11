<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Models\WMSW_Settings;
use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
use ShopifyWooImporter\Services\WMSW_Logger;

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

/**
 * Settings Handler
 * Handles AJAX requests for saving and retrieving plugin settings
 */
class WMSW_SettingsHandler
{
    public function __construct()
    {
        $this->initHooks();
    }

    private function initHooks()
    {
        add_action('wp_ajax_wmsw_save_settings', [$this, 'saveSettings']);
        add_action('wp_ajax_wmsw_get_setting', [$this, 'getSetting']);
        add_action('wp_ajax_wmsw_delete_setting', [$this, 'deleteSetting']);
    }

    /**
     * AJAX: Save a setting or multiple settings (single or bulk)
     */
    public function getSetting()
    {
        WMSW_SecurityHelper::verifyAdminRequest();
        $key = sanitize_text_field($_POST['key'] ?? '');
        $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : null;
        $isGlobal = !empty($_POST['is_global']) ? true : false;
        if (!$key) {
            wp_send_json_error(['message' => 'Missing setting key.']);
        }
        $setting = WMSW_Settings::get($key, $storeId, $isGlobal);
        if ($setting) {
            wp_send_json_success([
                'key' => $setting->getSettingKey(),
                'value' => $setting->getSettingValue(),
                'type' => $setting->getSettingType(),
                'is_global' => $setting->getIsGlobal(),
                'store_id' => $setting->getStoreId(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Setting not found.']);
        }
    }

    /**
     * AJAX: Delete a setting
     */
    public function deleteSetting()
    {
        WMSW_SecurityHelper::verifyAdminRequest();
        $key = sanitize_text_field($_POST['key'] ?? '');
        $storeId = isset($_POST['store_id']) ? intval($_POST['store_id']) : null;
        $isGlobal = !empty($_POST['is_global']) ? true : false;
        if (!$key) {
            wp_send_json_error(['message' => 'Missing setting key.']);
        }
        $result = WMSW_Settings::delete($key, $storeId, $isGlobal);
        if ($result) {
            wp_send_json_success(['message' => 'Setting deleted.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete setting.']);
        }
    }

    /**
     * AJAX: Save multiple settings (for the settings form)
     */
    public function saveSettings()
    {
        try {
            WMSW_SecurityHelper::verifyAdminRequest();

            // Get all form data
            $formData = $_POST;

            // Remove WordPress specific fields
            unset($formData['action'], $formData['nonce']);

            // Sanitize and prepare options array
            $options = [
                'import_batch_size' => absint($formData['import_batch_size'] ?? 50),
                'enable_auto_sync' => isset($formData['enable_auto_sync']),
                'sync_interval' => sanitize_text_field($formData['sync_interval'] ?? 'hourly'),
                'preserve_shopify_ids' => isset($formData['preserve_shopify_ids']),
                'log_retention_days' => absint($formData['log_retention_days'] ?? 30),
                'enable_debug_mode' => isset($formData['enable_debug_mode'])
            ];

            // Save to WordPress options
            update_option('wmsw_options', $options);

            // Save each setting to the custom table as global, track failures
            $failedSettings = [];
            global $wpdb;
            foreach ($options as $key => $value) {
                try {
                    if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger')) {
                        $logger = new \ShopifyWooImporter\Services\WMSW_Logger();
                        $logger->debug('Attempting to save setting', [
                            'key' => $key,
                            'value' => $value,
                            'global' => true
                        ]);
                    }
                    
                    // Try to get current setting first to check if it exists
                    $current = WMSW_Settings::get($key, null, true);
                    if ($current) {
                        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                            error_log("Current setting exists for key {$key}: " . print_r($current, true));
                        }
                    } else {
                        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                            error_log("No existing setting found for key {$key}");
                        }
                    }

                    $ok = WMSW_Settings::update($key, $value, null, true, 'string');
                    
                    if (!$ok) {
                        $failedSettings[] = [
                            'key' => $key,
                            'value' => $value,
                            'reason' => sprintf(
                                /* translators: %s: SQL error message */
                                __('Custom table update failed. Last SQL error: %s', 'wp-migrate-shopify-woo'),
                                $wpdb->last_error
                            )
                        ];
                        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                            error_log("Failed to save setting {$key}. SQL error: " . $wpdb->last_error);
                        }
                    }
                } catch (\Throwable $e) {
                    if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                        error_log("Exception while saving setting {$key}: " . $e->getMessage());
                    }
                    $failedSettings[] = [
                        'key' => $key,
                        'value' => $value,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            if (empty($failedSettings)) {
                wp_send_json_success([
                    'message' => __('All settings have been saved successfully and are now active.', 'wp-migrate-shopify-woo'),
                    'options' => $options
                ]);
            } else {
                $errorMsg = __('Some settings could not be saved to the database.', 'wp-migrate-shopify-woo');
                if (!empty($failedSettings)) {
                    $errorMsg .= ' ' . __('See details below.', 'wp-migrate-shopify-woo');
                }
                wp_send_json_error([
                    'message' => $errorMsg,
                    'failed_settings' => $failedSettings,
                    'options' => $options
                ]);
            }
        } catch (Throwable $e) {

            if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger')) {
                $logger = new \ShopifyWooImporter\Services\WMSW_Logger();
                $logger->error('Settings save error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'context' => isset($formData) ? $formData : []
                ]);
            }
            if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                error_log('Settings save error: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'An error occurred while saving settings.', 'error' => $e->getMessage()]);
        }
    }
}
