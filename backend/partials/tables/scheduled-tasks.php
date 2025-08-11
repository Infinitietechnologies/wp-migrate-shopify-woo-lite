<?php
/**
 * Scheduled Tasks Table Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Tables
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current page and filters
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_type = isset($_GET['task_type']) ? sanitize_text_field($_GET['task_type']) : '';

// Get scheduled tasks data
global $wpdb;
$table_name = $wpdb->prefix . 'wmsw_scheduled_tasks';

$where_conditions = [];
if (!empty($filter_status)) {
    $where_conditions[] = $wpdb->prepare("status = %s", $filter_status);
}
if (!empty($filter_type)) {
    $where_conditions[] = $wpdb->prepare("task_type = %s", $filter_type);
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

$offset = ($current_page - 1) * $per_page;
$tasks = $wpdb->get_results($wpdb->prepare(
    "SELECT st.*, ss.name as store_name 
     FROM {$table_name} st 
     LEFT JOIN {$wpdb->prefix}WMSW_shopify_stores ss ON st.store_id = ss.id
     {$where_clause} 
     ORDER BY st.next_run_at ASC 
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}{$where_clause}");

$task_types = [
    'sync_products' => __('Sync Products', 'wp-migrate-shopify-woo'),
    'sync_customers' => __('Sync Customers', 'wp-migrate-shopify-woo'),
    'sync_orders' => __('Sync Orders', 'wp-migrate-shopify-woo'),
    'sync_inventory' => __('Sync Inventory', 'wp-migrate-shopify-woo'),
    'import_products' => __('Import Products', 'wp-migrate-shopify-woo'),
    'import_customers' => __('Import Customers', 'wp-migrate-shopify-woo'),
    'import_orders' => __('Import Orders', 'wp-migrate-shopify-woo'),
    'cleanup' => __('Cleanup Task', 'wp-migrate-shopify-woo')
];
?>

<div class="swi-table-container">
    <div class="swi-table-actions">
        <a href="#" class="button button-primary" id="add-new-task">
            <?php _e('Schedule New Task', 'wp-migrate-shopify-woo'); ?>
        </a>

        <div class="swi-table-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>" />
                
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="scheduled" <?php selected($filter_status, 'scheduled'); ?>><?php _e('Scheduled', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="running" <?php selected($filter_status, 'running'); ?>><?php _e('Running', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>><?php _e('Completed', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php _e('Failed', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>><?php _e('Cancelled', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="paused" <?php selected($filter_status, 'paused'); ?>><?php _e('Paused', 'wp-migrate-shopify-woo'); ?></option>
                </select>

                <select name="task_type">
                    <option value=""><?php _e('All Task Types', 'wp-migrate-shopify-woo'); ?></option>
                    <?php foreach ($task_types as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($filter_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php _e('Filter', 'wp-migrate-shopify-woo'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=' . ($_GET['page'] ?? '')); ?>" class="button">
                    <?php _e('Clear Filters', 'wp-migrate-shopify-woo'); ?>
                </a>
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped swi-tasks-table">
        <thead>
            <tr>
                <th scope="col" class="column-cb">
                    <input type="checkbox" />
                </th>
                <th scope="col" class="column-task"><?php _e('Task', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-store"><?php _e('Store', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-status"><?php _e('Status', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-schedule"><?php _e('Schedule', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-next-run"><?php _e('Next Run', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-last-run"><?php _e('Last Run', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-actions"><?php _e('Actions', 'wp-migrate-shopify-woo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasks)) : ?>
                <tr class="no-items">
                    <td colspan="8" class="colspanchange">
                        <?php _e('No scheduled tasks found.', 'wp-migrate-shopify-woo'); ?>
                        <br><br>
                        <a href="#" class="button button-primary" id="add-first-task">
                            <?php _e('Schedule Your First Task', 'wp-migrate-shopify-woo'); ?>
                        </a>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($tasks as $task) : ?>
                    <tr data-task-id="<?php echo esc_attr($task->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="task_ids[]" value="<?php echo esc_attr($task->id); ?>" />
                        </th>
                        <td class="column-task">
                            <strong>
                                <?php echo esc_html($task_types[$task->task_type] ?? ucfirst(str_replace('_', ' ', $task->task_type))); ?>
                            </strong>
                            <?php if (!empty($task->task_name)) : ?>
                                <div class="task-name"><?php echo esc_html($task->task_name); ?></div>
                            <?php endif; ?>
                            <div class="row-actions visible">
                                <span class="edit">
                                    <a href="#" class="swi-edit-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                        <?php _e('Edit', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                                <?php if (in_array($task->status, ['scheduled', 'paused'])) : ?>
                                    | <span class="run">
                                        <a href="#" class="swi-run-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                            <?php _e('Run Now', 'wp-migrate-shopify-woo'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($task->status === 'scheduled') : ?>
                                    | <span class="pause">
                                        <a href="#" class="swi-pause-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                            <?php _e('Pause', 'wp-migrate-shopify-woo'); ?>
                                        </a>
                                    </span>
                                <?php elseif ($task->status === 'paused') : ?>
                                    | <span class="resume">
                                        <a href="#" class="swi-resume-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                            <?php _e('Resume', 'wp-migrate-shopify-woo'); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                                | <span class="delete">
                                    <a href="#" class="swi-delete-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                        <?php _e('Delete', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-store">
                            <?php echo esc_html($task->store_name ?? __('All Stores', 'wp-migrate-shopify-woo')); ?>
                        </td>
                        <td class="column-status">
                            <?php
                            include WMSW_PLUGIN_DIR . 'backend/partials/components/status-badge.php';
                            echo WMSW_status_badge($task->status);
                            ?>
                            <?php if ($task->status === 'running') : ?>
                                <div class="swi-task-progress">
                                    <?php 
                                    $progress = 0;
                                    if (!empty($task->progress_data)) {
                                        $progress_data = json_decode($task->progress_data, true);
                                        if (isset($progress_data['processed'], $progress_data['total']) && $progress_data['total'] > 0) {
                                            $progress = round(($progress_data['processed'] / $progress_data['total']) * 100);
                                        }
                                    }
                                    ?>
                                    <div class="swi-progress-bar">
                                        <div class="swi-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                                        <span class="swi-progress-text"><?php echo esc_html($progress); ?>%</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-schedule">
                            <span class="swi-schedule-info">
                                <?php
                                if ($task->recurrence === 'once') {
                                    _e('One-time', 'wp-migrate-shopify-woo');
                                } else {
                                    $schedules = wp_get_schedules();
                                    echo esc_html($schedules[$task->recurrence]['display'] ?? ucfirst($task->recurrence));
                                }
                                ?>
                            </span>
                        </td>
                        <td class="column-next-run">
                            <?php 
                            if (!empty($task->next_run_at) && $task->status === 'scheduled') {
                                $next_run = strtotime($task->next_run_at);
                                $now = current_time('timestamp');
                                
                                if ($next_run > $now) {
                                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_run));
                                    $time_diff = $next_run - $now;
                                    if ($time_diff < HOUR_IN_SECONDS) {
                                        echo '<br><small class="swi-time-remaining">' . sprintf(__('in %s', 'wp-migrate-shopify-woo'), human_time_diff($now, $next_run)) . '</small>';
                                    }
                                } else {
                                    echo '<span class="swi-overdue">' . __('Overdue', 'wp-migrate-shopify-woo') . '</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="column-last-run">
                            <?php 
                            if (!empty($task->last_run_at)) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($task->last_run_at)));
                                if (!empty($task->last_run_duration)) {
                                    echo '<br><small>' . sprintf(__('took %ds', 'wp-migrate-shopify-woo'), $task->last_run_duration) . '</small>';
                                }
                            } else {
                                echo '<span class="swi-never-run">' . __('Never', 'wp-migrate-shopify-woo') . '</span>';
                            }
                            ?>
                        </td>
                        <td class="column-actions">
                            <div class="swi-task-actions">
                                <?php if ($task->status === 'running') : ?>
                                    <button type="button" class="button button-small swi-cancel-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                        <?php _e('Cancel', 'wp-migrate-shopify-woo'); ?>
                                    </button>
                                <?php elseif (in_array($task->status, ['failed', 'cancelled'])) : ?>
                                    <button type="button" class="button button-small swi-retry-task" data-task-id="<?php echo esc_attr($task->id); ?>">
                                        <?php _e('Retry', 'wp-migrate-shopify-woo'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_tasks > $per_page) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_tasks / $per_page);
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Task Form Modal -->
<div id="swi-task-form-modal" class="swi-modal" style="display: none;">
    <div class="swi-modal-content swi-modal-large">
        <div class="swi-modal-header">
            <h3 id="swi-task-form-title"><?php _e('Schedule New Task', 'wp-migrate-shopify-woo'); ?></h3>
            <span class="swi-modal-close">&times;</span>
        </div>
        <div class="swi-modal-body">
            <form id="swi-task-form">
                <input type="hidden" name="task_id" id="task_id" value="" />
                <input type="hidden" name="action" value="WMSW_save_task" />
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wmsw_save_task'); ?>" />

                <?php include WMSW_PLUGIN_DIR . 'backend/partials/forms/schedule-setup.php'; ?>

                <div class="swi-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Task', 'wp-migrate-shopify-woo'); ?>
                    </button>
                    <button type="button" class="button swi-modal-close">
                        <?php _e('Cancel', 'wp-migrate-shopify-woo'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add new task
    $('#add-new-task, #add-first-task').click(function(e) {
        e.preventDefault();
        $('#swi-task-form-title').text('<?php _e('Schedule New Task', 'wp-migrate-shopify-woo'); ?>');
        $('#swi-task-form')[0].reset();
        $('#task_id').val('');
        $('#swi-task-form-modal').show();
    });

    // Edit task
    $('.swi-edit-task').click(function(e) {
        e.preventDefault();
        var taskId = $(this).data('task-id');
        
        $('#swi-task-form-title').text('<?php _e('Edit Scheduled Task', 'wp-migrate-shopify-woo'); ?>');
        $('#task_id').val(taskId);
        
        // Load task data
        $.post(ajaxurl, {
            action: 'wmsw_get_task',
            task_id: taskId,
            nonce: '<?php echo wp_create_nonce('wmsw_get_task'); ?>'
        }, function(response) {
            if (response.success) {
                var task = response.data.task;
                // Populate form fields with task data
                $('#swi-task-form-modal').show();
            } else {
                alert('Error loading task: ' + response.data.message);
            }
        });
    });

    // Task actions
    $('.swi-run-task, .swi-pause-task, .swi-resume-task, .swi-cancel-task, .swi-retry-task').click(function(e) {
        e.preventDefault();
        var taskId = $(this).data('task-id');
        var action = '';
        var confirmMessage = '';
        
        if ($(this).hasClass('swi-run-task')) {
            action = 'run';
            confirmMessage = '<?php _e('Are you sure you want to run this task now?', 'wp-migrate-shopify-woo'); ?>';
        } else if ($(this).hasClass('swi-pause-task')) {
            action = 'pause';
            confirmMessage = '<?php _e('Are you sure you want to pause this task?', 'wp-migrate-shopify-woo'); ?>';
        } else if ($(this).hasClass('swi-resume-task')) {
            action = 'resume';
            confirmMessage = '<?php _e('Are you sure you want to resume this task?', 'wp-migrate-shopify-woo'); ?>';
        } else if ($(this).hasClass('swi-cancel-task')) {
            action = 'cancel';
            confirmMessage = '<?php _e('Are you sure you want to cancel this running task?', 'wp-migrate-shopify-woo'); ?>';
        } else if ($(this).hasClass('swi-retry-task')) {
            action = 'retry';
            confirmMessage = '<?php _e('Are you sure you want to retry this task?', 'wp-migrate-shopify-woo'); ?>';
        }
        
        if (confirm(confirmMessage)) {
            $.post(ajaxurl, {
                action: 'wmsw_task_action',
                task_id: taskId,
                task_action: action,
                nonce: '<?php echo wp_create_nonce('wmsw_task_action'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    });

    // Delete task
    $('.swi-delete-task').click(function(e) {
        e.preventDefault();
        if (confirm('<?php _e('Are you sure you want to delete this task? This action cannot be undone.', 'wp-migrate-shopify-woo'); ?>')) {
            var taskId = $(this).data('task-id');
            
            $.post(ajaxurl, {
                action: 'wmsw_delete_task',
                task_id: taskId,
                nonce: '<?php echo wp_create_nonce('wmsw_delete_task'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        }
    });

    // Close modals
    $('.swi-modal-close, .swi-modal').click(function(e) {
        if (e.target === this || $(e.target).hasClass('swi-modal-close')) {
            $('.swi-modal').hide();
        }
    });
});
</script>
