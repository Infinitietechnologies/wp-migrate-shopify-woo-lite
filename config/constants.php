<?php

/**
 * Plugin Constants
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Database table names
define('WMSW_STORES_TABLE', 'wmsw_shopify_stores');
define('WMSW_TASKS_TABLE', 'wmsw_import_tasks');
define('WMSW_LOGS_TABLE', 'wmsw_import_logs');
define('WMSW_MAPPINGS_TABLE', 'wmsw_field_mappings');
define('WMSW_STORE_LOGS_TABLE', 'wmsw_store_logs');
define('WMSW_SETTINGS_TABLE', 'wmsw_settings');
define('WMSW_IMPORTS_TABLE', 'wmsw_imports');

// Shopify API constants
define('WMSW_SHOPIFY_API_VERSION', '2024-04');
define('WMSW_SHOPIFY_RATE_LIMIT', 40); // Requests per second
define('WMSW_SHOPIFY_TIMEOUT', 30); // Seconds

// Import batch sizes
define('WMSW_BATCH_SIZE_PRODUCTS', 50);
define('WMSW_BATCH_SIZE_CUSTOMERS', 50); // Increased from 1 to process more customers per request
define('WMSW_BATCH_SIZE_ORDERS', 25);

// Count operation batch sizes
define('WMSW_COUNT_BATCH_SIZE_PRODUCTS', 250); // Maximum items for counting products
define('WMSW_COUNT_BATCH_SIZE_CUSTOMERS', 250); // Maximum items for counting customers
define('WMSW_COUNT_BATCH_SIZE_ORDERS', 250); // Maximum items for counting orders
define('WMSW_MAX_COUNT_ITEMS', 250); // Maximum items to fetch per request when counting

// Maximum items to import in a single run (pagination handling)
define('WMSW_MAX_IMPORT_ITEMS', 250); // Set maximum items to import at once

// Background process constants
define('WMSW_PROCESS_TIMEOUT', 300); // 5 minutes
define('WMSW_PROCESS_MEMORY_LIMIT', '256M');

// Cache constants
define('WMSW_CACHE_DURATION', 3600); // 1 hour
define('WMSW_CACHE_GROUP', 'shopify_woo_importer');

// Pages URLs
define('WMSW_ADMIN_PAGE', admin_url('admin.php?page=wp-migrate-shopify-woo-lite'));
define('WMSW_STORE_PAGE', admin_url('admin.php?page=wp-migrate-shopify-woo-stores'));
define('WMSW_SETTINGS_PAGE', admin_url('admin.php?page=wp-migrate-shopify-woo-settings'));
define('WMSW_LOGS_PAGE', admin_url('admin.php?page=wp-migrate-shopify-woo-logs'));
