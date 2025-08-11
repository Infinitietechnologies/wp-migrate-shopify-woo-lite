<?php

namespace ShopifyWooImporter\Helpers;

/**
 * Database Helper for WMSW Plugin
 * 
 * Handles database operations for custom plugin tables with proper caching
 */
class WMSW_DatabaseHelper
{

    /**
     * Ensure all tables have the correct structure
     * This method is called during activation to fix any structural issues
     */
    public static function ensure_all_table_structures()
    {
        global $wpdb;

        // Ensure logs table structure
        self::ensure_logs_table_structure();

        // Ensure settings table structure
        self::ensure_settings_table_structure();

        // Ensure stores table structure
        self::ensure_stores_table_structure();

        // Ensure mappings table structure
        self::ensure_mappings_table_structure();

        // Ensure tasks table structure
        self::ensure_tasks_table_structure();

        // Ensure store logs table structure
        self::ensure_store_logs_table_structure();

        // Ensure imports table structure
        self::ensure_imports_table_structure();
    }

    /**
     * Add the context column to the logs table if it doesn't exist
     */
    public static function ensure_logs_table_structure()
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . WMSW_LOGS_TABLE;

        // Check if table exists first
        if (!self::table_exists($logs_table)) {
            return; // Let the activation handler create it
        }

        // Get existing columns
        $columns = $wpdb->get_results("DESCRIBE $logs_table");
        $existing_columns = [];

        foreach ($columns as $column) {
            $existing_columns[$column->Field] = $column;
        }

        // Define required columns with their definitions
        $required_columns = [
            'context' => 'longtext DEFAULT NULL',
            'task_id' => 'bigint(20) DEFAULT NULL',
            'level' => 'varchar(50) NOT NULL',
            'message' => 'longtext NOT NULL',
            'created_at' => 'datetime NOT NULL',
            'items_skipped' => 'int(11) NOT NULL DEFAULT 0'
        ];

        // Add missing columns
        foreach ($required_columns as $column_name => $definition) {
            if (!isset($existing_columns[$column_name])) {
                $wpdb->query("ALTER TABLE $logs_table ADD COLUMN $column_name $definition");
            }
        }

        // Handle column renames if needed
        if (isset($existing_columns['import_id']) && !isset($existing_columns['task_id'])) {
            $wpdb->query("ALTER TABLE $logs_table CHANGE import_id task_id bigint(20) NOT NULL");
        }

