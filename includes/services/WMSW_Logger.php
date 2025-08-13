<?php

namespace ShopifyWooImporter\Services;

/**
 * Logger Service
 */
class WMSW_Logger
{

    private $task_id;

    public function __construct($task_id = null)
    {
        $this->task_id = $task_id;
    }

    /**
     * Log info message
     */
    public function info($message, $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log debug message (only when debug is enabled)
     */
    public function debug($message, $context = []): void
    {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $this->log('debug', $message, $context);
    }

    /**
     * Public method to check if debug mode is enabled
     * Useful for other classes to check debug status
     */
    public function isDebugEnabled(): bool
    {
        return $this->is_debug_enabled();
    }

    /**
     * Log debug message to error_log only when debug is enabled
     * For backwards compatibility with existing error_log calls
     */
    public function debugLog($message, $prefix = '[SWI Debug]'): void
    {
        if (!$this->is_debug_enabled()) {
            return;
        }
    }

    /**
     * Static method to check debug mode without instantiating logger
     */
    public static function isDebugModeEnabled(): bool
    {
        // Check plugin-specific debug setting first
        $plugin_debug = \get_option('wmsw_enable_debug', false);
        if ($plugin_debug) {
            return true;
        }

        // Check plugin settings from options table
        $options = \get_option('wmsw_options', []);
        if (!empty($options['enable_debug_mode'])) {
            return true;
        }

        // Fall back to WordPress debug mode
        return (defined('WP_DEBUG') && \WP_DEBUG);
    }

    /**
     * Log message to database and/or file
     */
    private function log($level, $message, $context = []): void
    {
        // Format message with context if it's a string with placeholders
        if (is_string($message) && !empty($context)) {
            $message = $this->interpolate($message, $context);
        }

        // Store context as JSON if it's not empty and not already included in the message
        $context_json = null;
        if (!empty($context)) {
            $context_json = json_encode($context, JSON_PRETTY_PRINT);
        }

        // Log to database if task ID is provided
        if ($this->task_id) {
            $this->log_to_database($level, $message, $context_json);
        }

        // Log to file
        $this->log_to_file($level, $message);
    }

    /**
     * Log message to database
     */
    private function log_to_database($level, $message, $context_json = null): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . WMSW_LOGS_TABLE;

        // If message is complex and context_json isn't provided, convert it to JSON
        if ((is_array($message) || is_object($message)) && $context_json === null) {
            $context_json = json_encode($message, JSON_PRETTY_PRINT);
            $message = is_array($message) ? 'See details in context' : 'Error: ' . get_class($message);
        }

        // Make sure message is a string
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        // Prevent empty or null messages from being logged
        if (empty($message) || trim($message) === '') {
            return;
        }

        // Note: WordPress doesn't provide a built-in method for inserting into custom tables
        // This is the standard approach used in WordPress plugins for custom table operations
        $result = $wpdb->insert(
            $table_name,
            [
                'task_id' => $this->task_id,
                'level' => $level,
                'message' => $message,
                'context' => $context_json,
                'created_at' => \current_time('mysql')
            ],
            [
                '%d',  // task_id
                '%s',  // level
                '%s',  // message
                '%s',  // context
                '%s'   // created_at
            ]
        );

        // Check for DB errors and clear cache if successful
        if ($result) {
            // Clear any cached log data since we've added a new entry
            \wp_cache_delete('wmsw_logs_recent', 'wmsw_logs');
            \wp_cache_delete('wmsw_logs_count', 'wmsw_logs');
        }
    }

    /**
     * Log message to file
     */
    private function log_to_file($level, $message): void
    {
        // Check if file logging is enabled
        if (!$this->is_file_logging_enabled()) {
            return;
        }

        $date = gmdate('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        $log_message = "[$date] [$level_upper] $message" . PHP_EOL;

        $file = $this->get_log_file();

        // Attempt to write to the log file
        if ($file) {
            @file_put_contents($file, $log_message, FILE_APPEND);
        }
    }

    /**
     * Get log file path
     */    private function get_log_file(): string
    {
        $upload_dir = \wp_upload_dir();
        $logs_dir = \trailingslashit($upload_dir['basedir']) . 'swi-logs';

        // Create logs directory if it doesn't exist
        if (!is_dir($logs_dir)) {
            \wp_mkdir_p($logs_dir);

            // Create .htaccess file to protect logs
            @file_put_contents($logs_dir . '/.htaccess', 'deny from all');
            @file_put_contents($logs_dir . '/index.php', '<?php // Silence is golden');
        }

        // Return log file path
        return $logs_dir . '/swi-' . gmdate('Y-m-d') . '.log';
    }

    /**
     * Replace placeholders in message with context values
     */
    private function interpolate($message, $context = []): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // Interpolate replacement values into the message
        return strtr($message, $replace);
    }

    /**
     * Check if debug mode is enabled
     * Checks both plugin settings and WordPress debug mode
     */
    private function is_debug_enabled(): bool
    {
        // Check plugin-specific debug setting first
        $plugin_debug = \get_option('wmsw_enable_debug', false);
        if ($plugin_debug) {
            return true;
        }

        // Check plugin settings from options table
        $options = \get_option('wmsw_options', []);
        if (!empty($options['enable_debug_mode'])) {
            return true;
        }

        // Fall back to WordPress debug mode
        return (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Check if file logging is enabled
     */
    private function is_file_logging_enabled(): mixed
    {
        return \get_option('wmsw_enable_file_logging', true);
    }
}
