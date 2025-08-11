<?php

/**
 * Shopify Connection Form Partial
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get store data if editing
$store = $store ?? null;
$is_edit = !empty($store);
?>

<div class="swi-connection-form">
    <form id="shopify-connection-form" method="post">
        <?php wp_nonce_field('wmsw_save_store', 'nonce'); ?>
        <input type="hidden" name="action" value="WMSW_save_store">
        <?php if ($is_edit): ?>
            <input type="hidden" name="store_id" value="<?php echo esc_attr($store->id); ?>">
        <?php endif; ?>

        <div class="swi-form-header">
            <h3><?php echo $is_edit ? esc_html__('Edit Shopify Store', 'wp-migrate-shopify-woo') : esc_html__('Connect New Shopify Store', 'wp-migrate-shopify-woo'); ?></h3>
            <p class="description">
                <?php esc_html_e('Enter your Shopify store credentials to establish a secure connection.', 'wp-migrate-shopify-woo'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=shopify-settings&tab=api-setup')); ?>" target="_blank">
                    <?php esc_html_e('Need help getting API credentials?', 'wp-migrate-shopify-woo'); ?>
                </a>
            </p>
        </div>

        <div class="swi-form-grid">
            <div class="swi-form-row">
                <label for="store_name" class="swi-form-label">
                    <?php esc_html_e('Store Name', 'wp-migrate-shopify-woo'); ?>
                    <span class="required">*</span>
                </label>
                <input type="text"
                    name="store_name"
                    id="store_name"
                    class="swi-form-input"
                    value="<?php echo $is_edit ? esc_attr($store->store_name) : ''; ?>"
                    placeholder="<?php esc_attr_e('My Shopify Store', 'wp-migrate-shopify-woo'); ?>"
                    required>
                <p class="swi-form-help"><?php esc_html_e('A friendly name to identify this store in your dashboard.', 'wp-migrate-shopify-woo'); ?></p>
            </div>

            <div class="swi-form-row">
                <label for="shop_domain" class="swi-form-label">
                    <?php esc_html_e('Shop Domain', 'wp-migrate-shopify-woo'); ?>
                    <span class="required">*</span>
                </label>
                <div class="swi-input-group">
                    <input type="text"
                        name="shop_domain"
                        id="shop_domain"
                        class="swi-form-input"
                        value="<?php echo $is_edit ? esc_attr($store->shop_domain) : ''; ?>"
                        placeholder="<?php esc_attr_e('yourstore', 'wp-migrate-shopify-woo'); ?>"
                        required>
                    <span class="swi-input-suffix">.myshopify.com</span>
                </div>
                <p class="swi-form-help"><?php esc_html_e('Your Shopify store domain (just the store name, not the full URL).', 'wp-migrate-shopify-woo'); ?></p>
            </div>

            <div class="swi-form-row">
                <label for="access_token" class="swi-form-label">
                    <?php esc_html_e('Access Token', 'wp-migrate-shopify-woo'); ?>
                    <span class="required">*</span>
                </label>
                <div class="swi-password-field">
                    <input type="password"
                        name="access_token"
                        id="access_token"
                        class="swi-form-input"
                        value="<?php echo $is_edit ? esc_attr($store->access_token) : ''; ?>"
                        placeholder="<?php esc_attr_e('Enter your Shopify access token', 'wp-migrate-shopify-woo'); ?>"
                        required>
                    <button type="button" class="swi-toggle-password" title="<?php esc_attr_e('Show/Hide Password', 'wp-migrate-shopify-woo'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
                <p class="swi-form-help">
                    <?php esc_html_e('Your Shopify Private App password. This is kept secure and encrypted.', 'wp-migrate-shopify-woo'); ?>
                </p>
            </div>

            <div class="swi-form-row">
                <label class="swi-form-label"><?php esc_html_e('Store Status', 'wp-migrate-shopify-woo'); ?></label>
                <div class="swi-checkbox-group">
                    <label class="swi-checkbox-label">
                        <input type="checkbox"
                            name="is_active"
                            value="1"
                            <?php checked($is_edit ? $store->is_active : true); ?>>
                        <span class="swi-checkbox-text">
                            <?php esc_html_e('Active (Enable imports from this store)', 'wp-migrate-shopify-woo'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <div class="swi-form-row">
                <label for="api_version" class="swi-form-label">
                    <?php esc_html_e('API Version', 'wp-migrate-shopify-woo'); ?>
                    <span class="required">*</span>
                </label>
                <select name="api_version" id="api_version" class="swi-form-input" required>
                    <option value=""><?php esc_html_e('Select API Version...', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2024-04" <?php selected($is_edit ? $store->api_version ?? '' : '', '2024-04'); ?>><?php esc_html_e('2024-04 (Latest)', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2024-01" <?php selected($is_edit ? $store->api_version ?? '' : '', '2024-01'); ?>><?php esc_html_e('2024-01', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2023-10" <?php selected($is_edit ? $store->api_version ?? '' : '', '2023-10'); ?>><?php esc_html_e('2023-10', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2023-07" <?php selected($is_edit ? $store->api_version ?? '' : '', '2023-07'); ?>><?php esc_html_e('2023-07', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2023-04" <?php selected($is_edit ? $store->api_version ?? '' : WMSW_SHOPIFY_API_VERSION, '2023-04'); ?>><?php esc_html_e('2023-04', 'wp-migrate-shopify-woo'); ?></option>
                    <option value="2023-01" <?php selected($is_edit ? $store->api_version ?? '' : '', '2023-01'); ?>><?php esc_html_e('2023-01', 'wp-migrate-shopify-woo'); ?></option>
                </select>
                <p class="swi-form-help">
                    <?php esc_html_e('Select the Shopify API version to use. Newer versions may have more features but older versions provide better compatibility.', 'wp-migrate-shopify-woo'); ?>
                    <a href="https://shopify.dev/docs/api/release-notes" target="_blank" rel="noopener"><?php esc_html_e('View API Release Notes', 'wp-migrate-shopify-woo'); ?></a>
                </p>
            </div>
        </div>

        <div class="swi-form-actions">
            <div class="swi-form-primary-actions">
                <button type="button" id="test-connection-btn" class="button button-secondary">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Test Connection', 'wp-migrate-shopify-woo'); ?>
                </button>

                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php echo $is_edit ? esc_html__('Update Store', 'wp-migrate-shopify-woo') : esc_html__('Connect Store', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>

            <div class="swi-form-secondary-actions"> <a href="<?php echo esc_url(admin_url('admin.php?page=wp-migrate-shopify-woo-stores')); ?>" class="button button-link">
                    <?php esc_html_e('Cancel', 'wp-migrate-shopify-woo'); ?>
                </a>
            </div>
        </div>

        <!-- Connection Test Result -->
        <div id="connection-test-result" class="swi-connection-result d-none">
            <div class="swi-result-content">
                <div class="swi-result-icon"></div>
                <div class="swi-result-message"></div>
                <div class="swi-result-details"></div>
            </div>
        </div>
    </form>
</div>
