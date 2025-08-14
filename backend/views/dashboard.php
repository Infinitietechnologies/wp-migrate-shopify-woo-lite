<?php

/**
 * Dashboard View
 */

use ShopifyWooImporter\Models\WMSWL_ShopifyStore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$total_stores = WMSWL_ShopifyStore::get_all_stores_count(1);

$total_stores_all = WMSWL_ShopifyStore::get_all_stores_count(0);

// Include notification component
require_once WMSW_PLUGIN_DIR . 'backend/partials/components/notification.php';
?>

<div class="wrap swi-admin">
    <div class="swi-reset">
        <!-- Page Header -->
        <div class="swi-mb-6">
            <h1 class="swi-text-2xl swi-font-bold swi-text-gray-900 swi-mb-2">
                <?php esc_html_e('Shopify to WooCommerce Importer', 'wp-migrate-shopify-woo-lite'); ?>
            </h1>
            <p class="swi-text-gray-600">
                <?php esc_html_e('Manage your Shopify store connections and import data to WooCommerce.', 'wp-migrate-shopify-woo-lite'); ?>
            </p>
        </div>

        <!-- Connection Status Notices -->
        <?php if ($total_stores == 0): ?>
            <?php
            // translators: %s is a link to connect the first store
            WMSW_notification(
                sprintf(
                    // translators: %s is a link to connect the first store
                    __('No Shopify stores connected yet. %s to get started.', 'wp-migrate-shopify-woo-lite'),
                    '<a href="' . esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add')) . '" class="swi-notification-action">' . __('Connect your first store', 'wp-migrate-shopify-woo-lite') . '</a>'
                ),
                'warning',
                [
                    'title' => __('Welcome to Shopify WooCommerce Importer!', 'wp-migrate-shopify-woo-lite'),
                    'dismissible' => true,
                    'actions' => [
                        [
                            'label' => __('Connect Store', 'wp-migrate-shopify-woo-lite'),
                            'url' => admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add'),
                            'class' => 'button button-primary'
                        ],
                        [
                            'label' => __('View Documentation', 'wp-migrate-shopify-woo-lite'),
                            'url' => admin_url('admin.php?page=shopify-settings&tab=help'),
                            'class' => 'button button-secondary'
                        ]
                    ]
                ]
            );
            ?>
        <?php endif; ?>

        <!-- Stats Cards Grid -->
        <div class="swi-stats-dashboard w-25">
            <div class="swi-stat-card swi-stat-card-stores w-25">
                <div class="swi-stat-icon">
                    <span class="dashicons dashicons-store"></span>
                </div>
                <div class="swi-stat-content">
                    <h3><?php echo esc_html($total_stores); ?></h3>
                    <p><?php esc_html_e('Connected Stores', 'wp-migrate-shopify-woo-lite'); ?></p>
                    <?php if ($total_stores_all > $total_stores): ?>
                        <small class="swi-stat-note">
                            <?php
                            // translators: %d is the number of disabled stores
                            echo esc_html($total_stores_all - $total_stores) . ' ' . esc_html__('disabled', 'wp-migrate-shopify-woo-lite');
                            ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="swi-quick-actions mb-8 pt-0">
            <h2 class="swi-text-xl swi-font-semibold swi-text-gray-900 swi-mb-4">
                <?php esc_html_e('Quick Actions', 'wp-migrate-shopify-woo-lite'); ?>
            </h2>

            <div class="swi-quick-actions-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add')); ?>"
                    class="swi-quick-action-card swi-quick-action-primary">
                    <div class="swi-quick-action-icon">
                        <span class="dashicons dashicons-plus-alt"></span>
                    </div>
                    <div class="swi-quick-action-content">
                        <h3><?php esc_html_e('Connect Store', 'wp-migrate-shopify-woo-lite'); ?></h3>
                        <p><?php esc_html_e('Add another Shopify store to import data', 'wp-migrate-shopify-woo-lite'); ?></p>
                    </div>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores')); ?>"
                    class="swi-quick-action-card">
                    <div class="swi-quick-action-icon">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                    <div class="swi-quick-action-content">
                        <h3><?php esc_html_e('Manage Stores', 'wp-migrate-shopify-woo-lite'); ?></h3>
                        <p><?php esc_html_e('View and manage your connected stores', 'wp-migrate-shopify-woo-lite'); ?></p>
                    </div>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-logs')); ?>"
                    class="swi-quick-action-card">
                    <div class="swi-quick-action-icon">
                        <span class="dashicons dashicons-list-view"></span>
                    </div>
                    <div class="swi-quick-action-content">
                        <h3><?php esc_html_e('View Logs', 'wp-migrate-shopify-woo-lite'); ?></h3>
                        <p><?php esc_html_e('Check import history and system logs', 'wp-migrate-shopify-woo-lite'); ?></p>
                    </div>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-settings')); ?>"
                    class="swi-quick-action-card">
                    <div class="swi-quick-action-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="swi-quick-action-content">
                        <h3><?php esc_html_e('Settings', 'wp-migrate-shopify-woo-lite'); ?></h3>
                        <p><?php esc_html_e('Configure plugin settings and preferences', 'wp-migrate-shopify-woo-lite'); ?></p>
                    </div>
                </a>
            </div>

        </div>

        <!-- Import Tabs - Only show if stores are connected -->
        <?php if ((int)$total_stores > 0): ?>
            <div class="swi-import-section">
                <h2 class="swi-text-xl swi-font-semibold swi-text-gray-900 swi-mb-4">
                    <?php esc_html_e('Import Data', 'wp-migrate-shopify-woo-lite'); ?>
                </h2>

                <div class="swi-tabs">
                    <nav class="swi-tab-nav">
                        <div class="swi-tab-indicator"></div>
                        <button class="swi-tab-button active" data-tab="products">
                            <?php esc_html_e('Products', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <button class="swi-tab-button" data-tab="customers">
                            <?php esc_html_e('Customers', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <button class="swi-tab-button" data-tab="orders">
                            <?php esc_html_e('Orders', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <button class="swi-tab-button" data-tab="pages">
                            <?php esc_html_e('Pages', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <button class="swi-tab-button" data-tab="blogs">
                            <?php esc_html_e('Blogs', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                        <button class="swi-tab-button" data-tab="coupons">
                            <?php esc_html_e('Coupons', 'wp-migrate-shopify-woo-lite'); ?>
                        </button>
                    </nav>

                    <div class="swi-tab-content">
                        <div id="products-tab" class="swi-tab-pane active">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/products.php'; ?>
                        </div>

                        <div id="customers-tab" class="swi-tab-pane">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/customers.php'; ?>
                        </div>

                        <div id="orders-tab" class="swi-tab-pane">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/orders.php'; ?>
                        </div>

                        <div id="pages-tab" class="swi-tab-pane">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/pages.php'; ?>
                        </div>

                        <div id="blogs-tab" class="swi-tab-pane">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/blogs.php'; ?>
                        </div>

                        <div id="coupons-tab" class="swi-tab-pane">
                            <?php include WMSW_PLUGIN_DIR . 'backend/partials/tabs/coupons.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty state for import section -->
            <div class="swi-import-section-empty">
                <div class="swi-empty-state">
                    <div class="swi-empty-icon">
                        <span class="dashicons dashicons-download"></span>
                    </div>
                    <h3 class="swi-empty-title"><?php esc_html_e('Ready to Import Data', 'wp-migrate-shopify-woo-lite'); ?></h3>
                    <p class="swi-empty-description"><?php esc_html_e('Once you connect a Shopify store, you\'ll be able to import products, customers, orders, pages, blogs, and coupons directly to your WooCommerce store.', 'wp-migrate-shopify-woo-lite'); ?></p>
                    <div class="swi-empty-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-lite-stores&action=add')); ?>" class="swi-btn swi-btn-primary">
                            <span class="dashicons dashicons-plus-alt swi-mr-2"></span>
                            <?php esc_html_e('Connect Store to Start Importing', 'wp-migrate-shopify-woo-lite'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>