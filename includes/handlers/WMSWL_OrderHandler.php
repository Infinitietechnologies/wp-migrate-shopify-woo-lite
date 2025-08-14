<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Models\WMSW_ShopifyStore;
use ShopifyWooImporter\Models\WMSW_ImportLog;
use ShopifyWooImporter\Processors\WMSW_OrderProcessor;
use ShopifyWooImporter\Services\WMSW_Logger;
use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;

// WordPress functions
use function get_option;
use function add_action;
use function wp_send_json_error;
use function wp_send_json_success;
use function __;
use function sanitize_text_field;
use function set_transient;
use function get_transient;
use function delete_transient;
use function wp_schedule_single_event;
use function time;
use function wp_verify_nonce;
use function absint;

/**
 * Order Handler
 *
 * Handles order preview and import functionality
 */
class WMSW_OrderHandler
{
    /**
     * @var WMSW_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new WMSW_Logger();
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks()
    {
        // Register AJAX handler for order preview
        add_action('wp_ajax_wmsw_preview_orders', [$this, 'previewOrders']);
        add_action('wp_ajax_wmsw_start_orders_import', [$this, 'startOrdersImport']);
        add_action('wp_ajax_wmsw_get_orders_import_progress', [$this, 'getOrdersImportProgress']);
        add_action('wp_ajax_wmsw_check_active_order_imports', [$this, 'checkActiveImports']);

        // Register handler for background order import
        add_action('wmsw_process_order_import', [$this, 'processOrderImport'], 10, 3);
    }

    /**
     * Preview orders from Shopify store
     * AJAX handler for order preview
     */
    public function previewOrders()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        try {
            $store_id = absint($_POST['store_id'] ?? 0);
            $order_status = isset($_POST['order_status']) ? array_map('sanitize_text_field', sanitize_text_field(wp_unslash($_POST['order_status']))) : ['open', 'closed'];
            $limit = absint($_POST['limit'] ?? 5);

            if (!$store_id) {
                wp_send_json_error([
                    'message' => __('Please select a store.', 'wp-migrate-shopify-woo')
                ]);
            }

            // Get store details
            $store = WMSW_ShopifyStore::find($store_id);
            if (!$store) {
                wp_send_json_error([
                    'message' => __('Store not found.', 'wp-migrate-shopify-woo')
                ]);
            }

            // Initialize Shopify client
            $client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Fetch orders from Shopify using GraphQL
            $query_params = [
                'limit' => $limit
            ];

            // Add order status filter if provided
            if (!empty($order_status) && is_array($order_status)) {
                $query_params['order_status'] = $order_status;
            }

            $response = $client->get('orders', $query_params);

            if (isset($response['orders'])) {
                $orders = $response['orders'];
                $total_count = count($orders);

                // Format orders for preview (handling GraphQL response structure)
                $formatted_orders = [];
                foreach ($orders as $order) {
                    // Get total price from the new structure
                    $total_price = '0.00';
                    $currency = 'USD';
                    if (isset($order['currentTotalPriceSet']['shopMoney'])) {
                        $total_price = number_format((float)$order['currentTotalPriceSet']['shopMoney']['amount'], 2);
                        $currency = $order['currentTotalPriceSet']['shopMoney']['currencyCode'] ?? $order['currencyCode'] ?? 'USD';
                    }

                    $formatted_orders[] = [
                        'id' => $order['id'],
                        'order_number' => $order['name'] ?? '#' . ($order['id'] ?? ''),
                        'name' => $order['name'] ?? '#' . ($order['id'] ?? ''),
                        'customer_email' => $order['email'] ?? $order['customer']['email'] ?? __('Guest', 'wp-migrate-shopify-woo'),
                        'customer_name' => isset($order['customer']) ?
                            ($order['customer']['displayName'] ?? $order['customer']['email'] ?? __('Guest', 'wp-migrate-shopify-woo')) :
                            __('Guest', 'wp-migrate-shopify-woo'),
                        'total' => $total_price,
                        'currency' => $currency,
                        'status' => $order['displayFinancialStatus'] ?? 'PENDING',
                        'fulfillment_status' => $order['displayFulfillmentStatus'] ?? 'UNFULFILLED',
                        'created_at' => isset($order['createdAt']) ?
                            gmdate('Y-m-d H:i:s', strtotime($order['createdAt'])) :
                            gmdate('Y-m-d H:i:s'),
                        'line_items_count' => isset($order['lineItems']['nodes']) ?
                            count($order['lineItems']['nodes']) :
                            0
                    ];
                }

                wp_send_json_success([
                    'orders' => $formatted_orders,
                    'total_count' => $total_count,
                    'store_name' => $store->get_store_name()
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('No orders found or unable to fetch orders.', 'wp-migrate-shopify-woo')
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Order preview failed: ' . $e->getMessage(), [
                'store_id' => $store_id ?? null,
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => __('Failed to preview orders: ', 'wp-migrate-shopify-woo') . $e->getMessage()
            ]);
        }
    }

