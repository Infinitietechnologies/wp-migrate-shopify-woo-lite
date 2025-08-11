<?php
/**
 * Schedule Setup Form Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Forms
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$schedule_options = [
    'manual' => __('Manual', 'wp-migrate-shopify-woo'),
    'hourly' => __('Every Hour', 'wp-migrate-shopify-woo'),
    'twicedaily' => __('Twice Daily', 'wp-migrate-shopify-woo'),
    'daily' => __('Daily', 'wp-migrate-shopify-woo'),
    'weekly' => __('Weekly', 'wp-migrate-shopify-woo'),
    'monthly' => __('Monthly', 'wp-migrate-shopify-woo'),
    'custom' => __('Custom', 'wp-migrate-shopify-woo')
];

$current_schedule = get_option('wmsw_sync_schedule', 'manual');
$custom_cron = get_option('wmsw_custom_cron', '0 0 * * *');
$next_scheduled = wp_next_scheduled('wmsw_sync_hook');
?>

<div class="swi-form-section">
    <h3><?php _e('Sync Schedule', 'wp-migrate-shopify-woo'); ?></h3>
    <p class="description">
        <?php _e('Configure automatic synchronization between your Shopify stores and WooCommerce.', 'wp-migrate-shopify-woo'); ?>
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="sync_schedule"><?php _e('Schedule Frequency', 'wp-migrate-shopify-woo'); ?></label>
            </th>
            <td>
                <select name="sync_schedule" id="sync_schedule" class="regular-text">
                    <?php foreach ($schedule_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_schedule, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php _e('Choose how often to automatically sync data from Shopify.', 'wp-migrate-shopify-woo'); ?>
                </p>
            </td>
        </tr>

        <tr id="custom_cron_row" style="<?php echo $current_schedule !== 'custom' ? 'display: none;' : ''; ?>">
            <th scope="row">
                <label for="custom_cron"><?php _e('Custom Cron Expression', 'wp-migrate-shopify-woo'); ?></label>
            </th>
            <td>
                <input type="text" name="custom_cron" id="custom_cron" value="<?php echo esc_attr($custom_cron); ?>" class="regular-text" />
                <p class="description">
                    <?php _e('Enter a cron expression (e.g., "0 2 * * *" for daily at 2 AM).', 'wp-migrate-shopify-woo'); ?>
                    <a href="https://crontab.guru/" target="_blank"><?php _e('Cron expression help', 'wp-migrate-shopify-woo'); ?></a>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php _e('Current Status', 'wp-migrate-shopify-woo'); ?></label>
            </th>
            <td>
                <?php if ($next_scheduled) : ?>
                    <span class="swi-status-badge swi-status-active">
                        <?php _e('Scheduled', 'wp-migrate-shopify-woo'); ?>
                    </span>
                    <p class="description">
                        <?php 
                        printf(
                            __('Next sync: %s', 'wp-migrate-shopify-woo'),
                            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <span class="swi-status-badge swi-status-inactive">
                        <?php _e('Not Scheduled', 'wp-migrate-shopify-woo'); ?>
                    </span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h4><?php _e('Sync Options', 'wp-migrate-shopify-woo'); ?></h4>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('Items to Sync', 'wp-migrate-shopify-woo'); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php _e('Items to Sync', 'wp-migrate-shopify-woo'); ?></span>
                    </legend>
                    
                    <?php
                    $sync_items = get_option('wmsw_sync_items', [
                        'products' => true,
                        'customers' => false,
                        'orders' => false,
                        'inventory' => true
                    ]);
                    ?>
                    
                    <label for="sync_products">
                        <input name="sync_items[products]" type="checkbox" id="sync_products" value="1" <?php checked($sync_items['products'] ?? false); ?> />
                        <?php _e('Products', 'wp-migrate-shopify-woo'); ?>
                    </label><br>
                    
                    <label for="sync_customers">
                        <input name="sync_items[customers]" type="checkbox" id="sync_customers" value="1" <?php checked($sync_items['customers'] ?? false); ?> />
                        <?php _e('Customers', 'wp-migrate-shopify-woo'); ?>
                    </label><br>
                    
                    <label for="sync_orders">
                        <input name="sync_items[orders]" type="checkbox" id="sync_orders" value="1" <?php checked($sync_items['orders'] ?? false); ?> />
                        <?php _e('Orders', 'wp-migrate-shopify-woo'); ?>
                    </label><br>
                    
                    <label for="sync_inventory">
                        <input name="sync_items[inventory]" type="checkbox" id="sync_inventory" value="1" <?php checked($sync_items['inventory'] ?? false); ?> />
                        <?php _e('Inventory Levels', 'wp-migrate-shopify-woo'); ?>
                    </label>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="sync_batch_size"><?php _e('Batch Size', 'wp-migrate-shopify-woo'); ?></label>
            </th>
            <td>
                <input type="number" name="sync_batch_size" id="sync_batch_size" 
                       value="<?php echo esc_attr(get_option('wmsw_sync_batch_size', 50)); ?>" 
                       min="1" max="250" class="small-text" />
                <p class="description">
                    <?php _e('Number of items to process per batch (1-250).', 'wp-migrate-shopify-woo'); ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="sync_timeout"><?php _e('Timeout (seconds)', 'wp-migrate-shopify-woo'); ?></label>
            </th>
            <td>
                <input type="number" name="sync_timeout" id="sync_timeout" 
                       value="<?php echo esc_attr(get_option('wmsw_sync_timeout', 300)); ?>" 
                       min="30" max="3600" class="small-text" />
                <p class="description">
                    <?php _e('Maximum time to wait for each API request (30-3600 seconds).', 'wp-migrate-shopify-woo'); ?>
                </p>
            </td>
        </tr>
    </table>

    <div class="swi-form-actions">
        <button type="button" class="button" id="test_schedule">
            <?php _e('Test Schedule', 'wp-migrate-shopify-woo'); ?>
        </button>
        <button type="button" class="button" id="run_now" <?php echo !$next_scheduled ? 'disabled' : ''; ?>>
            <?php _e('Run Now', 'wp-migrate-shopify-woo'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#sync_schedule').change(function() {
        if ($(this).val() === 'custom') {
            $('#custom_cron_row').show();
        } else {
            $('#custom_cron_row').hide();
        }
    });

    $('#test_schedule').click(function() {
        var schedule = $('#sync_schedule').val();
        var customCron = $('#custom_cron').val();
        
        $.post(ajaxurl, {
            action: 'wmsw_test_schedule',
            nonce: '<?php echo wp_create_nonce('wmsw_test_schedule'); ?>',
            schedule: schedule,
            custom_cron: customCron
        }, function(response) {
            if (response.success) {
                alert('Schedule test successful: ' + response.data.message);
            } else {
                alert('Schedule test failed: ' + response.data.message);
            }
        });
    });

    $('#run_now').click(function() {
        if (confirm('Are you sure you want to run sync now?')) {
            $(this).prop('disabled', true).text('Running...');
            
            $.post(ajaxurl, {
                action: 'wmsw_run_sync_now',
                nonce: '<?php echo wp_create_nonce('wmsw_run_sync_now'); ?>'
            }, function(response) {
                $('#run_now').prop('disabled', false).text('Run Now');
                if (response.success) {
                    alert('Sync started successfully');
                    location.reload();
                } else {
                    alert('Failed to start sync: ' + response.data.message);
                }
            });
        }
    });
});
</script>
