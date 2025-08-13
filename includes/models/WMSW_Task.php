<?php

namespace ShopifyWooImporter\Models;

use function esc_sql;
use function current_time;
use const ARRAY_A;

/**
 * Task Model
 * 
 * Handles all database interactions for scheduled tasks including:
 * - CRUD operations
 * - Task scheduling and management
 * - Status tracking and updates
 * - Task execution and monitoring
 */
class WMSW_Task
{
    private $id;
    private $store_id;
    private $task_type;
    private $frequency;
    private $last_run;
    private $next_run;
    private $status;
    private $options;
    private $created_at;

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
        $this->store_id = $data['store_id'] ?? null;
        $this->task_type = $data['task_type'] ?? '';
        $this->frequency = $data['frequency'] ?? '';
        $this->last_run = $data['last_run'] ?? null;
        $this->next_run = $data['next_run'] ?? null;
        $this->status = $data['status'] ?? 'active';
        $this->options = $data['options'] ?? '';
        $this->created_at = $data['created_at'] ?? null;
    }

    /**
     * Get table name
     */
    public static function get_table_name()
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
     * Save task to database
     */
    public function save()
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use $wpdb->esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = $wpdb->esc_sql($table);

        $data = [
            'store_id' => $this->store_id,
            'task_type' => $this->task_type,
            'frequency' => $this->frequency,
            'last_run' => $this->last_run,
            'next_run' => $this->next_run,
            'status' => $this->status,
            'options' => is_array($this->options) ? json_encode($this->options) : $this->options
        ];

        if ($this->id) {
            // Update existing
            $result = $wpdb->update(
                $escaped_table,
                $data,
                ['id' => $this->id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $escaped_table,
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            }
        }

        return $result !== false;
    }

    /**
     * Delete task from database
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        // Use $wpdb->esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = $wpdb->esc_sql($table);

        $result = $wpdb->delete(
            $escaped_table,
            ['id' => $this->id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Find task by ID
     */
    public static function find($id)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use $wpdb->esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = $wpdb->esc_sql($table);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . esc_sql($table) . " WHERE id = %d", $id),
            \ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get all tasks with optional filtering
     */
    public static function get_all($filters = [], $order_by = 'created_at DESC', $limit = null)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        // Build WHERE conditions
        $where_conditions = [];
        $where_values = [];

        // Store filter
        if (!empty($filters['store_id'])) {
            $where_conditions[] = "store_id = %d";
            $where_values[] = $filters['store_id'];
        }

        // Task type filter
        if (!empty($filters['task_type'])) {
            $where_conditions[] = "task_type = %s";
            $where_values[] = $filters['task_type'];
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }

        // Frequency filter
        if (!empty($filters['frequency'])) {
            $where_conditions[] = "frequency = %s";
            $where_values[] = $filters['frequency'];
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Execute query with proper $wpdb->prepare()
        if ($where_values) {
            $order_limit_clause = "ORDER BY {$order_by}";
            if ($limit) {
                $order_limit_clause .= " LIMIT %d";
            }

            $tasks = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM" . esc_sql($table) . "%s %s %s",
                    $where_clause,
                    $where_values,
                    $order_limit_clause,
                ),
                \ARRAY_A
            );
        } else {
            $order_limit_clause = "ORDER BY {$order_by}";
            if ($limit) {
                $order_limit_clause .= " LIMIT {$limit}";
            }

            $tasks = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM" . esc_sql($table) . "%s %s %s",
                    $where_clause,
                    $where_values,
                    $order_limit_clause,
                ),
                \ARRAY_A
            );
        }

        // Convert to model instances
        $result = [];
        foreach ($tasks as $task) {
            $result[] = new self($task);
        }

        return $result;
    }

    /**
     * Get pending tasks (ready to run)
     */
    public static function get_pending_tasks($limit = 5)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use $wpdb->esc_sql for table names since %i is only supported in WP 6.2+

        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . esc_sql($table) . "
                WHERE status = 'active' AND next_run <= %s
                ORDER BY next_run ASC
                LIMIT %d",
                current_time('mysql'),
                $limit
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($tasks as $task) {
            $result[] = new self($task);
        }

        return $result;
    }

    /**
     * Get tasks by store ID
     */
    public static function get_by_store($store_id, $status = null, $limit = null)
    {
        $filters = ['store_id' => $store_id];
        if ($status) {
            $filters['status'] = $status;
        }

        return self::get_all($filters, 'created_at DESC', $limit);
    }

    /**
     * Get tasks by type
     */
    public static function get_by_type($task_type, $status = null, $limit = null)
    {
        $filters = ['task_type' => $task_type];
        if ($status) {
            $filters['status'] = $status;
        }

        return self::get_all($filters, 'created_at DESC', $limit);
    }

    /**
     * Get tasks by status
     */
    public static function get_by_status($status, $limit = null)
    {
        return self::get_all(['status' => $status], 'created_at DESC', $limit);
    }

    /**
     * Get overdue tasks (past due)
     */
    public static function get_overdue_tasks($limit = 10)
    {
        global $wpdb;
        $table = self::get_table_name();

        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM" . esc_sql($table) . "
                WHERE status = 'active' AND next_run < %s
                ORDER BY next_run ASC
                LIMIT %d",
                current_time('mysql'),
                $limit
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($tasks as $task) {
            $result[] = new self($task);
        }

        return $result;
    }

    /**
     * Get running tasks
     */
    public static function get_running_tasks($limit = 10)
    {
        return self::get_by_status('running', $limit);
    }

    /**
     * Get failed tasks
     */
    public static function get_failed_tasks($limit = 10)
    {
        return self::get_by_status('failed', $limit);
    }

    /**
     * Get completed tasks
     */
    public static function get_completed_tasks($limit = 10)
    {
        return self::get_by_status('completed', $limit);
    }

    /**
     * Update task status
     */
    public static function update_status($task_id, $status)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        $result = $wpdb->update(
            $escaped_table,
            ['status' => $status],
            ['id' => $task_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update task schedule
     */
    public static function update_schedule($task_id, $next_run = null, $last_run = null)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        $data = [];
        if ($next_run) {
            $data['next_run'] = $next_run;
        }
        if ($last_run) {
            $data['last_run'] = $last_run;
        }

        if (empty($data)) {
            return false;
        }

        $result = $wpdb->update(
            $escaped_table,
            $data,
            ['id' => $task_id],
            array_fill(0, count($data), '%s'),
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Calculate next run time based on frequency
     */
    public static function calculate_next_run_time($frequency, $from_time = null)
    {
        if (!$from_time) {
            $from_time = time();
        }

        switch ($frequency) {
            case 'hourly':
                return gmdate('Y-m-d H:i:s', $from_time + 3600);

            case 'twicedaily':
                return gmdate('Y-m-d H:i:s', $from_time + 43200);

            case 'daily':
                return gmdate('Y-m-d H:i:s', $from_time + 86400);

            case 'weekly':
                return gmdate('Y-m-d H:i:s', $from_time + 604800);

            case 'monthly':
                return gmdate('Y-m-d H:i:s', strtotime('+1 month', $from_time));

            default:
                return gmdate('Y-m-d H:i:s', $from_time + 86400); // Default to daily
        }
    }

    /**
     * Create a new task
     */
    public static function create($store_id, $task_type, $frequency, $options = [], $next_run = null)
    {
        if (!$next_run) {
            $next_run = self::calculate_next_run_time($frequency);
        }

        $task = new self([
            'store_id' => $store_id,
            'task_type' => $task_type,
            'frequency' => $frequency,
            'next_run' => $next_run,
            'status' => 'active',
            'options' => $options
        ]);

        return $task->save() ? $task : false;
    }

    /**
     * Schedule a one-time task
     */
    public static function schedule_one_time($store_id, $task_type, $run_at, $options = [])
    {
        $task = new self([
            'store_id' => $store_id,
            'task_type' => $task_type,
            'frequency' => 'once',
            'next_run' => $run_at,
            'status' => 'active',
            'options' => $options
        ]);

        return $task->save() ? $task : false;
    }

    /**
     * Reschedule a task
     */
    public function reschedule($frequency = null)
    {
        if ($frequency) {
            $this->frequency = $frequency;
        }

        $this->last_run = current_time('mysql');
        $this->next_run = self::calculate_next_run_time($this->frequency, strtotime($this->last_run));
        $this->status = 'active';

        return $this->save();
    }

    /**
     * Mark task as running
     */
    public function mark_running()
    {
        $this->status = 'running';
        return $this->save();
    }

    /**
     * Mark task as completed
     */
    public function mark_completed()
    {
        $this->status = 'completed';
        $this->last_run = current_time('mysql');
        return $this->save();
    }

    /**
     * Mark task as failed
     */
    public function mark_failed()
    {
        $this->status = 'failed';
        $this->last_run = current_time('mysql');
        return $this->save();
    }

    /**
     * Pause task
     */
    public function pause()
    {
        $this->status = 'paused';
        return $this->save();
    }

    /**
     * Resume task
     */
    public function resume()
    {
        $this->status = 'active';
        return $this->save();
    }

    /**
     * Get task count by status
     */
    public static function get_count_by_status($status = null)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        if ($status) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table) . " WHERE status = %s",
                $status
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM" . esc_sql($table));
        }

        return (int) $count;
    }

    /**
     * Get task count by store
     */
    public static function get_count_by_store($store_id, $status = null)
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        if ($status) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table) . " WHERE store_id = %d AND status = %s",
                $store_id,
                $status
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . esc_sql($table) . " WHERE store_id = %d",
                $store_id
            ));
        }

        return (int) $count;
    }

    /**
     * Get task statistics
     */
    public static function get_statistics()
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        $stats = [
            'total' => 0,
            'active' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'paused' => 0
        ];

        // Get total count
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($table));

        // Get counts by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM " . esc_sql($table) . " GROUP BY status"
        );

        foreach ($status_counts as $status_count) {
            if (isset($stats[$status_count->status])) {
                $stats[$status_count->status] = (int) $status_count->count;
            }
        }

        return $stats;
    }

    /**
     * Clean up old completed/failed tasks
     */
    public static function cleanup_old_tasks($days = 30, $statuses = ['completed', 'failed'])
    {
        global $wpdb;
        $table = self::get_table_name();

        // Use esc_sql for table names since %i is only supported in WP 6.2+
        $escaped_table = esc_sql($table);

        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $status_list = "'" . implode("','", array_map('esc_sql', $statuses)) . "'";

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . esc_sql($table) . " WHERE status IN %s AND created_at < %s",
                $status_list,
                $cutoff_date
            )
        );

        return $result !== false ? (int) $result : false;
    }

    // Getters
    public function get_id()
    {
        return $this->id;
    }
    public function get_store_id()
    {
        return $this->store_id;
    }
    public function get_task_type()
    {
        return $this->task_type;
    }
    public function get_frequency()
    {
        return $this->frequency;
    }
    public function get_last_run()
    {
        return $this->last_run;
    }
    public function get_next_run()
    {
        return $this->next_run;
    }
    public function get_status()
    {
        return $this->status;
    }
    public function get_options()
    {
        return is_string($this->options) ? json_decode($this->options, true) : $this->options;
    }
    public function get_created_at()
    {
        return $this->created_at;
    }

    // Setters
    public function set_store_id($store_id)
    {
        $this->store_id = $store_id;
    }
    public function set_task_type($task_type)
    {
        $this->task_type = $task_type;
    }
    public function set_frequency($frequency)
    {
        $this->frequency = $frequency;
    }
    public function set_last_run($last_run)
    {
        $this->last_run = $last_run;
    }
    public function set_next_run($next_run)
    {
        $this->next_run = $next_run;
    }
    public function set_status($status)
    {
        $this->status = $status;
    }
    public function set_options($options)
    {
        $this->options = $options;
    }
}
