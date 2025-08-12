<?php

namespace ShopifyWooImporter\Models;

/**
 * Import Log Model
 * 
 * Handles all database interactions for import logs including:
 * - CRUD operations
 * - Filtering and pagination
 * - Statistics and reporting
 * - Data export functionality
 */
class WMSW_ImportLog
{
    private $id;
    private $task_id;
    private $level;
    private $message;
    private $context;
    private $created_at;
    private $items_skipped;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * Populate model with data
     */
    public function populate($data)
    {
        $this->id = $data['id'] ?? null;
        $this->task_id = $data['task_id'] ?? null;
        $this->level = $data['level'] ?? '';
        $this->message = $data['message'] ?? '';
        $this->context = $data['context'] ?? '';
        $this->created_at = $data['created_at'] ?? null;
        $this->items_skipped = $data['items_skipped'] ?? 0;
    }

    /**
     * Get table name
     */
    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . WMSW_LOGS_TABLE;
    }

    /**
     * Get tasks table name
     */
    public static function get_tasks_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . WMSW_TASKS_TABLE;
    }

    /**
     * Get stores table name
     */
    public static function get_stores_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . WMSW_STORES_TABLE;
    }

    /**
     * Save log to database
     */
    public function save()
    {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'task_id' => $this->task_id,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'items_skipped' => $this->items_skipped
        ];

        if ($this->id) {
            // Update existing
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $this->id],
                ['%d', '%s', '%s', '%s', '%d'],
                ['%d']
            );
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table,
                $data,
                ['%d', '%s', '%s', '%s', '%d']
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            }
        }

        return $result !== false;
    }

    /**
     * Delete log from database
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $result = $wpdb->delete(
            self::get_table_name(),
            ['id' => $this->id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Find log by ID
     */
    public static function find($id)
    {
        global $wpdb;
        $table = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->esc_sql($table)} WHERE id = %d", $id),
            \ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get logs with advanced filtering and pagination
     */
    public static function get_logs($filters = [], $page = 1, $per_page = 50)
    {
        global $wpdb;

        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();
        $stores_table = self::get_stores_table_name();

        // Build WHERE conditions
        $where_conditions = [];
        $where_values = [];

        // Level filter
        if (!empty($filters['level'])) {
            $where_conditions[] = "l.level = %s";
            $where_values[] = $filters['level'];
        }

        // Store filter
        if (!empty($filters['store_id'])) {
            $where_conditions[] = "s.id = %d";
            $where_values[] = $filters['store_id'];
        }

        // Task type filter
        if (!empty($filters['task_type'])) {
            $where_conditions[] = "t.task_type = %s";
            $where_values[] = $filters['task_type'];
        }

        // Search query
        if (!empty($filters['search'])) {
            $where_conditions[] = "l.message LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(l.created_at) >= %s";
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(l.created_at) <= %s";
            $where_values[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Get logs with pagination
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, t.task_type, s.store_name
                 FROM {$wpdb->esc_sql($logs_table)} l
                 LEFT JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 LEFT JOIN {$wpdb->esc_sql($stores_table)} s ON t.store_id = s.id
                 {$where_clause}
                 ORDER BY l.created_at DESC
                 LIMIT %d OFFSET %d",
                array_merge($where_values, [$per_page, $offset])
            )
        );

        return $logs;
    }

    /**
     * Get total count of logs with filters
     */
    public static function get_total_count($filters = [])
    {
        global $wpdb;

        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();
        $stores_table = self::get_stores_table_name();

        // Build WHERE conditions
        $where_conditions = [];
        $where_values = [];

        // Level filter
        if (!empty($filters['level'])) {
            $where_conditions[] = "l.level = %s";
            $where_values[] = $filters['level'];
        }

        // Store filter
        if (!empty($filters['store_id'])) {
            $where_conditions[] = "s.id = %d";
            $where_values[] = $filters['store_id'];
        }

        // Task type filter
        if (!empty($filters['task_type'])) {
            $where_conditions[] = "t.task_type = %s";
            $where_values[] = $filters['task_type'];
        }

        // Search query
        if (!empty($filters['search'])) {
            $where_conditions[] = "l.message LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(l.created_at) >= %s";
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(l.created_at) <= %s";
            $where_values[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count
        if ($where_values) {
            $total_items = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->esc_sql($logs_table)} l
                     LEFT JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                     LEFT JOIN {$wpdb->esc_sql($stores_table)} s ON t.store_id = s.id
                     {$where_clause}",
                    $where_values
                )
            );
        } else {
            $total_items = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->esc_sql($logs_table)} l
                 LEFT JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 LEFT JOIN {$wpdb->esc_sql($stores_table)} s ON t.store_id = s.id"
            );
        }

        return (int) $total_items;
    }

    /**
     * Get logs by task ID
     */
    public static function get_logs_by_task($task_id)
    {
        global $wpdb;
        $table = self::get_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE task_id = %d ORDER BY created_at DESC",
                $task_id
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get logs by level
     */
    public static function get_logs_by_level($level, $limit = 100)
    {
        global $wpdb;
        $table = self::get_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE level = %s ORDER BY created_at DESC LIMIT %d",
                $level,
                $limit
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get logs by store
     */
    public static function get_logs_by_store($store_id, $limit = 100)
    {
        global $wpdb;
        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.* FROM {$wpdb->esc_sql($logs_table)} l
                 INNER JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 WHERE t.store_id = %d
                 ORDER BY l.created_at DESC
                 LIMIT %d",
                $store_id,
                $limit
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get statistics for different log levels
     */
    public static function get_statistics()
    {
        global $wpdb;
        $table = self::get_table_name();

        $stats = [
            'total' => 0,
            'error' => 0,
            'warning' => 0,
            'info' => 0,
            'debug' => 0
        ];

        // Get total count
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->esc_sql($table)}");

        // Get counts by level
        $level_counts = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$wpdb->esc_sql($table)} GROUP BY level"
        );

        foreach ($level_counts as $level_count) {
            if (isset($stats[$level_count->level])) {
                $stats[$level_count->level] = (int) $level_count->count;
            }
        }

        return $stats;
    }

    /**
     * Get available stores for filtering
     */
    public static function get_available_stores()
    {
        global $wpdb;
        $stores_table = self::get_stores_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($stores_table)} WHERE status = %d ORDER BY store_name",
                1
            )
        );
    }

    /**
     * Get available task types for filtering
     */
    public static function get_available_task_types()
    {
        global $wpdb;
        $tasks_table = self::get_tasks_table_name();

        return $wpdb->get_results(
            "SELECT DISTINCT task_type FROM {$wpdb->esc_sql($tasks_table)} WHERE task_type IS NOT NULL ORDER BY task_type"
        );
    }

    /**
     * Clear old logs (older than specified days)
     */
    public static function clear_old_logs($days = 30)
    {
        global $wpdb;
        $table = self::get_table_name();

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->esc_sql($table)} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result !== false;
    }

    /**
     * Clean up logs based on passed date
     * 
     * @param string $cutoff_date Date in 'Y-m-d H:i:s' format before which logs will be deleted
     * @param array $options Additional options for cleanup
     * @return array Result of cleanup operation
     */
    public static function cleanup_logs_by_date($cutoff_date, $options = [])
    {
        global $wpdb;
        $table = self::get_table_name();

        // Default options
        $default_options = [
            'preserve_never_delete' => true,  // Preserve logs with 'never_delete' context
            'dry_run' => false,               // If true, only count logs that would be deleted
            'batch_size' => 1000,             // Process in batches to avoid memory issues
            'log_operation' => true           // Log the cleanup operation itself
        ];

        $options = array_merge($default_options, $options);

        // Validate date format
        if (!is_string($cutoff_date) || !strtotime($cutoff_date)) {
            return [
                'success' => false,
                'message' => 'Invalid cutoff date format. Expected Y-m-d H:i:s format.',
                'deleted_count' => 0,
                'total_affected' => 0
            ];
        }

        // Build WHERE conditions
        $where_conditions = ["created_at < %s"];
        $where_values = [$cutoff_date];

        // Preserve logs with 'never_delete' context if option is enabled
        if ($options['preserve_never_delete']) {
            $where_conditions[] = "(context NOT LIKE %s OR context IS NULL)";
            $where_values[] = '%never_delete%';
        }

        // Build the full WHERE clause for the query string
        $where_clause = implode(' AND ', $where_conditions);

        if ($options['dry_run']) {
            // Use proper $wpdb->prepare() pattern
            $total_affected = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->esc_sql($table)}` WHERE `{$where_clause}`", ...$where_values));

            return [
                'success' => true,
                'message' => 'Dry run completed. No logs were deleted.',
                'deleted_count' => 0,
                'total_affected' => (int)$total_affected,
                'dry_run' => true
            ];
        }

        // Perform the actual deletion
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->esc_sql($table)} {$where_clause}",
                $where_values
            )
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to delete logs. Database error: ' . $wpdb->last_error,
                'deleted_count' => 0,
                'total_affected' => 0
            ];
        }

        $deleted_count = (int)$result;

        // Log the cleanup operation if enabled
        if ($options['log_operation']) {
            self::create(
                null, // No task_id for system operations
                'info',
                sprintf('Cleanup operation completed: Deleted %d logs older than %s', $deleted_count, $cutoff_date),
                json_encode([
                    'operation' => 'cleanup_logs_by_date',
                    'cutoff_date' => $cutoff_date,
                    'deleted_count' => $deleted_count,
                    'options' => $options
                ])
            );
        }

        return [
            'success' => true,
            'message' => sprintf('Successfully deleted %d logs older than %s', $deleted_count, $cutoff_date),
            'deleted_count' => $deleted_count,
            'total_affected' => $deleted_count,
            'dry_run' => false
        ];
    }

    /**
     * Export logs to CSV format
     */
    public static function export_to_csv($filters = [])
    {
        global $wpdb;

        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();
        $stores_table = self::get_stores_table_name();

        // Build WHERE conditions
        $where_conditions = [];
        $where_values = [];

        // Level filter
        if (!empty($filters['level'])) {
            $where_conditions[] = "l.level = %s";
            $where_values[] = $filters['level'];
        }

        // Store filter
        if (!empty($filters['store_id'])) {
            $where_conditions[] = "s.id = %d";
            $where_values[] = $filters['store_id'];
        }

        // Task type filter
        if (!empty($filters['task_type'])) {
            $where_conditions[] = "t.task_type = %s";
            $where_values[] = $filters['task_type'];
        }

        // Search query
        if (!empty($filters['search'])) {
            $where_conditions[] = "l.message LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(l.created_at) >= %s";
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(l.created_at) <= %s";
            $where_values[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get all logs for export
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, t.task_type, s.store_name
                 FROM {$wpdb->esc_sql($logs_table)} l
                 LEFT JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 LEFT JOIN {$wpdb->esc_sql($stores_table)} s ON t.store_id = s.id
                 {$where_clause}
                 ORDER BY l.created_at DESC",
                $where_values
            )
        );

        return $logs;
    }

    /**
     * Get pagination info
     */
    public static function get_pagination_info($total_items, $page, $per_page)
    {
        $total_pages = ceil($total_items / $per_page);
        $from = (($page - 1) * $per_page) + 1;
        $to = min($page * $per_page, $total_items);

        return [
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_items' => $total_items,
            'from' => $from,
            'to' => $to,
            'has_previous' => $page > 1,
            'has_next' => $page < $total_pages,
            'previous_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $page < $total_pages ? $page + 1 : null
        ];
    }

    /**
     * Create a new log entry
     */
    public static function create($task_id, $level, $message, $context = '', $items_skipped = 0)
    {
        $log = new self([
            'task_id' => $task_id,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'items_skipped' => $items_skipped,
            'created_at' => \current_time('mysql')
        ]);

        return $log->save() ? $log : false;
    }

    /**
     * Get logs with full context (including task and store information)
     */
    public static function get_logs_with_context($filters = [], $page = 1, $per_page = 50)
    {
        $logs = self::get_logs($filters, $page, $per_page);
        $total_count = self::get_total_count($filters);

        return [
            'logs' => $logs,
            'total_count' => $total_count,
            'pagination' => self::get_pagination_info($total_count, $page, $per_page)
        ];
    }

    /**
     * Get logs by date range
     */
    public static function get_logs_by_date_range($start_date, $end_date, $limit = 100)
    {
        global $wpdb;
        $table = self::get_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE DATE(created_at) BETWEEN %s AND %s ORDER BY created_at DESC LIMIT %d",
                $start_date,
                $end_date,
                $limit
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get error logs only
     */
    public static function get_error_logs($limit = 100)
    {
        return self::get_logs_by_level('error', $limit);
    }

    /**
     * Get warning logs only
     */
    public static function get_warning_logs($limit = 100)
    {
        return self::get_logs_by_level('warning', $limit);
    }

    /**
     * Get info logs only
     */
    public static function get_info_logs($limit = 100)
    {
        return self::get_logs_by_level('info', $limit);
    }

    /**
     * Get debug logs only
     */
    public static function get_debug_logs($limit = 100)
    {
        return self::get_logs_by_level('debug', $limit);
    }

    /**
     * Get logs count by level
     */
    public static function get_count_by_level($level)
    {
        global $wpdb;
        $table = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->esc_sql($table)} WHERE level = %s", $level)
        );
    }

    /**
     * Get logs count by store
     */
    public static function get_count_by_store($store_id)
    {
        global $wpdb;
        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->esc_sql($logs_table)} l
                 INNER JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 WHERE t.store_id = %d",
                $store_id
            )
        );
    }

    /**
     * Get logs count by task type
     */
    public static function get_count_by_task_type($task_type)
    {
        global $wpdb;
        $logs_table = self::get_table_name();
        $tasks_table = self::get_tasks_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->esc_sql($logs_table)} l
                 INNER JOIN {$wpdb->esc_sql($tasks_table)} t ON l.task_id = t.id
                 WHERE t.task_type = %s",
                $task_type
            )
        );
    }

    /**
     * Search logs by message content
     */
    public static function search_logs($search_term, $limit = 100)
    {
        global $wpdb;
        $table = self::get_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE message LIKE %s ORDER BY created_at DESC LIMIT %d",
                '%' . $wpdb->esc_like($search_term) . '%',
                $limit
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get recent logs (last N days)
     */
    public static function get_recent_logs($days = 7, $limit = 100)
    {
        global $wpdb;
        $table = self::get_table_name();

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY created_at DESC LIMIT %d",
                $days,
                $limit
            ),
            \ARRAY_A
        );

        $result = [];
        foreach ($logs as $log) {
            $result[] = new self($log);
        }

        return $result;
    }

    /**
     * Get logs summary for dashboard
     */
    public static function get_dashboard_summary()
    {
        $stats = self::get_statistics();
        $recent_logs = self::get_recent_logs(1, 10); // Last 24 hours, top 10

        return [
            'total_logs' => $stats['total'],
            'error_count' => $stats['error'],
            'warning_count' => $stats['warning'],
            'info_count' => $stats['info'],
            'debug_count' => $stats['debug'],
            'recent_logs' => $recent_logs,
            'error_rate' => $stats['total'] > 0 ? round(($stats['error'] / $stats['total']) * 100, 2) : 0
        ];
    }

    // Getters
    public function get_id()
    {
        return $this->id;
    }
    public function get_task_id()
    {
        return $this->task_id;
    }
    public function get_level()
    {
        return $this->level;
    }
    public function get_message()
    {
        return $this->message;
    }
    public function get_context()
    {
        return $this->context;
    }
    public function get_created_at()
    {
        return $this->created_at;
    }
    public function get_items_skipped()
    {
        return $this->items_skipped;
    }

    // Setters
    public function set_task_id($task_id)
    {
        $this->task_id = $task_id;
    }
    public function set_level($level)
    {
        $this->level = $level;
    }
    public function set_message($message)
    {
        $this->message = $message;
    }
    public function set_context($context)
    {
        $this->context = $context;
    }
    public function set_items_skipped($items_skipped)
    {
        $this->items_skipped = $items_skipped;
    }
}
