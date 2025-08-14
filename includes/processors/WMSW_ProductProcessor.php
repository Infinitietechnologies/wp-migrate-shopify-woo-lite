
<?php


namespace ShopifyWooImporter\Processors;
// Import WordPress functions used in this file (after namespace)
use function get_posts;
use function update_post_meta;
use function get_term_by;
use function wp_insert_term;
use function delete_transient;
use function is_wp_error;
use function download_url;
use function sanitize_file_name;
use function wp_parse_url;
use function esc_url_raw;
use function wp_delete_file;
use function media_handle_sideload;
use function set_post_thumbnail;
use function get_option;
use function get_transient;
use function set_transient;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Helpers\WMSW_PaginationHelper;
use ShopifyWooImporter\Helpers\WMSW_DatabaseHelper;
use ShopifyWooImporter\Services\WMSW_Logger;



/**
 * Product Processor
 * 
 * Note: For optimal performance, consider adding the following database index:
 * ALTER TABLE wp_postmeta ADD INDEX idx_wmsw_original_image_url (meta_key, meta_value(191));
 * This will significantly improve the performance of get_existing_attachment_id() method.
 */
class WMSW_ProductProcessor
{

    private $shopify_client;
    private $logger;
    private $batch_size;

    public function __construct(WMSW_ShopifyClient $shopify_client, WMSW_Logger $logger = null)
    {
        $this->shopify_client = $shopify_client;
        $this->logger = $logger ?: new WMSW_Logger();
        // Get batch size from settings, fallback to constant
    $settings = get_option('wmsw_options', []);
        $this->batch_size = isset($settings['import_batch_size']) ? (int)$settings['import_batch_size'] : WMSW_BATCH_SIZE_PRODUCTS;
    }
    /**
     * Import products from Shopify
     */
    public function import_products($options = [])
    {
        $this->logger->info('Starting product import batch', [
            'level' => 'info',
            'message' => 'Starting product import batch',
            'options' => $options
        ]);

        // Set default options
        $options = $this->set_default_options($options);

        // Use a unique tab key for products
        $tab = 'products';
        $cursor = WMSW_PaginationHelper::getCursor($tab);
        if (!empty($cursor)) {
            $options['after'] = $cursor;
            $this->logger->debug('Resuming product import from cursor: ' . $cursor);
        }

        // Safety check: Track batch processing to prevent infinite loops
        $batch_count_key = 'wmsw_product_batch_count_' . $tab;
    $batch_count = get_transient($batch_count_key) ?: 0;
        $max_batches = 1000; // Maximum number of batches to prevent infinite loops

        if ($batch_count >= $max_batches) {
            $this->logger->error('Maximum batch limit reached, stopping import to prevent infinite loop', [
                'batch_count' => $batch_count,
                'max_batches' => $max_batches
            ]);

            // Clean up cursor and batch count
            $this->cleanupBatchTracking($tab);

            return [
                'success' => false,
                'message' => 'Maximum batch limit reached, import stopped',
                'imported' => 0,
                'updated' => 0,
                'failed' => 0
            ];
        }

        // Increment batch count
    set_transient($batch_count_key, $batch_count + 1, 3600); // 1 hour expiry

        // Get one page of products from Shopify
        $this->logger->debug('Fetching products from Shopify API');
        $result = $this->get_shopify_products($options);
        $products = $result['products'];
        $pageInfo = $result['pageInfo'];

        if (empty($products)) {
            $this->logger->error('No products found to import');
            // Clean up cursor and batch count if nothing found
            $this->cleanupBatchTracking($tab);
            return [
                'success' => false,
                'message' => 'No products found to import',
                'imported' => 0,
                'updated' => 0,
                'failed' => 0
            ];
        }

        // Update the total items count in the import_logs table
        $this->update_total_items_count(count($products));

        $this->logger->info('Found ' . count($products) . ' products to process');

        // Process products
        $results = $this->process_products($products, $options);

        // Handle pagination cursor with safety checks
        $has_next_page = false;
        if (!empty($pageInfo['hasNextPage']) && !empty($pageInfo['endCursor'])) {
            // Additional safety check: ensure cursor is different from current one
            $current_cursor = $cursor;
            $next_cursor = $pageInfo['endCursor'];

            if ($current_cursor !== $next_cursor) {
                WMSW_PaginationHelper::setCursor($tab, $next_cursor);
                $has_next_page = true;
                $results['next_cursor'] = $next_cursor;
                $this->logger->debug('Pagination cursor updated from ' . $current_cursor . ' to ' . $next_cursor);
            } else {
                $this->logger->warning('Received same cursor as previous batch, ending pagination to prevent infinite loop', [
                    'current_cursor' => $current_cursor,
                    'next_cursor' => $next_cursor
                ]);
                $this->cleanupBatchTracking($tab);
                $has_next_page = false;
            }
        } else {
            $this->cleanupBatchTracking($tab);
            $has_next_page = false;
            $this->logger->debug('No more pages to process, pagination completed');
        }

        $results['has_next_page'] = $has_next_page;

        // Log a summary entry for the batch (info level, with context)
        $import_id = $this->get_current_import_id();
        $this->logger->info('Product import batch complete', [
            'level' => 'info',
            'message' => 'Product import batch complete',
            'import_id' => $import_id,
            'imported' => $results['imported'],
            'updated' => $results['updated'],
            'failed' => $results['failed'],
            'has_next_page' => $results['has_next_page'] ?? false,
            'next_cursor' => $results['next_cursor'] ?? null,
            'batch_count' => $batch_count + 1
        ]);

        return $results;
    }

