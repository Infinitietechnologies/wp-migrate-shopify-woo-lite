<?php

/**
 * Shopify Stores Table Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Tables
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the model
use ShopifyWooImporter\Models\WMSW_ShopifyStore;

// Verify nonce for security
$nonce_verified = false;
if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wmsw_stores_filter')) {
    $nonce_verified = true;
}

// Get current page and filters (only if nonce is verified or for initial page load)
$current_page = 1;
$filter_status = '';

if ($nonce_verified || !isset($_GET['_wpnonce'])) {
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
}

$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Get stores data using the model methods instead of direct database queries
$stores = WMSW_ShopifyStore::getStoresPaginated($per_page, $offset, $filter_status);
$total_stores = WMSW_ShopifyStore::getStoresCount($filter_status);
?>

<div class="swi-table-container">
        <div class="swi-table-actions">
        <a href="#" class="button button-primary" id="add-new-store">
            <?php esc_html_e('Add New Store', 'wp-migrate-shopify-woo'); ?>
        </a>

        <div class="swi-table-filters">
            <form method="get" action="">
                <?php wp_nonce_field('wmsw_stores_filter', '_wpnonce'); ?>
                <input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['page'] ?? ''))); ?>" />

                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>><?php esc_html_e('Active', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="inactive" <?php selected($filter_status, 'inactive'); ?>><?php esc_html_e('Inactive', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="error" <?php selected($filter_status, 'error'); ?>><?php esc_html_e('Error', 'wp-migrate-shopify-woo'); ?></option>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'wp-migrate-shopify-woo'); ?>" />
                <a href="<?php echo esc_url(admin_url('admin.php?page=wmsw-stores')); ?>" class="button">
                    <?php esc_html_e('Clear Filters', 'wp-migrate-shopify-woo'); ?>
                </a>
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped swi-stores-table">
        <thead>
            <tr>
                <th scope="col" class="column-cb">
                    <input type="checkbox" />
                </th>
                <th scope="col" class="column-name"><?php esc_html_e('Store Name', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-url"><?php esc_html_e('Store URL', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-api-version"><?php esc_html_e('API Version', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-default"><?php esc_html_e('Default', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-status"><?php esc_html_e('Status', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-last-sync"><?php esc_html_e('Last Sync', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-products"><?php esc_html_e('Products', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-created"><?php esc_html_e('Added', 'wp-migrate-shopify-woo'); ?></th>
                <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'wp-migrate-shopify-woo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stores)) : ?> <tr class="no-items">
                    <td colspan="9" class="colspanchange">
                        <?php esc_html_e('No Shopify stores found.', 'wp-migrate-shopify-woo'); ?>
                        <br><br>
                        <a href="#" class="button button-primary" id="add-first-store">
                            <?php esc_html_e('Add Your First Store', 'wp-migrate-shopify-woo'); ?>
                        </a>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($stores as $store) : ?>
                    <tr data-store-id="<?php echo esc_attr($store->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="store_ids[]" value="<?php echo esc_attr($store->id); ?>" />
                        </th>
                        <td class="column-name">
                            <strong>
                                <a href="#" class="swi-edit-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                    <?php echo esc_html($store->store_name); ?>
                                </a>
                            </strong>
                            <div class="row-actions visible">
                                <span class="edit">
                                    <a href="#" class="swi-edit-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Edit', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                                | <span class="test">
                                    <a href="#" class="swi-test-connection" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Test Connection', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                                | <span class="sync">
                                    <a href="#" class="swi-sync-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Sync Now', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span> | <span class="delete">
                                    <a href="#" class="swi-delete-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Delete', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                                | <span class="copy">
                                    <a href="#" class="swi-copy-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Copy', 'wp-migrate-shopify-woo'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-url">
                            <a href="https://<?php echo esc_html($store->shop_domain); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($store->shop_domain); ?>
                                <span class="dashicons dashicons-external text-xs"></span>
                            </a>
                        </td>
                        <td class="column-api-version">
                            <span class="swi-api-version">
                                <?php echo esc_html($store->api_version ?? '2023-10'); ?>
                            </span>
                        </td>

                        <td class="column-default">
                            <?php if (!empty($store->is_default)) : ?>
                                <span class="swi-default-badge">
                                    <span class="dashicons dashicons-star-filled swi-default-star"></span>
                                    <span class="screen-reader-text"><?php esc_html_e('Default Store', 'wp-migrate-shopify-woo'); ?></span>
                                </span>
                            <?php else : ?>
                                <button type="button" class="button button-small swi-set-default" data-store-id="<?php echo esc_attr($store->id); ?>">
                                    <?php esc_html_e('Set Default', 'wp-migrate-shopify-woo'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="column-status">
                            <?php
                            include WMSW_PLUGIN_DIR . 'backend/partials/components/status-badge.php';
                            echo wp_kses_post(WMSW_status_badge($store->status));
                            ?>
                        </td>
                        <td class="column-last-sync">
                            <?php
                            if (!empty($store->last_sync)) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($store->last_sync)));
                            } else {
                                echo '<span class="swi-never-synced">';
                                esc_html_e('Never', 'wp-migrate-shopify-woo');
                                echo '</span>';
                            }
                            ?>
                        </td>
                        <td class="column-products">
                            <span class="swi-product-count">
                                <?php
                                // Product count would need to be calculated from imported products
                                // For now, show placeholder
                                echo esc_html(__('N/A', 'wp-migrate-shopify-woo'));
                                ?>
                            </span>
                        </td>
                        <td class="column-created">
                            <?php echo esc_html(wp_date(get_option('date_format'), strtotime($store->created_at))); ?>
                        </td>
                        <td class="column-actions">
                            <div class="swi-store-actions">
                                <?php if ($store->status === 'active') : ?>
                                    <button type="button" class="button button-small swi-disable-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Disable', 'wp-migrate-shopify-woo'); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button button-small swi-enable-store" data-store-id="<?php echo esc_attr($store->id); ?>">
                                        <?php esc_html_e('Enable', 'wp-migrate-shopify-woo'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_stores > $per_page) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_stores / $per_page);
                echo wp_kses_post(paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ]));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Store Form Modal -->
<div id="swi-store-form-modal" class="swi-modal d-none">
    <div class="swi-modal-content swi-modal-large">
        <div class="swi-modal-header">
            <h3 id="swi-store-form-title"><?php esc_html_e('Add New Shopify Store', 'wp-migrate-shopify-woo'); ?></h3>
            <span class="swi-modal-close">&times;</span>
        </div>
        <div class="swi-modal-body">
            <form id="swi-store-form">
                <input type="hidden" name="store_id" id="store_id" value="" />
                <input type="hidden" name="action" value="WMSW_save_store" />
                <input type="hidden" name="nonce" id="swi-store-nonce" value="" />

                <?php include WMSW_PLUGIN_DIR . 'backend/partials/forms/shopify-connection.php'; ?>

                <div class="swi-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Store', 'wp-migrate-shopify-woo'); ?>
                    </button>
                    <button type="button" class="button swi-modal-close">
                        <?php esc_html_e('Cancel', 'wp-migrate-shopify-woo'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Connection Test Modal -->
<div id="swi-connection-test-modal" class="swi-modal d-none">
    <div class="swi-modal-content">
        <div class="swi-modal-header">
            <h3><?php esc_html_e('Connection Test Results', 'wp-migrate-shopify-woo'); ?></h3>
            <span class="swi-modal-close">&times;</span>
        </div>
        <div class="swi-modal-body">
            <div id="swi-connection-test-results">
                <!-- Results will be loaded here -->
            </div>
        </div>
    </div>
</div>
