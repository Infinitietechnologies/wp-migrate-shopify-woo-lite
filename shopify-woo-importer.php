<?php

/**
 * Plugin Name: WP Migrate & Import Shopify to WC Lite
 * Plugin URI: https://infinitietech.com/shopify-woo-importer
 * Description: Multi-Store Product & Data Migration Tool - Import from Shopify to WooCommerce in just a few clicks
 * Version: 1.0.0
 * Author: Infinitietech
 * Author URI: https://infinitietech.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-migrate-shopify-woo-lite
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WMSW_VERSION', '1.0.0');
define('WMSW_PLUGIN_FILE', __FILE__);
define('WMSW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WMSW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WMSW_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load configuration
require_once WMSW_PLUGIN_DIR . 'config/constants.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ShopifyWooImporter\\';
    $base_dir = WMSW_PLUGIN_DIR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $path_parts = explode('\\', $relative_class);

    // Default to includes directory
    $dir_map = [
        'Backend' => 'backend/',
        'Frontend' => 'frontend/',
        'Core' => 'includes/core/',
        'Handlers' => 'includes/handlers/',
        'Models' => 'includes/models/',
        'Services' => 'includes/services/',
        'Processors' => 'includes/processors/',
        'Integrations' => 'includes/integrations/',
        'Helpers' => 'includes/helpers/',
        'Database' => 'includes/database/',
        'Abstracts' => 'includes/abstracts/',
        'WooCommerce' => 'includes/woocommerce/',
    ];

    // Determine directory based on namespace
    $namespace = $path_parts[0];
    $directory = isset($dir_map[$namespace]) ? $dir_map[$namespace] : 'includes/';

    // Remove the namespace from path parts
    array_shift($path_parts);

    // Get file name and full path
    $file_name = end($path_parts) . '.php';
    $file = $base_dir . $directory . $file_name;

    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
final class WMSW_ShopifyWooImporter
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Initialize plugin components
        $this->init_components();
    }
    private function init_components()
    {
        // Initialize backend
        if (is_admin()) {
            require_once WMSW_PLUGIN_DIR . 'backend/WMSWL_Backend.php';
            new ShopifyWooImporter\Backend\WMSWL_Backend();
        }

        // Initialize frontend (if needed)
        require_once WMSW_PLUGIN_DIR . 'frontend/WMSWL_Frontend.php';
        new ShopifyWooImporter\Frontend\WMSWL_Frontend();

        // Initialize handlers
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_CronHandler.php';
        new ShopifyWooImporter\Handlers\WMSWL_CronHandler();
        
        // Initialize product handler for background processing
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_ProductHandler.php';
        $productHandler = new ShopifyWooImporter\Handlers\WMSWL_ProductHandler();

        // Load Customer Handler
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_CustomerHandler.php';
        $customerHandler = new ShopifyWooImporter\Handlers\WMSWL_CustomerHandler();

        // Load Order Handler
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_OrderHandler.php';
        $orderHandler = new ShopifyWooImporter\Handlers\WMSWL_OrderHandler();

        // If we're running in WP Cron, add our cron hooks
        if (defined('DOING_CRON') && DOING_CRON) {
            // Add your cron hooks here
        }
    }    public function activate()
    {
        // Run activation handler
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_ActivationHandler.php';
        $activation = new ShopifyWooImporter\Handlers\WMSWL_ActivationHandler();
        $activation->activate();
    }

    public function deactivate()
    {
        // Run deactivation handler
        require_once WMSW_PLUGIN_DIR . 'includes/handlers/WMSWL_DeactivationHandler.php';
        $deactivation = new ShopifyWooImporter\Handlers\WMSWL_DeactivationHandler();
        $deactivation->deactivate();
    }

    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p><strong>';
        esc_html_e('WP Migrate & Import Shopify to WooCommerce', 'wp-migrate-shopify-woo-lite');
        echo '</strong> ';
        esc_html_e('requires WooCommerce to be installed and active.', 'wp-migrate-shopify-woo-lite');
        echo '</p></div>';
    }
}

// Initialize the plugin
WMSW_ShopifyWooImporter::instance();
