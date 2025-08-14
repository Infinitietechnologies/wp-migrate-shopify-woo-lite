<?php

/**
 * Import Log Table Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Tables
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verify nonce for security
$nonce_verified = false;
if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wmsw_import_log_filter')) {
    $nonce_verified = true;
}

// Get current page and filters (only if nonce is verified or for initial page load)
$current_page = 1;
$filter_store = 0;
$filter_type = '';
$filter_status = '';

if ($nonce_verified || !isset($_GET['_wpnonce'])) {
    $current_page = isset($_GET['paged']) ? max(1, intval(wp_unslash($_GET['paged']))) : 1;
    $filter_store = isset($_GET['store_id']) ? intval(wp_unslash($_GET['store_id'])) : 0;
    $filter_type = isset($_GET['import_type']) ? sanitize_text_field(wp_unslash($_GET['import_type'])) : '';
    $filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
}

$per_page = 20;

// Get logs data (this would normally come from the Logger service)
$logs = []; // This should be populated by the Logger service
$total_logs = 0; // This should be the total count from the Logger service

// Use the ImportLog model for database operations
use ShopifyWooImporter\Models\WMSWL_ImportLog;

// Get available stores for filter
$stores = WMSWL_ImportLog::get_available_stores();
?>

<div class="swi-table-container">
    <div class="swi-table-filters">
        <form method="get" action="">
            <?php wp_nonce_field('wmsw_import_log_filter', '_wpnonce'); ?>
            <input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['page'] ?? ''))); ?>" />

            <select name="store_id">
                <option value=""><?php esc_html_e('All Stores', 'wp-migrate-shopify-woo-lite'); ?></option>
                <?php foreach ($stores as $store) : ?>
                    <option value="<?php echo esc_attr($store->id); ?>" <?php selected($filter_store, $store->id); ?>>
                        <?php echo esc_html($store->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="import_type">
                <option value=""><?php esc_html_e('All Types', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="products" <?php selected($filter_type, 'products'); ?>><?php esc_html_e('Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="customers" <?php selected($filter_type, 'customers'); ?>><?php esc_html_e('Customers', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="orders" <?php selected($filter_type, 'orders'); ?>><?php esc_html_e('Orders', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="pages" <?php selected($filter_type, 'pages'); ?>><?php esc_html_e('Pages', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="blogs" <?php selected($filter_type, 'blogs'); ?>><?php esc_html_e('Blogs', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="coupons" <?php selected($filter_type, 'coupons'); ?>><?php esc_html_e('Coupons', 'wp-migrate-shopify-woo-lite'); ?></option>
            </select>

            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php esc_html_e('Pending', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="processing" <?php selected($filter_status, 'processing'); ?>><?php esc_html_e('Processing', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="completed" <?php selected($filter_status, 'completed'); ?>><?php esc_html_e('Completed', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php esc_html_e('Failed', 'wp-migrate-shopify-woo-lite'); ?></option>
                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'wp-migrate-shopify-woo-lite'); ?></option>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'wp-migrate-shopify-woo-lite'); ?>" />
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . sanitize_text_field(wp_unslash($_GET['page'] ?? '')))); ?>" class="button">
                <?php esc_html_e('Clear Filters', 'wp-migrate-shopify-woo-lite'); ?>
            </a>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped swi-import-log-table">
        <thead>
            <tr>
                <th scope="col" class="column-id"><?php esc_html_e('ID', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-store"><?php esc_html_e('Store', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-type"><?php esc_html_e('Import Type', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-status"><?php esc_html_e('Status', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-items"><?php esc_html_e('Items', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-progress"><?php esc_html_e('Progress', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-started"><?php esc_html_e('Started', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-completed"><?php esc_html_e('Completed', 'wp-migrate-shopify-woo-lite'); ?></th>
                <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'wp-migrate-shopify-woo-lite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr class="no-items">
                    <td colspan="9" class="colspanchange">
                        <?php esc_html_e('No import logs found.', 'wp-migrate-shopify-woo-lite'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td class="column-id">
                            <strong>#<?php echo esc_html($log->id); ?></strong>
                        </td>
                        <td class="column-store">
                            <?php echo esc_html($log->store_name ?? esc_html__('Unknown', 'wp-migrate-shopify-woo-lite')); ?>
                        </td>
                        <td class="column-type">
                            <span class="swi-import-type-badge swi-type-<?php echo esc_attr($log->import_type); ?>">
                                <?php echo esc_html(ucfirst($log->import_type)); ?>
                            </span>
                        </td>
                        <td class="column-status">
                            <?php
                            include WMSW_PLUGIN_DIR . 'backend/partials/components/status-badge.php';
                            echo wp_kses_post(WMSW_status_badge($log->status));
                            ?>
                        </td>
                        <td class="column-items">
                            <span class="swi-items-count">
                                <?php echo esc_html($log->processed_items ?? 0); ?> / <?php echo esc_html($log->total_items ?? 0); ?>
                            </span>
                        </td>
                        <td class="column-progress">
                            <?php
                            $progress = 0;
                            if (isset($log->total_items) && $log->total_items > 0) {
                                $progress = round(($log->processed_items / $log->total_items) * 100);
                            }
                            ?>
                            <div class="swi-progress-bar">
                                <div class="swi-progress-fill" data-width="<?php echo esc_attr($progress); ?>"></div>
                                <span class="swi-progress-text"><?php echo esc_html($progress); ?>%</span>
                            </div>
                        </td>
                        <td class="column-started">
                            <?php
                            if (isset($log->started_at)) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->started_at)));
                            } else {
                                esc_html_e('-', 'wp-migrate-shopify-woo-lite');
                            }
                            ?>
                        </td>
                        <td class="column-completed">
                            <?php
                            if (isset($log->completed_at)) {
                                echo esc_html(
                                    wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->completed_at))
                                );
                            } else {
                                esc_html_e('-', 'wp-migrate-shopify-woo-lite');
                            }
                            ?>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="swi-view-log" data-log-id="<?php echo esc_attr($log->id); ?>">
                                        <?php esc_html_e('View Details', 'wp-migrate-shopify-woo-lite'); ?>
                                    </a>
                                </span>
                                <?php if (in_array($log->status, ['pending', 'processing'])) : ?>
                                    | <span class="cancel">
                                        <a href="#" class="swi-cancel-import" data-log-id="<?php echo esc_attr($log->id); ?>">
                                            <?php esc_html_e('Cancel', 'wp-migrate-shopify-woo-lite'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                <?php if (in_array($log->status, ['failed', 'cancelled'])) : ?>
                                    | <span class="retry">
                                        <a href="#" class="swi-retry-import" data-log-id="<?php echo esc_attr($log->id); ?>">
                                            <?php esc_html_e('Retry', 'wp-migrate-shopify-woo-lite'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                | <span class="delete">
                                    <a href="#" class="swi-delete-log" data-log-id="<?php echo esc_attr($log->id); ?>">
                                        <?php esc_html_e('Delete', 'wp-migrate-shopify-woo-lite'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_logs > $per_page) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_logs / $per_page);
                echo wp_kses_post(paginate_links([
                    'base' => esc_url_raw(add_query_arg('paged', '%#%')),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ]));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Log Details Modal -->
<div id="swi-log-details-modal" class="swi-modal d-none">
    <div class="swi-modal-content">
        <div class="swi-modal-header">
            <h3><?php esc_html_e('Import Log Details', 'wp-migrate-shopify-woo-lite'); ?></h3>
            <span class="swi-modal-close">&times;</span>
        </div>
        <div class="swi-modal-body">
            <div id="swi-log-details-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