    /**
     * Start orders import process
     * AJAX handler for starting order import
     */
    public function startOrdersImport()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        try {
            $store_id = absint($_POST['store_id'] ?? 0);
            $order_status = isset($_POST['order_status']) ? array_map('sanitize_text_field', sanitize_text_field(wp_unslash($_POST['order_status']))) : ['open', 'closed'];
            $create_customers = isset($_POST['create_customers']) ? (bool)sanitize_text_field(wp_unslash($_POST['create_customers'])) : true;
            $import_notes = isset($_POST['import_notes']) ? (bool)sanitize_text_field(wp_unslash($_POST['import_notes'])) : true;
            $import_refunds = isset($_POST['import_refunds']) ? (bool)sanitize_text_field(wp_unslash($_POST['import_refunds'])) : false;

            if (!$store_id) {
                wp_send_json_error([
                    'message' => __('Please select a store.', 'wp-migrate-shopify-woo')
                ]);
            }

            // Get store details
            $store = WMSW_ShopifyStore::find($store_id);
            if (!$store) {
                wp_send_json_error([
                    'message' => __('Store not found.', 'wp-migrate-shopify-woo')
                ]);
            }

            // Create import session for structured logging
            $import_id = $this->createOrderImportSession($store_id, [
                'order_status' => $order_status,
                'create_customers' => $create_customers,
                'import_notes' => $import_notes,
                'import_refunds' => $import_refunds
            ]);

            if (!$import_id) {
                wp_send_json_error([
                    'message' => __('Failed to create import session', 'wp-migrate-shopify-woo')
                ]);
                return;
            }

            // Set import progress transient (keeping existing transient system for compatibility)
            $progress_key = 'wmsw_orders_import_progress_' . $store_id;
            set_transient($progress_key, [
                'status' => 'starting',
                'current' => 0,
                'total' => 0,
                'message' => __('Initializing order import...', 'wp-migrate-shopify-woo'),
                'errors' => [],
                'start_time' => time(),
                'import_id' => $import_id  // Link to import session
            ], 3600);

            // Prepare import options
            $import_options = [
                'store_id' => $store_id,
                'order_status' => $order_status,
                'create_customers' => $create_customers,
                'import_notes' => $import_notes,
                'import_refunds' => $import_refunds,
                'progress_key' => $progress_key,
                'import_id' => $import_id  // Pass import_id for structured logging
            ];

            // Schedule background import
            wp_schedule_single_event(time(), 'wmsw_process_order_import', [$store_id, $import_options, $progress_key]);

            // Log the start of import using structured logging
            $this->logger->info('Order import session started', [
                'level' => 'info',
                'message' => 'Order import session started',
                'import_id' => $import_id,
                'store_id' => $store_id,
                'store_name' => $store->get_store_name(),
                'order_status' => $order_status,
                'create_customers' => $create_customers,
                'import_notes' => $import_notes,
                'import_refunds' => $import_refunds
            ]);

            wp_send_json_success([
                'message' => __('Orders import started successfully.', 'wp-migrate-shopify-woo'),
                'progress_key' => $progress_key,
                'import_id' => $import_id
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to start orders import: ' . $e->getMessage(), [
                'store_id' => $store_id ?? null,
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => __('Failed to start orders import: ', 'wp-migrate-shopify-woo') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get orders import progress
     * AJAX handler for checking import progress
     */
    public function getOrdersImportProgress()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }
        
        
        $progress_key = sanitize_text_field(wp_unslash($_POST['progress_key'] ?? ''));

        $this->logger->debugLog('Using progress_key: ' . $progress_key);

        if (!$progress_key) {
            wp_send_json_error([
                'message' => __('Invalid progress key.', 'wp-migrate-shopify-woo')
            ]);
        }

        $progress = get_transient($progress_key);

        if ($progress === false) {
            $this->logger->debugLog('Progress transient not found for key: ' . $progress_key);
            wp_send_json_error([
                'message' => __('Import progress not found.', 'wp-migrate-shopify-woo')
            ]);
        }

        wp_send_json_success($progress);
    }

    /**
     * Process order import in background
     * This method is called by WordPress cron
     */
    public function processOrderImport($store_id, $import_options, $progress_key)
    {
        $import_id = $import_options['import_id'] ?? null;

        try {
            // Get store details
            $store = WMSW_ShopifyStore::find($store_id);
            if (!$store) {
                throw new \Exception(__('Store not found.', 'wp-migrate-shopify-woo'));
            }

            // Update import session status to in_progress
            if ($import_id) {
                $this->updateOrderImportSession($import_id, [
                    'status' => 'in_progress',
                    'started_at' => current_time('mysql'),
                    'items_processed' => 0,
                    'level' => 'info',
                    'updated_at' => current_time('mysql'),
                    'message' => __('Import process started.', 'wp-migrate-shopify-woo')
                ]);
            }

            // Initialize Shopify client for the processor
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );
            
            // Initialize processor with required dependencies
            $processor = new WMSW_OrderProcessor($shopify_client, $this->logger);

            // Start the import process
            $result = $processor->import_orders($store, $import_options, $progress_key);

            // Update import session with final results
            if ($import_id) {
                $final_status = $result['success'] ? 'completed' : 'failed';
                $this->updateOrderImportSession($import_id, [
                    'status' => $final_status,
                    'items_processed' => ($result['imported_count'] ?? 0) + ($result['skipped_count'] ?? 0),
                    'items_succeeded' => $result['imported_count'] ?? 0,
                    'items_failed' => $result['error_count'] ?? 0,
                    'items_skipped' => $result['skipped_count'] ?? 0,
                    'log_data' => $result['success'] ? null : json_encode(['message' => $result['message'] ?? 'Unknown error']),
                    'completed_at' => current_time('mysql')
                ]);
            }

            if ($result['success']) {
                $this->logger->info('Order import batch completed successfully', [
                    'level' => 'info',
                    'message' => 'Order import batch completed successfully',
                    'import_id' => $import_id,
                    'store_id' => $store_id,
                    'imported_count' => $result['imported_count'] ?? 0,
                    'skipped_count' => $result['skipped_count'] ?? 0,
                    'error_count' => $result['error_count'] ?? 0
                ]);
            } else {
                $this->logger->error('Order import batch completed with errors', [
                    'level' => 'error',
                    'message' => 'Order import batch completed with errors',
                    'import_id' => $import_id,
                    'store_id' => $store_id,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            // Update import session with error status
            if ($import_id) {
                $this->updateOrderImportSession($import_id, [
                    'status' => 'failed',
                    'log_data' => json_encode(['error' => $e->getMessage()]),
                    'completed_at' => current_time('mysql')
                ]);
            }

            $this->logger->error('Order import process failed', [
                'level' => 'error',
                'message' => 'Order import process failed',
                'import_id' => $import_id,
                'store_id' => $store_id,
                'error' => $e->getMessage()
            ]);

            // Update progress with error
            $progress = get_transient($progress_key) ?: [];
            $progress['status'] = 'error';
            $progress['message'] = $e->getMessage();
            $progress['errors'][] = $e->getMessage();
            // Ensure we have completion data even in error case
            if (!isset($progress['imported_count'])) $progress['imported_count'] = 0;
            if (!isset($progress['skipped_count'])) $progress['skipped_count'] = 0;
            if (!isset($progress['error_count'])) $progress['error_count'] = 1;
            set_transient($progress_key, $progress, 3600);
        }
    }

    /**
     * Create an order import session record
     * 
     * @param int $store_id The ID of the Shopify store
     * @param array $options Import options
     * @return int|false The import ID or false on failure
     */
    private function createOrderImportSession($store_id, $options)
    {
        // Create import session using the model
        $import_session = WMSW_ImportLog::createImportSession($store_id, 'orders', $options);

        if ($import_session) {
            $import_id = $import_session->getId();
            
            // Log the session creation using logger abstraction
            $this->logger->info('Order import session created', [
                'level' => 'info',
                'message' => 'Order import session created',
                'import_id' => $import_id,
                'store_id' => $store_id,
                'import_type' => 'orders',
                'status' => 'initializing',
                'options' => $options
            ]);

            return $import_id;
        } else {
            $this->logger->error('Failed to create order import session', [
                'level' => 'error',
                'message' => 'Failed to create order import session',
                'store_id' => $store_id,
                'import_type' => 'orders',
                'options' => $options
            ]);

            return false;
        }
    }

    /**
     * Update order import session record
     *
     * @param int $import_id The import session ID
     * @param array $data The data to update
     * @return bool Success or failure
     */
    private function updateOrderImportSession($import_id, $data)
    {
        // Find the import session
        $import_session = WMSW_ImportLog::find($import_id);
        
        if (!$import_session) {
            $this->logger->error('Failed to find order import session', [
                'level' => 'error',
                'message' => 'Failed to find order import session',
                'import_id' => $import_id
            ]);
            return false;
        }

        // Update the import session
        $result = $import_session->updateImportSession($data);

        // Log the session update using logger abstraction
        if ($result) {
            $log_message = 'Order import session updated';
            $log_context = [
                'level' => 'info',
                'message' => $log_message,
                'import_id' => $import_id
            ];

            // Add specific data to context for better tracking
            if (isset($data['status'])) {
                $log_context['status'] = $data['status'];
                $log_message = "Order import session status updated to: {$data['status']}";
                $log_context['message'] = $log_message;
            }
            if (isset($data['items_processed'])) {
                $log_context['items_processed'] = $data['items_processed'];
            }
            if (isset($data['items_total'])) {
                $log_context['items_total'] = $data['items_total'];
            }

            $this->logger->info($log_message, $log_context);
        } else {
            $this->logger->error('Failed to update order import session', [
                'level' => 'error',
                'message' => 'Failed to update order import session',
                'import_id' => $import_id,
                'data' => $data
            ]);
        }

        return $result;
    }

    /**
     * Check for active order imports for a specific store
     * AJAX handler for checking if there are any active order imports running
     */
    public function checkActiveImports()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        // Get store ID
        $store_id = isset($_POST['store_id']) ? intval(sanitize_text_field(wp_unslash($_POST['store_id']))) : 0;
        
        if (!$store_id) {
            wp_send_json_error([
                'message' => __('No store specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Check for active order imports using the model
        $active_import = WMSW_ImportLog::findActiveImportSession($store_id, 'orders');

        if ($active_import) {
            // Calculate percentage
            $percentage = 0;
            if ($active_import->getItemsTotal() > 0) {
                $percentage = round(($active_import->getItemsProcessed() / $active_import->getItemsTotal()) * 100);
            }

            // Also check for transient-based progress (fallback)
            $progress_key = 'wmsw_orders_import_progress_' . $store_id;
            $transient_progress = get_transient($progress_key);
            
            $active_job = [
                'import_id' => $active_import->getId(),
                'progress_key' => $progress_key,
                'percentage' => $percentage,
                'message' => $active_import->getMessage() ?: 'Processing orders...',
                'current' => $active_import->getItemsProcessed(),
                'total' => $active_import->getItemsTotal(),
                'status' => $active_import->getStatus(),
                'completed' => false
            ];

            // If we have transient data, merge it for more detailed info
            if ($transient_progress) {
                $active_job = array_merge($active_job, [
                    'imported_count' => $transient_progress['imported_count'] ?? 0,
                    'skipped_count' => $transient_progress['skipped_count'] ?? 0,
                    'error_count' => $transient_progress['error_count'] ?? 0,
                ]);
            }

            wp_send_json_success([
                'active_import' => $active_job,
                'message' => __('Active order import found', 'wp-migrate-shopify-woo')
            ]);
        } else {
            wp_send_json_success([
                'active_import' => null,
                'message' => __('No active order imports found', 'wp-migrate-shopify-woo')
            ]);
        }
    }
}
