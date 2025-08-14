<?php

namespace ShopifyWooImporter\Backend;

use ShopifyWooImporter\Core\WMSWL_ShopifyClient;
use ShopifyWooImporter\Handlers\WMSWL_CustomerHandler;
use ShopifyWooImporter\Handlers\WMSWL_OrderHandler;
use ShopifyWooImporter\Handlers\WMSWL_SettingsHandler;
use ShopifyWooImporter\Handlers\WMSWL_StoreHandler;
use ShopifyWooImporter\Handlers\WMSWL_LogHandler;
use ShopifyWooImporter\Handlers\WMSWL_ProductHandler;
use ShopifyWooImporter\Models\WMSWL_ShopifyStore;
use ShopifyWooImporter\Helpers\WMSWL_SecurityHelper;
use ShopifyWooImporter\Models\WMSWL_Task;

/**
 * Backend/Admin Area Handler
 */
class WMSWL_Backend
{

    public function __construct()
    {
        $this->init_hooks();
    }
    private function init_hooks()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_WMSW_verify_connection', [$this, 'verify_shopify_connection']);
        add_action('wp_ajax_WMSW_get_store', [$this, 'get_store']);
        add_action('wp_ajax_WMSW_test_connection', [$this, 'ajax_test_shopify_connection']);
        add_action('wp_ajax_WMSW_set_default_store', [$this, 'ajax_set_default_store']);
        add_action('wp_ajax_WMSW_get_stores', [$this, 'ajax_get_stores']);
        add_action('wp_ajax_WMSW_fetch_shopify_categories', [$this, 'ajax_fetch_shopify_categories']);
        add_action('wp_ajax_WMSW_import_shopify_categories', [$this, 'ajax_import_shopify_categories']);



        // Add backward compatible hook for task progress (redirect to import progress)
        add_action('wp_ajax_WMSW_get_task_progress', [$this, 'redirect_to_import_progress']);

        // Logs AJAX handlers - removed filtered logs AJAX since we're using refresh-based filtering

        // Load handlers

