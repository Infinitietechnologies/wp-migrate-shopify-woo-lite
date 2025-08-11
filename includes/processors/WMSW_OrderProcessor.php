<?php

namespace ShopifyWooImporter\Processors;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Models\WMSW_ShopifyStore;
use ShopifyWooImporter\Services\WMSW_Logger;
use ShopifyWooImporter\Processors\WMSW_CustomerProcessor;

// WordPress functions
use function set_transient;
use function get_transient;
use function __;
use function update_post_meta;

/**
 * Order Processor
 *
 * Handles the processing and import of orders from Shopify to WooCommerce
 */
class WMSW_OrderProcessor
{
    /**
     * @var WMSW_ShopifyClient
     */
    private $shopify_client;

    /**
     * @var WMSW_Logger
     */
    private $logger;

    /**
     * @var WMSW_CustomerProcessor
     */
    private $customer_processor;

    /**
     * @var int Batch size for processing orders
     */
    private $batch_size;

    /**
     * @var int Maximum execution time in seconds (default: 5 minutes)
     */
    private $max_execution_time = 300;

    /**
     * @var int Start time of the import process
     */
    private $import_start_time;

    /**
     * Constructor
     */
    public function __construct(WMSW_ShopifyClient $shopify_client, WMSW_Logger $logger = null)
    {
        $this->shopify_client = $shopify_client;
        $this->logger = $logger ?: new WMSW_Logger();

        // Get batch size from settings if available, otherwise from constant, or fall back to default
        $settings = \get_option('wmsw_settings', []);
        $this->batch_size = isset($settings['import_batch_size']) ? (int)$settings['import_batch_size'] : (defined('wmsw_BATCH_SIZE_CUSTOMERS') ? WMSW_BATCH_SIZE_CUSTOMERS : 50);
    }


