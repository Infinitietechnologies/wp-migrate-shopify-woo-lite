<?php
/**
 * Status Badge Component
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $task is available from the including file
if (!isset($task)) {
    return;
}

$status = $task->status;
$status_class = '';
$status_text = '';

switch ($status) {
    case 'pending':
        $status_class = 'swi-status-pending';
        $status_text = __('Pending', 'wp-migrate-shopify-woo-lite');
        break;
    case 'processing':
        $status_class = 'swi-status-processing';
        $status_text = __('Processing', 'wp-migrate-shopify-woo-lite');
        break;
    case 'completed':
        $status_class = 'swi-status-completed';
        $status_text = __('Completed', 'wp-migrate-shopify-woo-lite');
        break;
    case 'failed':
        $status_class = 'swi-status-failed';
        $status_text = __('Failed', 'wp-migrate-shopify-woo-lite');
        break;
    case 'paused':
        $status_class = 'swi-status-paused';
        $status_text = __('Paused', 'wp-migrate-shopify-woo-lite');
        break;
    default:
        $status_class = 'swi-status-unknown';
        $status_text = __('Unknown', 'wp-migrate-shopify-woo-lite');
}
?>

<span class="swi-status-badge <?php echo esc_attr($status_class); ?>">
    <?php echo esc_html($status_text); ?>
</span>
