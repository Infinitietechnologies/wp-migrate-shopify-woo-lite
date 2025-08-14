<?php

/**
 * Customers Import Tab
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
    <form id="customer-import-form" method="post" class="swi-form">
        <?php wp_nonce_field('wmsw_import_customers', 'nonce'); ?>

        <!-- Grid Layout for Form -->
        <div class="swi-action-grid">
            <!-- Store Selection - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Store', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-form-field"> <label for="customer-store-id"><?php esc_html_e('Select Store', 'wp-migrate-shopify-woo-lite'); ?></label>
                        <select name="store_id" id="customer-store-id" required>
                            <option value=""><?php esc_html_e('Choose a store...', 'wp-migrate-shopify-woo-lite'); ?></option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo esc_attr($store->id); ?>"
                                    <?php echo $store->is_default == 1 ? 'selected' : '' ?>>
                                    <?php echo esc_html($store->store_name . ' (' . $store->shop_domain . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the Shopify store to import customers from.', 'wp-migrate-shopify-woo-lite'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Basic Import Options - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="mb-0 swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Import Options', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body pt-0">
                    <div class="">
                        <div class="swi-form-field swi-action-grid">
                            <div class="swi-checkbox-group swi-col-4 swi-col-sm-12">
                                <label>
                                    <input type="checkbox" name="import_addresses" value="1" checked>
                                    <?php esc_html_e('Import Customer Addresses', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Import all customer addresses from Shopify', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>

                            <div class="swi-checkbox-group swi-col-4 swi-col-sm-12">
                                <label>
                                    <input type="checkbox" name="send_welcome_email" value="1">
                                    <?php esc_html_e('Send Welcome Email', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Send welcome email to newly imported customers', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="swi-col-12">
                <div class="swi-form-actions">
                    <button type="submit" class="button button-primary" id="start-customers-import">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Start Customers Import', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>

                    <button type="button" class="button" id="preview-customers">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Preview Customers', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Import Progress Section -->
<div id="customers-import-progress" class="swi-progress-container swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Import Progress', 'wp-migrate-shopify-woo-lite'); ?>
        </h3>
    </div>
    <div class="swi-card-body">
        <div class="swi-progress-bar">
            <div id="customers-progress-fill" class="swi-progress-fill"></div>
        </div>
        <div class="swi-progress-info">
            <span id="customers-progress-text"></span>
            <span id="customers-progress-percentage"></span>
        </div>
        <div id="customers-progress-log"></div>
    </div>
</div>