        $this->getHandler('stores');
        $this->getHandler('products');
        $this->getHandler('customers');
        $this->getHandler('orders');
        $this->getHandler('settings');
        $this->getHandler('logs');
    }

    /**
     * Create admin menu items
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Shopify Importer', 'wp-migrate-shopify-woo-lite'),
            __('Shopify Importer', 'wp-migrate-shopify-woo-lite'),
            'manage_options',
            'wp-migrate-shopify-woo-lite',
            [$this, 'render_dashboard_page'],
            'dashicons-cart',
            58
        );

        add_submenu_page(
            'wp-migrate-shopify-woo-lite',
            __('Dashboard', 'wp-migrate-shopify-woo-lite'),
            __('Dashboard', 'wp-migrate-shopify-woo-lite'),
            'manage_options',
            'wp-migrate-shopify-woo-lite',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'wp-migrate-shopify-woo-lite',
            __('Shopify Stores', 'wp-migrate-shopify-woo-lite'),
            __('Shopify Stores', 'wp-migrate-shopify-woo-lite'),
            'manage_options',
            'wp-migrate-shopify-woo-lite-stores',
            [$this, 'render_stores_page']
        );

        add_submenu_page(
            'wp-migrate-shopify-woo-lite',
            __('Import Logs', 'wp-migrate-shopify-woo-lite'),
            __('Import Logs', 'wp-migrate-shopify-woo-lite'),
            'manage_options',
            'wp-migrate-shopify-woo-lite-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'wp-migrate-shopify-woo-lite',
            __('Settings', 'wp-migrate-shopify-woo-lite'),
            __('Settings', 'wp-migrate-shopify-woo-lite'),
            'manage_options',
            'wp-migrate-shopify-woo-lite-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue CSS styles for admin
     */
    public function enqueue_styles($hook)
    {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            'shopify-woo-importer-admin',
            WMSW_PLUGIN_URL . 'backend/assets/css/backend.css',
            [],
            WMSW_VERSION
        );

        wp_enqueue_style(
            'shopify-woo-importer-confirm-box',
            WMSW_PLUGIN_URL . 'backend/assets/vendors/notiflix.css',
            [],
            WMSW_VERSION
        );

        wp_enqueue_style(
            'shopify-woo-importer-form-grid',
            WMSW_PLUGIN_URL . 'backend/assets/css/form-grid.css',
            ['shopify-woo-importer-admin'],
            WMSW_VERSION
        );

        wp_enqueue_style(
            'shopify-woo-importer-logs',
            WMSW_PLUGIN_URL . 'backend/assets/css/logs.css',
            ['shopify-woo-importer-admin'],
            WMSW_VERSION
        );
    }

    /**
     * Enqueue JS scripts for admin
     */
    public function enqueue_scripts($hook)
    {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        // Enqueue Notiflix vendor script
        wp_enqueue_script(
            'notiflix',
            WMSW_PLUGIN_URL . 'backend/assets/vendors/notiflix.js',
            array('jquery'),
            WMSW_VERSION,
            true
        );
        // Enqueue confirm box utility, depends on Notiflix
        wp_enqueue_script(
            'shopify-woo-importer-confirm-box',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/confirm-box.js',
            array('jquery', 'notiflix'),
            WMSW_VERSION,
            true
        );
        // Enqueue backend.js, depends on confirm box
        wp_enqueue_script(
            'shopify-woo-importer-admin',
            WMSW_PLUGIN_URL . 'backend/assets/js/backend.js',
            array('jquery', 'shopify-woo-importer-confirm-box'),
            WMSW_VERSION,
            true
        );

        wp_enqueue_script(
            'shopify-woo-importer-settings',
            WMSW_PLUGIN_URL . 'backend/assets/js/settings.js',
            array('jquery', 'shopify-woo-importer-confirm-box'),
            WMSW_VERSION,
            true
        );

        // Enqueue product importer script
        wp_enqueue_script(
            'shopify-woo-importer-skeleton-loader',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/skeleton-loader.js',
            ['jquery'],
            WMSW_VERSION,
            true
        );

        wp_enqueue_script(
            'shopify-woo-importer-product',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/product-importer.js',
            ['jquery', 'shopify-woo-importer-admin', 'shopify-woo-importer-skeleton-loader', 'shopify-woo-importer-category'],
            WMSW_VERSION,
            true
        );

        // Customer Importer
        wp_enqueue_script(
            'shopify-woo-importer-customer',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/customer-importer.js',
            ['jquery', 'shopify-woo-importer-admin', 'shopify-woo-importer-skeleton-loader'],
            WMSW_VERSION,
            true
        );

        // Order Importer
        wp_enqueue_script(
            'shopify-woo-importer-order',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/order-importer.js',
            ['jquery', 'shopify-woo-importer-admin', 'shopify-woo-importer-skeleton-loader'],
            WMSW_VERSION,
            true
        );

        // Category Importer
        wp_enqueue_script(
            'shopify-woo-importer-category',
            WMSW_PLUGIN_URL . 'backend/assets/js/components/category-importer.js',
            ['jquery', 'shopify-woo-importer-admin'],
            WMSW_VERSION,
            true
        );



        // Coupon Importer
        wp_enqueue_script(
            'shopify-woo-importer-logs',
            WMSW_PLUGIN_URL . 'backend/assets/js/logs.js',
            ['jquery', 'shopify-woo-importer-admin', 'shopify-woo-importer-skeleton-loader'],
            WMSW_VERSION,
            true
        );

        wp_localize_script(
            'shopify-woo-importer-admin',
            'wmsw_ajax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('swi-admin-nonce'),
                'wmsw_logs' => wp_create_nonce('swi-logs-nonce'),
                'strings' => [
                    'connecting' => \esc_attr__('Connecting...', 'wp-migrate-shopify-woo-lite'),
                    'deactivating' => \esc_attr__('Deactivating...', 'wp-migrate-shopify-woo-lite'),
                    'activating' => \esc_attr__('Activating...', 'wp-migrate-shopify-woo-lite'),
                    'deleting' => \esc_attr__('Deleting...', 'wp-migrate-shopify-woo-lite'),
                    'saving' => \esc_attr__('Saving store...', 'wp-migrate-shopify-woo-lite'),
                    'loading' => \esc_attr__('Loading...', 'wp-migrate-shopify-woo-lite'),
                    'loading_stores' => \esc_attr__('Loading stores...', 'wp-migrate-shopify-woo-lite'),
                    'testing_connection' => \esc_attr__('Testing connection...', 'wp-migrate-shopify-woo-lite'),
                    'success' => \esc_attr__('Success!', 'wp-migrate-shopify-woo-lite'),
                    'error' => \esc_attr__('Error occurred', 'wp-migrate-shopify-woo-lite'),
                    'invalid_domain' => \esc_attr__('Invalid shop domain format. Please enter a valid Shopify store domain.', 'wp-migrate-shopify-woo-lite'),
                    'invalid_token' => \esc_attr__('Invalid access token format. Please enter a valid Shopify access token.', 'wp-migrate-shopify-woo-lite'),
                    'invalid_api_version' => \esc_attr__('Invalid API version format. Please enter a valid Shopify API version.', 'wp-migrate-shopify-woo-lite'),
                    'deactivate_store' => \esc_attr__('Are you sure you want to deactivate this store? You can reactivate it later.', 'wp-migrate-shopify-woo-lite'),
                    'activate_store' => \esc_attr__('Are you sure you want to activate this store? It will start syncing data.', 'wp-migrate-shopify-woo-lite'),
                    'delete_store' => \esc_attr__('Are you sure you want to delete this store? This action cannot be undone.', 'wp-migrate-shopify-woo-lite'),
                    'store_saved' => \esc_attr__('Store saved successfully!', 'wp-migrate-shopify-woo-lite'),
                    'store_deleted' => \esc_attr__('Store deleted successfully!', 'wp-migrate-shopify-woo-lite'),
                    // Product importer specific strings
                    'selectStoreFirst' => \esc_attr__('Please select a store first.', 'wp-migrate-shopify-woo-lite'),
                    'importFailed' => \esc_attr__('Failed to start import.', 'wp-migrate-shopify-woo-lite'),
                    'serverError' => \esc_attr__('Server error. Please try again.', 'wp-migrate-shopify-woo-lite'),
                    'importScheduled' => \esc_attr__('Import scheduled successfully!', 'wp-migrate-shopify-woo-lite'),
                    'scheduleImportFailed' => \esc_attr__('Failed to schedule import.', 'wp-migrate-shopify-woo-lite'),
                    'previewFailed' => \esc_attr__('Failed to preview products.', 'wp-migrate-shopify-woo-lite'),
                    'highThreadWarning' => \esc_attr__('High thread counts may cause server performance issues or timeouts.', 'wp-migrate-shopify-woo-lite'),
                    'schedulingSuccess' => \esc_attr__('Import has been scheduled successfully.', 'wp-migrate-shopify-woo-lite'),
                    'schedulingFailed' => \esc_attr__('Failed to schedule the import.', 'wp-migrate-shopify-woo-lite'),
                    'emailValidationError' => \esc_attr__('Please enter a valid email address.', 'wp-migrate-shopify-woo-lite'),
                    'processingOptionsTitle' => \esc_attr__('Processing Options', 'wp-migrate-shopify-woo-lite'),
                    'schedulingOptionsTitle' => \esc_attr__('Scheduling Options', 'wp-migrate-shopify-woo-lite'),
                    'notificationOptionsTitle' => \esc_attr__('Notification Options', 'wp-migrate-shopify-woo-lite'),
                    'showAdvancedFilters' => \esc_attr__('Show Advanced Filters', 'wp-migrate-shopify-woo-lite'),
                    'hideAdvancedFilters' => \esc_attr__('Hide Advanced Filters', 'wp-migrate-shopify-woo-lite'),

                    // Add any missing keys used in WMSW_ajax.strings.* in JS
                    'importComplete' => \esc_attr__('Import completed successfully!', 'wp-migrate-shopify-woo-lite'),
                    'customerPreviewTitle' => \esc_attr__('Customer Preview', 'wp-migrate-shopify-woo-lite'),
                    'noCustomersFound' => \esc_attr__('No customers found matching your criteria.', 'wp-migrate-shopify-woo-lite'),
                    'please_fill_shop_domain_and_token' => \esc_attr__('Please fill in Shop Domain and Access Token first.', 'wp-migrate-shopify-woo-lite'),
                    'validation_error' => \esc_attr__('Validation Error', 'wp-migrate-shopify-woo-lite'),
                    'success_title' => \esc_attr__('Success!', 'wp-migrate-shopify-woo-lite'),
                    'connection_failed' => \esc_attr__('Connection Failed', 'wp-migrate-shopify-woo-lite'),
                    'missing_store_id_or_nonce' => \esc_attr__('Missing store ID or security token.', 'wp-migrate-shopify-woo-lite'),
                    'confirm_delete_store' => \esc_attr__('Are you sure you want to delete this store? This action cannot be undone.', 'wp-migrate-shopify-woo-lite'),
                    'confirm_deactivate_store' => \esc_attr__('Are you sure you want to deactivate this store?', 'wp-migrate-shopify-woo-lite'),
                    'delete_store_title' => \esc_attr__('Delete Store', 'wp-migrate-shopify-woo-lite'),
                    'deactivate_store_title' => \esc_attr__('Deactivate Store', 'wp-migrate-shopify-woo-lite'),
                    'delete' => \esc_attr__('Delete', 'wp-migrate-shopify-woo-lite'),
                    'deactivate' => \esc_attr__('Deactivate', 'wp-migrate-shopify-woo-lite'),
                    'cancel' => \esc_attr__('Cancel', 'wp-migrate-shopify-woo-lite'),
                    'action_successful' => \esc_attr__('Action successful.', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_process_request' => \esc_attr__('Failed to process request.', 'wp-migrate-shopify-woo-lite'),
                    'ajax_request_failed' => \esc_attr__('AJAX request failed.', 'wp-migrate-shopify-woo-lite'),
                    'confirm_set_default_store' => \esc_attr__('Are you sure you want to set this store as default?', 'wp-migrate-shopify-woo-lite'),
                    'processing' => \esc_attr__('Processing...', 'wp-migrate-shopify-woo-lite'),
                    'set_default_store_title' => \esc_attr__('Set Default Store', 'wp-migrate-shopify-woo-lite'),
                    'set_default' => \esc_attr__('Set as Default', 'wp-migrate-shopify-woo-lite'),
                    'store_set_default_success' => \esc_attr__('Store set as default successfully.', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_set_store_default' => \esc_attr__('Failed to set store as default.', 'wp-migrate-shopify-woo-lite'),
                    'copying' => \esc_attr__('Copying...', 'wp-migrate-shopify-woo-lite'),
                    'exporting' => \esc_attr__('Exporting...', 'wp-migrate-shopify-woo-lite'),

                    // Customer importer specific strings
                    'store_copied_success' => \esc_attr__('Store copied successfully. The store domain has been modified with a unique suffix to avoid conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'store_copied_title' => \esc_attr__('Store Copied', 'wp-migrate-shopify-woo-lite'),
                    'store_copied_error' => \esc_attr__('Failed to copy store.', 'wp-migrate-shopify-woo-lite'),
                    'please_select_store_first' => \esc_attr__('Please select a store first.', 'wp-migrate-shopify-woo-lite'),

                    // Additional strings for hardcoded text in JavaScript
                    'import_completed_successfully' => \esc_attr__('Import completed successfully!', 'wp-migrate-shopify-woo-lite'),
                    'error_loading_store' => \esc_attr__('Error loading store:', 'wp-migrate-shopify-woo-lite'),
                    'error_prefix' => \esc_attr__('Error:', 'wp-migrate-shopify-woo-lite'),
                    'sync_started_successfully' => \esc_attr__('Sync started successfully!', 'wp-migrate-shopify-woo-lite'),
                    'confirm_sync_store' => \esc_attr__('Are you sure you want to sync this store?', 'wp-migrate-shopify-woo-lite'),
                    'confirm_toggle_store_status' => \esc_attr__('Are you sure you want to ', 'wp-migrate-shopify-woo-lite'),
                    'confirm_toggle_store_status_2' => \esc_attr__(' this store?', 'wp-migrate-shopify-woo-lite'),
                    'save_store' => \esc_attr__('Save Store', 'wp-migrate-shopify-woo-lite'),
                    'add_new_store' => \esc_attr__('Add New Store', 'wp-migrate-shopify-woo-lite'),
                    'edit_store' => \esc_attr__('Edit Store', 'wp-migrate-shopify-woo-lite'),
                    'syncing' => \esc_attr__('Syncing...', 'wp-migrate-shopify-woo-lite'),
                    'sync_now' => \esc_attr__('Sync Now', 'wp-migrate-shopify-woo-lite'),
                    'starting_import' => \esc_attr__('Starting import...', 'wp-migrate-shopify-woo-lite'),
                    'initializing_import' => \esc_attr__('Initializing import...', 'wp-migrate-shopify-woo-lite'),
                    'feature_coming_soon' => \esc_attr__('Feature coming soon...', 'wp-migrate-shopify-woo-lite'),
                    'no_tags' => \esc_attr__('No tags', 'wp-migrate-shopify-woo-lite'),
                    'no_products_found' => \esc_attr__('0 products found', 'wp-migrate-shopify-woo-lite'),
                    'product_preview_title' => \esc_attr__('Product Preview', 'wp-migrate-shopify-woo-lite'),
                    'products_found' => \esc_attr__('products found', 'wp-migrate-shopify-woo-lite'),
                    'product_found' => \esc_attr__('product found', 'wp-migrate-shopify-woo-lite'),
                    'no_products_found_criteria' => \esc_attr__('No products found based on your filter criteria.', 'wp-migrate-shopify-woo-lite'),
                    'unnamed_customer' => \esc_attr__('Unnamed Customer', 'wp-migrate-shopify-woo-lite'),
                    'disabled' => \esc_attr__('Disabled', 'wp-migrate-shopify-woo-lite'),
                    'active' => \esc_attr__('Active', 'wp-migrate-shopify-woo-lite'),
                    'email_label' => \esc_attr__('Email:', 'wp-migrate-shopify-woo-lite'),

                    // Additional strings for JavaScript files
                    'dismiss_notification' => \esc_attr__('Dismiss notification', 'wp-migrate-shopify-woo-lite'),
                    'dismiss_banner' => \esc_attr__('Dismiss banner', 'wp-migrate-shopify-woo-lite'),
                    'an_unexpected_error_occurred' => \esc_attr__('An unexpected error occurred', 'wp-migrate-shopify-woo-lite'),
                    'invalid_server_response' => \esc_attr__('Invalid server response', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_parse_server_response' => \esc_attr__('Failed to parse server response', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_process_server_response' => \esc_attr__('Failed to process server response', 'wp-migrate-shopify-woo-lite'),
                    'connection_failed_title' => \esc_attr__('Connection Failed', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_handle_error_response' => \esc_attr__('Failed to handle error response', 'wp-migrate-shopify-woo-lite'),
                    'error_analyzing_server_response' => \esc_attr__('Error analyzing server response', 'wp-migrate-shopify-woo-lite'),
                    'please_fill_required_fields' => \esc_attr__('Please fill in all required fields correctly.', 'wp-migrate-shopify-woo-lite'),
                    'validation_error_title' => \esc_attr__('Validation Error', 'wp-migrate-shopify-woo-lite'),
                    'form_validation_failed' => \esc_attr__('Form validation failed', 'wp-migrate-shopify-woo-lite'),
                    'progress_update_failed' => \esc_attr__('Progress Update Failed', 'wp-migrate-shopify-woo-lite'),
                    'unable_to_get_progress_updates' => \esc_attr__('Unable to get import progress updates. Import may still be running in the background.', 'wp-migrate-shopify-woo-lite'),
                    'import_completed_successfully' => \esc_attr__('Import completed successfully!', 'wp-migrate-shopify-woo-lite'),
                    'import_failed' => \esc_attr__('Import failed', 'wp-migrate-shopify-woo-lite'),
                    'import_complete' => \esc_attr__('Import Complete', 'wp-migrate-shopify-woo-lite'),
                    'import_failed_title' => \esc_attr__('Import Failed', 'wp-migrate-shopify-woo-lite'),
                    'feature_not_available' => \esc_attr__('Feature Not Available', 'wp-migrate-shopify-woo-lite'),
                    'preview_not_implemented' => \esc_attr__('preview functionality is not yet implemented.', 'wp-migrate-shopify-woo-lite'),

                    // Settings page strings
                    'failed_to_save_setting' => \esc_attr__('Failed to save setting.', 'wp-migrate-shopify-woo-lite'),
                    'setting_loaded' => \esc_attr__('Setting loaded.', 'wp-migrate-shopify-woo-lite'),
                    'setting_not_found' => \esc_attr__('Setting not found.', 'wp-migrate-shopify-woo-lite'),
                    'setting_deleted' => \esc_attr__('Setting deleted.', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_delete_setting' => \esc_attr__('Failed to delete setting.', 'wp-migrate-shopify-woo-lite'),
                    'delete_setting_title' => \esc_attr__('Delete Setting', 'wp-migrate-shopify-woo-lite'),
                    'setting_key_validation_error' => \esc_attr__('Setting key can only contain letters, numbers, underscores, and dashes.', 'wp-migrate-shopify-woo-lite'),
                    'all_settings_saved_successfully' => \esc_attr__('All settings have been saved successfully.', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_save_settings' => \esc_attr__('Failed to save settings.', 'wp-migrate-shopify-woo-lite'),
                    'missing_setting_key' => \esc_attr__('Missing setting key.', 'wp-migrate-shopify-woo-lite'),
                    'setting_loaded_successfully' => \esc_attr__('Setting loaded successfully.', 'wp-migrate-shopify-woo-lite'),
                    'loading_text' => \esc_attr__('Loading...', 'wp-migrate-shopify-woo-lite'),
                    'deleting_text' => \esc_attr__('Deleting...', 'wp-migrate-shopify-woo-lite'),
                    'saving_text' => \esc_attr__('Saving...', 'wp-migrate-shopify-woo-lite'),
                    'unknown_error' => \esc_attr__('Unknown error', 'wp-migrate-shopify-woo-lite'),
                    'phone_label' => \esc_attr__('Phone:', 'wp-migrate-shopify-woo-lite'),
                    'orders_label' => \esc_attr__('Orders:', 'wp-migrate-shopify-woo-lite'),
                    'total_spent_label' => \esc_attr__('Total Spent:', 'wp-migrate-shopify-woo-lite'),
                    'tags_label' => \esc_attr__('Tags:', 'wp-migrate-shopify-woo-lite'),
                    'created_label' => \esc_attr__('Created:', 'wp-migrate-shopify-woo-lite'),
                    'updated_label' => \esc_attr__('Updated:', 'wp-migrate-shopify-woo-lite'),
                    'show_details' => \esc_attr__('Show Details', 'wp-migrate-shopify-woo-lite'),
                    'hide_details' => \esc_attr__('Hide Details', 'wp-migrate-shopify-woo-lite'),
                    'unknown' => \esc_attr__('Unknown', 'wp-migrate-shopify-woo-lite'),
                    'profile_photo' => \esc_attr__('Profile photo', 'wp-migrate-shopify-woo-lite'),
                    'load_more' => \esc_attr__('Load More', 'wp-migrate-shopify-woo-lite'),
                    'of' => \esc_attr__('of', 'wp-migrate-shopify-woo-lite'),
                    'import_polling_timeout' => \esc_attr__('Import polling timeout. Please check the import logs for status.', 'wp-migrate-shopify-woo-lite'),
                    'polling_timeout' => \esc_attr__('Polling Timeout', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_get_progress' => \esc_attr__('Failed to get import progress.', 'wp-migrate-shopify-woo-lite'),
                    'progress_error' => \esc_attr__('Progress Error', 'wp-migrate-shopify-woo-lite'),
                    'error_starting_import' => \esc_attr__('Error starting import:', 'wp-migrate-shopify-woo-lite'),
                    'ajax_error' => \esc_attr__('AJAX Error', 'wp-migrate-shopify-woo-lite'),
                    'import_started' => \esc_attr__('Import Started', 'wp-migrate-shopify-woo-lite'),
                    'import_error' => \esc_attr__('Import Error', 'wp-migrate-shopify-woo-lite'),
                    'resuming_blog_import' => \esc_attr__('Resuming blog import progress tracking...', 'wp-migrate-shopify-woo-lite'),
                    'import_active' => \esc_attr__('Import Active', 'wp-migrate-shopify-woo-lite'),
                    'error_checking_imports' => \esc_attr__('Error checking for active blog imports', 'wp-migrate-shopify-woo-lite'),
                    'invalid_request_config' => \esc_attr__('Invalid request configuration', 'wp-migrate-shopify-woo-lite'),
                    'config_error' => \esc_attr__('Configuration Error', 'wp-migrate-shopify-woo-lite'),
                    'operation_completed' => \esc_attr__('Operation completed successfully', 'wp-migrate-shopify-woo-lite'),
                    'operation_failed' => \esc_attr__('Operation failed', 'wp-migrate-shopify-woo-lite'),
                    'operation_failed_title' => \esc_attr__('Operation Failed', 'wp-migrate-shopify-woo-lite'),
                    'error_processing_response' => \esc_attr__('Error processing server response', 'wp-migrate-shopify-woo-lite'),
                    'processing_error' => \esc_attr__('Processing Error', 'wp-migrate-shopify-woo-lite'),
                    'connection_error' => \esc_attr__('Connection error occurred', 'wp-migrate-shopify-woo-lite'),
                    'request_timeout' => \esc_attr__('Request timed out. Please try again.', 'wp-migrate-shopify-woo-lite'),
                    'invalid_response_format' => \esc_attr__('Invalid server response format', 'wp-migrate-shopify-woo-lite'),
                    'access_denied' => \esc_attr__('Access denied. Please check your permissions.', 'wp-migrate-shopify-woo-lite'),
                    'action_not_found' => \esc_attr__('Requested action not found', 'wp-migrate-shopify-woo-lite'),
                    'server_error' => \esc_attr__('Server error occurred. Please try again later.', 'wp-migrate-shopify-woo-lite'),
                    'network_error' => \esc_attr__('Network error. Please check your connection.', 'wp-migrate-shopify-woo-lite'),
                    'request_failed' => \esc_attr__('Request Failed', 'wp-migrate-shopify-woo-lite'),
                    'n_a' => \esc_attr__('N/A', 'wp-migrate-shopify-woo-lite'),
                    'confirm_default_title' => \esc_attr__('Are you sure?', 'wp-migrate-shopify-woo-lite'),
                    'yes' => \esc_attr__('Yes', 'wp-migrate-shopify-woo-lite'),
                    'no' => \esc_attr__('No', 'wp-migrate-shopify-woo-lite'),

                    // Page importer specific strings
                    'please_select_store_first' => \esc_attr__('Please select a store first.', 'wp-migrate-shopify-woo-lite'),
                    'no_store_selected' => \esc_attr__('No Store Selected', 'wp-migrate-shopify-woo-lite'),
                    'loading' => \esc_attr__('Loading...', 'wp-migrate-shopify-woo-lite'),
                    'checking' => \esc_attr__('Checking...', 'wp-migrate-shopify-woo-lite'),
                    'recheck_conflicts' => \esc_attr__('Recheck Conflicts', 'wp-migrate-shopify-woo-lite'),
                    'no_pages_found_conflicts' => \esc_attr__('No pages found to check for conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'no_pages' => \esc_attr__('No Pages', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_check_conflicts' => \esc_attr__('Failed to check conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_check_slug_conflicts' => \esc_attr__('Failed to check slug conflicts. Please try again.', 'wp-migrate-shopify-woo-lite'),
                    'network_error' => \esc_attr__('Network Error', 'wp-migrate-shopify-woo-lite'),
                    'no_content_available' => \esc_attr__('No content available', 'wp-migrate-shopify-woo-lite'),
                    'not_published' => \esc_attr__('Not published', 'wp-migrate-shopify-woo-lite'),
                    'page' => \esc_attr__('page', 'wp-migrate-shopify-woo-lite'),
                    'published' => \esc_attr__('Published', 'wp-migrate-shopify-woo-lite'),
                    'draft' => \esc_attr__('Draft', 'wp-migrate-shopify-woo-lite'),
                    'template' => \esc_attr__('Template:', 'wp-migrate-shopify-woo-lite'),
                    'content_preview' => \esc_attr__('Content Preview:', 'wp-migrate-shopify-woo-lite'),
                    'created' => \esc_attr__('Created:', 'wp-migrate-shopify-woo-lite'),
                    'updated' => \esc_attr__('Updated:', 'wp-migrate-shopify-woo-lite'),
                    'published_label' => \esc_attr__('Published:', 'wp-migrate-shopify-woo-lite'),
                    'slug_conflict' => \esc_attr__('Slug Conflict!', 'wp-migrate-shopify-woo-lite'),
                    'slug_already_exists' => \esc_attr__('Slug "/{handle}" already exists ({source}).', 'wp-migrate-shopify-woo-lite'),
                    'will_become' => \esc_attr__('Will become:', 'wp-migrate-shopify-woo-lite'),
                    'existing' => \esc_attr__('Existing:', 'wp-migrate-shopify-woo-lite'),
                    'no_conflict' => \esc_attr__('No Conflict', 'wp-migrate-shopify-woo-lite'),
                    'slug_is_available' => \esc_attr__('Slug is available', 'wp-migrate-shopify-woo-lite'),
                    'previous_shopify_import' => \esc_attr__('previous Shopify import', 'wp-migrate-shopify-woo-lite'),
                    'wordpress_page' => \esc_attr__('WordPress page', 'wp-migrate-shopify-woo-lite'),
                    'page_import_completed_successfully' => \esc_attr__('Page Import Completed Successfully', 'wp-migrate-shopify-woo-lite'),
                    'page_import_in_progress' => \esc_attr__('Page Import in Progress...', 'wp-migrate-shopify-woo-lite'),
                    'imported' => \esc_attr__('Imported:', 'wp-migrate-shopify-woo-lite'),
                    'updated_count' => \esc_attr__('Updated:', 'wp-migrate-shopify-woo-lite'),
                    'failed' => \esc_attr__('Failed:', 'wp-migrate-shopify-woo-lite'),
                    'skipped' => \esc_attr__('Skipped:', 'wp-migrate-shopify-woo-lite'),
                    'page_import_complete' => \esc_attr__('Page Import Complete!', 'wp-migrate-shopify-woo-lite'),
                    'page_import_completed_successfully_message' => \esc_attr__('Page import completed successfully!', 'wp-migrate-shopify-woo-lite'),
                    'import_complete' => \esc_attr__('Import Complete', 'wp-migrate-shopify-woo-lite'),
                    'resuming_page_import' => \esc_attr__('Resuming page import progress tracking...', 'wp-migrate-shopify-woo-lite'),
                    'error_checking_page_imports' => \esc_attr__('Error checking for active page imports', 'wp-migrate-shopify-woo-lite'),
                    'dismiss_notification' => \esc_attr__('Dismiss notification', 'wp-migrate-shopify-woo-lite'),

                    // Blog importer specific strings
                    'please_select_what_to_import' => \esc_attr__('Please select what to import.', 'wp-migrate-shopify-woo-lite'),
                    'validation_error' => \esc_attr__('Validation Error', 'wp-migrate-shopify-woo-lite'),
                    'no_store_selected_title' => \esc_attr__('No Store Selected', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_load_preview' => \esc_attr__('Failed to load preview', 'wp-migrate-shopify-woo-lite'),
                    'visible' => \esc_attr__('Visible', 'wp-migrate-shopify-woo-lite'),
                    'hidden' => \esc_attr__('Hidden', 'wp-migrate-shopify-woo-lite'),
                    'enabled' => \esc_attr__('Enabled', 'wp-migrate-shopify-woo-lite'),
                    'disabled' => \esc_attr__('Disabled', 'wp-migrate-shopify-woo-lite'),
                    'no_content_available' => \esc_attr__('No content available', 'wp-migrate-shopify-woo-lite'),
                    'not_published' => \esc_attr__('Not published', 'wp-migrate-shopify-woo-lite'),
                    'blog' => \esc_attr__('Blog:', 'wp-migrate-shopify-woo-lite'),
                    'unknown' => \esc_attr__('Unknown', 'wp-migrate-shopify-woo-lite'),
                    'checking_for_conflicts' => \esc_attr__('Checking for potential conflicts...', 'wp-migrate-shopify-woo-lite'),
                    'conflict_check' => \esc_attr__('Conflict Check', 'wp-migrate-shopify-woo-lite'),
                    'no_blogs_found_conflicts' => \esc_attr__('No blogs found to check for conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'all_clear' => \esc_attr__('All Clear', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_get_blog_data' => \esc_attr__('Failed to get blog data for conflict checking', 'wp-migrate-shopify-woo-lite'),
                    'check_failed' => \esc_attr__('Check Failed', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_check_conflicts' => \esc_attr__('Failed to check for conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'unknown_content' => \esc_attr__('Unknown Content', 'wp-migrate-shopify-woo-lite'),
                    'found_conflicts' => \esc_attr__('Found {count} potential conflicts.', 'wp-migrate-shopify-woo-lite'),
                    'conflicts_detected' => \esc_attr__('Conflicts Detected', 'wp-migrate-shopify-woo-lite'),
                    'no_conflicts_found' => \esc_attr__('No conflicts found!', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_check_conflicts_error' => \esc_attr__('Failed to check conflicts', 'wp-migrate-shopify-woo-lite'),
                    'unknown_blog' => \esc_attr__('Unknown Blog', 'wp-migrate-shopify-woo-lite'),
                    'published' => \esc_attr__('PUBLISHED', 'wp-migrate-shopify-woo-lite'),
                    'draft' => \esc_attr__('DRAFT', 'wp-migrate-shopify-woo-lite'),
                    'import_already_in_progress' => \esc_attr__('Import is already in progress', 'wp-migrate-shopify-woo-lite'),
                    'import_running' => \esc_attr__('Import Running', 'wp-migrate-shopify-woo-lite'),
                    'import_scheduled' => \esc_attr__('Import Scheduled', 'wp-migrate-shopify-woo-lite'),
                    'scheduling_failed' => \esc_attr__('Scheduling Failed', 'wp-migrate-shopify-woo-lite'),
                    'server_error' => \esc_attr__('Server Error', 'wp-migrate-shopify-woo-lite'),

                    // Logs specific strings
                    'invalid_log_id' => \esc_attr__('Invalid log ID provided', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_load_log_details' => \esc_attr__('Failed to Load Log Details', 'wp-migrate-shopify-woo-lite'),
                    'error_parsing_response' => \esc_attr__('Error parsing server response', 'wp-migrate-shopify-woo-lite'),
                    'parse_error' => \esc_attr__('Parse Error', 'wp-migrate-shopify-woo-lite'),
                    'connection_error' => \esc_attr__('Connection error', 'wp-migrate-shopify-woo-lite'),
                    'request_timed_out' => \esc_attr__('Request timed out', 'wp-migrate-shopify-woo-lite'),
                    'invalid_server_response' => \esc_attr__('Invalid server response', 'wp-migrate-shopify-woo-lite'),
                    'access_denied' => \esc_attr__('Access denied', 'wp-migrate-shopify-woo-lite'),
                    'action_not_found' => \esc_attr__('Action not found', 'wp-migrate-shopify-woo-lite'),
                    'server_error_occurred' => \esc_attr__('Server error', 'wp-migrate-shopify-woo-lite'),
                    'import_cancelled_successfully' => \esc_attr__('Import cancelled successfully', 'wp-migrate-shopify-woo-lite'),
                    'success' => \esc_attr__('Success', 'wp-migrate-shopify-woo-lite'),
                    'cancel_failed' => \esc_attr__('Cancel Failed', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_cancel_import' => \esc_attr__('Failed to cancel import', 'wp-migrate-shopify-woo-lite'),
                    'connection_error_occurred' => \esc_attr__('Connection error occurred', 'wp-migrate-shopify-woo-lite'),
                    'cancel_import_title' => \esc_attr__('Cancel Import', 'wp-migrate-shopify-woo-lite'),
                    'import_retry_initiated' => \esc_attr__('Import retry initiated successfully', 'wp-migrate-shopify-woo-lite'),
                    'retry_failed' => \esc_attr__('Retry Failed', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_retry_import' => \esc_attr__('Failed to retry import', 'wp-migrate-shopify-woo-lite'),
                    'retry_import_title' => \esc_attr__('Retry Import', 'wp-migrate-shopify-woo-lite'),
                    'log_deleted_successfully' => \esc_attr__('Log deleted successfully', 'wp-migrate-shopify-woo-lite'),
                    'delete_failed' => \esc_attr__('Delete Failed', 'wp-migrate-shopify-woo-lite'),
                    'failed_to_delete_log' => \esc_attr__('Failed to delete log', 'wp-migrate-shopify-woo-lite'),
                    'delete_log_title' => \esc_attr__('Delete Log', 'wp-migrate-shopify-woo-lite'),
                    'general_error' => \esc_attr__('An error occurred', 'wp-migrate-shopify-woo-lite'),
                ]
            ]
        );
    }

    /**
     * Check if current page is a plugin page
     */
    private function is_plugin_page($hook)
    {
        $plugin_pages = [
            'toplevel_page_wp-migrate-shopify-woo',
            'shopify-importer_page_wp-migrate-shopify-woo-lite-stores',
            'shopify-importer_page_wp-migrate-shopify-woo-lite-logs',
            'shopify-importer_page_wp-migrate-shopify-woo-lite-settings',
        ];

        return in_array($hook, $plugin_pages);
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page()
    {
        require_once WMSW_PLUGIN_DIR . 'backend/views/dashboard.php';
    }

    /**
     * Render stores page
     */
    public function render_stores_page()
    {
        require_once WMSW_PLUGIN_DIR . 'backend/views/shopify-stores.php';
    }

    /**
     * Render logs page
     */
    public function render_logs_page()
    {
        require_once WMSW_PLUGIN_DIR . 'backend/views/import-logs.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        require_once WMSW_PLUGIN_DIR . 'backend/views/settings.php';
    }

    /**
     * AJAX handler for verifying Shopify connection
     */
    public function verify_shopify_connection()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }

        // Validate required fields
        $required_fields = ['shop_url', 'api_key', 'api_password'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                wp_send_json_error(['message' => __('Please fill in all required fields', 'wp-migrate-shopify-woo-lite')]);
            }
        }

        $shop_url = empty($_POST['shop_url']) ? '' : sanitize_text_field(wp_unslash($_POST['shop_url']));
        $api_key = empty($_POST['api_key']) ? '' : sanitize_text_field(wp_unslash($_POST['api_key']));
        $api_password = empty($_POST['api_password']) ? '' : sanitize_text_field(wp_unslash($_POST['api_password']));

        // Create client and test connection
        try {
            $client = new WMSWL_ShopifyClient($shop_url, $api_key, $api_password);
            $shop = $client->get('shop');

            if (isset($shop['errors'])) {
                wp_send_json_error(['message' => __('Failed to connect: ', 'wp-migrate-shopify-woo-lite') . $shop['errors']]);
            }

            wp_send_json_success([
                // translators: %s is the shop name
                'message' => sprintf(__('Successfully connected to %s', 'wp-migrate-shopify-woo-lite'), $shop['shop']['name']),
                'shop_info' => $shop['shop']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Connection failed: ', 'wp-migrate-shopify-woo-lite') . $e->getMessage()]);
        }
    }




    /**
     * AJAX handler for retrieving a store
     */
    public function get_store()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }

        // Validate store ID
        if (!isset($_POST['store_id']) || empty($_POST['store_id'])) {
            wp_send_json_error(['message' => __('No store specified', 'wp-migrate-shopify-woo-lite')]);
        }

        $store_id = intval(wp_unslash($_POST['store_id']));

        try {
            $handler = new WMSWL_StoreHandler();
            $store = $handler->get_store($store_id);

            if (is_wp_error($store)) {
                wp_send_json_error(['message' => $store->get_error_message()]);
            }

            if (!$store) {
                wp_send_json_error(['message' => __('Store not found', 'wp-migrate-shopify-woo-lite')]);
            }

            wp_send_json_success([
                'store' => $store
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }


    /**
     * Get handler class instance or reference by name.
     *
     * @param string $handler
     * @return object|string|null
     */
    public function getHandler($handler)
    {
        switch (strtolower($handler)) {
            case 'stores':
            case 'storeshandler':
                return new WMSWL_StoreHandler();
            case 'products':
            case 'producthandler':
                return new WMSWL_ProductHandler();
            case 'customers':
            case 'customerhandler':
                return new WMSWL_CustomerHandler();
            case 'orders':
            case 'orderhandler':
                return new WMSWL_OrderHandler();
            case 'pages':
            case 'pagehandler':
                // Pages functionality removed in lite version
                return null;
            case 'blogs':
            case 'bloghandler':
                // Blogs functionality removed in lite version
                return null;
            case 'coupons':
            case 'couponhandler':
                // Coupons functionality removed in lite version
                return null;

            case 'settings':
                return new WMSWL_SettingsHandler();
            case 'logs':
                return new WMSWL_LogHandler();
            default:
                return null;
        }
    }

    /**
     * AJAX handler for testing Shopify connection
     */
    public function ajax_test_shopify_connection()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }

        if (!isset($_POST['shop_domain']) || empty($_POST['shop_domain'])) {
            wp_send_json_error(['message' => __('Shop domain is required', 'wp-migrate-shopify-woo-lite')]);
        }
        if (!isset($_POST['access_token']) || empty($_POST['access_token'])) {
            wp_send_json_error(['message' => __('Access token is required', 'wp-migrate-shopify-woo-lite')]);
        }

        $shop_domain = sanitize_text_field(wp_unslash($_POST['shop_domain']));
        $access_token = sanitize_text_field(wp_unslash($_POST['access_token']));
        $api_version = sanitize_text_field(wp_unslash($_POST['api_version'] ?? '2023-10'));


        try {

            $client = new WMSWL_ShopifyClient($shop_domain, $access_token, $api_version);
            // First try to get shop info
            $shop_info = $client->get('shop');


            if (isset($shop_info['shop'])) {
                wp_send_json_success([
                    'message' => __('Connection successful!', 'wp-migrate-shopify-woo-lite'),
                    'details' => sprintf(
                        // translators: %1$s is the shop name, %2$s is the shop domain
                        __('Connected to: %1$s (%2$s)', 'wp-migrate-shopify-woo-lite'),
                        $shop_info['shop']['name'] ?? $shop_domain,
                        $shop_info['shop']['domain'] ?? $shop_domain . '.myshopify.com'
                    )
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Connection failed. Invalid response from Shopify.', 'wp-migrate-shopify-woo-lite'),
                    'details' => __('The API returned an unexpected response format.', 'wp-migrate-shopify-woo-lite')
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Connection failed.', 'wp-migrate-shopify-woo-lite'),
                // translators: %s is the error message
                'details' => sprintf(__('Error: %s', 'wp-migrate-shopify-woo-lite'), $e->getMessage())
            ]);
        }
    }

    /**
     * Redirect old task progress AJAX calls to the product handler's getImportProgress method
     * This ensures backward compatibility with any code still using the old action name
     */
    public function redirect_to_import_progress()
    {
        // Get the product handler
        $product_handler = $this->getHandler('products');

        // Just call the getImportProgress method directly
        $product_handler->getImportProgress();

        // The handler's method will send the JSON response, so we don't need to return anything
    }

    /**
     * AJAX handler for setting a store as default
     */
    public function ajax_set_default_store()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }

        if (!isset($_POST['store_id']) || empty($_POST['store_id'])) {
            wp_send_json_error(['message' => __('Missing store ID.', 'wp-migrate-shopify-woo-lite')]);
        }
        $store_id = intval(wp_unslash($_POST['store_id']));
        if (!$store_id) {
            wp_send_json_error(['message' => __('Invalid store ID.', 'wp-migrate-shopify-woo-lite')]);
        }

        try {
            $handler = $this->getHandler('stores');
            if (!method_exists($handler, 'set_default_store')) {
                wp_send_json_error(['message' => __('Store handler does not support setting default store.', 'wp-migrate-shopify-woo-lite')]);
            }
            $result = $handler->set_default_store($store_id);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
            wp_send_json_success(['message' => __('Store set as default successfully.', 'wp-migrate-shopify-woo-lite')]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to set default store: ', 'wp-migrate-shopify-woo-lite') . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get all stores (for dropdowns)
     */
    public function ajax_get_stores()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }
        $stores = WMSWL_ShopifyStore::get_all();
        wp_send_json_success(['stores' => $stores]);
    }

    /**
     * AJAX: Fetch categories from Shopify
     */
    public function ajax_fetch_shopify_categories()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }

        if (!isset($_POST['store_id']) || empty($_POST['store_id'])) {
            wp_send_json_error(['message' => __('Missing store ID.', 'wp-migrate-shopify-woo-lite')]);
        }

        $store_id = intval(wp_unslash($_POST['store_id']));
        if (!$store_id) {
            wp_send_json_error(['message' => __('Invalid store ID.', 'wp-migrate-shopify-woo-lite')]);
        }
        $store = WMSWL_ShopifyStore::get($store_id);
        if (!$store) {
            wp_send_json_error(['message' => 'Store not found.']);
        }
        $client = new WMSWL_ShopifyClient($store->shop_domain, $store->access_token, $store->api_version);
        $categories = $client->get_collections(); // You may need to implement this method
        if (is_wp_error($categories)) {
            wp_send_json_error(['message' => $categories->get_error_message()]);
        }
        wp_send_json_success(['categories' => $categories]);
    }

    /**
     * AJAX: Import selected categories
     */
    public function ajax_import_shopify_categories()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo-lite')]);
        }
        if (!isset($_POST['store_id']) || empty($_POST['store_id'])) {
            wp_send_json_error(['message' => __('Missing store ID.', 'wp-migrate-shopify-woo-lite')]);
        }
        if (!isset($_POST['category_ids']) || empty($_POST['category_ids'])) {
            wp_send_json_error(['message' => __('Missing category IDs.', 'wp-migrate-shopify-woo-lite')]);
        }

        $store_id = intval(wp_unslash($_POST['store_id']));
        $category_ids = array_map('sanitize_text_field', wp_unslash($_POST['category_ids']));

        if (!$store_id) {
            wp_send_json_error(['message' => __('Invalid store ID.', 'wp-migrate-shopify-woo-lite')]);
        }
        $store = WMSWL_ShopifyStore::get($store_id);
        if (!$store) {
            wp_send_json_error(['message' => 'Store not found.']);
        }

        try {
            // Get Shopify client
            $client = new WMSWL_ShopifyClient($store->get_shop_domain(), $store->get_access_token(), $store->get_api_version());

            // Import collections
            $result = $client->import_collections($category_ids);

            // Log the import
            $this->log_category_import($store_id, $result);

            wp_send_json_success(['imported' => $result]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Log category import for tracking
     */
    private function log_category_import($store_id, $imported_categories)
    {
        global $wpdb;

        // Get store info for logging
        $store = WMSWL_ShopifyStore::get($store_id);
        if (!$store) {
            return;
        }

        // Log to database
        try {
            WMSWL_Task::create($store_id, 'import_categories', 'completed', count($imported_categories), count($imported_categories));
        } catch (\Exception $e) {
            // Log the error using WordPress logger if available
            if (class_exists('ShopifyWooImporter\\Services\\WMSWL_Logger')) {
                $logger = new \ShopifyWooImporter\Services\WMSWL_Logger();
                $logger->error('Error logging category import: ' . $e->getMessage());
            }
        }
    }
}
