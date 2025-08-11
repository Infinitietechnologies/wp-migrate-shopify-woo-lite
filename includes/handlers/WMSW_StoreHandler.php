<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Models\WMSW_ShopifyStore;
use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
use ShopifyWooImporter\Services\WMSW_StoreLogger;


/**
 * Handler for store management
 */
class WMSW_StoreHandler
{

    public function __construct()
    {
        $this->initHooks();
    }
    private function initHooks()
    {
        add_action('wp_ajax_wmsw_deactivate_store', [$this, 'toggleStatusStore']);
        add_action('wp_ajax_wmsw_delete_store', [$this, 'deleteStore']);
        add_action('wp_ajax_wmsw_check_store_connection', [$this, 'testConnectionWithStore']);
        add_action('wp_ajax_wmsw_save_store', [$this, 'saveStore']);
        add_action('wp_ajax_wmsw_set_default_store', [$this, 'setDefaultStore']);
        add_action('wp_ajax_wmsw_copy_store', [$this, 'copyStore']);
    }

    /**
     * Get all stores
     *
     * @return array Array of store objects
     */
    public function get_stores()
    {
        return WMSW_ShopifyStore::get_all();
    }

    /**
     * Get a specific store
     *
     * @param int $store_id Store ID
     * @return object|bool Store object or false
     */
    public function get_store($store_id)
    {
        return WMSW_ShopifyStore::find($store_id);
    }





