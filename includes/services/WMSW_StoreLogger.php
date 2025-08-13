<?php

/**
 * Store Logger class
 */

namespace ShopifyWooImporter\Services;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WMSW_StoreLogger
{

    /**
     * Cache group for store logs
     */
    const CACHE_GROUP = 'wmsw_store_logs';

    /**
     * Log a store action
     *
     * @param int $store_id The store ID
     * @param string $action_type The action type (create, update, delete, etc)
     * @param array $details Any additional details to log
     * @return bool Whether the log was successfully created
     */
    public static function log($store_id, $action_type, $details = [])
    {
        global $wpdb;

        $table = $wpdb->prefix . WMSW_STORE_LOGS_TABLE;
        $user_id = \get_current_user_id();

        $result = $wpdb->insert(
            $table,
            [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'action_type' => $action_type,
                'action_details' => \json_encode($details),
                'created_at' => \current_time('mysql')
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s'
            ]
        );

        if ($result !== false) {
            // Clear cache for this store's logs
            self::clear_store_logs_cache($store_id);
            return true;
        }

        return false;
    }

    /**
     * Get logs for a specific store
     *
     * @param int $store_id The store ID
     * @param int $limit Number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public static function get_store_logs($store_id, $limit = 50, $offset = 0)
    {
        // Try to get from cache first
        $cache_key = "store_logs_{$store_id}_{$limit}_{$offset}";
        $cached_results = \wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_results !== false) {
            return $cached_results;
        }

        global $wpdb;

        $table = $wpdb->prefix . WMSW_STORE_LOGS_TABLE;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name 
             FROM " . esc_sql($table) . " l
             LEFT JOIN " . esc_sql($wpdb->users) . " u ON l.user_id = u.ID
             WHERE l.store_id = %d
             ORDER BY l.created_at DESC
             LIMIT %d OFFSET %d",
            $store_id,
            $limit,
            $offset
        ));
        $results = $results ? $results : [];

        // Cache the results for 5 minutes
        \wp_cache_set($cache_key, $results, self::CACHE_GROUP, 300);

        return $results;
    }

    /**
     * Get all store logs
     *
     * @param int $limit Number of logs to return
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public static function get_all_logs($limit = 100, $offset = 0)
    {
        // Try to get from cache first
        $cache_key = "all_logs_{$limit}_{$offset}";
        $cached_results = \wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached_results !== false) {
            return $cached_results;
        }

        global $wpdb;

        $table = $wpdb->prefix . WMSW_STORE_LOGS_TABLE;

        $stores_table = $wpdb->prefix . WMSW_STORES_TABLE;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as user_name, s.store_name 
             FROM " . esc_sql($table) . " l
             LEFT JOIN " . esc_sql($wpdb->users) . "u ON l.user_id = u.ID
             LEFT JOIN " . esc_sql($stores_table) . " s ON l.store_id = s.id
             ORDER BY l.created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        $results = $results ? $results : [];

        // Cache the results for 5 minutes
        \wp_cache_set($cache_key, $results, self::CACHE_GROUP, 300);

        return $results;
    }

    /**
     * Clear cache for a specific store's logs
     *
     * @param int $store_id The store ID
     */
    private static function clear_store_logs_cache($store_id)
    {
        // Clear specific store logs cache
        \wp_cache_delete("store_logs_{$store_id}_50_0", self::CACHE_GROUP);
        \wp_cache_delete("store_logs_{$store_id}_100_0", self::CACHE_GROUP);

        // Clear all logs cache since it might contain this store's data
        \wp_cache_delete("all_logs_100_0", self::CACHE_GROUP);
        \wp_cache_delete("all_logs_50_0", self::CACHE_GROUP);
    }

    /**
     * Clear all store logs cache
     */
    public static function clear_all_cache()
    {
        \wp_cache_flush_group(self::CACHE_GROUP);
    }
}
