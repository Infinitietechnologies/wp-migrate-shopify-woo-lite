<?php

namespace ShopifyWooImporter\Services;

/**
 * Database Service
 * 
 * Handles database operations for the plugin including:
 * - Schema management
 * - Data cleanup operations
 * - Transient management
 * - Database maintenance tasks
 */
class WMSWL_DatabaseService
{
    /**
     * Drop existing plugin tables
     * Used during plugin activation to ensure clean state
     * 
     * @param array $tables Array of table names to drop
     */
    public static function drop_tables($tables)
    {
        global $wpdb;

        foreach ($tables as $table) {
            $escaped_table = esc_sql($table);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query(
                $wpdb->prepare(
                    "DROP TABLE IF EXISTS `%s`",
                    $escaped_table
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        }
    }

    /**
     * Clean up plugin transients
     * Used during plugin deactivation to remove temporary data
     * 
     * @param string $prefix Transient prefix to match (default: 'WMSW_')
     */
    public static function cleanup_transients($prefix = 'WMSW_')
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_' . $prefix . '%',
                '_transient_timeout_' . $prefix . '%'
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Get active customer import jobs for a specific store
     * 
     * @param int $store_id Store ID to check for active imports
     * @return array|null Active import job data or null if no active imports
     */
    public static function get_active_customer_import($store_id)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_customer_import_%'"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        foreach ($transients as $transient) {
            $job_id = str_replace('_transient_customer_import_', '', $transient->option_name);
            $job_data = \maybe_unserialize($transient->option_value);

            if (
                is_array($job_data) &&
                isset($job_data['store_id']) &&
                $job_data['store_id'] == $store_id &&
                (!isset($job_data['status']['completed']) || !$job_data['status']['completed'])
            ) {
                return [
                    'job_id' => $job_id,
                    'percentage' => isset($job_data['status']['percentage']) ? $job_data['status']['percentage'] : 0,
                    'message' => isset($job_data['status']['last_message']) ? $job_data['status']['last_message'] : 'Processing...',
                    'imported' => isset($job_data['status']['imported']) ? $job_data['status']['imported'] : 0,
                    'updated' => isset($job_data['status']['updated']) ? $job_data['status']['updated'] : 0,
                    'failed' => isset($job_data['status']['failed']) ? $job_data['status']['failed'] : 0,
                    'skipped' => isset($job_data['status']['skipped']) ? $job_data['status']['skipped'] : 0,
                    'completed' => false
                ];
            }
        }

        return null;
    }

    /**
     * Clean up old log entries
     * 
     * @param int $days Number of days to keep logs (default: 30)
     * @return int Number of deleted entries
     */
    public static function cleanup_old_logs($days = 30)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . WMSW_LOGS_TABLE;
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM" . esc_sql($table_name) . "WHERE created_at < %s",
                $cutoff_date
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Check if a table exists
     * 
     * @param string $table_name Table name to check
     * @return bool True if table exists, false otherwise
     */
    public static function table_exists($table_name)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $table_exists !== null;
    }

    /**
     * Get database table size information
     * 
     * @param string $table_name Table name to check
     * @return array Table size information
     */
    public static function get_table_info($table_name)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = %s",
                $table_name
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $table_info ?: ['row_count' => 0, 'size_mb' => 0];
    }
}