    /**
     * Create or update a store
     *
     * @param array $data Store data
     * @return WMSW_ShopifyStore|void Store object or sends json error response
     */    /**
     * AJAX handler for saving a store
     */
    public function saveStore()
    {
        // Check nonce and permissions in one call
        WMSW_SecurityHelper::verifyAdminRequest();

        // Validate required fields using enhanced security helper
        $required_fields = ['store_name', 'shop_domain', 'access_token', 'api_version'];
        WMSW_SecurityHelper::validateRequiredFields($required_fields);

        // Get sanitized data using enhanced security helper
        $store_id = WMSW_SecurityHelper::getPostData('store_id', 0, 'intval');
        $store_name = WMSW_SecurityHelper::getPostData('store_name');
        $shop_domain = WMSW_SecurityHelper::getPostData('shop_domain');
        $access_token = WMSW_SecurityHelper::getPostData('access_token');
        $api_version = WMSW_SecurityHelper::getPostData('api_version', '2023-10');
        $is_active = !empty($_POST['is_active']) ? 1 : 0;
        $is_default = !empty($_POST['is_default']) ? 1 : 0;

        // Handle update vs insert
        $store_id = isset($_POST['store_id']) ? absint($_POST['store_id']) : 0;

        try {

            if ($store_id > 0) {
                // Update existing store
                $store = WMSW_ShopifyStore::find($store_id);
                if (!$store) {
                    wp_send_json_error(['message' => __('Store not found', 'wp-migrate-shopify-woo')]);
                }
                $store->set_store_name($store_name);
                $store->set_shop_domain($shop_domain);
                $store->set_access_token($access_token);
                $store->set_api_version($api_version);
                $store->set_is_active($is_active);

                // Handle default store logic
                if ($is_default) {
                    // Reset all other stores to not default
                    global $wpdb;
                    $table = $wpdb->prefix . WMSW_STORES_TABLE;
                    $wpdb->update(
                        $table,
                        ['is_default' => 0],
                        ['is_default' => 1],
                        ['%d'],
                        ['%d']
                    );
                }
                $store->set_is_default($is_default);
            } else {
                // Create new store
                // Handle default store logic for new stores
                if ($is_default) {
                    // Reset all other stores to not default
                    global $wpdb;
                    $table = $wpdb->prefix . WMSW_STORES_TABLE;
                    $wpdb->update(
                        $table,
                        ['is_default' => 0],
                        ['is_default' => 1],
                        ['%d'],
                        ['%d']
                    );
                }

                $store = new WMSW_ShopifyStore([
                    'store_name' => $store_name,
                    'shop_domain' => $shop_domain,
                    'access_token' => $access_token,
                    'api_version' => $api_version,
                    'is_active' => $is_active,
                    'is_default' => $is_default
                ]);
            }
            if ($store->save()) {
                wp_send_json_success([
                    'message' => $store_id > 0
                        ? __('Store updated successfully', 'wp-migrate-shopify-woo')
                        : __('Store added successfully', 'wp-migrate-shopify-woo'),
                    'store_id' => $store->get_id()
                ]);
            } else {
                // Get last database error for debugging
                global $wpdb;
                $db_error = $wpdb->last_error;
                $error_message = __('Failed to save store', 'wp-migrate-shopify-woo');

                if (!empty($db_error) && defined('WP_DEBUG') && WP_DEBUG) {
                    $error_message .= ': ' . $db_error;
                }

                wp_send_json_error([
                    'message' => $error_message,
                    'debug_info' => [
                        'db_error' => $db_error,
                        'table_exists' => $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . WMSW_STORES_TABLE)) ? true : false,
                        'store_data' => [
                            'store_name' => $store_name,
                            'shop_domain' => $shop_domain,
                            'api_version' => $api_version,
                            'is_active' => $is_active
                        ]
                    ]
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' =>  $e->getMessage()]);
        }
    }



    /**
     * Delete a store
     *
     * @param int $store_id Store ID     
     * @return bool|void True on success or sends json error response
     */
    public function deleteStore()
    {
        // Check nonce and permissions in one call
        WMSW_SecurityHelper::verifyAdminRequest();

        // Get sanitized store ID using enhanced security helper
        $store_id = WMSW_SecurityHelper::sanitizeStoreId();


        try {
            $store = WMSW_ShopifyStore::find($store_id);
            // Get store

            if (!$store) {
                wp_send_json_error([
                    'message' => __('Store not found', 'wp-migrate-shopify-woo')
                ]);
            }

            // Delete store
            $result = $store->delete();

            if (!$result) {
                wp_send_json_error([
                    'message' => __('Failed to delete store', 'wp-migrate-shopify-woo')
                ]);
            }

            wp_send_json_success([
                'message' => __('Store deleted successfully', 'wp-migrate-shopify-woo')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX handler for setting a store as default
     */
    public function setDefaultStore()
    {
        // Check nonce and permissions
        WMSW_SecurityHelper::verifyAdminRequest();

        $store_id = absint($_POST['store_id'] ?? 0);

        if (!$store_id) {
            wp_send_json_error([
                'message' => esc_html__('Invalid store ID', 'wp-migrate-shopify-woo')
            ]);
        }

        global $wpdb;
        $table = $wpdb->prefix . WMSW_STORES_TABLE;

        // Get previous default store for logging
        $table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);
        $previous_default = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE is_default = %d", 1));

        // First, remove default status from all stores
        $wpdb->update(
            $table,
            ['is_default' => 0],
            ['is_default' => 1],
            ['%d'],
            ['%d']
        );

        // Then set the selected store as default
        $result = $wpdb->update(
            $table,
            ['is_default' => 1],
            ['id' => $store_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            // Log the change
            if (class_exists(WMSW_StoreLogger::class)) {
                WMSW_StoreLogger::log(
                    $store_id,
                    'set_default',
                    [
                        'previous_default_store_id' => $previous_default
                    ]
                );
            }

            wp_send_json_success([
                'message' => esc_html__('Default store set successfully', 'wp-migrate-shopify-woo')
            ]);
        } else {
            wp_send_json_error([
                'message' => esc_html__('Failed to set default store', 'wp-migrate-shopify-woo')
            ]);
        }
    }

    /**
     * Set a store as default (for backend AJAX handler)
     *
     * @param int $store_id
     * @return true|\WP_Error
     */
    public function set_default_store($store_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_STORES_TABLE;
        if (!$store_id) {
            return new \WP_Error('invalid_store_id', esc_html__('Invalid store ID', 'wp-migrate-shopify-woo'));
        }
        // Remove default from all
        $wpdb->update($table, ['is_default' => 0], ['is_default' => 1], ['%d'], ['%d']);
        // Set selected as default
        $result = $wpdb->update($table, ['is_default' => 1], ['id' => $store_id], ['%d'], ['%d']);
        if ($result !== false) {
            if (class_exists('wmsw_StoreLogger')) {
                \ShopifyWooImporter\Services\WMSW_StoreLogger::log($store_id, 'set_default', []);
            }
            return true;
        } else {
            return new \WP_Error('db_error', esc_html__('Failed to set default store', 'wp-migrate-shopify-woo'));
        }
    }

    /**
     * Get store client
     *
     * @param int $store_id Store ID
     * @return ShopifyClient|void Client object or json error response
     */
    public function get_store_client($store_id)
    {
        // Get store
        $store = WMSW_ShopifyStore::find($store_id);

        if (!$store) {
            \wp_send_json_error([
                'message' => \__('Store not found', 'wp-migrate-shopify-woo')
            ]);
        }

        try {
            // Create client
            $client = new WMSW_ShopifyClient(
                $store->shop_url,
                $store->api_key,
                $store->api_password,
                $store->api_version
            );

            return $client;
        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle store status (activate/deactivate)
     *
     * @return void Sends JSON response
     */
    public function toggleStatusStore()
    {
        // Check nonce and permissions in one call
        WMSW_SecurityHelper::verifyAdminRequest('nonce', 'swi-admin-nonce', 'manage_woocommerce');

        $store_id_raw = absint($_POST['store_id'] ?? 0);
        $store_id     = $store_id_raw;
        $store        = WMSW_ShopifyStore::find($store_id);

        if (!$store) {
            wp_send_json_error([
                'message'    => esc_html__('Store not found', 'wp-migrate-shopify-woo'),
                'error_type' => 'not_found',
            ]);
        }

        try {
            $current_status = $store->get_is_active();
            $new_status     = $current_status ? 0 : 1;

            $store->set_is_active($new_status);

            if ($store->save()) {
                $status_text = $new_status
                    ? esc_html__('activated', 'wp-migrate-shopify-woo')
                    : esc_html__('deactivated', 'wp-migrate-shopify-woo');

                wp_send_json_success([
                    'message' => sprintf(
                        /* translators: %s: status text (activated/deactivated) */
                        esc_html__('Store %s successfully', 'wp-migrate-shopify-woo'),
                        $status_text
                    ),
                ]);
            } else {
                wp_send_json_error([
                    'message' => esc_html__('Failed to update store status', 'wp-migrate-shopify-woo'),
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Error updating store status: %s', 'wp-migrate-shopify-woo'),
                    esc_html($e->getMessage())
                ),
            ]);
        }
    }


    /**
     * Test connection with the Shopify store.
     *
     * @return void Sends JSON response
     */
    public function testConnectionWithStore()
    {
        // Check nonce and permissions in one call
        WMSW_SecurityHelper::verifyAdminRequest();

        // Sanitize and get store ID
        $store_id_raw = absint($_POST['store_id'] ?? 0);
        $store_id     = $store_id_raw;
        $store        = WMSW_ShopifyStore::find($store_id);

        if (!$store) {
            wp_send_json_error([
                'message' => esc_html__('Store not found', 'wp-migrate-shopify-woo'),
            ]);
        }

        // Check if store is active
        if (!$store->get_is_active()) {
            wp_send_json_error([
                'message' => esc_html__('Store is not active', 'wp-migrate-shopify-woo'),
            ]);
        }

        // Check if store has a valid access token
        $access_token = $store->get_access_token();
        if (empty($access_token)) {
            wp_send_json_error([
                'message' => esc_html__('Store does not have a valid access token', 'wp-migrate-shopify-woo'),
            ]);
        }

        // Get store connection details
        $shop_domain = sanitize_text_field($store->get_shop_domain());
        $api_version = sanitize_text_field($store->get_api_version());

        if (empty($shop_domain) || empty($access_token) || empty($api_version)) {
            wp_send_json_error([
                'message' => esc_html__('Store is not configured properly', 'wp-migrate-shopify-woo'),
            ]);
        }

        $client = new WMSW_ShopifyClient($shop_domain, $access_token, $api_version);

        // Try to fetch shop info
        $shop_info = $client->get('shop');

        try {

            if (isset($shop_info['shop'])) {
                wp_send_json_success([
                    'message' => esc_html__('Connection successful!', 'wp-migrate-shopify-woo'),
                    'details' => sprintf(
                        /* translators: 1: shop name, 2: shop domain */
                        esc_html__('Connected to: %1$s (%2$s)', 'wp-migrate-shopify-woo'),
                        esc_html($shop_info['shop']['name'] ?? $shop_domain),
                        esc_html($shop_info['shop']['domain'] ?? $shop_domain . '.myshopify.com')
                    ),
                ]);
            } else {
                wp_send_json_error([
                    'message' => esc_html__('Connection failed. Invalid response from Shopify.', 'wp-migrate-shopify-woo'),
                    'details' => esc_html__('The API returned an unexpected response format.', 'wp-migrate-shopify-woo'),
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => esc_html__('Connection failed.', 'wp-migrate-shopify-woo'),
                'details' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Error: %s', 'wp-migrate-shopify-woo'),
                    esc_html($e->getMessage())
                ),
            ]);
        }
    }

    /**
     * Copy a store
     * 
     * Creates a new store with the same configuration as an existing store,
     * but with a different name and not set as the default store.
     */
    public function copyStore()
    {
        // Check nonce and permissions
        WMSW_SecurityHelper::verifyAdminRequest();

        $store_id = absint($_POST['store_id'] ?? 0);

        if (!$store_id) {
            wp_send_json_error([
                'message' => esc_html__('Invalid store ID', 'wp-migrate-shopify-woo')
            ]);
        }

        // Get the source store
        $source_store = WMSW_ShopifyStore::find($store_id);

        if (!$source_store) {
            wp_send_json_error([
                'message' => esc_html__('Source store not found', 'wp-migrate-shopify-woo')
            ]);
        }

        try {            // Create a new store with modified configuration
            // Generate a unique timestamp suffix for both name and domain
            $timestamp = time();
            $unique_domain = $source_store->get_shop_domain();
            $store_name = $source_store->get_store_name();

            // Make domain unique to avoid constraint errors
            $unique_domain = $this->make_unique_domain($unique_domain);

            // Check if store name already has (Copy) and add timestamp if it does
            if (strpos($store_name, '(Copy)') !== false) {
                $store_name = $store_name . ' ' . $timestamp;
            } else {
                $store_name = $store_name . ' (Copy)';
            }

            $new_store = new WMSW_ShopifyStore([
                'store_name' => $store_name,
                'shop_domain' => $unique_domain,
                'access_token' => $source_store->get_access_token(),
                'api_version' => $source_store->get_api_version(),
                'is_active' => $source_store->get_is_active(),
                'is_default' => 0 // Never set the copied store as default
            ]);

            if ($new_store->save()) {
                // Log the store creation
                if (class_exists(WMSW_StoreLogger::class)) {
                    WMSW_StoreLogger::log(
                        $new_store->get_id(),
                        'copy',
                        [
                            'source_store_id' => $source_store->get_id(),
                            'source_store_name' => $source_store->get_store_name()
                        ]
                    );
                }
                wp_send_json_success([
                    'message' => esc_html__('Store copied successfully. Note: The store domain has been modified for system compatibility.', 'wp-migrate-shopify-woo'),
                    'new_store_id' => $new_store->get_id()
                ]);
            } else {
                wp_send_json_error([
                    'message' => esc_html__('Failed to copy store', 'wp-migrate-shopify-woo')
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    esc_html__('Error copying store: %s', 'wp-migrate-shopify-woo'),
                    $e->getMessage()
                )
            ]);
        }
    }

    /**
     * Make shop domain unique by adding a timestamp suffix
     * 
     * @param string $domain The original shop domain
     * @return string The modified unique domain
     */
    private function make_unique_domain($domain)
    {
        $timestamp = time();

        // If domain already contains '-copy', replace it with a new timestamp-based suffix
        if (strpos($domain, '-copy-') !== false) {
            $domain = preg_replace('/-copy-\d+/', '', $domain);
        }

        // Add suffix to domain before the .myshopify.com part
        $domain_parts = explode('.', $domain);
        if (count($domain_parts) >= 3) {
            $domain_parts[0] .= '-copy-' . $timestamp;
            return implode('.', $domain_parts);
        }

        // Fallback if domain format is unexpected
        return str_replace('.myshopify.com', '-copy-' . $timestamp . '.myshopify.com', $domain);
    }
}