    /**
     * Import orders from Shopify store
     *
     * @param WMSW_ShopifyStore $store
     * @param array $import_options
     * @param string $progress_key
     * @return array
     */
    public function import_orders($store, $import_options, $progress_key)
    {
        // Record start time for timeout checking
        $this->import_start_time = time();
        
        try {
            // Initialize progress
            $this->update_progress($progress_key, [
                'status' => 'fetching',
                'message' => __('Fetching orders from Shopify...', 'wp-migrate-shopify-woo'),
                'current' => 0,
                'total' => 0,
                'start_time' => $this->import_start_time
            ]);

            // Initialize Shopify client
            $client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Get total orders count first
            $total_orders = $this->get_total_orders_count($client, $import_options['order_status']);

            $this->update_progress($progress_key, [
                'total' => $total_orders,
                'message' => sprintf(
                    /* translators: %d: number of orders found */
                    __('Found %d orders. Starting import...', 'wp-migrate-shopify-woo'), 
                    $total_orders
                )
            ]);

            // If no orders found, mark as completed immediately
            if ($total_orders == 0) {
                $this->update_progress($progress_key, [
                    'status' => 'completed',
                    'current' => 0,
                    'message' => __('No orders found to import.', 'wp-migrate-shopify-woo'),
                    'imported_count' => 0,
                    'skipped_count' => 0,
                    'error_count' => 0,
                    'errors' => []
                ]);

                return [
                    'success' => true,
                    'imported_count' => 0,
                    'skipped_count' => 0,
                    'error_count' => 0,
                    'errors' => []
                ];
            }

            $imported_count = 0;
            $skipped_count = 0;
            $error_count = 0;
            $errors = [];

            $page_info = null;
            $limit = $this->batch_size; // Use configured batch size instead of hardcoded value
            $batch_number = 0;
            $max_batches = ceil($total_orders / $limit) + 1; // Safety limit to prevent infinite loops

            do {
                $batch_number++;

                // Safety check to prevent infinite loops
                if ($batch_number > $max_batches) {
                    $this->logger->warning('Maximum batch limit reached, stopping import to prevent infinite loop', [
                        'batch_number' => $batch_number,
                        'max_batches' => $max_batches,
                        'processed_orders' => $imported_count + $skipped_count + $error_count
                    ]);
                    break;
                }

                // Check execution time to prevent timeouts
                $elapsed_time = time() - $this->import_start_time;
                if ($elapsed_time > $this->max_execution_time) {
                    $this->logger->warning('Maximum execution time reached, stopping import', [
                        'elapsed_time' => $elapsed_time,
                        'max_execution_time' => $this->max_execution_time,
                        'batch_number' => $batch_number,
                        'processed_orders' => $imported_count + $skipped_count + $error_count
                    ]);
                    
                    $this->update_progress($progress_key, [
                        'status' => 'timeout',
                        'current' => $imported_count + $skipped_count + $error_count,
                        'message' => sprintf(
                            /* translators: 1: number of processed orders, 2: number of batches */
                            __('Import stopped due to timeout. Processed %1$d orders in %2$d batches.', 'wp-migrate-shopify-woo'),
                            $imported_count + $skipped_count + $error_count,
                            $batch_number - 1
                        ),
                        'imported_count' => $imported_count,
                        'skipped_count' => $skipped_count,
                        'error_count' => $error_count,
                        'errors' => $errors
                    ]);
                    break;
                }

                // Fetch orders batch using GraphQL
                $query_params = [
                    'limit' => $limit
                ];

                // Add order status filter if provided
                if (!empty($import_options['order_status'])) {
                    $query_params['order_status'] = $import_options['order_status'];
                }

                if ($page_info) {
                    $query_params['page_info'] = $page_info;
                }

                $response = $client->getOrders($query_params);

                // Check if we have valid response data
                if (!is_array($response) || !isset($response['orders'])) {
                    $this->logger->warning('Invalid response from Shopify API', [
                        'batch_number' => $batch_number,
                        'response' => $response
                    ]);
                    break;
                }

                if (empty($response['orders'])) {
                    $this->logger->info('No more orders found, ending import', [
                        'batch_number' => $batch_number,
                        'total_processed' => $imported_count + $skipped_count + $error_count
                    ]);
                    break;
                }

                $orders = $response['orders'];

                // Log batch processing start
                $import_id = $import_options['import_id'] ?? null;
                $this->logger->info('Processing order batch', [
                    'level' => 'info',
                    'message' => 'Processing order batch',
                    'import_id' => $import_id,
                    'batch_number' => $batch_number,
                    'batch_size' => count($orders),
                    'total_processed_so_far' => $imported_count + $skipped_count + $error_count
                ]);

                // Process each order
                foreach ($orders as $order) {
                    try {
                        $this->update_progress($progress_key, [
                            'current' => $imported_count + $skipped_count + $error_count + 1,
                            'message' => sprintf(
                                /* translators: %s: order name or ID */
                                __('Processing order %s...', 'wp-migrate-shopify-woo'),
                                $order['name'] ?? $order['order_number'] ?? $order['id']
                            )
                        ]);

                        $result = $this->process_single_order($order, $store, $import_options);

                        if ($result['status'] === 'imported') {
                            $imported_count++;
                        } elseif ($result['status'] === 'skipped') {
                            $skipped_count++;
                        } else {
                            $error_count++;
                            $errors[] = $result['message'];
                        }
                    } catch (\Exception $e) {
                        $error_count++;
                        $error_message = sprintf(
                            /* translators: 1: order name or ID, 2: error message */
                            __('Error processing order %1$s: %2$s', 'wp-migrate-shopify-woo'),
                            $order['name'] ?? $order['id'],
                            $e->getMessage()
                        );
                        $errors[] = $error_message;

                        // Enhanced error logging with structured context
                        $this->logger->error('Order processing failed', [
                            'level' => 'error',
                            'message' => 'Order processing failed',
                            'import_id' => $import_id,
                            'order_id' => $order['id'] ?? null,
                            'order_name' => $order['name'] ?? null,
                            'batch_number' => $batch_number,
                            'error' => $e->getMessage(),
                            'error_context' => [
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]
                        ]);
                    }
                }

                // Get next page info if available - handle both cursor and traditional pagination
                $page_info = null;
                if (isset($response['page_info'])) {
                    $page_info = $response['page_info'];
                } elseif (isset($response['pagination']['next_page_info'])) {
                    $page_info = $response['pagination']['next_page_info'];
                } elseif (isset($response['orders']) && count($response['orders']) < $limit) {
                    // If we got fewer orders than requested, we've reached the end
                    $page_info = null;
                }

                // Additional safety check - if we're not processing any orders, break
                if (empty($orders)) {
                    $this->logger->info('No orders in batch, ending import', [
                        'batch_number' => $batch_number
                    ]);
                    break;
                }

                // Log pagination status
                $this->logger->debug('Pagination status', [
                    'batch_number' => $batch_number,
                    'orders_in_batch' => count($orders),
                    'has_next_page' => !empty($page_info),
                    'page_info' => $page_info
                ]);

                // Clear memory after processing each batch
                unset($orders, $response);
                
                // Force garbage collection for large imports
                if ($batch_number % 10 === 0) {
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

            } while (!empty($page_info) && $batch_number < $max_batches);

            // Final progress update
            $this->update_progress($progress_key, [
                'status' => 'completed',
                'current' => $imported_count + $skipped_count + $error_count,
                'message' => sprintf(
                    /* translators: 1: number of imported orders, 2: number of skipped orders, 3: number of errors */
                    __('Import completed. Imported: %1$d, Skipped: %2$d, Errors: %3$d', 'wp-migrate-shopify-woo'),
                    $imported_count,
                    $skipped_count,
                    $error_count
                ),
                'imported_count' => $imported_count,
                'skipped_count' => $skipped_count,
                'error_count' => $error_count,
                'errors' => $errors
            ]);

            // Log final import summary
            $import_id = $import_options['import_id'] ?? null;
            $this->logger->info('Order import completed', [
                'level' => 'info',
                'message' => 'Order import completed',
                'import_id' => $import_id,
                'total_processed' => $imported_count + $skipped_count + $error_count,
                'imported_count' => $imported_count,
                'skipped_count' => $skipped_count,
                'error_count' => $error_count,
                'success_rate' => $total_orders > 0 ? round(($imported_count / $total_orders) * 100, 2) : 0
            ]);

            return [
                'success' => true,
                'imported_count' => $imported_count,
                'skipped_count' => $skipped_count,
                'error_count' => $error_count,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->update_progress($progress_key, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()],
                'imported_count' => $imported_count ?? 0,
                'skipped_count' => $skipped_count ?? 0,
                'error_count' => ($error_count ?? 0) + 1
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'imported_count' => $imported_count ?? 0,
                'skipped_count' => $skipped_count ?? 0,
                'error_count' => ($error_count ?? 0) + 1
            ];
        }
    }

    /**
     * Get total orders count from Shopify
     */
    private function get_total_orders_count($client, $order_status)
    {
        try {
            $query_params = [];

            // Add order status filter if provided
            if (!empty($order_status) && is_array($order_status)) {
                $query_params['order_status'] = $order_status;
            }

            // Use the client's countOrders method directly
            $count = $client->countOrders($query_params);
            return is_array($count) ? ($count['count'] ?? 0) : (int)$count;
        } catch (\Exception $e) {
            $this->logger->warning('Could not get orders count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process a single order with structured logging
     */
    private function process_single_order($order, $store, $import_options)
    {
        $import_id = $import_options['import_id'] ?? null;
        $order_name = $order['name'] ?? $order['id'];

        try {
            // Normalize order data to handle new GraphQL field names
            $order = $this->normalize_order_data($order);

            $shopify_order_id = $order['id'];

            // Check if order already exists
            $existing_order = $this->find_existing_order($shopify_order_id);
            if ($existing_order) {
                $this->logger->info('Order already exists, skipping', [
                    'level' => 'info',
                    'message' => 'Order already exists, skipping',
                    'import_id' => $import_id,
                    'order_id' => $shopify_order_id,
                    'order_name' => $order_name,
                    'existing_wc_order_id' => $existing_order->get_id()
                ]);

                return [
                    'status' => 'skipped',
                    'message' => sprintf(
                    /* translators: %s: order name or ID */
                    __('Order %s already exists', 'wp-migrate-shopify-woo'), 
                    $order['name'] ?? $order['id']
                )
                ];
            }

            // Process customer if needed
            $customer_id = 0;
            if (isset($order['customer']) && !empty($order['customer']) && $import_options['create_customers']) {
                $customer_id = $this->process_order_customer($order['customer'], $store);
            }

            // Create WooCommerce order
            $wc_order = $this->create_woocommerce_order($order, $customer_id, $store, $import_options);

            if (!$wc_order) {
                throw new \Exception(__('Failed to create WooCommerce order', 'wp-migrate-shopify-woo'));
            }

            // Import refunds if requested
            if ($import_options['import_refunds'] && !empty($order['refunds'])) {
                $this->import_order_refunds($wc_order, $order['refunds']);
            }

            // Log successful order import
            $this->logger->info('Order imported successfully', [
                'level' => 'info',
                'message' => 'Order imported successfully',
                'import_id' => $import_id,
                'shopify_order_id' => $shopify_order_id,
                'order_name' => $order_name,
                'wc_order_id' => $wc_order->get_id(),
                'customer_id' => $customer_id,
                'order_total' => $order['total_price'] ?? 0,
                'refunds_imported' => $import_options['import_refunds'] && !empty($order['refunds'])
            ]);

            return [
                'status' => 'imported',
                'order_id' => $wc_order->get_id(),
                'message' => sprintf(
                    /* translators: %s: order name or ID */
                    __('Order %s imported successfully', 'wp-migrate-shopify-woo'), 
                    $order['name'] ?? $order['id']
                )
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create WooCommerce order from Shopify order data
     */
    private function create_woocommerce_order($order, $customer_id, $store, $import_options)
    {
        try {
            // Create new WC_Order
            $wc_order = new \WC_Order();

            // Set customer
            if ($customer_id > 0) {
                $wc_order->set_customer_id($customer_id);
            }

            // Set basic order data
            $wc_order->set_status($this->map_order_status($order['financial_status'] ?? 'pending'));
            $wc_order->set_currency($order['currency'] ?? 'USD');
            
            // Handle date conversion with proper timezone support
            if (isset($order['createdAt'])) {
                $wc_order->set_date_created($this->convert_shopify_date($order['createdAt']));
            } elseif (isset($order['created_at'])) {
                $wc_order->set_date_created($this->convert_shopify_date($order['created_at']));
            }
            
            if (isset($order['updatedAt'])) {
                $wc_order->set_date_modified($this->convert_shopify_date($order['updatedAt']));
            } elseif (isset($order['updated_at'])) {
                $wc_order->set_date_modified($this->convert_shopify_date($order['updated_at']));
            }

            // Set billing address
            if (isset($order['billing_address'])) {
                $this->set_order_billing_address($wc_order, $order['billing_address']);
            } elseif (isset($order['customer']['default_address'])) {
                $this->set_order_billing_address($wc_order, $order['customer']['default_address']);
            }

            // Set shipping address
            if (isset($order['shipping_address'])) {
                $this->set_order_shipping_address($wc_order, $order['shipping_address']);
            } elseif (isset($order['billing_address'])) {
                $this->set_order_shipping_address($wc_order, $order['billing_address']);
            }

            // Set customer email for guest orders (this is critical for order completion)
            if ($customer_id == 0 && isset($order['email']) && !empty($order['email'])) {
                $wc_order->set_billing_email($order['email']);
            }

            // Set customer phone if available
            if (isset($order['phone']) && !empty($order['phone']) && !$wc_order->get_billing_phone()) {
                $wc_order->set_billing_phone($order['phone']);
            }

            // Add line items
            if (isset($order['line_items'])) {
                $this->add_order_line_items($wc_order, $order['line_items'], $store);
            }

            // Add shipping lines
            if (isset($order['shipping_lines']) && !empty($order['shipping_lines'])) {
                $this->add_order_shipping_lines($wc_order, $order['shipping_lines']);
            }

            // Add tax lines
            if (isset($order['tax_lines']) && !empty($order['tax_lines'])) {
                $this->add_order_tax_lines($wc_order, $order['tax_lines']);
            }

            // Set totals
            $wc_order->set_total($order['total_price'] ?? 0);

            // Save the order
            $order_id = $wc_order->save();

            // Add order meta
            update_post_meta($order_id, '_shopify_order_id', $order['id']);
            update_post_meta($order_id, '_shopify_order_number', $order['order_number'] ?? '');
            update_post_meta($order_id, '_shopify_store_id', $store->get_id());

            if (isset($order['tags'])) {
                update_post_meta($order_id, '_shopify_tags', $order['tags']);
            }

            if (isset($order['order_status_url'])) {
                update_post_meta($order_id, '_shopify_order_status_url', $order['order_status_url']);
            }

            // Add order notes
            if ($import_options['import_notes'] && !empty($order['note'])) {
                $wc_order->add_order_note($order['note'], 0, false);
            }

            // Log successful import
            $this->logger->info('Order imported successfully', [
                'shopify_order_id' => $order['id'],
                'wc_order_id' => $order_id,
                'order_number' => $order['order_number'] ?? $order['name'] ?? '',
                'store_id' => $store->get_id()
            ]);

            return $wc_order;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create WooCommerce order: ' . $e->getMessage(), [
                'shopify_order_id' => $order['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find existing order by Shopify ID
     */
    private function find_existing_order($shopify_order_id)
    {
        global $wpdb;

        // Try to find by the provided ID format first
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_shopify_order_id' AND meta_value = %s",
            $shopify_order_id
        ));

        // If not found and this is a numeric ID, also try the GraphQL format
        if (!$order_id && is_numeric($shopify_order_id)) {
            $graphql_id = "gid://shopify/Order/" . $shopify_order_id;
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_shopify_order_id' AND meta_value = %s",
                $graphql_id
            ));
        }

        // If not found and this is a GraphQL ID, also try the numeric format
        if (!$order_id && strpos($shopify_order_id, 'gid://shopify/Order/') === 0) {
            $numeric_id = str_replace('gid://shopify/Order/', '', $shopify_order_id);
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_shopify_order_id' AND meta_value = %s",
                $numeric_id
            ));
        }

        return $order_id ? wc_get_order($order_id) : false;
    }

    /**
     * Map Shopify order status to WooCommerce status
     */
    private function map_order_status($shopify_status)
    {
        // Convert to lowercase for consistent mapping
        $shopify_status = strtolower($shopify_status);
        
        $status_map = [
            'pending' => 'pending',
            'paid' => 'processing',
            'partially_paid' => 'on-hold',
            'refunded' => 'refunded',
            'partially_refunded' => 'processing',
            'voided' => 'cancelled',
            'authorized' => 'on-hold',
            'cancelled' => 'cancelled',
            'unfulfilled' => 'processing',
            'partial' => 'processing',
            'fulfilled' => 'completed'
        ];

        return $status_map[$shopify_status] ?? 'pending';
    }

    /**
     * Set billing address for order
     */
    private function set_order_billing_address($wc_order, $address)
    {
        $wc_order->set_billing_first_name($address['first_name'] ?? '');
        $wc_order->set_billing_last_name($address['last_name'] ?? '');
        $wc_order->set_billing_company($address['company'] ?? '');
        $wc_order->set_billing_address_1($address['address1'] ?? '');
        $wc_order->set_billing_address_2($address['address2'] ?? '');
        $wc_order->set_billing_city($address['city'] ?? '');
        $wc_order->set_billing_state($address['province'] ?? '');
        $wc_order->set_billing_postcode($address['zip'] ?? '');
        $wc_order->set_billing_country($address['country_code'] ?? '');
        $wc_order->set_billing_phone($address['phone'] ?? '');
        
        // Set billing email if available in the address data
        if (!empty($address['email'])) {
            $wc_order->set_billing_email($address['email']);
        }
    }

    /**
     * Set shipping address for order
     */
    private function set_order_shipping_address($wc_order, $address)
    {
        $wc_order->set_shipping_first_name($address['first_name'] ?? '');
        $wc_order->set_shipping_last_name($address['last_name'] ?? '');
        $wc_order->set_shipping_company($address['company'] ?? '');
        $wc_order->set_shipping_address_1($address['address1'] ?? '');
        $wc_order->set_shipping_address_2($address['address2'] ?? '');
        $wc_order->set_shipping_city($address['city'] ?? '');
        $wc_order->set_shipping_state($address['province'] ?? '');
        $wc_order->set_shipping_postcode($address['zip'] ?? '');
        $wc_order->set_shipping_country($address['country_code'] ?? '');
    }

    /**
     * Add line items to order
     */
    private function add_order_line_items($wc_order, $line_items, $store)
    {
        foreach ($line_items as $line_item) {
            try {
                // Try to find the product by Shopify product ID or variant ID
                $product = $this->find_product_by_shopify_id($line_item['product_id'], $line_item['variant_id'] ?? null);

                $item = new \WC_Order_Item_Product();

                if ($product) {
                    $item->set_product($product);
                    $item->set_product_id($product->get_id());
                    if ($product->is_type('variation')) {
                        $item->set_variation_id($product->get_id());
                    }
                    
                    // Set the product name from WooCommerce product
                    $item->set_name($product->get_name());
                } else {
                    // Product not found locally, create a simple line item with basic info
                    $product_title = $line_item['title'] ?? __('Product not found', 'wp-migrate-shopify-woo');
                    $item->set_name($product_title);
                }

                $item->set_quantity($line_item['quantity'] ?? 1);
                $item->set_subtotal($line_item['price'] ?? 0);
                $item->set_total($line_item['price'] ?? 0);

                // Add essential Shopify metadata for reference
                $item->add_meta_data('_shopify_product_id', $line_item['product_id'], true);
                if (isset($line_item['variant_id'])) {
                    $item->add_meta_data('_shopify_variant_id', $line_item['variant_id'], true);
                }
                if (isset($line_item['sku']) && !empty($line_item['sku'])) {
                    $item->add_meta_data('_shopify_sku', $line_item['sku'], true);
                }

                $wc_order->add_item($item);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to add line item to order: ' . $e->getMessage(), [
                    'line_item' => $line_item,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Add shipping lines to order
     */
    private function add_order_shipping_lines($wc_order, $shipping_lines)
    {
        foreach ($shipping_lines as $shipping_line) {
            $item = new \WC_Order_Item_Shipping();
            $item->set_method_title($shipping_line['title'] ?? __('Shipping', 'wp-migrate-shopify-woo'));
            $item->set_method_id('shopify_import');
            $item->set_total($shipping_line['price'] ?? 0);

            $wc_order->add_item($item);
        }
    }

    /**
     * Add tax lines to order
     */
    private function add_order_tax_lines($wc_order, $tax_lines)
    {
        foreach ($tax_lines as $tax_line) {
            $item = new \WC_Order_Item_Tax();
            $item->set_name($tax_line['title'] ?? __('Tax', 'wp-migrate-shopify-woo'));
            $item->set_tax_total($tax_line['price'] ?? 0);
            $item->set_rate_id(0); // We don't have WC tax rate ID

            $wc_order->add_item($item);
        }
    }

    /**
     * Find WooCommerce product by Shopify product/variant ID
     */
    private function find_product_by_shopify_id($product_id, $variant_id = null)
    {
        global $wpdb;

        // First try to find by variant ID if available
        if ($variant_id) {
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_shopify_variant_id' AND meta_value = %s",
                $variant_id
            ));

            if ($post_id) {
                return wc_get_product($post_id);
            }
        }

        // Try to find by product ID
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_shopify_product_id' AND meta_value = %s",
            $product_id
        ));

        return $post_id ? wc_get_product($post_id) : false;
    }

    /**
     * Import order refunds
     */
    private function import_order_refunds($wc_order, $refunds)
    {
        foreach ($refunds as $refund) {
            try {
                $refund_data = [
                    'amount' => $refund['total_amount'] ?? 0,
                    'reason' => $refund['note'] ?? __('Shopify refund', 'wp-migrate-shopify-woo'),
                    'order_id' => $wc_order->get_id(),
                    'line_items' => [],
                    'refund_payment' => false, // Don't actually process payment
                    'restock_items' => false
                ];

                $wc_refund = wc_create_refund($refund_data);

                if ($wc_refund && !is_wp_error($wc_refund)) {
                    // Add Shopify refund metadata
                    update_post_meta($wc_refund->get_id(), '_shopify_refund_id', $refund['id']);
                    update_post_meta($wc_refund->get_id(), '_shopify_refund_created_at', $refund['created_at']);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to import refund: ' . $e->getMessage(), [
                    'refund' => $refund,
                    'order_id' => $wc_order->get_id()
                ]);
            }
        }
    }

    /**
     * Update import progress with structured logging
     */
    private function update_progress($progress_key, $data)
    {
        $current_progress = get_transient($progress_key) ?: [];
        $updated_progress = array_merge($current_progress, $data);
        set_transient($progress_key, $updated_progress, 3600);

        // Get import_id from transient for structured logging
        $import_id = $updated_progress['import_id'] ?? null;

        // Add structured logging for significant progress updates
        if (isset($data['status']) || isset($data['current']) || isset($data['total'])) {
            $log_context = [
                'level' => 'info',
                'message' => 'Order import progress updated',
                'import_id' => $import_id
            ];

            // Add relevant progress data to context
            if (isset($data['status'])) {
                $log_context['status'] = $data['status'];
                $log_context['message'] = "Order import status: {$data['status']}";
            }
            if (isset($updated_progress['current'])) {
                $log_context['items_processed'] = $updated_progress['current'];
            }
            if (isset($updated_progress['total'])) {
                $log_context['items_total'] = $updated_progress['total'];
            }
            if (isset($data['message'])) {
                $log_context['progress_message'] = $data['message'];
            }

            $this->logger->info($log_context['message'], $log_context);
        }

        // Log errors specifically
        if (isset($data['errors']) && !empty($data['errors'])) {
            $this->logger->error('Order import errors occurred', [
                'level' => 'error',
                'message' => 'Order import errors occurred',
                'import_id' => $import_id,
                'errors' => $data['errors']
            ]);
        }
    }

    /**
     * Process customer for order
     * Simple method to handle customer creation/lookup for orders
     */
    private function process_order_customer($customer, $store)
    {
        try {
            // Check if customer already exists by email
            if (isset($customer['email']) && !empty($customer['email'])) {
                $existing_user = get_user_by('email', $customer['email']);
                if ($existing_user) {
                    return $existing_user->ID;
                }
            }

            // Create new customer if email is provided
            if (isset($customer['email']) && !empty($customer['email'])) {
                $customer_data = [
                    'user_login' => $customer['email'],
                    'user_email' => $customer['email'],
                    'user_pass' => wp_generate_password(12, false),
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name' => $customer['last_name'] ?? '',
                    'role' => 'customer'
                ];

                $customer_id = wp_insert_user($customer_data);

                if (!is_wp_error($customer_id)) {
                    // Add Shopify metadata
                    update_user_meta($customer_id, '_shopify_customer_id', $customer['id'] ?? '');
                    update_user_meta($customer_id, '_shopify_store_id', $store->get_id());

                    return $customer_id;
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to process customer for order: ' . $e->getMessage(), [
                'customer_email' => $customer['email'] ?? 'no email',
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Transform GraphQL order data to expected format for processing
     * This ensures compatibility between old and new GraphQL field names
     */
    private function normalize_order_data($order)
    {
        // Extract numeric ID from GraphQL global ID (e.g., "gid://shopify/Order/6181729108192" -> "6181729108192")
        if (isset($order['id']) && strpos($order['id'], 'gid://shopify/Order/') === 0) {
            $order['id'] = str_replace('gid://shopify/Order/', '', $order['id']);
        }

        // Handle order number (Shopify's 'name' field contains the order number like "#1001")
        if (isset($order['name']) && !isset($order['order_number'])) {
            $order['order_number'] = $order['name'];
        }

        // Handle financial status
        if (isset($order['displayFinancialStatus']) && !isset($order['financial_status'])) {
            $order['financial_status'] = strtolower($order['displayFinancialStatus']);
        }

        // Handle fulfillment status
        if (isset($order['displayFulfillmentStatus']) && !isset($order['fulfillment_status'])) {
            $order['fulfillment_status'] = strtolower($order['displayFulfillmentStatus']);
        }

        // Handle currency from the new structure
        if (isset($order['currencyCode']) && !isset($order['currency'])) {
            $order['currency'] = $order['currencyCode'];
        }

        // Handle total price from new structure
        if (isset($order['currentTotalPriceSet']['shopMoney']) && !isset($order['total_price'])) {
            $order['total_price'] = $order['currentTotalPriceSet']['shopMoney']['amount'];
            if (!isset($order['currency'])) {
                $order['currency'] = $order['currentTotalPriceSet']['shopMoney']['currencyCode'];
            }
        }

        // Handle customer data normalization
        if (isset($order['customer'])) {
            $customer = $order['customer'];
            
            // Extract customer ID from GraphQL global ID
            if (isset($customer['id']) && strpos($customer['id'], 'gid://shopify/Customer/') === 0) {
                $order['customer']['id'] = str_replace('gid://shopify/Customer/', '', $customer['id']);
            }

            // Normalize customer name fields
            if (isset($customer['firstName'])) {
                $order['customer']['first_name'] = $customer['firstName'];
            }
            if (isset($customer['lastName'])) {
                $order['customer']['last_name'] = $customer['lastName'];
            }

            // Handle default address
            if (isset($customer['defaultAddress'])) {
                $address = $customer['defaultAddress'];
                $order['customer']['default_address'] = [
                    'first_name' => $address['firstName'] ?? '',
                    'last_name' => $address['lastName'] ?? '',
                    'company' => $address['company'] ?? '',
                    'address1' => $address['address1'] ?? '',
                    'address2' => $address['address2'] ?? '',
                    'city' => $address['city'] ?? '',
                    'province' => $address['province'] ?? '',
                    'country' => $address['country'] ?? '',
                    'zip' => $address['zip'] ?? '',
                    'phone' => $address['phone'] ?? '',
                    'country_code' => $address['countryCodeV2'] ?? ''
                ];
            }
        }

        // Handle billing address
        if (isset($order['billingAddress'])) {
            $billing = $order['billingAddress'];
            $order['billing_address'] = [
                'first_name' => $billing['firstName'] ?? '',
                'last_name' => $billing['lastName'] ?? '',
                'company' => $billing['company'] ?? '',
                'address1' => $billing['address1'] ?? '',
                'address2' => $billing['address2'] ?? '',
                'city' => $billing['city'] ?? '',
                'province' => $billing['province'] ?? '',
                'country' => $billing['country'] ?? '',
                'zip' => $billing['zip'] ?? '',
                'phone' => $billing['phone'] ?? '',
                'country_code' => $billing['countryCodeV2'] ?? ''
            ];
        }

        // Handle shipping address
        if (isset($order['shippingAddress'])) {
            $shipping = $order['shippingAddress'];
            $order['shipping_address'] = [
                'first_name' => $shipping['firstName'] ?? '',
                'last_name' => $shipping['lastName'] ?? '',
                'company' => $shipping['company'] ?? '',
                'address1' => $shipping['address1'] ?? '',
                'address2' => $shipping['address2'] ?? '',
                'city' => $shipping['city'] ?? '',
                'province' => $shipping['province'] ?? '',
                'country' => $shipping['country'] ?? '',
                'zip' => $shipping['zip'] ?? '',
                'phone' => $shipping['phone'] ?? '',
                'country_code' => $shipping['countryCodeV2'] ?? ''
            ];
        }

        // Handle line items pricing from new structure
        if (isset($order['lineItems']['nodes'])) {
            $order['line_items'] = [];

            foreach ($order['lineItems']['nodes'] as $line_item) {
                $normalized_item = [
                    'id' => $line_item['id'] ?? '',
                    'title' => $line_item['title'] ?? '',
                    'quantity' => $line_item['quantity'] ?? 1,
                    'price' => 0,
                    'product_id' => null,
                    'variant_id' => null
                ];

                // Handle pricing - prefer discounted price if available
                if (isset($line_item['discountedUnitPriceSet']['shopMoney']['amount'])) {
                    $normalized_item['price'] = $line_item['discountedUnitPriceSet']['shopMoney']['amount'];
                } elseif (isset($line_item['originalUnitPriceSet']['shopMoney']['amount'])) {
                    $normalized_item['price'] = $line_item['originalUnitPriceSet']['shopMoney']['amount'];
                }

                // Extract product ID from variant->product data (new simplified structure)
                if (isset($line_item['variant']['product']['id']) && strpos($line_item['variant']['product']['id'], 'gid://shopify/Product/') === 0) {
                    $normalized_item['product_id'] = str_replace('gid://shopify/Product/', '', $line_item['variant']['product']['id']);
                }
                // Fallback to direct product ID if available (legacy structure)
                elseif (isset($line_item['product']['id']) && strpos($line_item['product']['id'], 'gid://shopify/Product/') === 0) {
                    $normalized_item['product_id'] = str_replace('gid://shopify/Product/', '', $line_item['product']['id']);
                }

                // Extract variant ID from variant data
                if (isset($line_item['variant']['id']) && strpos($line_item['variant']['id'], 'gid://shopify/ProductVariant/') === 0) {
                    $normalized_item['variant_id'] = str_replace('gid://shopify/ProductVariant/', '', $line_item['variant']['id']);
                }

                // Add additional variant info if available
                if (isset($line_item['variant']['sku'])) {
                    $normalized_item['sku'] = $line_item['variant']['sku'];
                }

                $order['line_items'][] = $normalized_item;
            }
        }

        // Handle shipping lines
        if (isset($order['shippingLines']['nodes'])) {
            $order['shipping_lines'] = [];

            foreach ($order['shippingLines']['nodes'] as $shipping_line) {
                $normalized_shipping = [
                    'id' => $shipping_line['id'] ?? '',
                    'title' => $shipping_line['title'] ?? 'Shipping',
                    'price' => 0
                ];

                // Handle pricing - prefer discounted price if available
                if (isset($shipping_line['discountedPriceSet']['shopMoney']['amount'])) {
                    $normalized_shipping['price'] = $shipping_line['discountedPriceSet']['shopMoney']['amount'];
                } elseif (isset($shipping_line['originalPriceSet']['shopMoney']['amount'])) {
                    $normalized_shipping['price'] = $shipping_line['originalPriceSet']['shopMoney']['amount'];
                }

                $order['shipping_lines'][] = $normalized_shipping;
            }
        }

        // Handle tax lines
        if (isset($order['taxLines'])) {
            $order['tax_lines'] = [];

            foreach ($order['taxLines'] as $tax_line) {
                $normalized_tax = [
                    'title' => $tax_line['title'] ?? 'Tax',
                    'price' => 0,
                    'rate' => $tax_line['rate'] ?? 0,
                    'rate_percentage' => $tax_line['ratePercentage'] ?? 0
                ];

                // Handle pricing
                if (isset($tax_line['priceSet']['shopMoney']['amount'])) {
                    $normalized_tax['price'] = $tax_line['priceSet']['shopMoney']['amount'];
                }

                $order['tax_lines'][] = $normalized_tax;
            }
        }

        // Handle discount applications
        if (isset($order['discountApplications']['nodes'])) {
            $order['discount_applications'] = [];

            foreach ($order['discountApplications']['nodes'] as $discount) {
                $normalized_discount = [
                    'title' => $discount['title'] ?? $discount['code'] ?? 'Discount',
                    'value' => 0,
                    'value_type' => 'fixed_amount'
                ];

                // Handle discount value
                if (isset($discount['value'])) {
                    if (isset($discount['value']['amount'])) {
                        $normalized_discount['value'] = $discount['value']['amount'];
                        $normalized_discount['value_type'] = 'fixed_amount';
                    } elseif (isset($discount['value']['percentage'])) {
                        $normalized_discount['value'] = $discount['value']['percentage'];
                        $normalized_discount['value_type'] = 'percentage';
                    }
                }

                $order['discount_applications'][] = $normalized_discount;
            }
        }

        return $order;
    }

    /**
     * Convert Shopify date format to WooCommerce-compatible date
     *
     * @param string $shopify_date
     * @return \WC_DateTime|null
     */
    private function convert_shopify_date($shopify_date)
    {
        if (empty($shopify_date)) {
            return null;
        }

        try {
            // Shopify dates are typically in ISO 8601 format (UTC)
            // e.g., "2024-12-19T10:30:45-05:00" or "2024-12-19T15:30:45Z"
            
            // Create DateTime object from Shopify date string
            $datetime = new \DateTime($shopify_date);
            
            // Convert to WordPress timezone
            $wp_timezone = wp_timezone();
            $datetime->setTimezone($wp_timezone);
            
            // Return as WC_DateTime object
            return new \WC_DateTime($datetime->format('Y-m-d H:i:s'), $wp_timezone);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to convert Shopify date: ' . $e->getMessage(), [
                'shopify_date' => $shopify_date,
                'error' => $e->getMessage()
            ]);
            
            // Return current date as fallback
            return new \WC_DateTime();
        }
    }
}
