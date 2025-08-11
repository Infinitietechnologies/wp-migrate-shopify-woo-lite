<?php

namespace ShopifyWooImporter\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Demo User Handler
 * 
 * Handles creation and management of demo users for the plugin
 */
class WMSW_DemoUserHandler
{
    const DEMO_ROLE_NAME = 'shopify_importer_demo';
    const DEMO_ROLE_DISPLAY_NAME = 'Shopify Importer Demo';
    const DEMO_USER_LOGIN = 'shopify_importer_demo';
    const DEMO_USER_EMAIL = 'demo@shopify-importer.local';
    const DEMO_USER_PASSWORD = '12345678';

    /**
     * Plugin-specific capabilities
     */
    private static $plugin_capabilities = [
        'wmsw_view_logs',
        'wmsw_manage_stores',
        'wmsw_manage_settings',
        'wmsw_view_dashboard',
        'wmsw_access_import_pages',
        'wmsw_view_import_logs',
        'wmsw_manage_scheduled_tasks'
    ];

    /**
     * Create demo role and user
     */
    public static function create_demo_role_and_user()
    {
        // Create the demo role
        $role_created = self::create_demo_role();

        if (!$role_created) {
            return false;
        }

        // Create the demo user
        $user_created = self::create_demo_user();

        if (!$user_created) {
            return false;
        }

        // Store creation status
        \update_option('wmsw_demo_user_created', true);
        \update_option('wmsw_demo_user_created_at', \current_time('mysql'));

        return true;
    }

