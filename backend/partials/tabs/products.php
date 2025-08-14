<?php

/**
 * Products Import Tab
 */

use ShopifyWooImporter\Models\WMSWL_ShopifyStore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get available stores
global $wpdb;

$stores = WMSWL_ShopifyStore::get_all_active_stores();


// Load product import settings
$product_settings = [];
if (class_exists('ShopifyWooImporter\\Models\\WMSWL_Settings')) {
    $product_settings_keys = [
        'import_type',
        'processing_threads',
        'error_handling',
        'import_images',
        'import_variants',
        'import_videos',
        'import_descriptions',
        'import_seo',
        'import_collections',
        'import_tags',
        'import_vendor_as_brand',
        'preserve_ids',
        'import_metafields',
        'create_product_lookup_table',
        'overwrite_existing',
        'skip_no_inventory',
        'sync_inventory'
    ];
    foreach ($product_settings_keys as $key) {
        $setting = ShopifyWooImporter\Models\WMSWL_Settings::get($key, null, true);
        if ($setting) {
            $product_settings[$key] = $setting->getSettingValue();
        }
    }
}
if (empty($product_settings)) {
    $product_settings = get_option('wmsw_options', []);
}
$product_defaults = [
    'import_type' => 'all',
    'processing_threads' => 2,
    'error_handling' => 'continue',
    'import_images' => true,
    'import_variants' => true,
    'import_videos' => false,
    'import_descriptions' => false,
    'import_seo' => false,
    'import_collections' => false,
    'import_tags' => false,
    'import_vendor_as_brand' => false,
    'preserve_ids' => false,
    'import_metafields' => false,
    'create_product_lookup_table' => false,
    'overwrite_existing' => false,
    'skip_no_inventory' => false,
    'sync_inventory' => false
];
$product_settings = wp_parse_args($product_settings, $product_defaults);
?>

