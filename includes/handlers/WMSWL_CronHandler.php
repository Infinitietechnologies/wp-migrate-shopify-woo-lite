<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Models\WMSWL_ImportLog;
use ShopifyWooImporter\Models\WMSWL_ShopifyStore;
use ShopifyWooImporter\Models\WMSWL_Task;

/**
 * Cron Handler for scheduled tasks
 */
class WMSWL_CronHandler
{

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('wmsw_sync_stores', [$this, 'sync_stores']);
        add_action('wmsw_cleanup_logs', [$this, 'cleanup_logs']);
        add_action('wmsw_background_process', [$this, 'process_background_tasks']);
    }

    /**
     * Sync all active stores
     */
    public function sync_stores()
    {
        $active_stores = WMSWL_ShopifyStore::get_all_active_stores(1);

        if (empty($active_stores)) {
            return;
        }

        // Process each store
        foreach ($active_stores as $store) {
            // Queue sync task for each store
            $this->queue_sync_task($store->id);
        }
    }

    /**
     * Queue a sync task for a store
     */
    private function queue_sync_task($store_id)
    {
        // Get import settings
        $import_settings = get_option('wmsw_import_settings_' . $store_id, []);

        // If no settings or sync not enabled, skip
        if (empty($import_settings) || empty($import_settings['auto_sync'])) {
            return;
        }

        // Add task to queue
        $task_data = [
            'store_id' => $store_id,
            'type' => $import_settings['import_type'] ?? 'products',
        ];

        $this->add_background_task('sync_store', $task_data);
    }

    /**
     * Clean up old logs
     */
    public function cleanup_logs()
    {
        $retention_days = get_option('wmsw_log_retention_days', 30);

        // Calculate cutoff date
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        WMSWL_ImportLog::cleanup_logs_by_date($cutoff_date);
    }

    /**
     * Process background tasks
     */
    public function process_background_tasks()
    {
        global $wpdb;

        // Get pending background tasks
        $tasks = $this->get_pending_tasks();

        if (empty($tasks)) {
            return;
        }

        // Process tasks
        foreach ($tasks as $task) {
            $this->process_task($task);
        }
    }

    /**
     * Get pending tasks
     */
    private function get_pending_tasks($limit = 5)
    {
        // Use the Task model to get pending tasks
        return WMSWL_Task::get_pending_tasks($limit);
    }

    /**
     * Process a task
     */
    private function process_task($task)
    {
        global $wpdb;

        // Mark task as running
        $this->update_task_status($task->id, 'running');

        try {
            // Initialize task handler
            $result = $this->execute_task($task);

            // Update task schedule based on frequency
            $this->update_task_schedule($task);

            // Mark task as completed or failed
            $status = $result ? 'active' : 'failed';
            $this->update_task_status($task->id, $status);
        } catch (\Exception $e) {
            // Mark task as failed
            $this->update_task_status($task->id, 'failed');
        }
    }

    /**
     * Execute a task
     */
    private function execute_task($task)
    {
        $result = false;

        // Decode options
        $options = json_decode($task->options, true);

        // Get store handler
        $store_handler = new WMSWL_StoreHandler();
        $store = $store_handler->get_store($task->store_id);

        if (!$store) {
            return false;
        }

        switch ($task->task_type) {
            case 'sync_products':
                // Handle product sync
                // Implementation would go here
                $result = true;
                break;

            case 'sync_orders':
                // Handle order sync
                // Implementation would go here
                $result = true;
                break;

            default:
                // Unknown task type
                $result = false;
        }

        return $result;
    }

    /**
     * Update task status
     */
    private function update_task_status($task_id, $status)
    {
        // Use the Task model to update task status
        WMSWL_Task::update_status($task_id, $status);
    }

    /**
     * Update task schedule
     */
    private function update_task_schedule($task)
    {
        // Calculate next run time based on frequency
        $next_run = $this->calculate_next_run_time($task->frequency);

        // Use the Task model to update task schedule
        WMSWL_Task::update_schedule($task->id, $next_run, current_time('mysql'));
    }

    /**
     * Calculate next run time
     */
    private function calculate_next_run_time($frequency)
    {
        // Use the Task model's method to calculate next run time
        return WMSWL_Task::calculate_next_run_time($frequency);
    }

    /**
     * Add a background task to the queue
     */
    private function add_background_task($task_type, $data = [])
    {
        // Use the Task model to create a new task
        $task = WMSWL_Task::schedule_one_time(
            $data['store_id'] ?? 0,
            $task_type,
            current_time('mysql'),
            $data
        );

        return $task ? $task->get_id() : false;
    }
}
