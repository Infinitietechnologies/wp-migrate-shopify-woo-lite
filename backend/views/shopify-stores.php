<?php

/**
 * Shopify Stores Management View
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load security helper
use ShopifyWooImporter\Helpers\WMSWL_SecurityHelper;
use ShopifyWooImporter\Models\WMSWL_ShopifyStore;

// Check user permissions using security helper
WMSWL_SecurityHelper::verifyAdminPage();

// Handle actions
$action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
$store_id = isset($_GET['store_id']) ? absint(wp_unslash($_GET['store_id'])) : 0;

// Get stores data
global $wpdb;
$stores_table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);

if ($action === 'delete' && $store_id) {    // Handle store deletion
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'delete_store_' . $store_id)) {
        WMSWL_ShopifyStore::find($store_id)->delete();

        // Set a transient to display notification on the next page load
        set_transient('wmsw_store_deleted', true, 30);        // Redirect to the stores page without the action parameters
        wp_redirect(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores'));
        exit;
    }
}

$total_stores = WMSWL_ShopifyStore::get_all_stores_count(1);

$stores = WMSWL_ShopifyStore::get_all_active_stores(1);



// Include notification component
require_once WMSW_PLUGIN_DIR . 'backend/partials/components/notification.php';
?>

<div class="wrap swi-admin">
    <div class="swi-reset">
        <?php
        // Show success notification if store was deleted (using transient)
        if (get_transient('wmsw_store_deleted')):
            WMSW_notification(
                __('Store deleted successfully.', 'wp-migrate-shopify-woo-lite'),
                'success',
                [
                    'title' => __('Success!', 'wp-migrate-shopify-woo-lite'),
                    'dismissible' => true
                ]
            );
            // Delete the transient so the message doesn't show again on refresh
            delete_transient('wmsw_store_deleted');
        endif;
        ?>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <?php
            // Handle form display for add/edit
            $store = null;
            if ($action === 'edit' && $store_id) {
                $store = WMSWL_ShopifyStore::find($store_id);
            }
            ?>

            <!-- Page Header for Form -->
            <div class="swi-page-header">
                <div>
                    <h1 class="swi-page-title">
                        <?php echo $action === 'add' ? esc_html__('Add New Shopify Store', 'wp-migrate-shopify-woo-lite') : esc_html__('Edit Shopify Store', 'wp-migrate-shopify-woo-lite'); ?>
                    </h1>
                    <p class="swi-page-subtitle">
                        <?php esc_html_e('Configure your Shopify store connection and authentication.', 'wp-migrate-shopify-woo-lite'); ?>
                    </p>
                </div>
                <div class="swi-page-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores')); ?>" class="swi-btn swi-btn-secondary">
                        <span class="dashicons dashicons-arrow-left-alt swi-mr-2"></span>
                        <?php esc_html_e('Back to Stores', 'wp-migrate-shopify-woo-lite'); ?>
                    </a>
                </div>
            </div>

            <!-- Store Form -->
            <div class="swi-card">
                <div class="swi-card-header">
                    <h2 class="swi-card-title"><?php esc_html_e('Store Connection Details', 'wp-migrate-shopify-woo-lite'); ?></h2>
                </div>
                <div class="swi-card-body">
                    <form id="store-connection-form" method="post">
                        <?php wp_nonce_field('swi-admin-nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="wmsw_save_store">
                        <?php if ($store): ?>
                            <input type="hidden" name="store_id" value="<?php echo esc_attr($store->get_id()); ?>">
                        <?php endif; ?>

                        <div class="swi-form-row">
                            <div class="swi-form-group">
                                <label for="store_name" class="swi-form-label">
                                    <?php esc_html_e('Store Name', 'wp-migrate-shopify-woo-lite'); ?> <span class="text-error">*</span>
                                </label>
                                <input type="text" name="store_name" id="store_name" class="swi-form-input"
                                    value="<?php echo $store ? esc_attr($store->get_store_name()) : ''; ?>" required>
                                <p class="swi-form-help"><?php esc_html_e('A friendly name to identify this store.', 'wp-migrate-shopify-woo-lite'); ?></p>
                            </div>

                            <div class="swi-form-group">
                                <label for="shop_domain" class="swi-form-label">
                                    <?php esc_html_e('Shop Domain', 'wp-migrate-shopify-woo-lite'); ?> <span class="text-error">*</span>
                                </label>
                                <input type="text" name="shop_domain" id="shop_domain" class="swi-form-input"
                                    value="<?php echo $store ? esc_attr($store->get_shop_domain()) : ''; ?>"
                                    placeholder="yourstore.myshopify.com" required>
                                <p class="swi-form-help"><?php esc_html_e('Your Shopify store domain (without https://).', 'wp-migrate-shopify-woo-lite'); ?></p>
                            </div>
                        </div>
                        <div class="swi-form-row ">
                            <div class="swi-form-group">
                                <label for="access_token" class="swi-form-label">
                                    <?php esc_html_e('Access Token', 'wp-migrate-shopify-woo-lite'); ?> <span class="text-error">*</span>
                                </label>
                                <input type="password" name="access_token" id="access_token" class="swi-form-input"
                                    value="<?php echo $store ? esc_attr($store->get_access_token()) : ''; ?>" required>
                                <p class="swi-form-help"><?php esc_html_e('Private app access token from your Shopify store.', 'wp-migrate-shopify-woo-lite'); ?></p>
                            </div>

                            <div class="swi-form-group">
                                <label for="api_version" class="swi-form-label">
                                    <?php esc_html_e('API Version', 'wp-migrate-shopify-woo-lite'); ?> <span class="text-error">*</span>
                                </label>

                                <input type="text" name="api_version" id="api_version" class="swi-form-input"
                                    value="<?php echo $store ? esc_attr($store->get_api_version()) : ''; ?>" required>
                                <p class="swi-form-help">
                                    <?php esc_html_e('Select the Shopify API version to use. Newer versions may have more features but older versions provide better compatibility.', 'wp-migrate-shopify-woo-lite'); ?>
                                    <a href="https://shopify.dev/docs/api/release-notes" target="_blank" rel="noopener"><?php esc_html_e('View API Release Notes', 'wp-migrate-shopify-woo-lite'); ?></a>
                                </p>
                            </div>
                        </div>
                        <div class="swi-form-row">
                            <div class="swi-form-group">
                                <label class="swi-form-label">
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php echo ($store && $store->get_is_active()) ? 'checked' : ''; ?>>
                                    <?php esc_html_e('Active Store', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="swi-form-help"><?php esc_html_e('Enable this store for imports and synchronization.', 'wp-migrate-shopify-woo-lite'); ?></p>
                            </div>
                            <div class="swi-form-group">
                                <label class="swi-form-label">
                                    <input type="checkbox" name="is_default" value="1" <?php echo ($store && $store->get_is_default()) ? 'checked' : ''; ?>>
                                    <?php esc_html_e('Default Store', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="swi-form-help"><?php esc_html_e('Set as the default Shopify store for this site. Only one store can be default at a time.', 'wp-migrate-shopify-woo-lite'); ?></p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="swi-card-footer">
                    <div class="swi-flex swi-justify-between swi-items-center w-100">
                        <button type="button" class="swi-btn swi-btn-secondary" id="test-connection">
                            <span class="dashicons dashicons-admin-network swi-mr-2"></span>
                            <?php esc_html_e('Test Connection', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <div>
                            <button type="submit" id="store-connection-submit" form="store-connection-form" class="swi-btn swi-btn-success">
                                <span class="dashicons dashicons-yes swi-mr-2"></span>
                                <?php echo $action === 'add' ? esc_html__('Add Store', 'wp-migrate-shopify-woo-lite') : esc_html__('Update Store', 'wp-migrate-shopify-woo-lite'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Page Header for List -->
            <div class="swi-page-header">
                <div>
                    <h1 class="swi-page-title">
                        <?php esc_html_e('Shopify Stores', 'wp-migrate-shopify-woo-lite'); ?>
                    </h1>
                    <p class="swi-page-subtitle">
                        <?php esc_html_e('Manage your Shopify store connections and settings.', 'wp-migrate-shopify-woo-lite'); ?>
                    </p>
                </div>
                <div class="swi-page-actions"> <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add')); ?>" class="swi-btn swi-btn-primary">
                        <span class="dashicons dashicons-plus-alt swi-mr-2"></span>
                        <?php esc_html_e('Add New Store', 'wp-migrate-shopify-woo-lite'); ?>
                    </a>
                </div>
            </div>

            <?php if ((int)$total_stores == 0): ?>
                <!-- Empty State -->
                <div class="swi-import-section-empty">
                    <div class="swi-empty-state">
                        <div class="swi-empty-icon">
                            <span class="dashicons dashicons-store"></span>
                        </div>
                        <h2 class="swi-empty-title"><?php esc_html_e('No Shopify Stores Connected', 'wp-migrate-shopify-woo-lite'); ?></h2>
                        <p class="swi-empty-description"><?php esc_html_e('Connect your first Shopify store to start importing products, customers, and orders.', 'wp-migrate-shopify-woo-lite'); ?></p> <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add')); ?>" class="swi-btn swi-btn-primary">
                            <span class="dashicons dashicons-plus-alt swi-mr-2"></span>
                            <?php esc_html_e('Connect Shopify Store', 'wp-migrate-shopify-woo-lite'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Stores Grid -->
                <div class="swi-action-grid">
                    <?php foreach ($stores as $store): ?>
                        <div class="swi-col-4">

                            <div class="swi-card">
                                <div class="swi-card-header">
                                    <div class="swi-flex swi-justify-between swi-items-center w-100">
                                        <div class="swi-flex swi-items-center">
                                            <h3 class="swi-card-title"><?php echo esc_html($store['store_name']); ?></h3>
                                            <?php if ($store['is_default']): ?>
                                                <span class="swi-default-badge swi-ml-2" title="<?php esc_attr_e('Default Store', 'wp-migrate-shopify-woo-lite'); ?>">
                                                    <span class="dashicons dashicons-star-filled swi-default-star"></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="swi-connection-status <?php echo $store['is_active'] ? 'swi-connection-connected' : 'swi-connection-disconnected'; ?>">
                                            <span class="dashicons dashicons-<?php echo $store['is_active'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                                            <?php echo $store['is_active'] ? esc_html__('Active', 'wp-migrate-shopify-woo-lite') : esc_html__('Inactive', 'wp-migrate-shopify-woo-lite'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="swi-card-body">
                                    <div class="swi-mb-4">
                                        <p><strong><?php esc_html_e('Domain:', 'wp-migrate-shopify-woo-lite'); ?></strong> <?php echo esc_html($store['shop_domain']); ?></p>
                                        <p><strong><?php esc_html_e('API Version:', 'wp-migrate-shopify-woo-lite'); ?></strong> <?php echo esc_html($store['api_version'] ?? '2023-10'); ?></p>
                                        <p><strong><?php esc_html_e('Last Sync:', 'wp-migrate-shopify-woo-lite'); ?></strong>
                                            <?php echo $store['last_sync'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($store['last_sync']))) : esc_html__('Never', 'wp-migrate-shopify-woo-lite'); ?>
                                        </p>
                                        <p><strong><?php esc_html_e('Connected:', 'wp-migrate-shopify-woo-lite'); ?></strong>
                                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($store['created_at']))); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="swi-card-footer">
                                    <div class="swi-table-actions">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=edit&store_id=' . $store['id'])); ?>" class="swi-btn swi-btn-sm swi-btn-secondary">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e('Edit', 'wp-migrate-shopify-woo-lite'); ?>
                                        </a>

                                        <button type="button" class="swi-btn swi-btn-sm swi-btn-secondary swi-test-connection"
                                            data-store-id="<?php echo esc_attr($store['id']); ?>">
                                            <span class="dashicons dashicons-admin-network"></span>
                                            <?php esc_html_e('Test', 'wp-migrate-shopify-woo-lite'); ?>
                                        </button>

                                        <?php if (!$store['is_default']): ?>
                                            <button type="button" class="swi-btn swi-btn-sm swi-btn-info swi-set-default"
                                                data-store-id="<?php echo esc_attr($store['id']); ?>">
                                                <span class="dashicons dashicons-star-filled"></span>
                                                <?php esc_html_e('Set Default', 'wp-migrate-shopify-woo-lite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($store['is_active']): ?>
                                            <button href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=deactivate&store_id=' . $store['id']), 'deactivate_store_' . $store['id'])); ?>" class="swi-btn swi-btn-sm swi-btn-warning deactivate-store">
                                                <span class="dashicons dashicons-controls-pause"></span>
                                                <?php esc_html_e('Deactivate', 'wp-migrate-shopify-woo-lite'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=deactivate&store_id=' . $store['id']), 'deactivate_store_' . $store['id'])); ?>" class="swi-btn swi-btn-sm swi-btn-success deactivate-store">
                                                <span class="dashicons dashicons-controls-play"></span>
                                                <?php esc_html_e('Activate', 'wp-migrate-shopify-woo-lite'); ?>
                                            </button>
                                        <?php endif; ?>                                         <button type="button"
                                            data-store-id="<?php echo esc_attr($store['id']); ?>"
                                            class="swi-btn swi-btn-sm swi-btn-danger delete-store">
                                            <span class="dashicons dashicons-trash"></span>
                                            <?php esc_html_e('Delete', 'wp-migrate-shopify-woo-lite'); ?>
                                        </button>

                                        <button type="button"
                                            data-store-id="<?php echo esc_attr($store['id']); ?>"
                                            class="swi-btn swi-btn-sm swi-btn-secondary swi-copy-store">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            <?php esc_html_e('Copy', 'wp-migrate-shopify-woo-lite'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>