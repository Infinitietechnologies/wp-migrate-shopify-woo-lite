<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Models\WMSW_ShopifyStore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Activation Handler
 */
class WMSW_ActivationHandler
{
    public function activate()
    {
        $this->create_database_tables();
        $this->ensure_database_structure();
        $this->upgrade_database();
        $this->create_default_options();
        $this->schedule_cron_events();
        $this->set_default_capabilities();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_database_tables()
    {
        global $wpdb;
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // Drop existing tables if they exist to avoid conflicts
        $this->drop_existing_tables();

        // Shopify stores table
        $stores_table = $wpdb->prefix . \WMSW_STORES_TABLE;
        $stores_sql = "CREATE TABLE $stores_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                store_name varchar(255) NOT NULL,
                shop_domain varchar(255) NOT NULL,
                access_token varchar(255) NOT NULL,
                api_version varchar(20) NOT NULL DEFAULT '2023-07',
                is_active tinyint(1) NOT NULL DEFAULT 1,
                is_default tinyint(1) NOT NULL DEFAULT 0,
                last_sync datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY shop_domain (shop_domain)
        ) $charset_collate;";

        // Import logs table
        $logs_table = $wpdb->prefix . \WMSW_LOGS_TABLE;
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            import_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            items_total int(11) NOT NULL DEFAULT 0,
            items_processed int(11) NOT NULL DEFAULT 0,
            items_succeeded int(11) NOT NULL DEFAULT 0,
            items_failed int(11) NOT NULL DEFAULT 0,
            options longtext DEFAULT NULL,
            log_data longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY store_id (store_id)
        ) $charset_collate;";

        // Import data mapping table
        $map_table = $wpdb->prefix . \WMSW_MAPPINGS_TABLE;
        $map_sql = "CREATE TABLE $map_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            shopify_id varchar(255) NOT NULL,
            woocommerce_id bigint(20) NOT NULL,
            object_type varchar(50) NOT NULL,
            last_imported datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY shopify_object (store_id,shopify_id,object_type),
            KEY woocommerce_id (woocommerce_id),
            KEY object_type (object_type)
        ) $charset_collate;";        // Scheduled tasks table
        $tasks_table = $wpdb->prefix . \WMSW_TASKS_TABLE;
        $tasks_sql = "CREATE TABLE $tasks_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            task_type varchar(50) NOT NULL,
            frequency varchar(20) NOT NULL,
            last_run datetime DEFAULT NULL,
            next_run datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            options longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY store_id (store_id),
            KEY next_run (next_run),
            KEY status (status)
        ) $charset_collate;";

        // Store logs table
        $store_logs_table = $wpdb->prefix . \WMSW_STORE_LOGS_TABLE;
        $store_logs_sql = "CREATE TABLE $store_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            action_details longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Settings table
        $settings_table = $wpdb->prefix . \WMSW_SETTINGS_TABLE;
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) DEFAULT NULL,
            setting_key varchar(255) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_type varchar(50) NOT NULL DEFAULT 'string',
            is_global tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (store_id, setting_key),
            KEY is_global (is_global)
        ) $charset_collate;";

        // Import sessions table
        $imports_table = $wpdb->prefix . \WMSW_IMPORTS_TABLE;
        $imports_sql = "CREATE TABLE $imports_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            import_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            imported_items int(11) NOT NULL DEFAULT 0,
            skipped_items int(11) NOT NULL DEFAULT 0,
            error_items int(11) NOT NULL DEFAULT 0,
            progress_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            current_batch int(11) NOT NULL DEFAULT 0,
            batch_size int(11) NOT NULL DEFAULT 10,
            filters longtext DEFAULT NULL,
            options longtext DEFAULT NULL,
            error_log longtext DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY import_type (import_type),
            KEY status (status),
            KEY progress_percentage (progress_percentage)
        ) $charset_collate;";

        // Run the SQL queries
        \dbDelta($stores_sql);
        \dbDelta($logs_sql);
        \dbDelta($map_sql);
        \dbDelta($tasks_sql);
        \dbDelta($store_logs_sql);
        \dbDelta($settings_sql);
        \dbDelta($imports_sql);
    }

    /**
     * Upgrade database if needed
     */
    private function upgrade_database()
    {
        global $wpdb;
        $current_version = get_option('wmsw_db_version', '0.0.0');

        // If the current version is different from the plugin version
        if (version_compare($current_version, WMSW_VERSION, '<')) {

            // Migrate existing tokens to encrypted format (for version 1.1+)
            if (version_compare($current_version, '1.1.0', '<')) {
                // Import the ShopifyStore model to handle token migration
                require_once WMSW_PLUGIN_DIR . 'includes/models/WMSW_ShopifyStore.php';

                // Run the token migration
                try {
                    WMSW_ShopifyStore::migrate_tokens_to_encrypted();
                } catch (\Exception $e) {
                }
            }

            // Update DB version
            update_option('wmsw_db_version', WMSW_VERSION);
        }
    }

    /**
     * Create default options
     */
    private function create_default_options()
    {
        // General settings
        add_option('wmsw_enable_debug', false);
        add_option('wmsw_import_batch_size', 25);

        // Image handling
        add_option('wmsw_download_images', true);
        add_option('wmsw_set_featured_image', true);

        // Product settings
        add_option('wmsw_import_drafts', false);
        add_option('wmsw_preserve_stock', true);
    }

    /**
     * Schedule CRON events
     */
    private function schedule_cron_events()
    {
        // Schedule the event to check for upcoming scheduled tasks
        if (!wp_next_scheduled('wmsw_scheduled_tasks_check')) {
            wp_schedule_event(time(), 'hourly', 'wmsw_scheduled_tasks_check');
        }

        // Schedule event for cleanup old logs (once daily)
        if (!wp_next_scheduled('wmsw_cleanup_old_logs')) {
            wp_schedule_event(time(), 'daily', 'wmsw_cleanup_old_logs');
        }
    }

    /**
     * Set default capabilities
     */
    private function set_default_capabilities()
    {
        $capabilities = [
            'wmsw_manage_importer',
            'wmsw_run_imports',
            'wmsw_view_logs',
        ];

        // Get administrator role
        $role = get_role('administrator');

        // Add capabilities to administrator
        if ($role) {
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /**
     * Drop existing tables to avoid conflicts during activation
     */
    private function drop_existing_tables()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . \WMSW_STORES_TABLE,
            $wpdb->prefix . \WMSW_TASKS_TABLE,
            $wpdb->prefix . \WMSW_LOGS_TABLE,
            $wpdb->prefix . \WMSW_MAPPINGS_TABLE,
            $wpdb->prefix . \WMSW_STORE_LOGS_TABLE,
            $wpdb->prefix . \WMSW_SETTINGS_TABLE,
            $wpdb->prefix . \WMSW_IMPORTS_TABLE
        ];

        foreach ($tables as $table) {
            $escaped_table = esc_sql($table);
            $wpdb->query(
                $wpdb->prepare(
                    "DROP TABLE IF EXISTS `%s`", 
                    $escaped_table
                )
            );
        }
    }

    /**
     * Ensure all database tables have the correct structure
     */
    private function ensure_database_structure()
    {
        // Load database helper for comprehensive structure checks
        require_once WMSW_PLUGIN_DIR . 'includes/helpers/WMSW_DatabaseHelper.php';

        // Run comprehensive structure checks for all tables
        \ShopifyWooImporter\Helpers\WMSW_DatabaseHelper::ensure_all_table_structures();
    }
}
