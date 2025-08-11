<?php

namespace ShopifyWooImporter\Handlers;


/**
 * Plugin Deactivation Handler
 */
class WMSW_DeactivationHandler
{

    public function deactivate()
    {
        $this->clear_scheduled_events();
        $this->cleanup_temporary_data();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled cron events
     */
    private function clear_scheduled_events()
    {
        // Clear the scheduled task events
        $cron_events = [
            'wmsw_scheduled_tasks_check',
            'wmsw_cleanup_old_logs',
        ];

        foreach ($cron_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }
    }

    /**
     * Cleanup temporary data
     */
    private function cleanup_temporary_data()
    {
        // Remove transients
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_WMSW_%' 
            OR option_name LIKE '_transient_timeout_WMSW_%'"
        );

        // Delete any temporary files
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'swi-temp';

        if (is_dir($temp_dir)) {
            $this->recursive_delete($temp_dir);
        }
    }

    /**
     * Recursively delete a directory
     */
    private function recursive_delete($dir)
    {
        global $wp_filesystem;

        // Initialize the WP Filesystem if not already done
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem->is_dir($dir)) {
            return;
        }

        $files = $wp_filesystem->dirlist($dir);

        foreach ($files as $file => $details) {
            $path = trailingslashit($dir) . $file;

            if ('d' === $details['type']) {
                $this->recursive_delete($path);
            } else {
                $wp_filesystem->delete($path);
            }
        }

        $wp_filesystem->rmdir($dir);
    }
}
