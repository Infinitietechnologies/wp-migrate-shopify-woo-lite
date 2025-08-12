<?php

/**
 * Import Logs View
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// Verify nonce for security
$nonce_verified = false;
if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wmsw_logs_filter')) {
    $nonce_verified = true;
}

// Use the ImportLog model for all database operations
use ShopifyWooImporter\Models\WMSW_ImportLog;

// Pagination and filters (only if nonce is verified or for initial page load)
$page     = 1;
$per_page = 50;
$active_status = intval(1);

// Filters
$date_from        = '';
$date_to          = '';
$level_filter     = '';
$store_filter     = '';
$task_type_filter = '';
$search_query     = '';

if ($nonce_verified || !isset($_GET['_wpnonce'])) {
    $page     = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
    
    $date_from        = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
    $date_to          = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));
    $level_filter     = sanitize_text_field(wp_unslash($_GET['level'] ?? ''));
    $store_filter     = isset($_GET['store_id']) ? absint(wp_unslash($_GET['store_id'])) : '';
    $task_type_filter = sanitize_text_field(wp_unslash($_GET['task_type'] ?? ''));
    $search_query     = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
}

// Build filters array for the model
$filters = [];
if ($level_filter) $filters['level'] = $level_filter;
if ($store_filter) $filters['store_id'] = $store_filter;
if ($task_type_filter) $filters['task_type'] = $task_type_filter;
if ($search_query) $filters['search'] = $search_query;
if ($date_from) $filters['date_from'] = $date_from;
if ($date_to) $filters['date_to'] = $date_to;

// Get logs data using the model
$logs_data = WMSW_ImportLog::get_logs_with_context($filters, $page, $per_page);
$logs = $logs_data['logs'];
$total_items = $logs_data['total_count'];
$pagination = $logs_data['pagination'];

// Get available stores and task types for filters
$stores = WMSW_ImportLog::get_available_stores();
$task_types = WMSW_ImportLog::get_available_task_types();

// Get statistics
$stats = WMSW_ImportLog::get_statistics();
$error_count = $stats['error'];
$warning_count = $stats['warning'];
$info_count = $stats['info'];
$debug_count = $stats['debug'];

// Pagination
$total_pages = $pagination['total_pages'];
?>


<div class="wrap swi-admin swi-logs-page">
    <div class="swi-reset">
        <!-- Page Header -->
        <div class="swi-page-header">
            <div>
                <h1 class="swi-page-title">
                    <?php esc_html_e('Import Logs', 'wp-migrate-shopify-woo'); ?>
                </h1>
                <p class="swi-page-subtitle">
                    <?php esc_html_e('View detailed logs of all import processes and troubleshoot issues.', 'wp-migrate-shopify-woo'); ?>
                </p>
            </div>
            <div class="swi-page-actions">
                <button type="button" class="swi-btn swi-btn-secondary clear-logs-30" id="clear-logs">
                    <span class="dashicons dashicons-trash swi-mr-2"></span>
                    <?php esc_html_e('Clear Old Logs', 'wp-migrate-shopify-woo'); ?>
                </button>
                <button type="button" class="swi-btn swi-btn-primary" id="export-logs">
                    <span class="dashicons dashicons-download swi-mr-2"></span>
                    <?php esc_html_e('Export Logs', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>
        </div>

        <!-- Stats Dashboard -->
        <div class="swi-stats-dashboard">
            <div class="swi-stat-card info align-items-start">
                <div class="swi-stat-icon">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div>
                    <div class="swi-stat-number"><?php echo esc_html($total_items); ?></div>
                    <div class="swi-stat-label"><?php esc_html_e('Total Logs', 'wp-migrate-shopify-woo'); ?></div>
                </div>
            </div>

            <div class="swi-stat-card stat-error align-items-start">
                <div class="swi-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div>
                    <div class="swi-stat-number"><?php echo esc_html($error_count); ?></div>
                    <div class="swi-stat-label"><?php esc_html_e('Errors', 'wp-migrate-shopify-woo'); ?></div>
                </div>
            </div>

            <div class="swi-stat-card warning align-items-start">
                <div class="swi-stat-icon">
                    <span class="dashicons dashicons-flag"></span>
                </div>
                <div>
                    <div class="swi-stat-number"><?php echo esc_html($warning_count); ?></div>
                    <div class="swi-stat-label"><?php esc_html_e('Warnings', 'wp-migrate-shopify-woo'); ?></div>
                </div>
            </div>

            <div class="swi-stat-card info align-items-start">
                <div class="swi-stat-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div>
                    <div class="swi-stat-number"><?php echo esc_html($info_count); ?></div>
                    <div class="swi-stat-label"><?php esc_html_e('Info', 'wp-migrate-shopify-woo'); ?></div>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="swi-filters-panel">
            <div class="swi-filters-toggle" id="toggle-filters">
                <span>
                    <span class="dashicons dashicons-filter swi-mr-2"></span>
                    <?php esc_html_e('Advanced Filters', 'wp-migrate-shopify-woo'); ?>
                </span>
                <span class="dashicons dashicons-arrow-down-alt2" id="filter-toggle-icon"></span>
            </div>

            <div class="swi-filters-body" id="filters-body">
                <form id="swi-logs-filter-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <?php wp_nonce_field('wmsw_logs_filter', '_wpnonce'); ?>
                    <input type="hidden" name="page" value="wp-migrate-shopify-woo-logs">
                    <!-- Search Box -->
                    <div class="swi-search-box">
                        <span class="dashicons dashicons-search swi-search-icon"></span>
                        <input type="text" name="search" class="swi-form-input" placeholder="<?php esc_attr_e('Search in log messages...', 'wp-migrate-shopify-woo'); ?>" value="<?php echo esc_attr($search_query); ?>">
                    </div>

                    <div class="swi-filters-row">
                        <!-- Log Level Filter -->
                        <div class="swi-filter-group">
                            <label for="level" class="swi-filter-label"><?php esc_html_e('Log Level', 'wp-migrate-shopify-woo'); ?></label>
                            <select name="level" id="level" class="swi-form-select">
                                <option value=""><?php esc_html_e('All Levels', 'wp-migrate-shopify-woo'); ?></option>
                                <option value="error" <?php selected($level_filter, 'error'); ?>><?php esc_html_e('Error', 'wp-migrate-shopify-woo'); ?></option>
                                <option value="warning" <?php selected($level_filter, 'warning'); ?>><?php esc_html_e('Warning', 'wp-migrate-shopify-woo'); ?></option>
                                <option value="info" <?php selected($level_filter, 'info'); ?>><?php esc_html_e('Info', 'wp-migrate-shopify-woo'); ?></option>
                                <option value="debug" <?php selected($level_filter, 'debug'); ?>><?php esc_html_e('Debug', 'wp-migrate-shopify-woo'); ?></option>
                            </select>
                        </div>

                        <!-- Store Filter -->
                        <div class="swi-filter-group">
                            <label for="store_id" class="swi-filter-label"><?php esc_html_e('Store', 'wp-migrate-shopify-woo'); ?></label>
                            <select name="store_id" id="store_id" class="swi-form-select">
                                <option value=""><?php esc_html_e('All Stores', 'wp-migrate-shopify-woo'); ?></option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo esc_attr($store->id); ?>">
                                        <?php echo esc_html($store->store_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Task Type Filter -->
                        <div class="swi-filter-group d-none">
                            <label for="task_type" class="swi-filter-label"><?php esc_html_e('Task Type', 'wp-migrate-shopify-woo'); ?></label>
                            <select name="task_type" id="task_type" class="swi-form-select">
                                <option value=""><?php esc_html_e('All Task Types', 'wp-migrate-shopify-woo'); ?></option>
                                <?php foreach ($task_types as $task_type): ?>
                                    <option value="<?php echo esc_attr($task_type->task_type); ?>" <?php selected($task_type_filter, $task_type->task_type); ?>>
                                        <?php echo esc_html(ucfirst($task_type->task_type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="swi-filters-row">
                        <!-- Date Range Filter -->
                        <div class="swi-filter-group">
                            <label class="swi-filter-label"><?php esc_html_e('Date Range', 'wp-migrate-shopify-woo'); ?></label>
                            <div class="swi-date-range">
                                <input type="date" name="date_from" class="swi-form-input" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php esc_attr_e('From', 'wp-migrate-shopify-woo'); ?>">
                                <input type="date" name="date_to" class="swi-form-input" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php esc_attr_e('To', 'wp-migrate-shopify-woo'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="swi-filter-actions">
                        <button type="submit" class="swi-btn swi-btn-primary">
                            <span class="dashicons dashicons-search swi-mr-2"></span>
                            <?php esc_html_e('Apply Filters', 'wp-migrate-shopify-woo'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-logs')); ?>" class="swi-btn swi-btn-secondary">
                            <span class="dashicons dashicons-dismiss swi-mr-2"></span>
                            <?php esc_html_e('Clear Filters', 'wp-migrate-shopify-woo'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <!-- Empty State -->
            <div class="swi-empty-state mx-0">
                <div class="swi-empty-icon">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <h2 class="swi-empty-title"><?php esc_html_e('No Import Logs Found', 'wp-migrate-shopify-woo'); ?></h2>
                <p class="swi-empty-description">
                    <?php if ($level_filter || $store_filter || $task_type_filter || $search_query || $date_from || $date_to): ?>
                        <?php esc_html_e('No logs match your current filters. Try adjusting the filter criteria.', 'wp-migrate-shopify-woo'); ?>
                    <?php else: ?>
                        <?php esc_html_e('No import logs have been recorded yet. Logs will appear here once you start importing data.', 'wp-migrate-shopify-woo'); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="swi-logs-table-container" id="swi-logs-table-container">
                <table class="swi-logs-table" id="swi-logs-table">
                    <thead>
                        <tr>
                            <th width="15%"><?php esc_html_e('Date/Time', 'wp-migrate-shopify-woo'); ?></th>
                            <th width="10%"><?php esc_html_e('Level', 'wp-migrate-shopify-woo'); ?></th>
                            <th width="15%"><?php esc_html_e('Store', 'wp-migrate-shopify-woo'); ?></th>
                            <th width="15%"><?php esc_html_e('Task', 'wp-migrate-shopify-woo'); ?></th>
                            <th><?php esc_html_e('Message', 'wp-migrate-shopify-woo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                                <td>
                                    <span class="swi-level-badge swi-level-<?php echo esc_attr($log->level); ?>">
                                        <?php echo esc_html(ucfirst($log->level)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log->store_name): ?>
                                        <?php echo esc_html($log->store_name); ?>
                                    <?php else: ?>
                                        <em><?php esc_html_e('System', 'wp-migrate-shopify-woo'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log->task_id && $log->task_type): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-logs&task_id=' . $log->task_id)); ?>">
                                            <?php echo esc_html(ucfirst($log->task_type) . ' #' . $log->task_id); ?>
                                        </a>
                                    <?php else: ?>
                                        <em><?php esc_html_e('N/A', 'wp-migrate-shopify-woo'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($log->message); ?>

                                    <?php if (!empty($log->context) && $log->context !== 'null'): ?>
                                        <button type="button" class="swi-context-toggle" data-log-id="<?php echo esc_attr($log->id); ?>">
                                            <?php esc_html_e('Show Details', 'wp-migrate-shopify-woo'); ?>
                                        </button>
                                        <div class="swi-context-content d-none" id="log-context-<?php echo esc_attr($log->id); ?>">
                                            <pre><?php echo esc_html($log->context); ?></pre>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="swi-pagination">
                                <?php
            // Build safe query args for pagination
            $safe_query_args = [
                'page' => 'wp-migrate-shopify-woo-logs',
                '_wpnonce' => wp_create_nonce('wmsw_logs_filter')
            ];
            
            // Add filters only if they exist and are safe
            if ($date_from) $safe_query_args['date_from'] = $date_from;
            if ($date_to) $safe_query_args['date_to'] = $date_to;
            if ($level_filter) $safe_query_args['level'] = $level_filter;
            if ($store_filter) $safe_query_args['store_id'] = $store_filter;
            if ($task_type_filter) $safe_query_args['task_type'] = $task_type_filter;
            if ($search_query) $safe_query_args['search'] = $search_query;
            
            // Previous page link
            if ($pagination['has_previous']):
                $prev_url = add_query_arg(array_merge($safe_query_args, ['paged' => $pagination['previous_page']]), admin_url('admin.php'));
            ?>
                <a href="<?php echo esc_url($prev_url); ?>" class="swi-pagination-item">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
            <?php endif; ?>

            <?php
            // Page numbers
            $start_page = max(1, $pagination['current_page'] - 2);
            $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);

            if ($start_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg(array_merge($safe_query_args, ['paged' => 1]), admin_url('admin.php'))); ?>" class="swi-pagination-item">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                    <span class="swi-pagination-item">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++):
                $is_active = $i === $pagination['current_page'];
                $url = add_query_arg(array_merge($safe_query_args, ['paged' => $i]), admin_url('admin.php'));
            ?>
                <a href="<?php echo esc_url($url); ?>" class="swi-pagination-item <?php echo $is_active ? 'active' : ''; ?>">
                    <?php echo esc_html($i); ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_page < $pagination['total_pages']): ?>
                <?php if ($end_page < $pagination['total_pages'] - 1): ?>
                    <span class="swi-pagination-item">...</span>
                <?php endif; ?>
                <a href="<?php echo esc_url(add_query_arg(array_merge($safe_query_args, ['paged' => $pagination['total_pages']]), admin_url('admin.php'))); ?>" class="swi-pagination-item">
                    <?php echo esc_html($pagination['total_pages']); ?>
                </a>
            <?php endif; ?>

            <?php
            // Next page link
            if ($pagination['has_next']):
                $next_url = add_query_arg(array_merge($safe_query_args, ['paged' => $pagination['next_page']]), admin_url('admin.php'));
            ?>
                <a href="<?php echo esc_url($next_url); ?>" class="swi-pagination-item">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            <?php endif; ?>
                </div>

                <div class="swi-pagination-info">
                    <?php
                    echo esc_html(sprintf(
                        // translators: %1$d is the first item number, %2$d is the last item number, %3$d is the total number of logs
                        __('Showing %1$d-%2$d of %3$d logs', 'wp-migrate-shopify-woo'),
                        $pagination['from'],
                        $pagination['to'],
                        $pagination['total_items']
                    ));
                    ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="swi-filter-actions swi-mt-4">
                <button type="button" id="clear-logs" class="swi-btn swi-btn-secondary clear-logs-30">
                    <span class="dashicons dashicons-trash swi-mr-2"></span>
                    <?php esc_html_e('Clear Logs Older Than 30 Days', 'wp-migrate-shopify-woo'); ?>
                </button>
                <button type="button" id="export-logs-csv" class="swi-btn swi-btn-primary">
                    <span class="dashicons dashicons-download swi-mr-2"></span>
                    <?php esc_html_e('Export to CSV', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>