<div class="swi-import-form">
    <form id="products-import-form" method="post" class="swi-form">
        <?php wp_nonce_field('wmsw_import_products', 'nonce'); ?>

        <!-- Grid Layout for Form -->
        <div class="swi-action-grid">
            <!-- Store Selection - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Store', 'wp-migrate-shopify-woo-lite'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-form-group">
                        <label for="store_id"><?php esc_html_e('Select Store', 'wp-migrate-shopify-woo-lite'); ?></label>
                        <select name="store_id" class="swi-form-select" id="store_id" required>
                            <option value=""><?php esc_html_e('Choose a store...', 'wp-migrate-shopify-woo-lite'); ?></option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo esc_attr($store['id']); ?>"
                                    <?php echo $store['is_default'] == 1 ? 'selected' : '' ?>>
                                    <?php echo esc_html($store['store_name'] . ' (' . $store['shop_domain'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the Shopify store to import products from.', 'wp-migrate-shopify-woo-lite'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Import Settings -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Import Settings', 'wp-migrate-shopify-woo-lite'); ?>
                    </h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-action-grid">
                        <!-- Import Type -->
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label for="import_type" class="swi-form-label">
                                    <?php esc_html_e('Import Type', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <select name="import_type" id="import_type" class="swi-form-select">
                                    <option value="all" <?php selected($product_settings['import_type'] ?? 'all', 'all'); ?>><?php esc_html_e('Import All Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    <option value="new" <?php selected($product_settings['import_type'] ?? '', 'new'); ?>><?php esc_html_e('Import New Products Only', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    <option value="updated" <?php selected($product_settings['import_type'] ?? '', 'updated'); ?>><?php esc_html_e('Update Existing Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    <option value="custom" <?php selected($product_settings['import_type'] ?? '', 'custom'); ?>><?php esc_html_e('Custom Date Range', 'wp-migrate-shopify-woo-lite'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose which products to import.', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Error Handling -->
                        <div class="swi-col-4 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label for="error_handling" class="swi-form-label">
                                    <?php esc_html_e('Error Handling', 'wp-migrate-shopify-woo-lite'); ?>
                                </label>
                                <select name="error_handling" id="error_handling" class="swi-form-select">
                                    <option value="stop" <?php selected($product_settings['error_handling'] ?? '', 'stop'); ?>><?php esc_html_e('Stop on Error', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    <option value="continue" <?php selected($product_settings['error_handling'] ?? 'continue', 'continue'); ?>><?php esc_html_e('Continue and Log Errors', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    <option value="skip" <?php selected($product_settings['error_handling'] ?? '', 'skip'); ?>><?php esc_html_e('Skip Problematic Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('How to handle errors during the import process.', 'wp-migrate-shopify-woo-lite'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Content Options -->
                    <div class="swi-action-grid">
                        <div class="swi-col-6 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label class="swi-form-label"><?php esc_html_e('Content Options', 'wp-migrate-shopify-woo-lite'); ?></label>
                                <div class="swi-checkbox-group">
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_images" value="1" <?php checked($product_settings['import_images']); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import product images', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_variants" value="1" <?php checked($product_settings['import_variants']); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import product variants', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_videos" value="1" <?php checked($product_settings['import_videos'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import product videos', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_descriptions" value="1" <?php checked($product_settings['import_descriptions'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import descriptions', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_seo" value="1" <?php checked($product_settings['import_seo'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import SEO data (meta title, description)', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="swi-col-6 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label class="swi-form-label"><?php esc_html_e('Taxonomy Options', 'wp-migrate-shopify-woo-lite'); ?></label>
                                <div class="swi-checkbox-group">
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_collections" value="1" <?php checked($product_settings['import_collections'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import collections as categories', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_tags" value="1" <?php checked($product_settings['import_tags'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import product tags', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_vendor_as_brand" value="1" <?php checked($product_settings['import_vendor_as_brand'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import vendor as brand', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div class="swi-action-grid">
                        <div class="swi-col-6 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label class="swi-form-label"><?php esc_html_e('Data Structure Options', 'wp-migrate-shopify-woo-lite'); ?></label>
                                <div class="swi-checkbox-group">
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="preserve_ids" value="1" <?php checked($product_settings['preserve_ids'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Preserve Shopify product IDs', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="import_metafields" value="1" <?php checked($product_settings['import_metafields'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Import custom metafields', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="create_product_lookup_table" value="1" <?php checked($product_settings['create_product_lookup_table'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Create Shopify-WooCommerce product lookup table', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="swi-col-6 swi-col-sm-12">
                            <div class="swi-form-field">
                                <label class="swi-form-label"><?php esc_html_e('Advanced Options', 'wp-migrate-shopify-woo-lite'); ?></label>
                                <div class="swi-checkbox-group">
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="overwrite_existing" value="1" <?php checked($product_settings['overwrite_existing'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Overwrite existing products', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="skip_no_inventory" value="1" <?php checked($product_settings['skip_no_inventory'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Skip products with no inventory', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                    <label class="swi-checkbox-item">
                                        <input type="checkbox" name="sync_inventory" value="1" <?php checked($product_settings['sync_inventory'] ?? false); ?>>
                                        <span class="swi-checkbox-label"><?php esc_html_e('Sync inventory levels', 'wp-migrate-shopify-woo-lite'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters Toggle - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title">
                        <?php esc_html_e('Advanced Filters', 'wp-migrate-shopify-woo-lite'); ?>
                    </h3>
                    <button type="button" class="button" id="toggle-advanced-filters">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Show Advanced Filters', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>
                </div>

                <!-- Advanced Filters Section - Full Width, Initially Hidden -->
                <div id="advanced-filters-section" class="swi-col-12 swi-form-card advanced-filter border-none shadow-none">

                    <div class="swi-card-body mb-0">
                        <div class="swi-action-grid">
                            <!-- Product Type - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label for="product_type"><?php esc_html_e('Product Type', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <select name="product_type" id="product_type">
                                        <option value=""><?php esc_html_e('All product types', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="physical"><?php esc_html_e('Physical Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="digital"><?php esc_html_e('Digital Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="subscription"><?php esc_html_e('Subscription Products', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Filter by product type', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Vendor - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label for="vendor"><?php esc_html_e('Vendor', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <input type="text" name="vendor" id="vendor" placeholder="<?php esc_attr_e('Enter vendor name', 'wp-migrate-shopify-woo-lite'); ?>">
                                    <p class="description">
                                        <?php esc_html_e('Filter products by specific vendor', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Tags - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label for="tags"><?php esc_html_e('Tags', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <input type="text" name="tags" id="tags" placeholder="<?php esc_attr_e('Enter tags (comma separated)', 'wp-migrate-shopify-woo-lite'); ?>">
                                    <p class="description">
                                        <?php esc_html_e('Filter products by tags (comma separated)', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Price Range - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label><?php esc_html_e('Price Range', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <div class="price-range-inputs">
                                        <input type="number" name="min_price" id="min_price" placeholder="<?php esc_attr_e('Min price', 'wp-migrate-shopify-woo-lite'); ?>" step="0.01" min="0">
                                        <span><?php esc_html_e('to', 'wp-migrate-shopify-woo-lite'); ?></span>
                                        <input type="number" name="max_price" id="max_price" placeholder="<?php esc_attr_e('Max price', 'wp-migrate-shopify-woo-lite'); ?>" step="0.01" min="0">
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Filter products by price range', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div> <!-- Inventory Status - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label for="inventory_status"><?php esc_html_e('Inventory Status', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <select name="inventory_status" id="inventory_status">
                                        <option value=""><?php esc_html_e('All inventory statuses', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="in_stock"><?php esc_html_e('In Stock', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="out_of_stock"><?php esc_html_e('Out of Stock', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="low_stock"><?php esc_html_e('Low Stock', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Filter by inventory status', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Product Status - Half Width -->
                            <div class="swi-col-6 swi-col-sm-12">
                                <div class="swi-form-field">
                                    <label for="product_status"><?php esc_html_e('Product Status', 'wp-migrate-shopify-woo-lite'); ?></label>
                                    <select name="product_status" id="product_status">
                                        <option value=""><?php esc_html_e('All product statuses', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="active" selected><?php esc_html_e('Active', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="archived"><?php esc_html_e('Archived', 'wp-migrate-shopify-woo-lite'); ?></option>
                                        <option value="draft"><?php esc_html_e('Draft', 'wp-migrate-shopify-woo-lite'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Filter by Shopify product status', 'wp-migrate-shopify-woo-lite'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div><!-- End of swi-action-grid -->

            <div class="swi-col-12">
                <div class="swi-form-actions">
                    <button type="submit" class="button button-primary" id="start-products-import">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Start Products Import', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>

                    <button type="button" class="button" id="preview-products">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Preview Products', 'wp-migrate-shopify-woo-lite'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div><!-- End of swi-import-form -->

<!-- Import Progress Section -->
<div id="products-import-progress" class="swi-progress-container swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Import Progress', 'wp-migrate-shopify-woo-lite'); ?>
        </h3>
    </div>
    <div class="swi-card-body">
        <div class="swi-progress-bar">
            <div id="products-progress-fill" class="swi-progress-fill"></div>
        </div>
        <div class="swi-progress-info">
            <span id="products-progress-text"></span>
            <span id="products-progress-percentage"></span>
        </div>
        <div id="products-progress-log"></div>
    </div>
</div>

<!-- Product Import Scripts are now enqueued via WMSW_Backend.php -->