<?php

/**
 * Categories Import Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get available stores
global $wpdb;
$stores_table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);
$stores = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$stores_table}` WHERE is_active = %d", 1));
?>

<div class="swi-import-form" id="swi-categories-tab">
    <form id="swi-categories-import-form" class="swi-form">
        <?php wp_nonce_field('wmsw_import_categories', 'nonce'); ?>

        <!-- Grid Layout for Form -->
        <div class="swi-action-grid">
            <!-- Store Selection - Full Width -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-header">
                    <h3 class="swi-card-title"><?php esc_html_e('Store', 'wp-migrate-shopify-woo'); ?></h3>
                </div>
                <div class="swi-card-body">
                    <div class="swi-form-field">
                        <label for="swi-categories-store-select">
                            <?php esc_html_e('Select Store', 'wp-migrate-shopify-woo'); ?></label>

                        <select id="swi-categories-store-select" name="store_id" required>
                            <option value=""><?php esc_html_e('Choose a store...', 'wp-migrate-shopify-woo'); ?></option>
                            <?php foreach ($stores as $store): ?>
                                <option
                                    value="<?php echo $store->id; ?>"
                                    <?php echo $store->is_default ? 'selected' : '' ?>>
                                    <?php echo $store->store_name . ' (' . $store->shop_domain . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the Shopify store to import categories from.', 'wp-migrate-shopify-woo'); ?>
                        </p>
                    </div>

                    <!-- Advanced Filters Toggle - Full Width -->
                    <div class="swi-col-12 swi-form-card">
                        <div class="swi-card-header">
                            <h3 class="swi-card-title">
                                <?php esc_html_e('Advanced Filters', 'wp-migrate-shopify-woo'); ?></h3> <button type="button" class="button" id="swi-categories-toggle-filters"> <span class="dashicons dashicons-filter"></span>
                                <?php esc_html_e('Show Advanced Filters', 'wp-migrate-shopify-woo'); ?>
                            </button>
                        </div>

                        <!-- Advanced Filters Section - Initially Hidden -->
                        <div id="swi-categories-advanced-filters" class="swi-col-12 swi-form-card advanced-filter border-none shadow-none">
                            <div class="swi-card-body mb-0">
                                <div class="swi-action-grid">
                                    <!-- Filter by Products - Half Width -->
                                    <div class="swi-col-6 swi-col-sm-12">
                                        <div class="swi-form-field"> <label for="swi-categories-filter-has-products"><?php esc_html_e('Filter by Products', 'wp-migrate-shopify-woo'); ?></label>
                                            <div class="swi-checkbox-group">
                                                <label><input type="checkbox" id="swi-categories-filter-has-products" value="1"> <?php esc_html_e('Only show categories with products', 'wp-migrate-shopify-woo'); ?></label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sort Options - Half Width -->
                                    <div class="swi-col-6 swi-col-sm-12">
                                        <div class="swi-form-field"> <label for="swi-categories-sort"><?php esc_html_e('Sort By', 'wp-migrate-shopify-woo'); ?></label>
                                            <select id="swi-categories-sort">
                                                <option value="title_asc"><?php esc_html_e('Sort by name (A-Z)', 'wp-migrate-shopify-woo'); ?></option>
                                                <option value="title_desc"><?php esc_html_e('Sort by name (Z-A)', 'wp-migrate-shopify-woo'); ?></option>
                                                <option value="products_count"><?php esc_html_e('Sort by product count', 'wp-migrate-shopify-woo'); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Search - Full Width -->
                                    <div class="swi-col-12">
                                        <div class="swi-form-field"> <label for="swi-categories-search"><?php esc_html_e('Search Categories', 'wp-migrate-shopify-woo'); ?></label>
                                            <input type="text" id="swi-categories-search" placeholder="<?php esc_attr_e('Search categories...', 'wp-migrate-shopify-woo'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="swi-form-actions">
                        <button type="button" id="swi-categories-preview-btn" class="button button-secondary">
                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Preview Categories', 'wp-migrate-shopify-woo'); ?>
                        </button>
                        <button type="button" id="swi-categories-fetch-btn" class="button button-primary">
                            <span class="dashicons dashicons-download"></span> <?php esc_html_e('Fetch Categories', 'wp-migrate-shopify-woo'); ?>
                        </button>
                    </div>
                </div>
            </div> <!-- Note about category settings -->
            <div class="swi-col-12 swi-form-card">
                <div class="swi-card-body">
                    <div class="swi-form-field">
                        <p class="description">
                            <?php esc_html_e('Categories are imported based on Shopify\'s product collection data. You can filter and select which categories to import below.', 'wp-migrate-shopify-woo'); ?>
                        </p>
                    </div>
                </div>
            </div>


        </div>
        <!-- Bottom form actions removed to avoid duplicate fetch button -->
    </form>
</div><!-- End of swi-import-form -->

<!-- Categories Results Section -->
<div id="swi-categories-results" class="swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title"><?php esc_html_e('Categories', 'wp-migrate-shopify-woo'); ?></h3>
        <div class="swi-selection-actions">
            <button type="button" id="swi-categories-select-all" class="button button-secondary">
                <?php esc_html_e('Select All', 'wp-migrate-shopify-woo'); ?>
            </button>
            <button type="button" id="swi-categories-deselect-all" class="button button-secondary">
                <?php esc_html_e('Deselect All', 'wp-migrate-shopify-woo'); ?>
            </button>
        </div>
    </div>
    <div class="swi-card-body">
        <div id="swi-categories-grid-list" class="swi-categories-grid">
            <!-- Categories will be listed here for import selection -->
            <div class="swi-skeleton-loader">
                <div class="swi-skeleton-line"></div>
                <div class="swi-skeleton-line"></div>
                <div class="swi-skeleton-line"></div>
            </div>
            <!-- Empty state message will appear here when no categories are found -->
            <div class="swi-empty-state">
                <div class="swi-empty-icon">
                    <span class="dashicons dashicons-category"></span>
                </div>
                <p><?php esc_html_e('No categories found. Please fetch categories from your Shopify store.', 'wp-migrate-shopify-woo'); ?></p>
            </div>
        </div>
        <div class="swi-form-actions">
            <button type="button" id="swi-categories-import-btn" class="button button-primary">
                <span class="dashicons dashicons-migrate"></span> <?php esc_html_e('Import Selected Categories', 'wp-migrate-shopify-woo'); ?>
            </button>
            <button type="button" id="swi-categories-preview-btn" class="button button-secondary">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Preview Categories', 'wp-migrate-shopify-woo'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Import Progress Section -->
<div id="categories-import-progress" class="swi-progress-container swi-card">
    <div class="swi-card-header">
        <h3 class="swi-card-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Import Progress', 'wp-migrate-shopify-woo'); ?>
        </h3>
    </div>
    <div class="swi-card-body">
        <div class="swi-progress-bar">
            <div id="categories-progress-fill" class="swi-progress-fill"></div>
        </div>
        <div class="swi-progress-info">
            <span id="categories-progress-text"></span>
            <span id="categories-progress-percentage"></span>
        </div>
        <div id="categories-progress-log"></div>
    </div>
</div>
