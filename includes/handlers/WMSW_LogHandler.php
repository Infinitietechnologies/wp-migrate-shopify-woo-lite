<?php

namespace ShopifyWooImporter\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
use ShopifyWooImporter\Processors\WMSW_LogProcessor;
use ShopifyWooImporter\Services\WMSW_Logger;

class WMSW_LogHandler
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers
        \add_action('wp_ajax_wmsw_add_log', array($this, 'add_log'));
        \add_action('wp_ajax_wmsw_get_logs', array($this, 'get_logs'));
        \add_action('wp_ajax_wmsw_clear_old_logs', array($this, 'clear_old_logs'));
        \add_action('wp_ajax_wmsw_export_logs_csv', array($this, 'export_logs_csv'));
    }


    /**
     * AJAX: Add a log entry
     */
    public function add_log()
    {
        if (!WMSW_SecurityHelper::verifyAdminRequest()) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
        }
        $logger = new WMSW_Logger();
        try {
            $logger->info('Log entry created', [
                'status' => 'pending',
                'data' => $_POST
            ]);
            // Simulate process
            $logger->info('Log entry in progress', [
                'status' => 'in_progress',
                'data' => $_POST
            ]);
            $logger->info('Log entry completed', [
                'status' => 'completed',
                'data' => $_POST
            ]);
            \wp_send_json_success(['message' => 'Log entry created and completed.']);
        } catch (\Exception $e) {
            $logger->error('Log entry failed', [
                    'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => $_POST
                ]);
            \wp_send_json_error(['message' => 'Failed to add log: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get logs
     */
    public function get_logs()
    {
        if (!WMSW_SecurityHelper::verifyAdminRequest()) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
        }
        try {
            $logs_data = WMSW_LogProcessor::get_logs($_POST);
            \wp_send_json_success([
                'logs' => $logs_data['logs'] ?? [],
                'total' => $logs_data['total'] ?? 0,
                'total_pages' => $logs_data['total_pages'] ?? 0,
                'current_page' => $logs_data['current_page'] ?? 1
            ]);
        } catch (\Exception $e) {
            $logger = new WMSW_Logger();
            $logger->error('Failed to get logs', [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => $_POST
            ]);
            \wp_send_json_error(['message' => 'Failed to get logs: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Clear logs older than X days
     */
    public function clear_old_logs()
    {
        if (!WMSW_SecurityHelper::verifyAdminRequest()) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
        }
        $logger = new WMSW_Logger();
        try {
            $logger->info('Clear old logs started', [
                'status' => 'pending',
                    'action' => 'clear_old_logs',
                'user_id' => \get_current_user_id(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
            $result = WMSW_LogProcessor::clear_old_logs($days);
            // Add a special log entry that should never be cleared
            $user_id = \get_current_user_id();
            $user_data = \get_userdata($user_id);
            $user_name = $user_data ? $user_data->display_name : 'Unknown User';
            $never_delete_message = 'Logs cleared by ' . $user_name . ' at ' . gmdate('Y-m-d H:i:s');
            $logger->info($never_delete_message, [
                'never_delete' => true,
                'user_id' => \get_current_user_id(),
                'timestamp' => gmdate('Y-m-d H:i:s'),
            ]);
            $logger->info('Clear old logs completed', [
                'status' => 'completed',
                'days' => $days,
                'result' => $result
            ]);
            \wp_send_json_success(['result' => $result]);
        } catch (\Exception $e) {
            $logger->error('Clear old logs failed', [
                    'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => $_POST
                ]);
            \wp_send_json_error(['message' => 'Failed to clear logs: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Export logs as CSV
     */
    public function export_logs_csv()
    {
        if (!WMSW_SecurityHelper::verifyAdminRequest()) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
        }
        $logger = new WMSW_Logger();
        try {
            $logger->info('Export logs started', [
                'status' => 'pending',
                    'action' => 'export_logs',
                'user_id' => \get_current_user_id(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $csv = WMSW_LogProcessor::export_logs_csv($_POST);
            $logger->info('Export logs completed', [
                'status' => 'completed',
            ]);
            \wp_send_json_success(['csv' => $csv]);
        } catch (\Exception $e) {
            $logger->error('Export logs failed', [
                    'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => $_POST
            ]);
            \wp_send_json_error(['message' => 'Failed to export logs: ' . $e->getMessage()]);
        }
    }


}