    /**
     * Create the demo role with appropriate capabilities
     */
    private static function create_demo_role()
    {
        // Remove existing role if it exists
        \remove_role(self::DEMO_ROLE_NAME);

        // Create new role with minimal capabilities
        $role = \add_role(
            self::DEMO_ROLE_NAME,
            self::DEMO_ROLE_DISPLAY_NAME,
            [
                'read' => true,
                'level_0' => true,
                'level_1' => true,
                'level_2' => true,
                'level_3' => true,
                'level_4' => true,
                'level_5' => true,
                'level_6' => true,
                'level_7' => true,
                'level_8' => true,
                'level_9' => true,
                'level_10' => true,
            ]
        );

        if (!$role) {
            return false;
        }

        // Add plugin-specific capabilities
        foreach (self::$plugin_capabilities as $capability) {
            $role->add_cap($capability);
        }

        // Add minimal capabilities needed for the plugin to work
        $role->add_cap('upload_files'); // For importing images
        $role->add_cap('unfiltered_html'); // For rich content

        // Add read capabilities for viewing pages, posts, and other content
        $role->add_cap('read_private_posts');
        $role->add_cap('read_private_pages');
        $role->add_cap('read_private_products');
        $role->add_cap('read_private_shop_orders');
        $role->add_cap('read_private_shop_coupons');
        $role->add_cap('read_private_shop_customers');

        // Add capabilities to view but not edit content
        $role->add_cap('list_users'); // Can view users list
        $role->add_cap('edit_theme_options'); // Can view theme options (read-only)
        $role->add_cap('manage_options'); // Can view settings (read-only)
        $role->add_cap('activate_plugins'); // Can view plugins (read-only)
        $role->add_cap('install_plugins'); // Can view plugin installation (read-only)
        $role->add_cap('update_plugins'); // Can view plugin updates (read-only)
        $role->add_cap('delete_plugins'); // Can view plugin deletion (read-only)
        $role->add_cap('edit_plugins'); // Can view plugin editing (read-only)
        $role->add_cap('install_themes'); // Can view theme installation (read-only)
        $role->add_cap('update_themes'); // Can view theme updates (read-only)
        $role->add_cap('delete_themes'); // Can view theme deletion (read-only)
        $role->add_cap('edit_themes'); // Can view theme editing (read-only)
        $role->add_cap('switch_themes'); // Can view theme switching (read-only)
        $role->add_cap('edit_dashboard'); // Can view dashboard (read-only)
        $role->add_cap('update_core'); // Can view core updates (read-only)
        $role->add_cap('export'); // Can view export functionality (read-only)
        $role->add_cap('import'); // Can view import functionality (read-only)
        $role->add_cap('delete_site'); // Can view site deletion (read-only)
        $role->add_cap('manage_network'); // Can view network management (read-only)
        $role->add_cap('manage_sites'); // Can view site management (read-only)
        $role->add_cap('manage_network_users'); // Can view network users (read-only)
        $role->add_cap('manage_network_plugins'); // Can view network plugins (read-only)
        $role->add_cap('manage_network_themes'); // Can view network themes (read-only)
        $role->add_cap('manage_network_options'); // Can view network options (read-only)
        $role->add_cap('upgrade_network'); // Can view network upgrades (read-only)
        $role->add_cap('setup_network'); // Can view network setup (read-only)
        $role->add_cap('delete_sites'); // Can view site deletion (read-only)
        $role->add_cap('manage_network_users'); // Can view network users (read-only)
        $role->add_cap('manage_network_plugins'); // Can view network plugins (read-only)
        $role->add_cap('manage_network_themes'); // Can view network themes (read-only)
        $role->add_cap('manage_network_options'); // Can view network options (read-only)
        $role->add_cap('upgrade_network'); // Can view network upgrades (read-only)
        $role->add_cap('setup_network'); // Can view network setup (read-only)
        $role->add_cap('delete_sites'); // Can view site deletion (read-only)

        // WooCommerce capabilities
        $role->add_cap('manage_woocommerce');
        $role->add_cap('view_woocommerce_reports');
        $role->add_cap('edit_products');
        $role->add_cap('read_products');
        $role->add_cap('delete_products');
        $role->add_cap('edit_others_products');
        $role->add_cap('publish_products');
        $role->add_cap('read_private_products');
        $role->add_cap('delete_private_products');
        $role->add_cap('delete_published_products');
        $role->add_cap('delete_others_products');
        $role->add_cap('edit_private_products');
        $role->add_cap('edit_published_products');
        $role->add_cap('manage_product_terms');
        $role->add_cap('edit_product_terms');
        $role->add_cap('delete_product_terms');
        $role->add_cap('assign_product_terms');
        $role->add_cap('edit_shop_orders');
        $role->add_cap('read_shop_orders');
        $role->add_cap('delete_shop_orders');
        $role->add_cap('edit_others_shop_orders');
        $role->add_cap('publish_shop_orders');
        $role->add_cap('read_private_shop_orders');
        $role->add_cap('delete_private_shop_orders');
        $role->add_cap('delete_published_shop_orders');
        $role->add_cap('delete_others_shop_orders');
        $role->add_cap('edit_private_shop_orders');
        $role->add_cap('edit_published_shop_orders');
        $role->add_cap('manage_shop_order_terms');
        $role->add_cap('edit_shop_order_terms');
        $role->add_cap('delete_shop_order_terms');
        $role->add_cap('assign_shop_order_terms');
        $role->add_cap('edit_shop_coupons');
        $role->add_cap('read_shop_coupons');
        $role->add_cap('delete_shop_coupons');
        $role->add_cap('edit_others_shop_coupons');
        $role->add_cap('publish_shop_coupons');
        $role->add_cap('read_private_shop_coupons');
        $role->add_cap('delete_private_shop_coupons');
        $role->add_cap('delete_published_shop_coupons');
        $role->add_cap('delete_others_shop_coupons');
        $role->add_cap('edit_private_shop_coupons');
        $role->add_cap('edit_published_shop_coupons');
        $role->add_cap('manage_shop_coupon_terms');
        $role->add_cap('edit_shop_coupon_terms');
        $role->add_cap('delete_shop_coupon_terms');
        $role->add_cap('assign_shop_coupon_terms');
        $role->add_cap('edit_shop_customers');
        $role->add_cap('read_shop_customers');
        $role->add_cap('delete_shop_customers');
        $role->add_cap('edit_others_shop_customers');
        $role->add_cap('publish_shop_customers');
        $role->add_cap('read_private_shop_customers');
        $role->add_cap('delete_private_shop_customers');
        $role->add_cap('delete_published_shop_customers');
        $role->add_cap('delete_others_shop_customers');
        $role->add_cap('edit_private_shop_customers');
        $role->add_cap('edit_published_shop_customers');
        $role->add_cap('manage_shop_customer_terms');
        $role->add_cap('edit_shop_customer_terms');
        $role->add_cap('delete_shop_customer_terms');
        $role->add_cap('assign_shop_customer_terms');

        return true;
    }

