<?php

/**
 * Uninstall Script
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin constants
if (!defined('WMSW_STORES_TABLE')) {
    require_once plugin_dir_path(__FILE__) . 'config/constants.php';
}

/**
 * Remove plugin data on uninstall
 */
function wmsw_uninstall_plugin()
{
    global $wpdb;

    // Check if user wants to keep data
    $keep_data = get_option('wmsw_keep_data_on_uninstall', false);

    if (!$keep_data) {
        // Drop all custom tables
        $tables = [
            $wpdb->prefix . WMSW_STORES_TABLE,
            $wpdb->prefix . WMSW_TASKS_TABLE,
            $wpdb->prefix . WMSW_LOGS_TABLE,
            $wpdb->prefix . WMSW_MAPPINGS_TABLE,
            $wpdb->prefix . WMSW_STORE_LOGS_TABLE,
            $wpdb->prefix . WMSW_SETTINGS_TABLE,
            $wpdb->prefix . WMSW_IMPORTS_TABLE
        ];

        foreach ($tables as $table) {
            // Note: WordPress doesn't provide a built-in method for dropping tables
            // This is the standard approach used in WordPress plugins
            // Use esc_sql for table names since %i is only supported in WP 6.2+
            $escaped_table = esc_sql($table);
            $result = $wpdb->query("DROP TABLE IF EXISTS " . esc_sql($table));
            if ($result === false) {
                // Log error will be here
            }
        }

        // Remove options using WordPress functions
        delete_option('wmsw_options');
        delete_option('wmsw_version');
        delete_option('wmsw_keep_data_on_uninstall');
        delete_option('wmsw_db_version');

        // Remove user meta using WordPress functions with caching
        $user_meta_deleted = delete_metadata('user', 0, 'wmsw_user_preferences', '', true);
        if ($user_meta_deleted === false) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // You can add error logs here
            }
        }

        // Remove post meta using WordPress functions with caching
        $post_meta_keys = [
            '_shopify_product_id',
            '_shopify_customer_id',
            '_shopify_order_id',
            '_shopify_page_id',
            '_shopify_blog_id',
            '_shopify_coupon_id'
        ];

        foreach ($post_meta_keys as $meta_key) {
            $post_meta_deleted = delete_metadata('post', 0, $meta_key, '', true);
            if ($post_meta_deleted === false) {
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // You can add error logs here
                }
            }
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('wmsw_sync_stores');
        wp_clear_scheduled_hook('wmsw_cleanup_logs');
        wp_clear_scheduled_hook('wmsw_background_process');
        wp_clear_scheduled_hook('wmsw_scheduled_tasks_check');
        wp_clear_scheduled_hook('wmsw_cleanup_old_logs');

        // Remove uploaded files
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/shopify-woo-importer/';

        if (is_dir($plugin_upload_dir)) {
            wmsw_remove_directory($plugin_upload_dir);
        }

        // Clear caches
        wp_cache_flush_group(WMSW_CACHE_GROUP);

        // Remove capabilities
        $roles = ['administrator', 'shop_manager'];
        $capabilities = [
            'wmsw_manage_importer',
            'wmsw_run_imports',
            'wmsw_view_logs',
            'manage_shopify_import'
        ];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}

/**
 * Recursively remove directory
 */
function wmsw_remove_directory($dir)
{
    global $wp_filesystem;

    // Initialize the WP Filesystem if not already done
    if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if (!$wp_filesystem->is_dir($dir)) {
        return;
    }

    $files = $wp_filesystem->dirlist($dir);

    foreach ($files as $file => $details) {
        $path = trailingslashit($dir) . $file;

        if ('d' === $details['type']) {
            wmsw_remove_directory($path);
        } else {
            $wp_filesystem->delete($path);
        }
    }

    $wp_filesystem->rmdir($dir);
}

// Run uninstall
wmsw_uninstall_plugin();