    /**
     * Set default import options
     */
    private function set_default_options($options)
    {
        $defaults = [
            'import_drafts' => false,
            'overwrite_existing' => true,
            'download_images' => true,
            'set_featured_image' => true,
            'preserve_stock' => true,
            'import_collections' => true,
            'update_categories' => true,
            // Product-specific settings with defaults
            'import_variants' => true,
            'import_videos' => false,
            'import_descriptions' => false,
            'import_seo' => false,
            'import_tags' => false,
            'import_vendor_as_brand' => false,
            'preserve_ids' => false,
            'import_metafields' => false,
            'create_product_lookup_table' => false,
            'skip_no_inventory' => false,
            'sync_inventory' => false,
            'processing_threads' => 2,
            'error_handling' => 'continue',
            'import_type' => 'all'
        ];

        return array_merge($defaults, $options);
    }

    /**
     * Retrieve products from Shopify
     */
    private function get_shopify_products($options)
    {
        try {
            $query_params = [
                'limit' => $this->batch_size
            ];

            // Check both import_drafts and includeDrafts options for backward compatibility
            $include_drafts = !empty($options['import_drafts']) || !empty($options['includeDrafts']);

            $status = null;
            $status_keys = ['product_status', 'status', 'status'];

            foreach ($status_keys as $key) {
                if (isset($options[$key]) && !empty($options[$key])) {
                    $status = $options[$key];
                    $this->logger->debug("Found product status '{$status}' in options[{$key}]");
                    break;
                }
            }
            // Apply the appropriate status parameter based on the status for GraphQL queries
            switch ($status) {
                case 'draft':
                    $query_params['query'] = 'status:DRAFT';
                    $this->logger->debug('Applied query=status:DRAFT filter for draft products');
                    break;
                case 'active':
                    $query_params['query'] = 'status:ACTIVE';
                    $this->logger->debug('Applied query=status:ACTIVE filter for active products');
                    break;
                case 'archived':
                    $query_params['query'] = 'status:ARCHIVED';
                    $this->logger->debug('Applied query=status:ARCHIVED filter for archived products');
                    break;
                default:
                    if ($status === null) {
                        $query_params['query'] = 'status:ACTIVE';
                        $this->logger->debug('Defaulted to query=status:ACTIVE filter');
                    } else {
                        $this->logger->debug("Using provided status: {$status}");
                        $query_params['query'] = 'status:' . strtoupper($status);
                    }
                    break;
            }

            // Add after cursor if present
            if (!empty($options['after'])) {
                $query_params['after'] = $options['after'];
            }

            // Log the API query parameters
            $this->logger->debug('Shopify API query parameters: ' . json_encode($query_params));

            // Get one page of products
            $response = $this->shopify_client->get_single_page('products', $query_params, $this->batch_size);

            if (isset($response['errors'])) {
                $this->logger->error('Error retrieving products: ' . $response['errors']);
                return ['products' => [], 'pageInfo' => []];
            }

            if (!isset($response['products']) || !is_array($response['products'])) {
                $this->logger->error('Invalid product response from Shopify API');
                return ['products' => [], 'pageInfo' => []];
            }

            $products = $response['products'];
            $pageInfo = isset($response['pageInfo']) ? $response['pageInfo'] : [];
            $this->logger->debug('Retrieved ' . count($products) . ' products from Shopify API');
            return [
                'products' => $products,
                'pageInfo' => $pageInfo
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception retrieving products: ' . $e->getMessage());
            return ['products' => [], 'pageInfo' => []];
        }
    }

    /**
     * Process products
     */
    private function process_products($products, $options)
    {
        $results = [
            'success' => true,
            'imported' => 0,
            'updated' => 0,
            'failed' => 0
        ];

        $this->logger->debug('Starting to process ' . count($products) . ' products with options: ' . json_encode($options));

        foreach ($products as $product) {
            try {
                $this->logger->debug('Processing product: ' . $product['id'] . ' - ' . $product['title']);

                // Check if product already exists
                $wc_product_id = $this->get_existing_product_id($product['id']);

                if ($wc_product_id) {
                    $this->logger->debug('Product already exists, WooCommerce ID: ' . $wc_product_id);

                    // Update existing product
                    if ($options['overwrite_existing']) {
                        $this->logger->debug('Updating existing product');
                        $result = $this->update_product($wc_product_id, $product, $options);

                        if ($result) {
                            $results['updated']++;
                            $this->logger->debug('Product updated successfully');
                        } else {
                            $results['failed']++;
                            $this->logger->error('Failed to update product');
                        }
                    } else {
                        $this->logger->debug('Skipping product update (overwrite_existing=false)');
                    }
                } else {
                    // Create new product
                    $this->logger->debug('Creating new product');
                    $wc_product_id = $this->create_product($product, $options);

                    if ($wc_product_id) {
                        $results['imported']++;
                        $this->logger->debug('Product created successfully, WooCommerce ID: ' . $wc_product_id);

                        // Map Shopify ID to WooCommerce ID
                        $this->map_product_id($product['id'], $wc_product_id);
                        $this->logger->debug('Product ID mapping created');
                    } else {
                        $results['failed']++;
                        $this->logger->error('Failed to create product');
                    }
                }                // Update the progress counter after each product
                // Process log to track what happened with each product
                $this->logger->debug("Processing product: " . ($product['title'] ?? 'Unknown') .
                    " (Shopify ID: " . ($product['id'] ?? 'Unknown') . ")");
                $this->logger->debug("Results so far: " .
                    "imported={$results['imported']}, " .
                    "updated={$results['updated']}, " .
                    "failed={$results['failed']}");

                // Get current progress before updating
                $current_progress = $this->get_current_progress();
                $new_progress_count = $current_progress + 1; // Always increment by 1 for each product processed
                $this->logger->debug("Incrementing progress from {$current_progress} to {$new_progress_count}");

                // Update progress with the new count
                $this->update_import_progress($new_progress_count);
            } catch (\Exception $e) {
                $this->logger->error('Error processing product ' . $product['title'] . ': ' . $e->getMessage());
                $results['failed']++;
            }
        }

        return $results;
    }
    /**
     * Update the import progress
     * 
     * @param int $processed_count Number of processed items
     * @param int|null $import_id Import ID (optional, will try to detect if not provided)
     */
    private function update_import_progress($processed_count, $import_id = null)
    {
        $this->logger->debug('Begin updating import progress: ' . $processed_count . ' processed');

        // Use provided import_id or try to get it
        if (!$import_id) {
            $import_id = $this->get_current_import_id();
        }

        if ($import_id) {
            // Get the current status to ensure we're updating the right record
            $current_obj = WMSW_DatabaseHelper::get_import_log($import_id);

            $current = null;
            if ($current_obj) {
                $current = [
                    'id' => $current_obj->id,
                    'items_total' => $current_obj->items_total,
                    'items_processed' => $current_obj->items_processed,
                    'status' => $current_obj->status
                ];
            }
            if ($current) {
                $this->logger->debug("Current import status: items_total={$current['items_total']}, items_processed={$current['items_processed']}, status={$current['status']}");

                // Only update if the import is still in progress
                if ($current['status'] !== 'in_progress') {
                    $this->logger->debug("Skipping progress update as import status is '{$current['status']}', not 'in_progress'");
                    return;
                }

                // Store the import_id globally for future use
                $GLOBALS['wmsw_current_import_id'] = $import_id;

                // Make sure we have valid values
                if ($processed_count <= 0) {
                    // If processed_count is being reset to 0, use it directly
                    $new_processed_count = $processed_count;
                    $this->logger->debug("Resetting processed count to {$new_processed_count}");
                } else {
                    // Otherwise, make sure we're incrementing correctly
                    $new_processed_count = $processed_count;
                    $this->logger->debug("Setting processed count to {$new_processed_count}");
                }

                $result = WMSW_DatabaseHelper::update_import_log($import_id, [
                    'items_processed' => $new_processed_count,
                    'updated_at' => gmdate('Y-m-d H:i:s')
                ]);

                // Log the progress update using logger abstraction
                if ($result !== false) {
                    $this->logger->info('Import progress updated', [
                        'level' => 'info',
                        'message' => 'Import progress updated',
                        'import_id' => $import_id,
                        'items_processed' => $new_processed_count,
                        'items_total' => $current['items_total']
                    ]);
                    $this->logger->debug('Updated import progress for import ID: ' . $import_id . ' to ' . $new_processed_count);
                } else {
                    $this->logger->error('Failed to update import progress', [
                        'level' => 'error',
                        'message' => 'Failed to update import progress',
                        'import_id' => $import_id,
                        'items_processed' => $new_processed_count
                    ]);
                }
            } else {
                $this->logger->error("Import record not found in database: import_id={$import_id}");
            }
        } else {
            $this->logger->error('Could not update import progress - no import ID found');
        }
    }
    /**
     * Get the current import ID from global state
     *
     * @return int|null The import ID or null if not found
     */
    private function get_current_import_id()
    {
        global $WMSW_current_import_id;

        if (isset($WMSW_current_import_id) && $WMSW_current_import_id > 0) {
            $this->logger->debug('Found import ID in global variable: ' . $WMSW_current_import_id);
            return $WMSW_current_import_id;
        }

        // As a fallback, try to get the latest import from the database
        $latest_import = WMSW_DatabaseHelper::get_latest_in_progress_import_id();

        if ($latest_import) {
            $this->logger->debug('Found latest in-progress import ID from database: ' . $latest_import);

            // Store for future use
            $GLOBALS['wmsw_current_import_id'] = $latest_import;
            return $latest_import;
        }

        $this->logger->error('Could not find import ID in globals, backtrace, or database');
        return null;
    }

    /**
     * Get the current progress count from the database
     * 
     * @param int|null $import_id Import ID (optional)
     * @return int Current progress count
     */
    private function get_current_progress($import_id = null)
    {
        // Use provided import_id or try to get it
        if (!$import_id) {
            $import_id = $this->get_current_import_id();
        }

        if (!$import_id) {
            $this->logger->error('Could not get current progress - no import ID found');
            return 0;
        }

        $current_obj = WMSW_DatabaseHelper::get_import_log($import_id);

        if ($current_obj && isset($current_obj->items_processed)) {
            $this->logger->debug("Current progress for import ID {$import_id}: {$current_obj->items_processed}");
            return (int) $current_obj->items_processed;
        }

        $this->logger->error("Could not retrieve current progress for import ID {$import_id}");
        return 0;
    }

    /**
     * Get existing WooCommerce product ID by Shopify ID
     */
    private function get_existing_product_id($shopify_id)
    {
        $product_id = WMSW_DatabaseHelper::get_product_mapping($shopify_id);

        // Double check that the product still exists in WooCommerce
        if ($product_id) {
            $product_id = intval($product_id);

            // Check if product exists in WooCommerce using global functions
            $get_post_fn = '\\get_post';
            $get_post_type_fn = '\\get_post_type';

            if (function_exists($get_post_fn) && function_exists($get_post_type_fn)) {
                $post = $get_post_fn($product_id);
                if (!$post || $get_post_type_fn($product_id) !== 'product') {
                    $this->logger->warning("WooCommerce product with ID {$product_id} no longer exists, will create a new one");
                    $product_id = 0;
                }
            }
        }

        return $product_id ? intval($product_id) : 0;
    }

    /**
     * Map Shopify product ID to WooCommerce product ID
     */
    private function map_product_id($shopify_id, $woocommerce_id, $store_id = 1)
    {
        // Make sure we have valid IDs
        if (empty($shopify_id) || empty($woocommerce_id)) {
            $this->logger->error("Cannot map product IDs: Shopify ID or WooCommerce ID is empty");
            return false;
        }

        $this->logger->debug("Mapping Shopify product ID {$shopify_id} to WooCommerce ID {$woocommerce_id}");

        // First check if this WooCommerce ID is already mapped to a different Shopify ID
        $existing_woo_mapping = WMSW_DatabaseHelper::get_existing_woo_mapping($woocommerce_id);

        if ($existing_woo_mapping && $existing_woo_mapping !== $shopify_id) {
            $this->logger->warning("WooCommerce ID {$woocommerce_id} is already mapped to Shopify ID {$existing_woo_mapping}");
            // Delete conflicting mapping to avoid confusion
            WMSW_DatabaseHelper::delete_mapping([
                'woocommerce_id' => $woocommerce_id,
                'shopify_id' => $existing_woo_mapping,
                'object_type' => 'product'
            ]);
        }

        // Use the DatabaseHelper to handle the upsert operation
        $result = WMSW_DatabaseHelper::upsert_product_mapping($shopify_id, $woocommerce_id, $store_id);

        if ($result) {
            $this->logger->debug("Product mapping operation completed successfully");
        } else {
            $this->logger->error("Failed to map product IDs");
        }

        return $result;
    }

    /**
     * Update WooCommerce product
     */    private function update_product($wc_product_id, $shopify_product, $options)
    {
        try {
            // Get WooCommerce product, ensuring we use the global namespace function
            $wc_get_product_fn = '\\wc_get_product';
            $wc_product_class = '\\WC_Product';

            $this->logger->debug('Attempting to get product with ID: ' . $wc_product_id);

            if (function_exists($wc_get_product_fn)) {
                $product = $wc_get_product_fn($wc_product_id);
            } else {
                // Fallback for older WooCommerce versions or if function not available
                if (class_exists($wc_product_class)) {
                    $product = new $wc_product_class($wc_product_id);
                } else {
                    $this->logger->error('WooCommerce product class not found');
                    return false;
                }
            }

            if (!$product) {
                $this->logger->error('WooCommerce product not found: ' . $wc_product_id);
                return false;
            }

            // Update basic product data
            $this->update_product_data($product, $shopify_product, $options);

            // Update product meta
            $this->update_product_meta($product, $shopify_product);

            // Update images
            if ($options['download_images']) {
                $this->update_product_images($product, $shopify_product, $options);
            }

            // Update variants
            $this->update_product_variants($product, $shopify_product, $options);

            // Update categories if requested
            if ($options['update_categories'] && $options['import_collections']) {
                $this->update_product_categories($product, $shopify_product);
            }

            // Save the product
            $product->save();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating product: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Create new WooCommerce product
     */
    private function create_product($shopify_product, $options)
    {
        try {
            // Check if this product already exists by SKU to prevent duplicates
            if (!empty($shopify_product['variants'][0]['sku'])) {
                $sku = $shopify_product['variants'][0]['sku'];
                $this->logger->debug("Checking if product with SKU '{$sku}' already exists");

                // Use WordPress global function
                $wc_get_product_id_by_sku = '\\wc_get_product_id_by_sku';
                if (function_exists($wc_get_product_id_by_sku)) {
                    $existing_product_id = $wc_get_product_id_by_sku($sku);

                    if ($existing_product_id) {
                        $this->logger->debug("Found existing product with ID {$existing_product_id} for SKU '{$sku}'");

                        // Map the Shopify ID to this existing WooCommerce product
                        $this->map_product_id($shopify_product['id'], $existing_product_id);

                        // If we want to overwrite, update the product instead of creating a new one
                        if ($options['overwrite_existing']) {
                            $this->logger->debug("Updating existing product instead of creating duplicate");
                            return $this->update_product($existing_product_id, $shopify_product, $options)
                                ? $existing_product_id : false;
                        } else {
                            $this->logger->debug("Skipping product creation to prevent duplicate (overwrite_existing=false)");
                            return $existing_product_id;
                        }
                    }
                }
            }

            // Determine product type
            $product_type = $this->determine_product_type($shopify_product);

            $this->logger->debug("Creating new product with type: {$product_type}");

            // Check if WooCommerce exists and is active using the proper namespace handling
            if (!function_exists('\\wc_get_product_object')) {
                $this->logger->error('WooCommerce product creation function not found');
                return false;
            }

            // Create a new product - need to escape our namespace
            $wp_function = '\\wc_get_product_object';
            $product = $wp_function($product_type);

            if (!$product) {
                $this->logger->error('Failed to create WooCommerce product object');
                return false;
            }

            // Set basic product data
            $this->update_product_data($product, $shopify_product, $options);

            // Set product meta
            $this->update_product_meta($product, $shopify_product);

            // Add images
            if ($options['download_images']) {
                $this->update_product_images($product, $shopify_product, $options);
            }

            // Add variants
            $this->update_product_variants($product, $shopify_product, $options);

            // Add categories if requested
            if ($options['import_collections']) {
                $this->update_product_categories($product, $shopify_product);
            }

            // Save the product
            $product->save();

            return $product->get_id();
        } catch (\Exception $e) {
            $this->logger->error('Error creating product: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine WooCommerce product type based on Shopify product
     */
    private function determine_product_type($shopify_product)
    {
        // Check if product has variants
        if (isset($shopify_product['variants']) && count($shopify_product['variants']) > 1) {
            return 'variable';
        }

        return 'simple';
    }

    /**
     * Update product data
     */
    private function update_product_data($product, $shopify_product, $options)
    {
        $this->logger->debug('Setting basic product data');

        try {
            // Set product title
            $product->set_name($shopify_product['title']);

            // Set product description
            if (!empty($shopify_product['body_html'])) {
                $product->set_description($shopify_product['body_html']);
            }

            // Set status (published or draft)
            if ($this->logger->isDebugEnabled()) {
                $this->logger->debug('Shopify product status: ' . json_encode($shopify_product['status']));
            }
            $this->logger->debug('Shopify product status received', ['status' => $shopify_product['status']]);

            $status = isset($shopify_product['status']) && strtoupper($shopify_product['status']) === "DRAFT"
                ? 'draft'
                : 'publish';

            $this->logger->debug('Final WooCommerce status being set', ['woocommerce_status' => $status]);
            $product->set_status($status);

            // Set SKU for simple products
            if ($product->is_type('simple') && !empty($shopify_product['variants'][0]['sku'])) {
                $product->set_sku($shopify_product['variants'][0]['sku']);
            }

            // Set price for simple products
            if ($product->is_type('simple') && isset($shopify_product['variants'][0]['price'])) {
                $product->set_regular_price($shopify_product['variants'][0]['price']);
            }

            // Log completion
            $this->logger->debug('Product data set successfully');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating product data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update product meta
     */
    private function update_product_meta($product, $shopify_product)
    {
        $this->logger->debug('Setting product meta');
        try {
            // Store Shopify ID
            $product->update_meta_data('_shopify_product_id', $shopify_product['id']);

            // Store handle
            $product->update_meta_data('_shopify_handle', $shopify_product['handle']);

            // Store vendor
            if (!empty($shopify_product['vendor'])) {
                $product->update_meta_data('_shopify_vendor', $shopify_product['vendor']);
            }

            // Store product type
            if (!empty($shopify_product['product_type'])) {
                $product->update_meta_data('_shopify_product_type', $shopify_product['product_type']);
            }

            // Store published date
            if (!empty($shopify_product['published_at'])) {
                $product->update_meta_data('_shopify_published_at', $shopify_product['published_at']);
            }

            // Store last import date
            $product->update_meta_data('_shopify_imported_at', gmdate('Y-m-d H:i:s'));

            $this->logger->debug('Product meta set successfully');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating product meta: ' . $e->getMessage());
            return false;
        }
    }

    private function update_product_images($product, $shopify_product, $options)
    {
        if (empty($shopify_product['images']) || !is_array($shopify_product['images'])) {
            $this->logger->debug('No images to import for this product.');
            return false;
        }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

        $image_ids = [];

        if ($this->logger->isDebugEnabled()) {
            $this->logger->debug('Shopify product images: ' . json_encode($shopify_product['images']));
        }

        foreach ($shopify_product['images'] as $image) {
            $image_url = $this->get_image_url($image);
            if (empty($image_url)) {
                continue;
            }

            $attachment_id = $this->get_existing_attachment_id($image_url);
            if ($attachment_id) {
                $this->logger->debug("Image already exists: {$image_url} (ID: {$attachment_id})");
                $image_ids[] = $attachment_id;
                continue;
            }

            $attachment_id = $this->sideload_image($image_url, $product->get_id());
            if ($attachment_id) {
                $image_ids[] = $attachment_id;
            }
        }

        if (!empty($image_ids)) {
            // Ensure the product is saved before attaching images
            if (method_exists($product, 'save')) {
                $product->save(); // Save to ensure post exists
            }
            // Attach featured image
            set_post_thumbnail($product->get_id(), $image_ids[0]);
            // Attach gallery images
            if (count($image_ids) > 1) {
                update_post_meta($product->get_id(), '_product_image_gallery', implode(',', array_slice($image_ids, 1)));
            }
            $this->logger->debug('Imported and attached ' . count($image_ids) . ' images.');
        } else {
            $this->logger->debug('No images were imported.');
        }

        return true;
    }

    private function get_image_url($image)
    {
        if (is_array($image)) {
            return !empty($image['url']) ? $image['url'] : (!empty($image['src']) ? $image['src'] : '');
        }
        return is_string($image) ? $image : '';
    }

    private function get_existing_attachment_id($url)
    {
        static $cache = [];
        if (isset($cache[$url])) {
            return $cache[$url];
        }

        // Use direct SQL query for better performance instead of meta_query
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = %s 
             AND pm.meta_value = %s 
             AND p.post_type = 'attachment' ",
            '_WMSW_original_image_url',
            $url
        ));
            $url
        ));

        $attachment_id = $attachment_id ? (int) $attachment_id : 0;
        $cache[$url] = $attachment_id;
        
        return $attachment_id;
    }

    private function sideload_image($image_url, $product_id)
    {
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
            $this->logger->error("Download failed: {$image_url}");
            return 0;
        }

        $file_array = [
            'name'     => sanitize_file_name(basename(wp_parse_url($image_url, PHP_URL_PATH))),
            'tmp_name' => $tmp,
        ];

    $attachment_id = media_handle_sideload($file_array, $product_id);
    if (is_wp_error($attachment_id)) {
            wp_delete_file($file_array['tmp_name']);
            $this->logger->error("Sideload failed: {$image_url}");
            return 0;
        }

    update_post_meta($attachment_id, '_WMSW_original_image_url', esc_url_raw($image_url));
        return $attachment_id;
    }


    /**
     * Update product variants
     */
    private function update_product_variants($product, $shopify_product, $options)
    {
        // For now, we'll just log that this would handle variants
        $has_variants = isset($shopify_product['variants']) && count($shopify_product['variants']) > 1;
        $this->logger->debug('Product ' . ($has_variants ? 'has' : 'does not have') . ' variants to process');
        return true;
    }

    /**
     * Update product categories
     */
    private function update_product_categories($product, $shopify_product)
    {
        $this->logger->debug('Processing product categories for product: ' . $shopify_product['title']);

        // Check if product has collections
        if (empty($shopify_product['collections'])) {
            $this->logger->debug('No collections found for product');
            return true;
        }

        $category_ids = [];

        foreach ($shopify_product['collections'] as $collection) {
            $this->logger->debug('Processing collection: ' . $collection['title']);

            // Check if category exists in WooCommerce
            $category_id = $this->get_or_create_category($collection);

            if ($category_id) {
                $category_ids[] = $category_id;
                $this->logger->debug('Added category ID: ' . $category_id . ' for collection: ' . $collection['title']);
            } else {
                $this->logger->warning('Failed to create/get category for collection: ' . $collection['title']);
            }
        }

        // Set categories for the product
        if (!empty($category_ids)) {
            $product->set_category_ids($category_ids);
            $this->logger->debug('Set ' . count($category_ids) . ' categories for product: ' . $shopify_product['title']);
        }

        return true;
    }

    /**
     * Get existing category or create new one
     */
    private function get_or_create_category($collection)
    {
        $this->logger->debug('Getting or creating category for collection: ' . $collection['title']);

        // First check if category exists by handle (slug)
    $existing_term = get_term_by('slug', $collection['handle'], 'product_cat');

        if ($existing_term) {
            $this->logger->debug('Found existing category by slug: ' . $collection['handle'] . ' (ID: ' . $existing_term->term_id . ')');
            return $existing_term->term_id;
        }

        // Check if category exists by name
    $existing_term = get_term_by('name', $collection['title'], 'product_cat');

        if ($existing_term) {
            $this->logger->debug('Found existing category by name: ' . $collection['title'] . ' (ID: ' . $existing_term->term_id . ')');
            return $existing_term->term_id;
        }

        // Create new category
        $this->logger->debug('Creating new category: ' . $collection['title']);

        $category_data = [
            'description' => $collection['description'] ?? '',
            'slug' => $collection['handle']
        ];

    $result = wp_insert_term($collection['title'], 'product_cat', $category_data);

    if (is_wp_error($result)) {
            $this->logger->error('Error creating category: ' . $result->get_error_message());
            return false;
        }

        $category_id = $result['term_id'];
        $this->logger->debug('Created new category: ' . $collection['title'] . ' (ID: ' . $category_id . ')');

        // Store mapping for future reference
        $this->map_category_id($collection['id'], $category_id);

        return $category_id;
    }

    /**
     * Map Shopify collection ID to WooCommerce category ID
     */
    private function map_category_id($shopify_collection_id, $woocommerce_category_id, $store_id = 1)
    {
        // Use the DatabaseHelper to handle the upsert operation
        $result = WMSW_DatabaseHelper::upsert_category_mapping($shopify_collection_id, $woocommerce_category_id, $store_id);

        if ($result) {
            $this->logger->debug("Category mapping operation completed successfully for collection ID: {$shopify_collection_id}");
        } else {
            $this->logger->error("Failed to map category IDs for collection ID: {$shopify_collection_id}");
        }

        return $result;
    }

    /**
     * Clean up batch tracking transients
     */
    private function cleanupBatchTracking($tab = 'products')
    {
        $batch_count_key = 'wmsw_product_batch_count_' . $tab;
    delete_transient($batch_count_key);
        WMSW_PaginationHelper::deleteCursor($tab);
        $this->logger->debug('Cleaned up batch tracking for tab: ' . $tab);
    }

    /**
     * Update the total items count in the import logs table
     *
     * @param int $total_count The total number of items to import
     */
    private function update_total_items_count($total_count)
    {
        // Get the import ID
        $import_id = $this->get_current_import_id();

        if (!$import_id) {
            $this->logger->warning('Could not update total items count - no import ID found', [
                'level' => 'warning',
                'message' => 'Could not update total items count - no import ID found',
                'total_count' => $total_count
            ]);
            return;
        }

        $result = WMSW_DatabaseHelper::update_import_log($import_id, [
            'items_total' => $total_count,
            'updated_at' => gmdate('Y-m-d H:i:s')
        ]);

        // Log the total items count update using logger abstraction
        if ($result !== false) {
            $this->logger->info('Updated total items count', [
                'level' => 'info',
                'message' => 'Updated total items count',
                'import_id' => $import_id,
                'items_total' => $total_count
            ]);
        } else {
            $this->logger->error('Failed to update total items count', [
                'level' => 'error',
                'message' => 'Failed to update total items count',
                'import_id' => $import_id,
                'items_total' => $total_count
            ]);
        }
    }

    /**
     * Optimize database performance for image lookups
     * This method can be called during plugin activation or manually
     */
    public function optimize_database_performance()
    {
        global $wpdb;
        
        try {
            // Check if the index already exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $index_exists = $wpdb->get_var(
                "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'idx_wmsw_original_image_url'"
            );
            
            if (!$index_exists) {
                // Add index for better performance on image lookups
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                $result = $wpdb->query(
                    "ALTER TABLE {$wpdb->postmeta} ADD INDEX idx_wmsw_original_image_url (meta_key, meta_value(191))"
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                if ($result !== false) {
                    $this->logger->info('Database index added successfully for improved image lookup performance');
                    return true;
                } else {
                    $this->logger->warning('Failed to add database index - this may be due to insufficient permissions');
                    return false;
                }
            } else {
                $this->logger->info('Database index already exists for image lookups');
                return true;
            }
        } catch (Exception $e) {
            $this->logger->error('Error optimizing database performance: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get performance statistics for image lookups
     */
    public function get_performance_stats()
    {
        global $wpdb;
        
        $stats = [
            'total_attachments' => 0,
            'attachments_with_wmsw_meta' => 0,
            'cache_hit_rate' => 0,
            'index_status' => 'unknown'
        ];
        
        try {
            // Check total attachments
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats['total_attachments'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
            );
            
            // Check attachments with WMSW meta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats['attachments_with_wmsw_meta'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_WMSW_original_image_url'"
            );
            
            // Check index status
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $index_exists = $wpdb->get_var(
                "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'idx_wmsw_original_image_url'"
            );
            $stats['index_status'] = $index_exists ? 'exists' : 'missing';
            
        } catch (Exception $e) {
            $this->logger->error('Error getting performance stats: ' . $e->getMessage());
        }
        
        return $stats;
    }
}