    /**
     * Create the demo user
     */
    private static function create_demo_user()
    {
        // Check if demo user already exists
        $existing_user = get_user_by('login', self::DEMO_USER_LOGIN);
        if ($existing_user) {
            // Update existing user with demo role
            $existing_user->set_role(self::DEMO_ROLE_NAME);
            return true;
        }

        // Create new demo user
        $user_id = wp_create_user(
            self::DEMO_USER_LOGIN,
            self::DEMO_USER_PASSWORD,
            self::DEMO_USER_EMAIL
        );

        if (is_wp_error($user_id)) {
            return false;
        }

        // Set user role
        $user = get_user_by('id', $user_id);
        $user->set_role(self::DEMO_ROLE_NAME);

        // Set user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => 'Shopify',
            'last_name' => 'Demo',
            'display_name' => 'Shopify Importer Demo',
            'nickname' => 'shopify_demo'
        ]);

        // Add user meta for demo identification
        update_user_meta($user_id, 'wmsw_is_demo_user', true);
        update_user_meta($user_id, 'wmsw_demo_created_at', current_time('mysql'));

        return true;
    }

    /**
     * Remove demo role and user
     */
    public static function remove_demo_role_and_user()
    {
        // Remove demo user
        $demo_user = get_user_by('login', self::DEMO_USER_LOGIN);
        if ($demo_user) {
            wp_delete_user($demo_user->ID);
        }

        // Remove demo role
        remove_role(self::DEMO_ROLE_NAME);

        // Remove options
        delete_option('wmsw_demo_user_created');
        delete_option('wmsw_demo_user_created_at');

        return true;
    }

    /**
     * Get demo user credentials
     */
    public static function get_demo_credentials()
    {
        return [
            'username' => self::DEMO_USER_LOGIN,
            'password' => self::DEMO_USER_PASSWORD,
            'email' => self::DEMO_USER_EMAIL
        ];
    }

    /**
     * Check if demo user exists
     */
    public static function demo_user_exists()
    {
        return get_user_by('login', self::DEMO_USER_LOGIN) !== false;
    }

    /**
     * Check if demo role exists
     */
    public static function demo_role_exists()
    {
        return get_role(self::DEMO_ROLE_NAME) !== null;
    }

    /**
     * Get demo user info
     */
    public static function get_demo_user_info()
    {
        $user = get_user_by('login', self::DEMO_USER_LOGIN);

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'role' => self::DEMO_ROLE_NAME,
            'created_at' => get_user_meta($user->ID, 'wmsw_demo_created_at', true)
        ];
    }

    /**
     * Display demo user notice in admin
     */
    public static function display_demo_user_notice()
    {
        if (!self::demo_user_exists()) {
            return;
        }

        $credentials = self::get_demo_credentials();

        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . esc_html__('Shopify Importer Demo User Created', 'wp-migrate-shopify-woo') . '</strong></p>';
        echo '<p>' . esc_html__('A demo user has been created for testing the plugin:', 'wp-migrate-shopify-woo') . '</p>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li><strong>' . esc_html__('Username:', 'wp-migrate-shopify-woo') . '</strong> ' . esc_html($credentials['username']) . '</li>';
        echo '<li><strong>' . esc_html__('Password:', 'wp-migrate-shopify-woo') . '</strong> ' . esc_html($credentials['password']) . '</li>';
        echo '<li><strong>' . esc_html__('Email:', 'wp-migrate-shopify-woo') . '</strong> ' . esc_html($credentials['email']) . '</li>';
        echo '</ul>';
        echo '<p><em>' . esc_html__('This demo user has access to view plugin features and settings, but import functionality is disabled for security.', 'wp-migrate-shopify-woo') . '</em></p>';
        echo '</div>';
    }

    /**
     * Add plugin capabilities to existing roles
     */
    public static function add_plugin_capabilities_to_roles()
    {
        $roles_to_update = ['administrator', 'editor'];

        foreach ($roles_to_update as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach (self::$plugin_capabilities as $capability) {
                    $role->add_cap($capability);
                }
            }
        }
    }

    /**
     * Get all plugin capabilities
     */
    public static function get_plugin_capabilities()
    {
        return self::$plugin_capabilities;
    }

    /**
     * Check if current user is demo user
     */
    public static function is_demo_user()
    {
        $current_user = \wp_get_current_user();
        return $current_user && $current_user->user_login === self::DEMO_USER_LOGIN;
    }

    /**
     * Hide admin menu items for demo users
     */
    public static function hide_admin_menus_for_demo()
    {
        if (!self::is_demo_user()) {
            return;
        }

        // Remove all default WordPress admin menus
        \remove_menu_page('index.php'); // Dashboard
        \remove_menu_page('edit.php'); // Posts
        \remove_menu_page('upload.php'); // Media
        \remove_menu_page('edit.php?post_type=page'); // Pages
        \remove_menu_page('edit-comments.php'); // Comments
        \remove_menu_page('themes.php'); // Appearance
        \remove_menu_page('plugins.php'); // Plugins
        \remove_menu_page('users.php'); // Users
        \remove_menu_page('tools.php'); // Tools
        \remove_menu_page('import.php'); // Tools
        \remove_menu_page('options-general.php'); // Settings

        // Remove WooCommerce main menu and all its submenus
        \remove_menu_page('woocommerce');

        // Remove WooCommerce submenu pages
        \remove_submenu_page('woocommerce', 'wc-admin'); // WooCommerce Home
        \remove_submenu_page('woocommerce', 'edit.php?post_type=product'); // Products
        \remove_submenu_page('woocommerce', 'edit.php?post_type=shop_order'); // Orders
        \remove_submenu_page('woocommerce', 'edit.php?post_type=shop_coupon'); // Coupons
        \remove_submenu_page('woocommerce', 'edit.php?post_type=shop_webhook'); // Webhooks

        \remove_submenu_page('woocommerce', 'wc-payments'); // Payment Gateways
        \remove_submenu_page('woocommerce', 'wc-settings'); // Settings
        \remove_submenu_page('woocommerce', 'wc-status'); // Status
        \remove_submenu_page('woocommerce', 'wc-addons'); // Extensions
        \remove_submenu_page('woocommerce', 'wc-reports'); // Reports
        \remove_submenu_page('woocommerce', 'wc-marketing'); // Marketing
        \remove_submenu_page('woocommerce', 'wc-payments'); // Payments

        // Remove standalone WooCommerce pages that might be added by other plugins
        \remove_menu_page('edit.php?post_type=product'); // Products (standalone)
        \remove_menu_page('edit.php?post_type=shop_order'); // Orders (standalone)
        \remove_menu_page('edit.php?post_type=shop_coupon'); // Coupons (standalone)
        \remove_menu_page('wc-admin'); // WooCommerce Admin (standalone)
        \remove_menu_page('wc-marketing'); // Marketing (standalone)
        \remove_menu_page('wc-payments'); // Payments (standalone)
        \remove_menu_page('wc-reports'); // Analytics/Reports (standalone)

        // Additional WooCommerce menu removals for newer versions
        \remove_menu_page('woocommerce-marketing'); // WooCommerce Marketing (newer versions)
        \remove_menu_page('woocommerce-payments'); // WooCommerce Payments (newer versions)
        \remove_menu_page('woocommerce-analytics'); // WooCommerce Analytics (newer versions)
        \remove_menu_page('wc-analytics'); // WooCommerce Analytics (alternative)
        \remove_menu_page('wc-payments'); // WooCommerce Payments (alternative)
        \remove_menu_page('wc-marketing'); // WooCommerce Marketing (alternative)

        // Remove other common plugin menus
        \remove_menu_page('jetpack');
        \remove_menu_page('edit.php?post_type=acf-field-group');
        \remove_menu_page('edit.php?post_type=wp_block');
        \remove_menu_page('edit.php?post_type=wp_navigation');
        \remove_menu_page('edit.php?post_type=wp_template');
        \remove_menu_page('edit.php?post_type=wp_template_part');
        \remove_menu_page('edit.php?post_type=wp_global_styles');

        // Remove submenus from remaining menus
        \remove_submenu_page('index.php', 'update-core.php');
        \remove_submenu_page('index.php', 'index.php');

        // Hook to remove any dynamically added WooCommerce menus
        \add_action('admin_menu', function () {
            \remove_menu_page('wc-admin');
            \remove_menu_page('wc-marketing');
            \remove_menu_page('wc-payments');
            \remove_menu_page('wc-analytics');
            \remove_menu_page('woocommerce-marketing');
            \remove_menu_page('woocommerce-payments');
            \remove_menu_page('woocommerce-analytics');

            // Remove additional WooCommerce menu items that might be added by plugins
            \remove_menu_page('wc-payments-overview');
            \remove_menu_page('wc-payments-transactions');
            \remove_menu_page('wc-payments-deposits');
            \remove_menu_page('wc-payments-disputes');
            \remove_menu_page('wc-payments-settings');
            \remove_menu_page('wc-analytics-overview');
            \remove_menu_page('wc-analytics-products');
            \remove_menu_page('wc-analytics-orders');
            \remove_menu_page('wc-analytics-customers');
            \remove_menu_page('wc-analytics-revenue');
            \remove_menu_page('wc-analytics-settings');

            // Remove any menu items with "payments" or "analytics" in the slug
            global $menu;
            if (isset($menu) && is_array($menu)) {
                foreach ($menu as $key => $item) {
                    if (isset($item[2]) && (
                        strpos($item[2], 'payments') !== false ||
                        strpos($item[2], 'analytics') !== false ||
                        strpos($item[2], 'wc-') === 0
                    )) {
                        unset($menu[$key]);
                    }
                }
            }
        }, 999);

        // Additional hook to catch any remaining WooCommerce menu items
        \add_action('admin_menu', function () {
            global $menu;
            if (isset($menu) && is_array($menu)) {
                foreach ($menu as $key => $item) {
                    if (isset($item[2])) {
                        $menu_slug = $item[2];
                        $menu_title = isset($item[0]) ? $item[0] : '';

                        // Remove any menu items that are WooCommerce related
                        if (
                            strpos($menu_slug, 'wc-') === 0 ||
                            strpos($menu_slug, 'woocommerce') !== false ||
                            strpos($menu_slug, 'payments') !== false ||
                            strpos($menu_slug, 'analytics') !== false ||
                            strpos($menu_title, 'Payments') !== false ||
                            strpos($menu_title, 'Analytics') !== false ||
                            strpos($menu_title, 'WooCommerce') !== false
                        ) {
                            unset($menu[$key]);
                        }
                    }
                }
            }
        }, 1000);
    }

    /**
     * Redirect demo users away from restricted pages
     */
    public static function redirect_demo_users()
    {
        if (!self::is_demo_user()) {
            return;
        }

        global $pagenow;

        // List of pages that demo users should not access
        $restricted_pages = [
            'index.php', // Dashboard
            'edit.php', // Posts
            'upload.php', // Media
            'edit.php?post_type=page', // Pages
            'edit-comments.php', // Comments
            'themes.php', // Appearance
            'plugins.php', // Plugins
            'users.php', // Users
            'tools.php', // Tools
            'options-general.php', // Settings
            'profile.php', // Profile
        ];

        // Check if current page is restricted
        foreach ($restricted_pages as $page) {
            if (
                strpos($pagenow, $page) !== false ||
                (isset($_GET['page']) && $_GET['page'] === $page)
            ) {
                \wp_redirect(\admin_url('admin.php?page=wp-migrate-shopify-woo'));
                exit;
            }
        }

        // Redirect from WooCommerce pages
        if (isset($_GET['post_type']) && in_array($_GET['post_type'], ['product', 'shop_order', 'shop_coupon'])) {
            \wp_redirect(\admin_url('admin.php?page=wp-migrate-shopify-woo'));
            exit;
        }

        // Redirect from WooCommerce admin pages
        if (isset($_GET['page'])) {
            $wc_pages = [
                'wc-admin', // WooCommerce Home
                'wc-settings', // WooCommerce Settings
                'wc-status', // WooCommerce Status
                'wc-addons', // WooCommerce Extensions
                'wc-reports', // WooCommerce Reports/Analytics
                'wc-marketing', // WooCommerce Marketing
                'wc-payments', // WooCommerce Payments
                'wc-analytics', // WooCommerce Analytics
                'woocommerce', // WooCommerce main page
                'woocommerce-marketing', // WooCommerce Marketing (newer versions)
                'woocommerce-payments', // WooCommerce Payments (newer versions)
                'woocommerce-analytics', // WooCommerce Analytics (newer versions)
            ];

            if (in_array($_GET['page'], $wc_pages)) {
                \wp_redirect(\admin_url('admin.php?page=wp-migrate-shopify-woo'));
                exit;
            }
        }
    }

    /**
     * Customize admin bar for demo users
     */
    public static function customize_admin_bar_for_demo()
    {
        if (!self::is_demo_user()) {
            return;
        }

        \add_action('admin_bar_menu', function ($wp_admin_bar) {
            // Remove default WordPress nodes
            $wp_admin_bar->remove_node('wp-logo');
            $wp_admin_bar->remove_node('site-name');
            $wp_admin_bar->remove_node('updates');
            $wp_admin_bar->remove_node('comments');
            $wp_admin_bar->remove_node('new-content');
            $wp_admin_bar->remove_node('user-info');
            $wp_admin_bar->remove_node('edit-profile');

            // Keep my-account and logout nodes but customize them
            $wp_admin_bar->remove_node('my-account');
            $wp_admin_bar->remove_node('logout');

            // Add custom demo user info
            $wp_admin_bar->add_node([
                'id' => 'demo-user-info',
                'title' => 'Demo User - Shopify Importer',
                "class" => "demo-user-info",
                'href' => \admin_url('admin.php?page=wp-migrate-shopify-woo'),
            ]);

            // Add logout option
            $wp_admin_bar->add_node([
                'id' => 'demo-logout',
                'title' => 'Logout',
                'href' => \wp_logout_url(),
                'parent' => 'demo-user-info',
            ]);

           
        }, 999);
    }

    /**
     * Restrict editing capabilities for demo users while allowing viewing
     */
    public static function restrict_editing_capabilities()
    {
        if (!self::is_demo_user()) {
            return;
        }

        // Hook to restrict editing capabilities
        \add_action('user_has_cap', function ($allcaps, $caps, $args) {
            $user_id = $args[1];
            $user = \get_user_by('id', $user_id);

            if ($user && $user->user_login === self::DEMO_USER_LOGIN) {
                // Remove editing capabilities while keeping viewing capabilities
                $editing_caps_to_remove = [
                    'edit_posts',
                    'edit_published_posts',
                    'edit_private_posts',
                    'edit_others_posts',
                    'edit_pages',
                    'edit_published_pages',
                    'edit_private_pages',
                    'edit_others_pages',
                    'publish_posts',
                    'publish_pages',
                    'delete_posts',
                    'delete_published_posts',
                    'delete_private_posts',
                    'delete_others_posts',
                    'delete_pages',
                    'delete_published_pages',
                    'delete_private_pages',
                    'delete_others_pages',
                    'edit_products',
                    'edit_published_products',
                    'edit_private_products',
                    'edit_others_products',
                    'publish_products',
                    'delete_products',
                    'delete_published_products',
                    'delete_private_products',
                    'delete_others_products',
                    'edit_shop_orders',
                    'edit_published_shop_orders',
                    'edit_private_shop_orders',
                    'edit_others_shop_orders',
                    'publish_shop_orders',
                    'delete_shop_orders',
                    'delete_published_shop_orders',
                    'delete_private_shop_orders',
                    'delete_others_shop_orders',
                    'edit_shop_coupons',
                    'edit_published_shop_coupons',
                    'edit_private_shop_coupons',
                    'edit_others_shop_coupons',
                    'publish_shop_coupons',
                    'delete_shop_coupons',
                    'delete_published_shop_coupons',
                    'delete_private_shop_coupons',
                    'delete_others_shop_coupons',
                    'edit_shop_customers',
                    'edit_published_shop_customers',
                    'edit_private_shop_customers',
                    'edit_others_shop_customers',
                    'publish_shop_customers',
                    'delete_shop_customers',
                    'delete_published_shop_customers',
                    'delete_private_shop_customers',
                    'delete_others_shop_customers',
                    'edit_comment',
                    'edit_comments',
                    'moderate_comments',
                    'delete_comment',
                    'delete_comments',
                    'approve_comment',
                    'approve_comments',
                    'unapprove_comment',
                    'unapprove_comments',
                    'reply_comment',
                    'reply_comments',
                    'edit_comment_meta',
                    'delete_comment_meta',
                    'add_comment_meta',
                    'edit_users',
                    'delete_users',
                    'create_users',
                    'promote_users',
                    'remove_users',
                    'edit_theme_options',
                    'switch_themes',
                    'edit_themes',
                    'delete_themes',
                    'install_themes',
                    'update_themes',
                    'activate_plugins',
                    'edit_plugins',
                    'delete_plugins',
                    'install_plugins',
                    'update_plugins',
                    'manage_options',
                    'update_core',
                    'export',
                    'import',
                    'delete_site',
                    'manage_network',
                    'manage_sites',
                    'manage_network_users',
                    'manage_network_plugins',
                    'manage_network_themes',
                    'manage_network_options',
                    'upgrade_network',
                    'setup_network',
                    'delete_sites'
                ];

                foreach ($editing_caps_to_remove as $cap) {
                    unset($allcaps[$cap]);
                }
            }

            return $allcaps;
        }, 10, 3);

        // Hook to prevent form submissions and edits
        \add_action('admin_init', function () {
            if (isset($_POST) && !empty($_POST)) {
                // Allow only plugin-specific form submissions (excluding imports and store modifications)
                $allowed_actions = [
                    'wmsw_settings_action',
                    'wmsw_log_action'
                ];

                $has_allowed_action = false;
                foreach ($allowed_actions as $action) {
                    if (isset($_POST[$action]) || isset($_GET['action']) && $_GET['action'] === $action) {
                        $has_allowed_action = true;
                        break;
                    }
                }

                // Block import-related actions
                $blocked_actions = [
                    'wmsw_import_action',
                    'wmsw_import_products',
                    'wmsw_import_customers',
                    'wmsw_import_orders',
                    'wmsw_import_coupons',
                    'wmsw_import_pages',
                    'wmsw_import_blogs',
                    'wmsw_run_imports',
                    'import',
                    'start_import',
                    'process_import'
                ];

                foreach ($blocked_actions as $action) {
                    if (isset($_POST[$action]) || isset($_GET['action']) && $_GET['action'] === $action) {
                        \wp_die(
                            \esc_html__('Demo users cannot perform imports. This action is restricted.', 'wp-migrate-shopify-woo'),
                            \esc_html__('Import Restricted', 'wp-migrate-shopify-woo'),
                            ['response' => 403]
                        );
                    }
                }

                // Block store management actions
                $blocked_store_actions = [
                    'wmsw_store_action',
                    'add_store',
                    'edit_store',
                    'delete_store',
                    'save_store',
                    'update_store',
                    'create_store'
                ];

                foreach ($blocked_store_actions as $action) {
                    if (isset($_POST[$action]) || isset($_GET['action']) && $_GET['action'] === $action) {
                        \wp_die(
                            \esc_html__('Demo users cannot modify stores. This action is restricted.', 'wp-migrate-shopify-woo'),
                            \esc_html__('Store Modification Restricted', 'wp-migrate-shopify-woo'),
                            ['response' => 403]
                        );
                    }
                }

                if (!$has_allowed_action) {
                    \wp_die(
                        \esc_html__('Demo users cannot edit content. This action is restricted.', 'wp-migrate-shopify-woo'),
                        \esc_html__('Action Restricted', 'wp-migrate-shopify-woo'),
                        ['response' => 403]
                    );
                }
            }
        });

        // Hook to disable edit links and buttons
        \add_action('admin_footer', function () {
            echo '<style>
                /* Style for demo logout button */
                #wp-admin-bar-demo-logout-main .ab-item {
                    background-color: #dc3232 !important;
                    color: #fff !important;
                    font-weight: bold !important;
                }
                #wp-admin-bar-demo-logout-main .ab-item:hover {
                    background-color: #a00 !important;
                    color: #fff !important;
                }
                #wp-admin-bar-demo-user-info .ab-item {
                    background-color: #0073aa !important;
                    color: #fff !important;
                }
                #wp-admin-bar-demo-user-info .ab-item:hover {
                    background-color: #005177 !important;
                    color: #fff !important;
                }
            </style>';
            
            echo '<script>
                jQuery(document).ready(function($) {
                    // Disable edit buttons and links
                    $("a[href*=\'post.php?action=edit\'], a[href*=\'post-new.php\'], .edit-link, .edit-button, .add-new-h2, .page-title-action").hide();
                    $("input[type=\'submit\'][value*=\'Update\'], input[type=\'submit\'][value*=\'Publish\'], input[type=\'submit\'][value*=\'Save\']").prop("disabled", true).hide();
                    
                    
                    
                    // Additional store management button selectors
                    $("input[type=\'submit\'][value*=\'Store\'], input[type=\'submit\'][value*=\'Add\'], input[type=\'submit\'][value*=\'Save\'], input[type=\'submit\'][value*=\'Update\']").prop("disabled", true).hide();
                    $(".page-title-action, .add-new-h2").hide();
                    
                    // Show demo user notice on edit pages
                    if (window.location.href.indexOf("post.php?action=edit") > -1 || window.location.href.indexOf("post-new.php") > -1) {
                        $("body").prepend("<div class=\'notice notice-warning\'><p><strong>Demo Mode:</strong> You can view content but cannot edit it.</p></div>");
                    }
                    
                    // Show demo user notice on import pages
                    if (window.location.href.indexOf("wp-migrate-shopify-woo") > -1) {
                        $(".wmsw-import-section, .import-tab").each(function() {
                            $(this).prepend("<div class=\'notice notice-info\'><p><strong>Demo Mode:</strong> Import functionality is disabled for demo users. You can view the interface and settings but cannot perform actual imports.</p></div>");
                        });
                    }
                    
                    // Show demo user notice on store management pages
                    if (window.location.href.indexOf("wp-migrate-shopify-woo-stores") > -1) {
                        $("body").prepend("<div class=\'notice notice-info\'><p><strong>Demo Mode:</strong> You can view all store information and settings, but store management functionality is disabled. You cannot add, edit, or delete stores.</p></div>");
                    }
                });
            </script>';
        });
    }
}
