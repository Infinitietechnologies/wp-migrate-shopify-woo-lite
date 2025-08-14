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

        // Use esc_sql for table name since it's a constant table name
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $import_log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . esc_sql($table_name) . " WHERE id = %d", $import_id)
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $import_id]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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

        // Use esc_sql for table name since it's a constant table name
        $escaped_table = \esc_sql($table_name);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $import_id = $wpdb->get_var(
            "SELECT id FROM " . esc_sql($table_name) . " WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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

        // Use esc_sql for table name since it's a constant table name
        $escaped_table = \esc_sql($table_name);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT woocommerce_id FROM " . esc_sql($table_name) . " WHERE shopify_id = %s AND object_type = 'product'",
                $shopify_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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

        // Use esc_sql for table name since it's a constant table name
        $escaped_table = \esc_sql($table_name);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $shopify_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT shopify_id FROM " . esc_sql($table_name) . " WHERE woocommerce_id = %d AND object_type = 'product'",
                $woocommerce_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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

        // Use esc_sql for table name since it's a constant table name
        $escaped_table = \esc_sql($table_name);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . esc_sql($table_name) . " WHERE shopify_id = %s AND object_type = %s",
                $shopify_id,
                $object_type
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

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
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        } else {
            // Create new mapping
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
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
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        } else {
            // Create new mapping
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete($table_name, $where_conditions);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        if (false !== $result) {
            // Invalidate all mapping caches since we don't know which specific ones
            wp_cache_flush_group(self::CACHE_GROUP_MAPPINGS);
        }

        return false !== $result;
    }
}
