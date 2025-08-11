<?php
/**
 * Import page with tabs for all import types
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'products';

// Define available tabs
$tabs = [
    'products' => __('Products', 'wp-migrate-shopify-woo'),
    'customers' => __('Customers', 'wp-migrate-shopify-woo'),
    'orders' => __('Orders', 'wp-migrate-shopify-woo'),
    'pages' => __('Pages', 'wp-migrate-shopify-woo'),
    'blogs' => __('Blogs', 'wp-migrate-shopify-woo'),
    'categories' => __('Categories', 'wp-migrate-shopify-woo'),
    'coupons' => __('Coupons', 'wp-migrate-shopify-woo')
];

// Validate current tab
if (!array_key_exists($current_tab, $tabs)) {
    $current_tab = 'products';
}
?>

<div class="wrap wmsw-import-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Import from Shopify', 'wp-migrate-shopify-woo'); ?>
    </h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab_name) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_id)); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_name); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Tab Content -->
    <div class="wmsw-tab-content">
        <?php
        $tab_file = WMSW_PLUGIN_DIR . 'backend/partials/tabs/' . $current_tab . '.php';
        if (file_exists($tab_file)) {
            require_once $tab_file;
        } else {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Tab content not found.', 'wp-migrate-shopify-woo');
            echo '</p></div>';
        }
        ?>
    </div>
</div> 