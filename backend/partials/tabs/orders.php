<?php

/**
 * Orders Import Tab
 */

use ShopifyWooImporter\Models\WMSWL_ShopifyStore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get available stores
$stores = WMSWL_ShopifyStore::get_all_active_stores();

?>

<div class="swi-import-form">
    <form id="orders-import-form" method="post" class="swi-form">
        <?php wp_nonce_field('wmsw_import_orders', 'nonce'); ?>

        <!-- Grid Layout for Form -->
        <div class="swi-action-grid">
            <!-- Store Selection - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Store', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-form-field">
                        <label for="store_id"><?php esc_html_e('Select Store', 'wp-migrate-shopify-woo-lite'); ?></label>
                        <select name="store_id" id="store_id" required>
                            <option value=""><?php esc_html_e('Choose a store...', 'wp-migrate-shopify-woo-lite'); ?></option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo esc_attr($store->id); ?>"
                                    <?php echo $store->is_default == 1 ? 'selected' : '' ?>>
                                    <?php echo esc_html($store->store_name . ' (' . $store->shop_domain . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the Shopify store to import orders from.', 'wp-migrate-shopify-woo-lite'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Order Status Filter -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Order Status Filter', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-action-grid">
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="order_status[]" value="open" checked>
                                    <?php esc_html_e('Open Orders', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Orders that are still active and can be modified', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="order_status[]" value="closed" checked>
                                    <?php esc_html_e('Closed Orders', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Orders that have been fulfilled and closed', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="order_status[]" value="cancelled">
                                    <?php esc_html_e('Cancelled Orders', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Orders that have been cancelled', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Options -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Import Options', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-action-grid swi-options-group">
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="create_customers" value="1" checked>
                                    <?php esc_html_e('Create Customers', 'wp-migrate-shopify-woo-lite'); ?>
                                    <span class="swi-help-tip" title="<?php esc_attr_e('If checked, new WooCommerce customers will be created for Shopify customers that do not already exist.', 'wp-migrate-shopify-woo-lite'); ?>">&#9432;</span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Automatically create customer accounts if they don\'t exist.', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="import_notes" value="1" checked>
                                    <?php esc_html_e('Import Order Notes', 'wp-migrate-shopify-woo-lite'); ?>
                                    <span class="swi-help-tip" title="<?php esc_attr_e('If checked, order notes and comments from Shopify will be imported into WooCommerce.', 'wp-migrate-shopify-woo-lite'); ?>">&#9432;</span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Include order notes and comments from Shopify.', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label>
                                    <input type="checkbox" name="import_refunds" value="1">
                                    <?php esc_html_e('Import Refunds', 'wp-migrate-shopify-woo-lite'); ?>
                                    <span class="swi-help-tip" title="<?php esc_attr_e('If checked, refund information and transaction history will be imported.', 'wp-migrate-shopify-woo-lite'); ?>">&#9432;</span>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Import refund information and transaction history.', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="swi-col-12">
                <div class="swi-form-actions">
                    <button type="submit" class="button button-primary" id="start-orders-import">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Start Orders Import', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>

                    <button type="button" class="button" id="preview-orders">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Preview Orders', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div><!-- End of swi-import-form -->

<!-- Import Progress Section -->
<div id="orders-import-progress" class="swi-progress-container swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Import Progress', 'wp-migrate-shopify-woo-lite'); ?>
        </h3>
    </div>
    <div class="swi-card-body">
        <div class="swi-progress-bar">
            <div id="orders-progress-fill" class="swi-progress-fill"></div>
        </div>
        <div class="swi-progress-info">
            <span id="orders-progress-text"></span>
            <span id="orders-progress-percentage"></span>
        </div>
        <div id="orders-progress-log"></div>
    </div>
</div>

<!-- Orders Preview Section -->
<div id="orders-preview" class="swi-progress-container swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title"><?php esc_html_e('Orders Preview', 'wp-migrate-shopify-woo-lite'); ?></h3>
        <button type="button" class="button" id="close-preview">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="swi-card-body">
        <div id="preview-content">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>