        if (isset($existing_columns['log_level']) && !isset($existing_columns['level'])) {
            $wpdb->query("ALTER TABLE $logs_table CHANGE log_level level varchar(50) NOT NULL");
        }
    }

    /**
     * Ensure settings table structure
     */
    public static function ensure_settings_table_structure()
    {
        global $wpdb;
        $settings_table = $wpdb->prefix . WMSW_SETTINGS_TABLE;

        if (!self::table_exists($settings_table)) {
            return;
        }

        // Check for duplicate keys and remove them
        $indexes = $wpdb->get_results("SHOW INDEX FROM $settings_table");
        $setting_key_indexes = [];

        foreach ($indexes as $index) {
            if ($index->Column_name === 'setting_key') {
                $setting_key_indexes[] = $index;
            }
        }

        // If there are multiple setting_key indexes, remove the duplicate
        if (count($setting_key_indexes) > 1) {
            // Keep the unique constraint, remove the simple key
            foreach ($setting_key_indexes as $index) {
                if ($index->Key_name !== 'unique_setting') {
                    $wpdb->query("ALTER TABLE $settings_table DROP INDEX {$index->Key_name}");
                }
            }
        }
    }

    /**
     * Ensure stores table structure
     */
    public static function ensure_stores_table_structure()
    {
        global $wpdb;
        $stores_table = $wpdb->prefix . WMSW_STORES_TABLE;

        if (!self::table_exists($stores_table)) {
            return;
        }

        // Add any missing columns for stores table
        $required_columns = [
            'api_version' => 'varchar(20) NOT NULL DEFAULT "2024-04"',
            'is_default' => 'tinyint(1) NOT NULL DEFAULT 0'
        ];

        foreach ($required_columns as $column_name => $definition) {
            self::add_column_if_not_exists($stores_table, $column_name, $definition);
        }
    }

    /**
     * Ensure mappings table structure
     */
    public static function ensure_mappings_table_structure()
    {
        global $wpdb;
        $mappings_table = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        if (!self::table_exists($mappings_table)) {
            return;
        }

        // Add any missing columns for mappings table
        $required_columns = [
            'last_imported' => 'datetime NOT NULL'
        ];

        foreach ($required_columns as $column_name => $definition) {
            self::add_column_if_not_exists($mappings_table, $column_name, $definition);
        }
    }

    /**
     * Ensure tasks table structure
     */
    public static function ensure_tasks_table_structure()
    {
        global $wpdb;
        $tasks_table = $wpdb->prefix . WMSW_TASKS_TABLE;

        if (!self::table_exists($tasks_table)) {
            return;
        }

        // Add any missing columns for tasks table
        $required_columns = [
            'status' => 'varchar(20) NOT NULL DEFAULT "active"',
            'options' => 'longtext DEFAULT NULL'
        ];

        foreach ($required_columns as $column_name => $definition) {
            self::add_column_if_not_exists($tasks_table, $column_name, $definition);
        }
    }

    /**
     * Ensure store logs table structure
     */
    public static function ensure_store_logs_table_structure()
    {
        global $wpdb;
        $store_logs_table = $wpdb->prefix . WMSW_STORE_LOGS_TABLE;

        if (!self::table_exists($store_logs_table)) {
            return;
        }

        // Add any missing columns for store logs table
        $required_columns = [
            'action_details' => 'longtext DEFAULT NULL'
        ];

        foreach ($required_columns as $column_name => $definition) {
            self::add_column_if_not_exists($store_logs_table, $column_name, $definition);
        }
    }

    /**
     * Ensure imports table structure
     */
    public static function ensure_imports_table_structure()
    {
        global $wpdb;
        $imports_table = $wpdb->prefix . WMSW_IMPORTS_TABLE;

        if (!self::table_exists($imports_table)) {
            return;
        }

        // Add any missing columns for imports table
        $required_columns = [
            'progress_percentage' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
            'current_batch' => 'int(11) NOT NULL DEFAULT 0',
            'batch_size' => 'int(11) NOT NULL DEFAULT 10',
            'filters' => 'longtext DEFAULT NULL',
            'options' => 'longtext DEFAULT NULL',
            'error_log' => 'longtext DEFAULT NULL'
        ];

        foreach ($required_columns as $column_name => $definition) {
            self::add_column_if_not_exists($imports_table, $column_name, $definition);
        }
    }

    /**
     * Check if a column exists in a table
     */
    public static function column_exists($table_name, $column_name)
    {
        global $wpdb;

        $result = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            $column_name
        ));

        return !empty($result);
    }

    /**
     * Check if a table exists
     */
    public static function table_exists($table_name)
    {
        global $wpdb;

        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        return $result === $table_name;
    }

    /**
     * Add a column if it doesn't exist
     */
    public static function add_column_if_not_exists($table_name, $column_name, $definition)
    {
        global $wpdb;

        if (!self::column_exists($table_name, $column_name)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column_name $definition");
            return true;
        }

        return false;
    }

    /**
     * Rename a column if it exists and the new name doesn't
     */
    public static function rename_column_if_exists($table_name, $old_name, $new_name, $definition)
    {
        global $wpdb;

        if (self::column_exists($table_name, $old_name) && !self::column_exists($table_name, $new_name)) {
            $wpdb->query("ALTER TABLE $table_name CHANGE $old_name $new_name $definition");
            return true;
        }

        return false;
    }

    /**
     * Cache group for import logs
     */
    const CACHE_GROUP_IMPORT_LOGS = 'wmsw_import_logs';

    /**
     * Cache group for mappings
     */
    const CACHE_GROUP_MAPPINGS = 'wmsw_mappings';

    /**
     * Cache expiration time in seconds (1 hour)
     */
    const CACHE_EXPIRATION = 3600;

    /**
     * Get import log by ID with caching
     *
     * @param int $import_id The import ID
     * @return object|null The import log object or null if not found
     */
    public static function get_import_log($import_id)
    {
        $cache_key = 'import_log_' . $import_id;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP_IMPORT_LOGS);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wmsw_import_logs';

        $import_log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $import_id)
        );

        if ($import_log) {
            wp_cache_set($cache_key, $import_log, self::CACHE_GROUP_IMPORT_LOGS, self::CACHE_EXPIRATION);
        }

        return $import_log;
    }

    /**
     * Update import log with cache invalidation
     *
     * @param int $import_id The import ID
     * @param array $data The data to update
     * @return bool|int Number of rows affected or false on failure
     */
    public static function update_import_log($import_id, $data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wmsw_import_logs';

        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $import_id]
        );

        if (false !== $result) {
            // Invalidate cache
            $cache_key = 'import_log_' . $import_id;
            wp_cache_delete($cache_key, self::CACHE_GROUP_IMPORT_LOGS);
        }

        return $result;
    }

    /**
     * Get latest in-progress import ID with caching
     *
     * @return int|null The import ID or null if not found
     */
    public static function get_latest_in_progress_import_id()
    {
        $cache_key = 'latest_in_progress_import_id';
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP_IMPORT_LOGS);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wmsw_import_logs';

        $import_id = $wpdb->get_var(
            "SELECT id FROM {$table_name} WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1"
        );

        if ($import_id) {
            $import_id = intval($import_id);
            wp_cache_set($cache_key, $import_id, self::CACHE_GROUP_IMPORT_LOGS, self::CACHE_EXPIRATION);
        }

        return $import_id ? intval($import_id) : null;
    }

    /**
     * Get product mapping by Shopify ID with caching
     *
     * @param string $shopify_id The Shopify product ID
     * @return int|null The WooCommerce product ID or null if not found
     */
    public static function get_product_mapping($shopify_id)
    {
        $cache_key = 'product_mapping_' . md5($shopify_id);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP_MAPPINGS);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT woocommerce_id FROM {$table_name} WHERE shopify_id = %s AND object_type = 'product'",
                $shopify_id
            )
        );

        if ($product_id) {
            $product_id = intval($product_id);
            wp_cache_set($cache_key, $product_id, self::CACHE_GROUP_MAPPINGS, self::CACHE_EXPIRATION);
        }

        return $product_id ? intval($product_id) : null;
    }

    /**
     * Get existing WooCommerce ID mapping by WooCommerce ID with caching
     *
     * @param int $woocommerce_id The WooCommerce product ID
     * @return string|null The Shopify ID or null if not found
     */
    public static function get_existing_woo_mapping($woocommerce_id)
    {
        $cache_key = 'woo_mapping_' . $woocommerce_id;
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP_MAPPINGS);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        $shopify_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT shopify_id FROM {$table_name} WHERE woocommerce_id = %d AND object_type = 'product'",
                $woocommerce_id
            )
        );

        if ($shopify_id) {
            wp_cache_set($cache_key, $shopify_id, self::CACHE_GROUP_MAPPINGS, self::CACHE_EXPIRATION);
        }

        return $shopify_id;
    }

    /**
     * Check if mapping exists for Shopify ID with caching
     *
     * @param string $shopify_id The Shopify ID
     * @param string $object_type The object type (product, category, etc.)
     * @return bool True if mapping exists, false otherwise
     */
    public static function mapping_exists($shopify_id, $object_type = 'product')
    {
        $cache_key = 'mapping_exists_' . md5($shopify_id . '_' . $object_type);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP_MAPPINGS);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE shopify_id = %s AND object_type = %s",
                $shopify_id,
                $object_type
            )
        );

        $result = !empty($exists);
        wp_cache_set($cache_key, $result, self::CACHE_GROUP_MAPPINGS, self::CACHE_EXPIRATION);

        return $result;
    }

    /**
     * Insert or update product mapping with cache invalidation
     *
     * @param string $shopify_id The Shopify product ID
     * @param int $woocommerce_id The WooCommerce product ID
     * @param int $store_id The store ID
     * @return bool True on success, false on failure
     */
    public static function upsert_product_mapping($shopify_id, $woocommerce_id, $store_id = 1)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        // Check if mapping already exists
        $existing = self::mapping_exists($shopify_id, 'product');

        if ($existing) {
            // Update existing mapping
            $result = $wpdb->update(
                $table_name,
                [
                    'woocommerce_id' => $woocommerce_id,
                    'last_imported' => gmdate('Y-m-d H:i:s')
                ],
                [
                    'shopify_id' => $shopify_id,
                    'object_type' => 'product'
                ]
            );
        } else {
            // Create new mapping
            $result = $wpdb->insert(
                $table_name,
                [
                    'store_id' => $store_id,
                    'shopify_id' => $shopify_id,
                    'woocommerce_id' => $woocommerce_id,
                    'object_type' => 'product',
                    'last_imported' => gmdate('Y-m-d H:i:s')
                ]
            );
        }

        if (false !== $result) {
            // Invalidate related caches
            $cache_keys = [
                'product_mapping_' . md5($shopify_id),
                'woo_mapping_' . $woocommerce_id,
                'mapping_exists_' . md5($shopify_id . '_product')
            ];

            foreach ($cache_keys as $cache_key) {
                wp_cache_delete($cache_key, self::CACHE_GROUP_MAPPINGS);
            }
        }

        return false !== $result;
    }

    /**
     * Insert or update category mapping with cache invalidation
     *
     * @param string $shopify_collection_id The Shopify collection ID
     * @param int $woocommerce_category_id The WooCommerce category ID
     * @param int $store_id The store ID
     * @return bool True on success, false on failure
     */
    public static function upsert_category_mapping($shopify_collection_id, $woocommerce_category_id, $store_id = 1)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        // Check if mapping already exists
        $existing = self::mapping_exists($shopify_collection_id, 'category');

        if ($existing) {
            // Update existing mapping
            $result = $wpdb->update(
                $table_name,
                [
                    'woocommerce_id' => $woocommerce_category_id,
                    'last_imported' => gmdate('Y-m-d H:i:s')
                ],
                [
                    'shopify_id' => $shopify_collection_id,
                    'object_type' => 'category'
                ]
            );
        } else {
            // Create new mapping
            $result = $wpdb->insert(
                $table_name,
                [
                    'store_id' => $store_id,
                    'shopify_id' => $shopify_collection_id,
                    'woocommerce_id' => $woocommerce_category_id,
                    'object_type' => 'category',
                    'last_imported' => gmdate('Y-m-d H:i:s')
                ]
            );
        }

        if (false !== $result) {
            // Invalidate related caches
            $cache_key = 'mapping_exists_' . md5($shopify_collection_id . '_category');
            wp_cache_delete($cache_key, self::CACHE_GROUP_MAPPINGS);
        }

        return false !== $result;
    }

    /**
     * Delete mapping with cache invalidation
     *
     * @param array $where_conditions The WHERE conditions for deletion
     * @return bool True on success, false on failure
     */
    public static function delete_mapping($where_conditions)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WMSW_MAPPINGS_TABLE;

        $result = $wpdb->delete($table_name, $where_conditions);

        if (false !== $result) {
            // Invalidate all mapping caches since we don't know which specific ones
            wp_cache_flush_group(self::CACHE_GROUP_MAPPINGS);
        }

        return false !== $result;
    }

    /**
     * Clear all caches for this plugin
     */
    public static function clear_all_caches()
    {
        wp_cache_flush_group(self::CACHE_GROUP_IMPORT_LOGS);
        wp_cache_flush_group(self::CACHE_GROUP_MAPPINGS);
    }
}
