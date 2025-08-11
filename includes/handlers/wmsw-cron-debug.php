<?php
// A temporary debug script to check WP Cron status

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(dirname(__FILE__))))));
    require_once(ABSPATH . '/wp-load.php');
}

// Test if our handler is properly registered
global $wp_filter;

echo "Checking if 'wmsw_process_product_import' hook has registered callbacks:<br>";

if (isset($wp_filter['wmsw_process_product_import']) && !empty($wp_filter['wmsw_process_product_import'])) {
    echo "✅ Handler found for 'wmsw_process_product_import'<br>";
    print_r($wp_filter['wmsw_process_product_import']);
} else {
    echo "❌ No handler registered for 'wmsw_process_product_import'<br>";
}

echo "<br>Checking scheduled cron events:<br>";
$cron_jobs = _get_cron_array();
$found = false;

foreach ($cron_jobs as $timestamp => $cron_job) {
    foreach ($cron_job as $hook => $job_data) {
        if ($hook === 'wmsw_process_product_import') {
            $found = true;
            echo "✅ Found scheduled 'wmsw_process_product_import' event at: " . date('Y-m-d H:i:s', $timestamp) . "<br>";
            echo "Arguments: <pre>" . print_r(array_values($job_data)[0]['args'], true) . "</pre>";
        }
    }
}

if (!$found) {
    echo "❌ No scheduled 'wmsw_process_product_import' events found<br>";
}

echo "<br>Checking if WP Cron is disabled (should be false for normal operation):<br>";
echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? "❌ WP Cron is disabled" : "✅ WP Cron is enabled";

// Check if the plugin has access to run the cron
echo "<br><br>Testing direct cron event execution:<br>";

// Try to directly execute a cron test
function handle_cron_test($message) {
    global $wpdb;
    $table = $wpdb->prefix . 'wmsw_logs';
    $wpdb->insert($table, [
        'level' => 'info',
        'message' => 'Cron test: ' . $message,
        'context' => json_encode(['test' => true, 'time' => current_time('mysql')]),
        'created_at' => current_time('mysql')
    ]);
    
    echo "✅ Test cron event executed and logged successfully";
}

add_action('wmsw_test_cron', 'handle_cron_test', 10, 1);

// Schedule the test cron event
if (!wp_next_scheduled('wmsw_test_cron')) {
    wp_schedule_single_event(time(), 'wmsw_test_cron', ['Test at ' . current_time('mysql')]);
    echo "✅ Test cron event scheduled<br>";
} else {
    echo "ℹ️ Test cron event already scheduled<br>";
}

// Manually trigger the cron system
do_action('wmsw_test_cron', 'Manual test at ' . current_time('mysql'));

echo "<br><br>Done testing cron functionality.";
