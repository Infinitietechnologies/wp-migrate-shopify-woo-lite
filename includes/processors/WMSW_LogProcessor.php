<?php

namespace ShopifyWooImporter\Processors;

if (!defined('ABSPATH')) exit;

class WMSW_LogProcessor
{
    /**
     * Add a log entry
     */
    public static function add_log($level, $message, $context = '', $task_id = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . (defined('WMSW_LOGS_TABLE') ? WMSW_LOGS_TABLE : 'wmsw_logs');
        $wpdb->insert($table, [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'task_id' => $task_id,
            'created_at' => current_time('mysql', 1)
        ]);
        return $wpdb->insert_id;
    }

    /**
     * Get logs with filters
     */
    public static function get_logs($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . (defined('WMSW_LOGS_TABLE') ? WMSW_LOGS_TABLE : 'wmsw_logs');
        
        // Build WHERE clause
        $where = [];
        $params = [];
        
        // Level filter
        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $params[] = $args['level'];
        }
        
        // Task ID filter
        if (!empty($args['task_id'])) {
            $where[] = 'task_id = %d';
            $params[] = $args['task_id'];
        }
        
        // Store ID filter (from context)
        if (!empty($args['store_id'])) {
            $where[] = 'context LIKE %s';
            $params[] = '%"store_id":"' . $args['store_id'] . '"%';
        }
        
        // Import type filter (from context)
        if (!empty($args['import_type'])) {
            $where[] = 'context LIKE %s';
            $params[] = '%"import_type":"' . $args['import_type'] . '"%';
        }
        
        // Status filter (from context)
        if (!empty($args['status'])) {
            $where[] = 'context LIKE %s';
            $params[] = '%"status":"' . $args['status'] . '"%';
        }
        
        // Date range filters
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where[] = 'message LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count for pagination
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table $where_sql", $params));
        
        // Pagination
        $per_page = isset($args['per_page']) ? intval($args['per_page']) : 20;
        $paged = isset($args['paged']) ? intval($args['paged']) : 1;
        $offset = ($paged - 1) * $per_page;
        
        // Build main query with pagination
        $all_params = array_merge($params, [$per_page, $offset]);
        
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", $all_params));
        
        // Calculate total pages
        $total_pages = ceil($total / $per_page);
        
        return [
            'logs' => $logs,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $paged,
            'per_page' => $per_page
        ];
    }

    /**
     * Clear logs older than X days
     */
    public static function clear_old_logs($days = 30)
    {
        global $wpdb;
        $table = $wpdb->prefix . (defined('WMSW_LOGS_TABLE') ? WMSW_LOGS_TABLE : 'wmsw_logs');
        $date = gmdate('Y-m-d H:i:s', strtotime('-' . intval($days) . ' days'));
        // Never delete logs with 'never_delete' in context
        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);
        $result = $wpdb->query($wpdb->prepare("DELETE FROM `{$escaped_table}` WHERE created_at < %s AND (context NOT LIKE %s OR context IS NULL)", $date, '%never_delete%'));
        // Add a special log entry that should never be cleared
        $user_id = \get_current_user_id();
        $user_data = \get_userdata($user_id);
        $user_name = $user_data ? $user_data->display_name : 'System';
        $never_delete_message = 'Logs cleared by ' . $user_name . ' at ' . gmdate('Y-m-d H:i:s');
        $wpdb->insert($table, [
            'level' => 'info',
            'message' => $never_delete_message,
            'context' => json_encode([
                'never_delete' => true,
                'user_id' => (\get_current_user_id() ? \get_current_user_id() : 'system'),
                'timestamp' => gmdate('Y-m-d H:i:s'),
            ]),
            'created_at' => current_time('mysql', 1)
        ]);
        return $result;
    }

    /**
     * Export logs as CSV (returns string)
     */
    public static function export_logs_csv($args = [])
    {
        $logs = self::get_logs($args);
        if (empty($logs)) return '';
        $csv = "ID,Level,Message,Context,Task ID,Created At\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%d","%s","%s","%s","%s","%s"\n',
                $log->id,
                $log->level,
                str_replace('"', '""', $log->message),
                str_replace('"', '""', $log->context),
                $log->task_id,
                $log->created_at
            );
        }
        return $csv;
    }
}
