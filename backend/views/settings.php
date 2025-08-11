<?php

/**
 * Settings View
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load from custom table if available, fallback to options, then defaults
$options = [];
if (class_exists('ShopifyWooImporter\\Models\\WMSW_Settings')) {
    $settings_keys = [
        'import_batch_size',
        'enable_auto_sync',
        'sync_interval',
        'preserve_shopify_ids',
        'log_retention_days',
        'enable_debug_mode'
    ];
    foreach ($settings_keys as $key) {
        $setting = ShopifyWooImporter\Models\WMSW_Settings::get($key, null, true);
        if ($setting) {
            $options[$key] = $setting->getSettingValue();
        }
    }
}
if (empty($options)) {
    $options = get_option('wmsw_options', []);
}
$defaults = [
    'import_batch_size' => 50,
    'enable_auto_sync' => false,
    'sync_interval' => 'hourly',
    'preserve_shopify_ids' => true,
    'log_retention_days' => 30,
    'enable_debug_mode' => false
];
$options = wp_parse_args($options, $defaults);

?>

<div class="wrap swi-admin">
    <div class="swi-reset">

        <!-- Page Header -->
        <div class="swi-page-header">
            <div>
                <h1 class="swi-page-title pt-0">
                    <?php esc_html_e('Settings', 'wp-migrate-shopify-woo'); ?>
                </h1>
                <p class="swi-page-subtitle">
                    <?php esc_html_e('Configure import preferences and logging settings.', 'wp-migrate-shopify-woo'); ?>
                </p>
            </div>
            <div class="swi-page-actions">
                <button type="button" class="swi-btn swi-btn-secondary" id="reset-settings">
                    <span class="dashicons dashicons-undo swi-mr-2"></span>
                    <?php esc_html_e('Reset to Defaults', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>
        </div>

        <form method="post" action="" class="swi-settings-form" data-auto-save data-auto-save-delay="3000">
            <input type="hidden" name="action" value="WMSW_save_settings">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('swi-admin-nonce')); ?>">
            <!-- Import Settings -->
            <div class="swi-card mb-6">
                <div class="swi-card-header">
                    <h2 class="swi-card-title">
                        <span class="dashicons dashicons-download swi-mr-2"></span>
                        <?php esc_html_e('Import Settings', 'wp-migrate-shopify-woo'); ?>
                    </h2>
                </div>
                <div class="swi-card-body">
                    <!-- Basic Import Settings Grid -->
                    <div class="swi-action-grid p-1">

                        <div class="swi-col-4 swi-col-md-12">
                            <div class="swi-form-group">
                                <label for="import_batch_size" class="swi-form-label">
                                    <?php esc_html_e('Batch Size', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <input type="number" name="import_batch_size" id="import_batch_size"
                                    class="swi-form-input" value="<?php echo esc_attr($options['import_batch_size']); ?>"
                                    min="1" max="250">
                                <p class="swi-form-help"><?php esc_html_e('Number of items to process in each batch during import.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>

                        <div class="swi-col-4 swi-col-md-12 d-none">
                            <div class="swi-form-group">
                                <label class="swi-form-label">
                                    <input type="checkbox" name="enable_auto_sync" value="1" <?php checked($options['enable_auto_sync']); ?>>
                                    <?php esc_html_e('Enable Auto Sync', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <p class="swi-form-help"><?php esc_html_e('Automatically sync changes from Shopify to WooCommerce.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>

                        <div class="swi-col-4 swi-col-md-12 d-none">
                            <div class="swi-form-group">
                                <label for="sync_interval" class="swi-form-label">
                                    <?php esc_html_e('Sync Interval', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <select name="sync_interval" id="sync_interval" class="swi-form-select">
                                    <option value="hourly" <?php selected($options['sync_interval'], 'hourly'); ?>><?php esc_html_e('Hourly', 'wp-migrate-shopify-woo'); ?></option>
                                    <option value="twicedaily" <?php selected($options['sync_interval'], 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'wp-migrate-shopify-woo'); ?></option>
                                    <option value="daily" <?php selected($options['sync_interval'], 'daily'); ?>><?php esc_html_e('Daily', 'wp-migrate-shopify-woo'); ?></option>
                                </select>
                                <p class="swi-form-help"><?php esc_html_e('How often to check for updates from Shopify.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>

                        <div class="swi-col-12 d-none">
                            <div class="swi-form-group">
                                <label class="swi-form-label">
                                    <input type="checkbox" name="preserve_shopify_ids" value="1" <?php checked($options['preserve_shopify_ids']); ?>>
                                    <?php esc_html_e('Preserve Shopify IDs', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <p class="swi-form-help"><?php esc_html_e('Store original Shopify IDs in WooCommerce meta fields for future reference.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logging & Debug Settings -->
            <div class="swi-card mb-6">
                <div class="swi-card-header">
                    <h2 class="swi-card-title">
                        <span class="dashicons dashicons-admin-tools swi-mr-2"></span>
                        <?php esc_html_e('Logging & Debug Settings', 'wp-migrate-shopify-woo'); ?>
                    </h2>
                </div>
                <div class="swi-card-body">
                    <div class="swi-action-grid p-1">
                        <div class="swi-col-6 swi-col-md-12">
                            <div class="swi-form-group">
                                <label for="log_retention_days" class="swi-form-label">
                                    <?php esc_html_e('Log Retention (days)', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <input type="number" name="log_retention_days" id="log_retention_days"
                                    class="swi-form-input" value="<?php echo esc_attr($options['log_retention_days']); ?>"
                                    min="1" max="365">
                                <p class="swi-form-help"><?php esc_html_e('Number of days to keep import logs before automatic cleanup.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>

                        <div class="swi-col-6 swi-col-md-12">
                            <div class="swi-form-group">
                                <label class="swi-form-label">
                                    <input type="checkbox" name="enable_debug_mode" value="1" <?php checked($options['enable_debug_mode']); ?>>
                                    <?php esc_html_e('Enable debug mode', 'wp-migrate-shopify-woo'); ?>
                                </label>
                                <p class="swi-form-help"><?php esc_html_e('Enable detailed logging for troubleshooting. May impact performance.', 'wp-migrate-shopify-woo'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Settings -->
            <div class="swi-card">
                <div class="swi-card-footer">
                    <div class="swi-flex swi-justify-between swi-items-center">
                        <div class="swi-text-sm swi-text-gray-600">
                            <?php esc_html_e('Settings are saved automatically and apply to all future imports.', 'wp-migrate-shopify-woo'); ?>
                        </div>
                        <button type="submit" class="swi-btn swi-btn-primary swi-mt-6">
                            <span class="dashicons dashicons-saved swi-mr-2"></span>
                            <?php esc_html_e('Save Settings', 'wp-migrate-shopify-woo'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
