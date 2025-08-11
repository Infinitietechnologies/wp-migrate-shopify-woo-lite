<?php
namespace ShopifyWooImporter\Handlers;

/**
 * Cron Handler for scheduled tasks
 */
class WMSW_CronHandler {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wmsw_sync_stores', [$this, 'sync_stores']);
        add_action('wmsw_cleanup_logs', [$this, 'cleanup_logs']);
        add_action('wmsw_background_process', [$this, 'process_background_tasks']);
        

    }
    
    /**
     * Sync all active stores
     */
    public function sync_stores() {
        global $wpdb;
        
        // Get all active stores
        $stores_table = $wpdb->prefix . WMSW_STORES_TABLE;
        $active_stores = $wpdb->get_results(
            "SELECT * FROM {$stores_table} WHERE active = 1"
        );
        
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
    private function queue_sync_task($store_id) {
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
    public function cleanup_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . WMSW_LOGS_TABLE;
        $retention_days = get_option('wmsw_log_retention_days', 30);
        
        // Calculate cutoff date
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete logs older than retention period
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$logs_table} WHERE completed_at < %s",
                $cutoff_date
            )
        );
    }
    
    /**
     * Process background tasks
     */
    public function process_background_tasks() {
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
    private function get_pending_tasks($limit = 5) {
        global $wpdb;
        
        // Get tasks table
        $tasks_table = $wpdb->prefix . WMSW_TASKS_TABLE;
        
        // Get scheduled tasks that need to be run
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tasks_table} 
                WHERE status = 'active' AND next_run <= %s
                ORDER BY next_run ASC
                LIMIT %d",
                current_time('mysql'),
                $limit
            )
        );
        
        return $tasks;
    }
    
    /**
     * Process a task
     */
    private function process_task($task) {
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
            // Log error
            error_log('SWI Task Error: ' . $e->getMessage());
            
            // Mark task as failed
            $this->update_task_status($task->id, 'failed');
        }
    }
    
    /**
     * Execute a task
     */
    private function execute_task($task) {
        $result = false;
        
        // Decode options
        $options = json_decode($task->options, true);
        
        // Get store handler
        $store_handler = new WMSW_StoreHandler();
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
    private function update_task_status($task_id, $status) {
        global $wpdb;
        
        $tasks_table = $wpdb->prefix . WMSW_TASKS_TABLE;
        
        $wpdb->update(
            $tasks_table,
            ['status' => $status],
            ['id' => $task_id]
        );
    }
    
    /**
     * Update task schedule
     */
    private function update_task_schedule($task) {
        global $wpdb;
        
        $tasks_table = $wpdb->prefix . WMSW_TASKS_TABLE;
        
        // Calculate next run time based on frequency
        $next_run = $this->calculate_next_run_time($task->frequency);
        
        $wpdb->update(
            $tasks_table,
            [
                'last_run' => current_time('mysql'),
                'next_run' => $next_run,
            ],
            ['id' => $task->id]
        );
    }
    
    /**
     * Calculate next run time
     */
    private function calculate_next_run_time($frequency) {
        $time = time();
        
        switch ($frequency) {
            case 'hourly':
                return gmdate('Y-m-d H:i:s', $time + 3600);
                
            case 'twicedaily':
                return gmdate('Y-m-d H:i:s', $time + 43200);
                
            case 'daily':
                return gmdate('Y-m-d H:i:s', $time + 86400);
                
            case 'weekly':
                return gmdate('Y-m-d H:i:s', $time + 604800);
                
            default:
                return gmdate('Y-m-d H:i:s', $time + 86400);
        }
    }
    
    /**
     * Add a background task to the queue
     */
    private function add_background_task($task_type, $data = []) {
        global $wpdb;
        
        $tasks_table = $wpdb->prefix . WMSW_TASKS_TABLE;
        
        $wpdb->insert(
            $tasks_table,
            [
                'store_id' => $data['store_id'] ?? 0,
                'task_type' => $task_type,
                'frequency' => $data['frequency'] ?? 'once',
                'next_run' => current_time('mysql'),
                'status' => 'active',
                'options' => json_encode($data),
            ]
        );
        
        return $wpdb->insert_id;
    }
    

}
