<?php

namespace ShopifyWooImporter\Models;

// WordPress functions
use function current_time;
use function wp_json_encode;

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

    // Import session specific properties
    private $store_id;
    private $import_type;
    private $status;
    private $options;
    private $items_total;
    private $items_processed;
    private $items_succeeded;
    private $items_failed;
    private $log_data;
    private $started_at;
    private $completed_at;
    private $updated_at;

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

        // Import session specific fields
        $this->store_id = $data['store_id'] ?? null;
        $this->import_type = $data['import_type'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->options = $data['options'] ?? null;
        $this->items_total = $data['items_total'] ?? null;
        $this->items_processed = $data['items_processed'] ?? null;
        $this->items_succeeded = $data['items_succeeded'] ?? null;
        $this->items_failed = $data['items_failed'] ?? null;
        $this->log_data = $data['log_data'] ?? null;
        $this->started_at = $data['started_at'] ?? null;
        $this->completed_at = $data['completed_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
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
     * Create a new import session
     * 
     * @param int $store_id The ID of the Shopify store
     * @param string $import_type The type of import (products, orders, customers, etc.)
     * @param array $options Import options
     * @return WMSW_ImportLog|false The created import session or false on failure
     */
    public static function createImportSession($store_id, $import_type, $options = [])
    {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'store_id' => $store_id,
            'import_type' => $import_type,
            'status' => 'initializing',
            'options' => is_array($options) ? \wp_json_encode($options) : $options,
            'created_at' => \current_time('mysql'),
            'updated_at' => \current_time('mysql')
        ];

        $result = $wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            return self::find($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Update import session data
     * 
     * @param array $data The data to update
     * @return bool Success or failure
     */
    public function updateImportSession($data)
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        // Always update the updated_at timestamp
        $data['updated_at'] = \current_time('mysql');

        // Prepare format array based on data types
        $formats = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['store_id', 'items_total', 'items_processed', 'items_succeeded', 'items_failed', 'items_skipped'])) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $this->id],
            $formats,
            ['%d']
        );

        // Update the model properties if successful
        if ($result !== false) {
            $this->populate(array_merge($this->toArray(), $data));
        }

        return $result !== false;
    }

    /**
     * Convert model to array
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'created_at' => $this->created_at,
            'items_skipped' => $this->items_skipped,
            'store_id' => $this->store_id,
            'import_type' => $this->import_type,
            'status' => $this->status,
            'options' => $this->options,
            'items_total' => $this->items_total,
            'items_processed' => $this->items_processed,
            'items_succeeded' => $this->items_succeeded,
            'items_failed' => $this->items_failed,
            'log_data' => $this->log_data,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get ID
     * 
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get status
     * 
     * @return string|null
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get message
     * 
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get items total
     * 
     * @return int|null
     */
    public function getItemsTotal()
    {
        return $this->items_total;
    }

    /**
     * Get items processed
     * 
     * @return int|null
     */
    public function getItemsProcessed()
    {
        return $this->items_processed;
    }

    /**
     * Find active import session for a store and type
     * 
     * @param int $store_id Store ID
     * @param string $import_type Import type
     * @return WMSW_ImportLog|null
     */
    public static function findActiveImportSession($store_id, $import_type)
    {
        global $wpdb;
        $table = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE store_id = %d AND import_type = %s AND status = 'in_progress' ORDER BY id DESC LIMIT 1",
                $store_id,
                $import_type
            ),
            \ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Count running imports except for the specified import ID
     * 
     * @param int $exclude_import_id The import ID to exclude from count
     * @return int Number of other running imports
     */
    public static function countRunningImportsExcept($exclude_import_id)
    {
        global $wpdb;
        $table = self::get_table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->esc_sql($table)} WHERE status = 'in_progress' AND id != %d",
                $exclude_import_id
            )
        );

        return intval($count);
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
                FROM " . esc_sql($logs_table) . " l
                LEFT JOIN " . esc_sql($tasks_table) . " t ON l.task_id = t.id
                LEFT JOIN" .  esc_sql($stores_table) . " s ON t.store_id = s.id
                " . esc_sql($where_clause) . "  %s
                ORDER BY l.created_at DESC
                LIMIT %d OFFSET %d",
                $where_values,
                $per_page,
                $offset
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
                    "SELECT COUNT(*) FROM " . esc_sql($logs_table) . " l
                     LEFT JOIN " . esc_sql($tasks_table) . " t ON l.task_id = t.id
                     LEFT JOIN " . esc_sql($stores_table) . " s ON t.store_id = s.id
                    " . esc_sql($where_clause) . "%s",
                    $where_values
                )
            );
        } else {
            $total_items = $wpdb->get_var(
                "SELECT COUNT(*) FROM" . esc_sql($logs_table) . " l
                 LEFT JOIN" . esc_sql($tasks_table) . " t ON l.task_id = t.id
                 LEFT JOIN" . esc_sql($stores_table) . " s ON t.store_id = s.id"
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
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM" . esc_sql($table));

        // Get counts by level
        $level_counts = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM" . esc_sql($table) . "GROUP BY level"
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
                "SELECT * FROM" . esc_sql($stores_table) . " WHERE is_active = %d ORDER BY store_name",
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
            "SELECT DISTINCT task_type FROM " . esc_sql($tasks_table) . " WHERE task_type IS NOT NULL ORDER BY task_type"
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
            $total_affected = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM" .
                        esc_sql($table)
                        . "WHERE %s %s",
                    $where_clause,
                    $where_values
                )
            );

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
                "DELETE FROM" . esc_sql($table) . "%s %s",
                $where_clause,
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
                 %s %s
                 ORDER BY l.created_at DESC",
                $where_clause,
                ...$where_values
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

    /**
     * Find stuck imports that are older than the specified time limit
     * 
     * @param int $store_id The store ID to check for stuck imports
     * @param string $import_type The import type ('products', 'orders', 'customers')
     * @param string $time_limit Time limit string (e.g., '-1 hour', '-30 minutes')
     * @return array Array of stuck import objects
     */
    public static function findStuckImports($store_id, $import_type, $time_limit = '-1 hour')
    {
        global $wpdb;
        $table = self::get_table_name();

        $cutoff_time = gmdate('Y-m-d H:i:s', strtotime($time_limit));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->esc_sql($table)} WHERE store_id = %d AND import_type = %s AND status = 'in_progress' AND created_at < %s",
                $store_id,
                $import_type,
                $cutoff_time
            )
        );

        $stuck_imports = [];
        if ($results) {
            foreach ($results as $row) {
                $stuck_imports[] = new self((array) $row);
            }
        }

        return $stuck_imports;
    }

    /**
     * Check for active customer imports for a specific store
     * 
     * @param int $store_id Store ID to check for active imports
     * @return array|null Active import job data or null if no active imports
     */
    public static function get_active_customer_import($store_id)
    {
        return \ShopifyWooImporter\Services\WMSW_DatabaseService::get_active_customer_import($store_id);
    }
}
