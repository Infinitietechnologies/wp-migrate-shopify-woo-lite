<?php
/**
 * Field Mapping Form Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Forms
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current field mappings
$product_mappings = get_option('wmsw_product_field_mappings', []);
$customer_mappings = get_option('wmsw_customer_field_mappings', []);

// Default Shopify to WooCommerce field mappings
$default_product_mappings = [
    'shopify_field' => 'woocommerce_field',
    'title' => 'post_title',
    'body_html' => 'post_content',
    'handle' => 'post_name',
    'vendor' => 'product_brand',
    'product_type' => 'product_category',
    'tags' => 'product_tag',
    'status' => 'post_status',
    'price' => 'regular_price',
    'compare_at_price' => 'sale_price',
    'weight' => 'weight',
    'sku' => 'sku',
    'inventory_quantity' => 'stock_quantity',
    'inventory_policy' => 'manage_stock',
    'fulfillment_service' => 'sold_individually'
];

$default_customer_mappings = [
    'email' => 'user_email',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'phone' => 'billing_phone',
    'accepts_marketing' => 'accepts_marketing',
    'created_at' => 'user_registered',
    'tags' => 'customer_tags'
];

$shopify_product_fields = [
    'title' => 'Product Title',
    'body_html' => 'Product Description',
    'handle' => 'Product Handle/Slug',
    'vendor' => 'Vendor/Brand',
    'product_type' => 'Product Type',
    'tags' => 'Tags',
    'status' => 'Status',
    'price' => 'Price',
    'compare_at_price' => 'Compare At Price',
    'weight' => 'Weight',
    'sku' => 'SKU',
    'inventory_quantity' => 'Inventory Quantity',
    'inventory_policy' => 'Inventory Policy',
    'fulfillment_service' => 'Fulfillment Service',
    'created_at' => 'Created Date',
    'updated_at' => 'Updated Date'
];

$woocommerce_product_fields = [
    'post_title' => 'Product Name',
    'post_content' => 'Product Description',
    'post_excerpt' => 'Product Short Description',
    'post_name' => 'Product Slug',
    'post_status' => 'Product Status',
    'regular_price' => 'Regular Price',
    'sale_price' => 'Sale Price',
    'weight' => 'Weight',
    'length' => 'Length',
    'width' => 'Width',
    'height' => 'Height',
    'sku' => 'SKU',
    'stock_quantity' => 'Stock Quantity',
    'manage_stock' => 'Manage Stock',
    'stock_status' => 'Stock Status',
    'sold_individually' => 'Sold Individually',
    'product_brand' => 'Product Brand',
    'product_category' => 'Product Category',
    'product_tag' => 'Product Tags'
];

$shopify_customer_fields = [
    'email' => 'Email Address',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'phone' => 'Phone Number',
    'accepts_marketing' => 'Accepts Marketing',
    'created_at' => 'Created Date',
    'updated_at' => 'Updated Date',
    'tags' => 'Customer Tags',
    'state' => 'Account State',
    'total_spent' => 'Total Spent',
    'orders_count' => 'Orders Count'
];

$woocommerce_customer_fields = [
    'user_email' => 'Email Address',
    'user_login' => 'Username',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'display_name' => 'Display Name',
    'user_registered' => 'Registration Date',
    'billing_first_name' => 'Billing First Name',
    'billing_last_name' => 'Billing Last Name',
    'billing_phone' => 'Billing Phone',
    'billing_email' => 'Billing Email',
    'shipping_first_name' => 'Shipping First Name',
    'shipping_last_name' => 'Shipping Last Name',
    'customer_tags' => 'Customer Tags',
    'accepts_marketing' => 'Accepts Marketing'
];
?>

<div class="swi-form-section">
    <h3><?php _e('Field Mapping Configuration', 'wp-migrate-shopify-woo'); ?></h3>
    <p class="description">
        <?php _e('Map Shopify fields to their corresponding WooCommerce fields. This ensures data is imported to the correct locations.', 'wp-migrate-shopify-woo'); ?>
    </p>

    <div class="swi-tabs-wrapper">
        <div class="swi-tabs-nav">
            <button type="button" class="swi-tab-button active" data-tab="product-mapping">
                <?php _e('Product Fields', 'wp-migrate-shopify-woo'); ?>
            </button>
            <button type="button" class="swi-tab-button" data-tab="customer-mapping">
                <?php _e('Customer Fields', 'wp-migrate-shopify-woo'); ?>
            </button>
        </div>

        <div class="swi-tab-content" id="product-mapping">
            <h4><?php _e('Product Field Mapping', 'wp-migrate-shopify-woo'); ?></h4>
            <p class="description">
                <?php _e('Define how Shopify product fields should be mapped to WooCommerce product fields.', 'wp-migrate-shopify-woo'); ?>
            </p>

            <div class="swi-mapping-controls">
                <button type="button" class="button" id="add_product_mapping">
                    <?php _e('Add Custom Mapping', 'wp-migrate-shopify-woo'); ?>
                </button>
                <button type="button" class="button" id="reset_product_mappings">
                    <?php _e('Reset to Defaults', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>

            <table class="wp-list-table widefat fixed striped swi-mapping-table" id="product_mappings_table">
                <thead>
                    <tr>
                        <th><?php _e('Shopify Field', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('WooCommerce Field', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('Transform', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('Actions', 'wp-migrate-shopify-woo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($default_product_mappings as $shopify_field => $woo_field) : 
                        $mapping = $product_mappings[$shopify_field] ?? ['field' => $woo_field, 'transform' => ''];
                    ?>
                    <tr>
                        <td>
                            <select name="product_mappings[<?php echo esc_attr($shopify_field); ?>][shopify_field]" class="shopify-field-select">
                                <?php foreach ($shopify_product_fields as $field => $label) : ?>
                                    <option value="<?php echo esc_attr($field); ?>" <?php selected($shopify_field, $field); ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="product_mappings[<?php echo esc_attr($shopify_field); ?>][woo_field]" class="woo-field-select">
                                <?php foreach ($woocommerce_product_fields as $field => $label) : ?>
                                    <option value="<?php echo esc_attr($field); ?>" <?php selected($mapping['field'], $field); ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="product_mappings[<?php echo esc_attr($shopify_field); ?>][transform]" 
                                   value="<?php echo esc_attr($mapping['transform'] ?? ''); ?>" 
                                   placeholder="<?php _e('Optional transform function', 'wp-migrate-shopify-woo'); ?>" 
                                   class="regular-text" />
                        </td>
                        <td>
                            <button type="button" class="button-link-delete remove-mapping" title="<?php _e('Remove mapping', 'wp-migrate-shopify-woo'); ?>">
                                <?php _e('Remove', 'wp-migrate-shopify-woo'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="swi-tab-content" id="customer-mapping" style="display: none;">
            <h4><?php _e('Customer Field Mapping', 'wp-migrate-shopify-woo'); ?></h4>
            <p class="description">
                <?php _e('Define how Shopify customer fields should be mapped to WooCommerce customer fields.', 'wp-migrate-shopify-woo'); ?>
            </p>

            <div class="swi-mapping-controls">
                <button type="button" class="button" id="add_customer_mapping">
                    <?php _e('Add Custom Mapping', 'wp-migrate-shopify-woo'); ?>
                </button>
                <button type="button" class="button" id="reset_customer_mappings">
                    <?php _e('Reset to Defaults', 'wp-migrate-shopify-woo'); ?>
                </button>
            </div>

            <table class="wp-list-table widefat fixed striped swi-mapping-table" id="customer_mappings_table">
                <thead>
                    <tr>
                        <th><?php _e('Shopify Field', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('WooCommerce Field', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('Transform', 'wp-migrate-shopify-woo'); ?></th>
                        <th><?php _e('Actions', 'wp-migrate-shopify-woo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($default_customer_mappings as $shopify_field => $woo_field) : 
                        $mapping = $customer_mappings[$shopify_field] ?? ['field' => $woo_field, 'transform' => ''];
                    ?>
                    <tr>
                        <td>
                            <select name="customer_mappings[<?php echo esc_attr($shopify_field); ?>][shopify_field]" class="shopify-field-select">
                                <?php foreach ($shopify_customer_fields as $field => $label) : ?>
                                    <option value="<?php echo esc_attr($field); ?>" <?php selected($shopify_field, $field); ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="customer_mappings[<?php echo esc_attr($shopify_field); ?>][woo_field]" class="woo-field-select">
                                <?php foreach ($woocommerce_customer_fields as $field => $label) : ?>
                                    <option value="<?php echo esc_attr($field); ?>" <?php selected($mapping['field'], $field); ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="customer_mappings[<?php echo esc_attr($shopify_field); ?>][transform]" 
                                   value="<?php echo esc_attr($mapping['transform'] ?? ''); ?>" 
                                   placeholder="<?php _e('Optional transform function', 'wp-migrate-shopify-woo'); ?>" 
                                   class="regular-text" />
                        </td>
                        <td>
                            <button type="button" class="button-link-delete remove-mapping" title="<?php _e('Remove mapping', 'wp-migrate-shopify-woo'); ?>">
                                <?php _e('Remove', 'wp-migrate-shopify-woo'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="swi-form-section">
        <h4><?php _e('Transform Functions', 'wp-migrate-shopify-woo'); ?></h4>
        <p class="description">
            <?php _e('Available transform functions you can use in the Transform field:', 'wp-migrate-shopify-woo'); ?>
        </p>
        <ul class="swi-transform-functions">
            <li><code>strtolower</code> - <?php _e('Convert to lowercase', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>strtoupper</code> - <?php _e('Convert to uppercase', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>ucfirst</code> - <?php _e('Capitalize first letter', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>ucwords</code> - <?php _e('Capitalize each word', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>strip_tags</code> - <?php _e('Remove HTML tags', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>trim</code> - <?php _e('Remove whitespace', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>format_price</code> - <?php _e('Format as price', 'wp-migrate-shopify-woo'); ?></li>
            <li><code>bool_to_yes_no</code> - <?php _e('Convert boolean to yes/no', 'wp-migrate-shopify-woo'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.swi-tab-button').click(function() {
        var tab = $(this).data('tab');
        
        $('.swi-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.swi-tab-content').hide();
        $('#' + tab).show();
    });

    // Add product mapping
    $('#add_product_mapping').click(function() {
        var newRow = '<tr>' +
            '<td><select name="product_mappings[new_' + Date.now() + '][shopify_field]" class="shopify-field-select">' +
            <?php foreach ($shopify_product_fields as $field => $label) : ?>
            '<option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)</option>' +
            <?php endforeach; ?>
            '</select></td>' +
            '<td><select name="product_mappings[new_' + Date.now() + '][woo_field]" class="woo-field-select">' +
            <?php foreach ($woocommerce_product_fields as $field => $label) : ?>
            '<option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)</option>' +
            <?php endforeach; ?>
            '</select></td>' +
            '<td><input type="text" name="product_mappings[new_' + Date.now() + '][transform]" placeholder="<?php _e('Optional transform function', 'wp-migrate-shopify-woo'); ?>" class="regular-text" /></td>' +
            '<td><button type="button" class="button-link-delete remove-mapping"><?php _e('Remove', 'wp-migrate-shopify-woo'); ?></button></td>' +
            '</tr>';
        
        $('#product_mappings_table tbody').append(newRow);
    });

    // Add customer mapping
    $('#add_customer_mapping').click(function() {
        var newRow = '<tr>' +
            '<td><select name="customer_mappings[new_' + Date.now() + '][shopify_field]" class="shopify-field-select">' +
            <?php foreach ($shopify_customer_fields as $field => $label) : ?>
            '<option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)</option>' +
            <?php endforeach; ?>
            '</select></td>' +
            '<td><select name="customer_mappings[new_' + Date.now() + '][woo_field]" class="woo-field-select">' +
            <?php foreach ($woocommerce_customer_fields as $field => $label) : ?>
            '<option value="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($field); ?>)</option>' +
            <?php endforeach; ?>
            '</select></td>' +
            '<td><input type="text" name="customer_mappings[new_' + Date.now() + '][transform]" placeholder="<?php _e('Optional transform function', 'wp-migrate-shopify-woo'); ?>" class="regular-text" /></td>' +
            '<td><button type="button" class="button-link-delete remove-mapping"><?php _e('Remove', 'wp-migrate-shopify-woo'); ?></button></td>' +
            '</tr>';
        
        $('#customer_mappings_table tbody').append(newRow);
    });

    // Remove mapping
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('tr').remove();
    });

    // Reset to defaults
    $('#reset_product_mappings, #reset_customer_mappings').click(function() {
        if (confirm('<?php _e('Are you sure you want to reset all mappings to default values?', 'wp-migrate-shopify-woo'); ?>')) {
            location.reload();
        }
    });
});
</script>
