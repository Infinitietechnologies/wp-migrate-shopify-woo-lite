<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Models\WMSW_ShopifyStore;
use ShopifyWooImporter\Models\WMSW_ImportLog;
use ShopifyWooImporter\Processors\WMSW_ProductProcessor;
use ShopifyWooImporter\Services\WMSW_Logger;
use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
use ShopifyWooImporter\Helpers\WMSW_PaginationHelper;

use function get_option;
use function add_action;
use function wp_send_json_error;
use function wp_send_json_success;
use function __;
use function current_time;

/**
 * Product Preview Handler
 * 
 * Handles product preview functionality
 */
class WMSW_ProductHandler
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
        // Register AJAX handler for product preview
        add_action('wp_ajax_wmsw_preview_products', [$this, 'previewProducts']);
        add_action('wp_ajax_wmsw_start_products_import', [$this, 'startProductsImport']);
        add_action('wp_ajax_wmsw_get_import_progress', [$this, 'getImportProgress']);
        add_action('wp_ajax_wmsw_check_active_imports', [$this, 'checkActiveImports']);

        // Register handler for background product import
        add_action('wmsw_process_product_import', [$this, 'processProductImport'], 10, 3);
    }

    /**
     * Handle AJAX request for product preview
     */
    public function previewProducts()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        // Validate store ID
        if (empty($_POST['store_id'])) {
            wp_send_json_error([
                'message' => __('No store specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        $store_id = intval(sanitize_text_field(wp_unslash($_POST['store_id'])));

        $store_details = new WMSW_ShopifyStore();
        $store =  $store_details->find($store_id);

        // Check if store exists and has required fields
        if (empty($store->get_id())) {
            wp_send_json_error([
                'message' => __('Store not found', 'wp-migrate-shopify-woo')
            ]);
            return;
        }        // Get options from request
        $options = isset($_POST['options']) ? array_map('sanitize_text_field', sanitize_text_field(wp_unslash($_POST['options']))) : [];

        // Set preview limit
        $options['limit'] = isset($options['preview_limit']) ? absint($options['preview_limit']) : 10;
        $options['preview_mode'] = true;

        // Get status filter if available (active, archived, draft)
        if (isset($_POST['options']['status'])) {
            $options['status'] = sanitize_text_field(wp_unslash($_POST['options']['status']));
        }

        // Log all filter options for debugging
        $this->logger->debug('Filter options', [
            'level' => 'debug',
            'message' => 'Filter options for product preview',
            'store_id' => $store_id,
            'options' => $options
        ]);

        try {
            // Get Shopify client
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Get products for preview
            $products = $this->fetchPreviewProducts($shopify_client, $options);

            if (empty($products)) {
                $this->logger->info('No products found for preview', [
                    'level' => 'info',
                    'message' => 'No products found for preview',
                    'store_id' => $store_id,
                    'task_id' => null,
                    'options' => $options
                ]);
                wp_send_json_error([
                    'message' => __('No products found matching your criteria', 'wp-migrate-shopify-woo')
                ]);
                return;
            }

            // Format products for preview display
            $preview_data = $this->formatProductsForPreview($products);

            // Get a sample of the formatted data for debugging
            $sample_data = !empty($preview_data) ? $preview_data[0] : [];
            $this->logger->debug('Sample of formatted preview data', [
                'level' => 'debug',
                'message' => 'Sample of formatted preview data',
                'store_id' => $store_id,
                'sample_data' => $sample_data
            ]);

            // Add a summary info log for the preview
            $this->logger->info('Product preview completed', [
                'level' => 'info',
                'message' => 'Product preview completed',
                'store_id' => $store_id,
                'task_id' => null,
                'total_found' => count($products),
                'options' => $options
            ]);

            // Send success response with debug info 
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %d: number of products found */
                    __('Found %d products matching your criteria', 'wp-migrate-shopify-woo'),
                    count($products)
                ),
                'preview_data' => $preview_data,
                'total_count' => count($products),
                'debug_info' => [
                    'api_version' => $store->get_api_version(),
                    'has_formatted_data' => !empty($preview_data),
                    'sample_has_image' => !empty($sample_data) && !empty($sample_data['image'])
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Product preview error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    /**
     * Fetch products from Shopify API for preview
     *
     * @param WMSW_ShopifyClient $client Shopify API client
     * @param array $options Query options
     * @return array Array of products
     */
    private function fetchPreviewProducts($client, $options)
    {
        // Build query parameters for Shopify API
        $query_params = $this->buildApiQueryParams($options);
        // Log the query parameters for debugging
        $this->logger->debug('Shopify API query parameters: ' . json_encode($query_params));

        // Get products from Shopify API
        $response = $client->get_paginated('products', $query_params);

        // Log the API response summary
        $product_count = isset($response['products']) ? count($response['products']) : 0;

        $this->logger->debug('Shopify API returned ' . $product_count . ' products');

        if (isset($response['errors'])) {
            $this->logger->error('Error retrieving products: ' . json_encode($response['errors']));
            return [];
        }

        if (!isset($response['products']) || !is_array($response['products'])) {
            $this->logger->error('Invalid product response from Shopify API');
            return [];
        }

        // Enhanced logging for product images
        if ($product_count > 0) {
            // Log first product sample for debugging
            $sample = $response['products'][0];
            $this->logger->debug('Sample product: ID=' . $sample['id'] . ', Title=' . $sample['title']);

            // Debug the full structure of the first product to see all fields
            $this->logger->debug('Full product data structure: ' . json_encode(array_keys($sample)));

            // Log the status field specifically
            if (isset($sample['status'])) {
                $this->logger->debug('Sample product status field: "' . $sample['status'] . '" (type: ' . gettype($sample['status']) . ')');
            } else {
                $this->logger->debug('Sample product does not have a "status" field');
            }

            // Detailed image logging
            if (isset($sample['image'])) {
                $this->logger->debug('Sample product image data type: ' . gettype($sample['image']));
                $this->logger->debug('Sample product image data: ' . json_encode($sample['image']));
            } else {
                $this->logger->debug('Sample product has no "image" property');
            }

            // Check images array
            if (isset($sample['images']) && is_array($sample['images'])) {
                $this->logger->debug('Sample product images count: ' . count($sample['images']));
                if (!empty($sample['images'])) {
                    $this->logger->debug('First image in array data type: ' . gettype($sample['images'][0]));
                    $this->logger->debug('First image in array: ' . json_encode($sample['images'][0]));
                }
            } else {
                $this->logger->debug('Sample product has no "images" array or it is empty');
            }
        }

        // Get products from response
        $products = $response['products'];
        // Apply post-retrieval filters (price, inventory, and other complex filters)
        $products = $this->applyPostRetrievalFilters($products, $options);

        return $products;
    }
    /**
     * Build API query parameters for Shopify products endpoint
     *
     * @param array $options Filter options from the form
     * @return array Query parameters for the Shopify API
     */
    private function buildApiQueryParams($options)
    {
        $query_params = array(
            'limit' => isset($options['limit']) ? (int)$options['limit'] : 10
        );

        // Log raw options for debugging
        $this->logger->debug('Building API query params with options: ' . json_encode($options));

        // IDs filter - highest priority filter (comma-separated list)
        if (!empty($options['ids'])) {
            $query_params['ids'] = $options['ids'];
            return $query_params; // If we're filtering by IDs, ignore other filters
        }


        // Handle draft filter before adding other basic params
        // First, check all possible keys where product status might be specified
        $status = null;
        $status_keys = ['product_status', 'status', 'status'];

        foreach ($status_keys as $key) {
            if (isset($options[$key]) && !empty($options[$key])) {
                $status = $options[$key];
                $this->logger->debug("Found product status '{$status}' in options[{$key}]");
                break;
            }
        }        // Apply the appropriate status parameter based on the status for GraphQL queries
        switch ($status) {
            case 'draft':
                // For GraphQL, we need to format the status as "status:DRAFT"
                $query_params['query'] = 'status:DRAFT';
                $this->logger->debug('Applied query=status:DRAFT filter for draft products');
                break;

            case 'active':
                // For GraphQL, we need to format the status as "status:ACTIVE"
                $query_params['query'] = 'status:ACTIVE';
                $this->logger->debug('Applied query=status:ACTIVE filter for active products');
                break;

            case 'archived':
                // For GraphQL, we need to format the status as "status:ARCHIVED"
                $query_params['query'] = 'status:ARCHIVED';
                $this->logger->debug('Applied query=status:ARCHIVED filter for archived products');
                break;

            default:
                // Only set a default if no status was explicitly provided
                if ($status === null) {
                    $query_params['query'] = 'status:ACTIVE'; // Default to active products
                    $this->logger->debug('Defaulted to query=status:ACTIVE filter');
                } else {
                    $this->logger->debug("Using provided status: {$status}");
                    // For other status values, format properly for GraphQL
                    $query_params['query'] = 'status:' . strtoupper($status);
                }
                break;
        }

        // But only apply this if we haven't already set a specific status
        if (!isset($query_params['status'])) {
            if (isset($options['includeDrafts']) && $options['includeDrafts'] === true) {
                $query_params['status'] = 'any';
                $this->logger->debug('Applied status=any filter due to includeDrafts=true');
            } else if (isset($options['import_drafts']) && $options['import_drafts'] === true) {
                $query_params['status'] = 'any';
                $this->logger->debug('Applied status=any filter due to import_drafts=true');
            }
        }

        $this->addBasicFilterParams($query_params, $options);
        $this->addDateFilterParams($query_params, $options);

        // Include fields selection (for better performance)
        $fields = array(
            'id',
            'title',
            'body_html',
            'vendor',
            'product_type',
            'created_at',
            'updated_at',
            'published_at',
            'tags',
            'variants',
            'images',
            'image',
            'handle',
            'status'
        );
        $query_params['fields'] = implode(',', $fields);

        // Log the final query parameters for debugging
        $this->logger->debug('Final Shopify API query parameters: ' . json_encode($query_params));

        return $query_params;
    }

    /**
     * Add basic filter parameters to the query
     *
     * @param array &$query_params Query parameters to update
     * @param array $options Filter options from the form
     */
    private function addBasicFilterParams(&$query_params, $options)
    {
        // Handle filter by specific handles
        if (!empty($options['handle'])) {
            $query_params['handle'] = $options['handle'];
        }

        // Product "status" filter (active, archived, draft)
        // Note: "status" and "status" are different in Shopify API
        // status can be 'active', 'archived', or 'draft'
        // status can be 'published', 'unpublished', or 'any'
        if (!empty($options['status'])) {
            // Only add the status filter if it's not draft
            // Draft is handled via status parameter in buildApiQueryParams
            if ($options['status'] !== 'draft' && $options['status'] !== 'active') {
                $query_params['status'] = $options['status'];
                $this->logger->debug('Applied status filter: ' . $options['status']);
            }
        }

        // Collection filter
        if (!empty($options['collection_id'])) {
            $query_params['collection_id'] = $options['collection_id'];
        }

        // Product type filter
        if (!empty($options['productType'])) {
            $query_params['product_type'] = $options['productType'];
        }

        // Vendor filter
        if (!empty($options['vendor'])) {
            $query_params['vendor'] = $options['vendor'];
        }

        // Tag filter
        if (!empty($options['tags'])) {
            $query_params['tag'] = $options['tags'];
        }
    }

    /**
     * Add date filter parameters to the query
     *
     * @param array &$query_params Query parameters to update
     * @param array $options Filter options from the form
     */
    private function addDateFilterParams(&$query_params, $options)
    {
        // Created date filters
        if (!empty($options['dateFrom'])) {
            $query_params['created_at_min'] = gmdate('c', strtotime($options['dateFrom']));
        }

        if (!empty($options['dateTo'])) {
            $query_params['created_at_max'] = gmdate('c', strtotime($options['dateTo']));
        }

        // Published date filters
        if (!empty($options['published_at_min'])) {
            $query_params['published_at_min'] = gmdate('c', strtotime($options['published_at_min']));
        }

        if (!empty($options['published_at_max'])) {
            $query_params['published_at_max'] = gmdate('c', strtotime($options['published_at_max']));
        }

        // Updated date filters
        if (!empty($options['updated_at_min'])) {
            $query_params['updated_at_min'] = gmdate('c', strtotime($options['updated_at_min']));
        }

        if (!empty($options['updated_at_max'])) {
            $query_params['updated_at_max'] = gmdate('c', strtotime($options['updated_at_max']));
        }
    }
    /**
     * Apply post-retrieval filters to products
     *
     * @param array $products List of products to filter
     * @param array $options Filter options
     * @return array Filtered products
     */
    private function applyPostRetrievalFilters($products, $options)
    {
        // Apply price filter if specified
        if (!empty($options['minPrice']) || !empty($options['maxPrice'])) {
            $products = $this->applyPriceFilter($products, $options);
        }

        // Apply inventory filter if specified
        if (!empty($options['inventoryStatus']) && $options['inventoryStatus'] !== 'all') {
            $products = $this->applyInventoryFilter($products, $options['inventoryStatus']);
        }

        // Apply import type filter (new, existing, all)
        if (!empty($options['importType']) && $options['importType'] !== 'all') {
            $products = $this->applyImportTypeFilter($products, $options['importType']);
        }

        // Limit the number of products if we're in preview mode
        if (isset($options['preview_mode']) && $options['preview_mode'] && isset($options['limit'])) {
            $limit = (int)$options['limit'];
            $products = array_slice($products, 0, $limit);
        }

        return $products;
    }
    /**
     * Filter products by price range
     *
     * @param array $products Products to filter
     * @param array $options Filter options containing minPrice and maxPrice
     * @return array Filtered products
     */
    private function applyPriceFilter($products, $options)
    {
        $result = array();
        $min_price_filter = !empty($options['minPrice']) ? (float)$options['minPrice'] : null;
        $max_price_filter = !empty($options['maxPrice']) ? (float)$options['maxPrice'] : null;

        // If no price filters are set, return all products
        if ($min_price_filter === null && $max_price_filter === null) {
            return $products;
        }

        foreach ($products as $product) {
            if ($this->isProductInPriceRange($product, $min_price_filter, $max_price_filter)) {
                $result[] = $product;
            }
        }

        return $result;
    }

    /**
     * Check if product is within the specified price range
     *
     * @param array $product The product to check
     * @param float|null $min_price Minimum price filter
     * @param float|null $max_price Maximum price filter
     * @return boolean Whether the product is in the price range
     */
    private function isProductInPriceRange($product, $min_price, $max_price)
    {
        // Skip products without variants or if no price filters are set
        if (empty($product['variants']) || ($min_price === null && $max_price === null)) {
            return $min_price === null && $max_price === null;
        }

        // Get product price
        $product_price = $this->getMinProductPrice($product);

        // Product has no valid price
        if ($product_price === false) {
            return false;
        }

        // Check price against filters
        $min_price_ok = $min_price === null || $product_price >= $min_price;
        $max_price_ok = $max_price === null || $product_price <= $max_price;

        return $min_price_ok && $max_price_ok;
    }

    /**
     * Get the minimum price of a product from all its variants
     *
     * @param array $product The product to check
     * @return float|false The minimum price or false if no valid price found
     */
    private function getMinProductPrice($product)
    {
        $prices = array();

        foreach ($product['variants'] as $variant) {
            if (isset($variant['price'])) {
                $prices[] = (float)$variant['price'];
            }
        }

        return !empty($prices) ? min($prices) : false;
    }

    /**
     * Filter products by inventory status
     *
     * @param array $products Products to filter
     * @param string $inventoryStatus The inventory filter (in_stock, out_of_stock, all)
     * @return array Filtered products
     */
    private function applyInventoryFilter($products, $inventoryStatus)
    {
        // Don't filter if set to "all"
        if ($inventoryStatus === 'all') {
            return $products;
        }

        $result = array();

        foreach ($products as $product) {
            if ($this->matchesInventoryStatus($product, $inventoryStatus)) {
                $result[] = $product;
            }
        }

        return $result;
    }

    /**
     * Check if a product matches the specified inventory status
     *
     * @param array $product The product to check
     * @param string $inventoryStatus The desired status (in_stock, out_of_stock)
     * @return boolean Whether the product matches the inventory status
     */
    private function matchesInventoryStatus($product, $inventoryStatus)
    {
        $inventory_quantity = $this->getTotalInventoryQuantity($product);

        if ($inventoryStatus === 'in_stock') {
            return $inventory_quantity > 0;
        }

        if ($inventoryStatus === 'out_of_stock') {
            return $inventory_quantity <= 0;
        }

        return true;
    }

    /**
     * Get the total inventory quantity across all variants of a product
     *
     * @param array $product The product to check
     * @return int Total inventory quantity
     */
    private function getTotalInventoryQuantity($product)
    {
        $total = 0;

        if (!empty($product['variants'])) {
            foreach ($product['variants'] as $variant) {
                $total += isset($variant['inventory_quantity']) ? (int)$variant['inventory_quantity'] : 0;
            }
        }

        return $total;
    }

    /**
     * Filter products by import type
     *
     * @param array $products Products to filter
     * @param string $importType The import type (new, existing, all)
     * @return array Filtered products
     */
    private function applyImportTypeFilter($products, $importType)
    {
        if ($importType === 'all') {
            return $products;
        }

        // Get existing product handles from WooCommerce
        $existing_handles = $this->getExistingProductHandles();
        $result = array();

        foreach ($products as $product) {
            $handle = isset($product['handle']) ? $product['handle'] : '';

            // Check if this is an existing product
            $exists = in_array($handle, $existing_handles);

            // Filter based on import type
            $include = false;

            if ($importType === 'new') {
                $include = !$exists;
            } elseif ($importType === 'existing') {
                $include = $exists;
            } else {
                $include = true;
            }

            if ($include) {
                $result[] = $product;
            }
        }

        return $result;
    }

    private function handleMetaValue()
    {
        // Create a cache key
        $cache_key = 'shopify_handle_product_meta';
        $results   = wp_cache_get($cache_key);

        if (false === $results) {
            // Query posts with post_type and post_status conditions
            $posts = get_posts([
                'post_type'      => 'product',
                'post_status'    => ['publish', 'draft'],
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);

            $results = [];

            if (! empty($posts)) {
                foreach ($posts as $post_id) {
                    $meta_value = get_post_meta($post_id, '_shopify_handle', true);
                    if ('' !== $meta_value) {
                        $results[] = (object) ['meta_value' => $meta_value];
                    }
                }
            }

            // Store in cache for 1 hour
            wp_cache_set($cache_key, $results, '', HOUR_IN_SECONDS);
        }

        return $results;
    }

    /**
     * Get handles of existing products in WooCommerce
     *
     * @return array Array of product handles
     */
    private function getExistingProductHandles()
    {
        $handles = array();

        $results = $this->handleMetaValue();

        if (!empty($results)) {
            foreach ($results as $result) {
                if (isset($result->meta_value) && !empty($result->meta_value)) {
                    $handles[] = $result->meta_value;
                }
            }
        }

        return $handles;
    }
    /**
     * Format products for preview display
     *
     * @param array $products Products from Shopify API
     * @return array Formatted product data for preview
     */
    private function formatProductsForPreview($products)
    {
        $formatted_products = array();

        foreach ($products as $product) {
            // Get product image
            $image_url = $this->getProductImageUrl($product);

            // Clean and truncate description
            $description = $this->getFormattedDescription($product);

            // Debug product data
            $this->logger->debug('Formatting product: ' . $product['id'] . ' - ' . $product['title']);
            $this->logger->debug('Image URL: ' . $image_url);

            // Format product data
            $formatted_product = array(
                'id' => $product['id'],
                'title' => $product['title'],
                'description' => $description,
                'image' => $image_url,
                'price' => $this->getProductPrice($product),
                'variants_count' => isset($product['variants']) ? count($product['variants']) : 0,
                'images_count' => isset($product['images']) ? count($product['images']) : 0,
                'status' => $this->getFormattedStatus($product),
                'published' => !empty($product['published_at']),
                'vendor' => isset($product['vendor']) ? $product['vendor'] : '',
                'product_type' => isset($product['product_type']) ? $product['product_type'] : '',
                'type' => isset($product['product_type']) ? $product['product_type'] : '', // Add 'type' key to match JS
                'handle' => $product['handle'],
                'tags' => isset($product['tags']) ? $product['tags'] : '',
                'created_at' => $product['created_at'] ?? "",
                'updated_at' => $product['updated_at'] ?? "",
                'inventory_total' => $this->getTotalInventoryQuantity($product),
                'collections' => isset($product['collections']) ? $product['collections'] : array()
            );            // Add raw image data for debugging 
            // Always include raw data for debugging regardless of whether image_url is empty
            if (isset($product['image'])) {
                $formatted_product['raw_image_data'] = json_encode($product['image']);
            }

            if (isset($product['images']) && is_array($product['images']) && !empty($product['images'])) {
                $formatted_product['raw_images_data'] = json_encode(array_slice($product['images'], 0, 2)); // Include first 2 images max
            }

            // Add debug info for frontend
            $formatted_product['debug_info'] = [
                'has_image' => !empty($image_url),
                'has_image_property' => isset($product['image']),
                'has_images_array' => isset($product['images']) && is_array($product['images']),
                'images_count' => isset($product['images']) ? count($product['images']) : 0
            ];

            $formatted_products[] = $formatted_product;
        }

        return $formatted_products;
    }
    /**
     * Get the product's primary image URL
     * 
     * @param array $product Product data
     * @return string Image URL
     */
    private function getProductImageUrl($product)
    {
        $image_url = '';

        // Debug the image structure
        $this->logger->debug('Product ID: ' . $product['id'] . ' - Title: ' . $product['title']);

        // Log the complete image structure for debugging
        if (isset($product['image'])) {
            $this->logger->debug('Raw image data: ' . json_encode($product['image']));
        }

        if (isset($product['images']) && is_array($product['images']) && !empty($product['images'])) {
            $this->logger->debug('First image in images array: ' . json_encode($product['images'][0]));
        }

        // Try different image formats based on Shopify API response structure
        if (!empty($product['image'])) {
            if (is_array($product['image']) && isset($product['image']['src'])) {
                $image_url = $product['image']['src'];
                $this->logger->debug('Found image URL in product[image][src]: ' . $image_url);
            } elseif (is_object($product['image']) && isset($product['image']->src)) {
                // Handle object format
                $image_url = $product['image']->src;
                $this->logger->debug('Found image URL in product[image]->src: ' . $image_url);
            } elseif (is_string($product['image'])) {
                $image_url = $product['image'];
                $this->logger->debug('Found image URL as string in product[image]: ' . $image_url);
            }
        }

        // If no primary image, try to get from images array
        if (empty($image_url) && !empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $index => $image) {
                if (is_array($image) && isset($image['src'])) {
                    $image_url = $image['src'];
                    $this->logger->debug("Found image URL in product[images][$index][src]: " . $image_url);
                    break;
                } elseif (is_object($image) && isset($image->src)) {
                    $image_url = $image->src;
                    $this->logger->debug("Found image URL in product[images][$index]->src: " . $image_url);
                    break;
                } elseif (is_string($image)) {
                    $image_url = $image;
                    $this->logger->debug("Found image URL as string in product[images][$index]: " . $image_url);
                    break;
                }
            }
        }

        // Try one more fallback - check if there's a URL property directly
        if (empty($image_url) && isset($product['image_url'])) {
            $image_url = $product['image_url'];
            $this->logger->debug('Found image URL in product[image_url]: ' . $image_url);
        }

        if (empty($image_url)) {
            $this->logger->debug('No image URL found for product ' . $product['id']);
        } else {
            // Ensure the URL uses HTTPS
            if (strpos($image_url, 'http:') === 0) {
                $image_url = 'https:' . substr($image_url, 5);
                $this->logger->debug('Converted image URL to HTTPS: ' . $image_url);
            }
        }

        return $image_url;
    }

    /**
     * Format and truncate the product description
     * 
     * @param array $product Product data
     * @return string Formatted description
     */
    private function getFormattedDescription($product)
    {
        if (empty($product['body_html'])) {
            return '';
        }

        // Simple strip tags and truncate
        $clean_text = \wp_strip_all_tags($product['body_html']);
        $truncated = substr($clean_text, 0, 200);
        return $truncated . (strlen($clean_text) > 200 ? '...' : '');
    }
    /**
     * Get formatted product price or price range
     *
     * @param array $product Product data
     * @return string Formatted price
     */
    private function getProductPrice($product)
    {
        if (empty($product['variants'])) {
            return '';
        }

        // Get all prices from variants
        $prices = array_map(function ($variant) {
            return floatval($variant['price']);
        }, $product['variants']);

        $min_price = min($prices);
        $max_price = max($prices);

        // If min and max are the same, show single price
        if ($min_price === $max_price) {
            return '$' . number_format($min_price, 2);
        }

        // Otherwise show price range
        return '$' . number_format($min_price, 2) . ' - $' . number_format($max_price, 2);
    }

    /**
     * Start the product import process
     * 
     * Handles AJAX request for starting the import of products from Shopify
     */
    public function startProductsImport()
    {
        // Check for duplicate call
        static $already_called = false;
        if ($already_called) {
            $this->logger->debugLog('[WMSW_ProductHandler] startProductsImport called multiple times - preventing duplicate execution');
            return;
        }

        $this->logger->debugLog('[WMSW_ProductHandler] Starting product import');

        if (!empty($_POST['_via_backend'])) {
            $this->logger->debugLog('[WMSW_ProductHandler] Request came via WMSW_Backend handler - continuing with single import process');
        }


        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        // Validate store ID
        if (empty($_POST['store_id'])) {
            wp_send_json_error([
                'message' => __('No store specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        $store_id = intval($_POST['store_id']);
        $store_details = new WMSW_ShopifyStore();
        $store = $store_details->find($store_id);

        // Check if store exists
        if (empty($store->get_id())) {
            wp_send_json_error([
                'message' => __('Store not found', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Get global import settings
        $global_options = get_option('wmsw_options', []);

        // Collect product-specific settings from the form
        $product_settings_keys = [
            'import_type',
            'processing_threads',
            'error_handling',
            'import_images',
            'import_variants',
            'import_videos',
            'import_descriptions',
            'import_seo',
            'import_collections',
            'import_tags',
            'import_vendor_as_brand',
            'preserve_ids',
            'import_metafields',
            'create_product_lookup_table',
            'overwrite_existing',
            'skip_no_inventory',
            'sync_inventory'
        ];

        $product_settings = [];
        foreach ($product_settings_keys as $key) {
            if (isset($_POST[$key])) {
                $product_settings[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
            }
        }

        // Only allow advanced filters from POST (not import settings)
        $advanced_filter_keys = [
            'status',
            'product_type',
            'vendor',
            'tags',
            'min_price',
            'max_price',
            'inventory_status',
            'product_status',
            'date_from',
            'date_to',
            'collection_id',
        ];
        $filters = [];
        foreach ($advanced_filter_keys as $key) {
            if (isset($_POST['options'][$key])) {
                $filters[$key] = sanitize_text_field(wp_unslash($_POST['options'][$key]));
            }
        }

        // Merge global options with product settings and advanced filters (product settings override global, filters override if same key)
        $options = array_merge($global_options, $product_settings, $filters);

        // Prevent multiple in_progress imports for the same store
        $this->handleStuckImports($store_id);

        // Check for any remaining in-progress imports
        $active_import = WMSW_ImportLog::findActiveImportSession($store_id, 'products');
        if ($active_import) {
            wp_send_json_success([
                'message' => __('An import is already running for this store.', 'wp-migrate-shopify-woo'),
                'import_id' => $active_import->getId()
            ]);
            return;
        }

        // Clean up any stale pagination cursors
        WMSW_PaginationHelper::deleteCursor('products');

        // Create import session only if no existing import is running
        $import_id = $this->createImportSession($store_id, $options);

        if (!$import_id) {
            wp_send_json_error([
                'message' => __('Failed to create import session', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Initialize the background import process
        $result =  $this->initializeImport($import_id, $store, $options);

        wp_send_json_success([
            'message' => \__('Product import has started. You can monitor progress in the logs.', 'wp-migrate-shopify-woo'),
            'import_id' => $import_id,  // Return this import_id value to the JS
            'total_products' => $result['total_products']
        ]);
    }

    /**
     * Create an import session record
     * 
     * @param int $store_id The ID of the Shopify store
     * @param array $options Import options
     * @return int|false The import ID or false on failure
     */
    private function createImportSession($store_id, $options)
    {
        // Create import session using the model
        $import_session = WMSW_ImportLog::createImportSession($store_id, 'products', $options);

        if ($import_session) {
            $import_id = $import_session->getId();

            $this->logger->debugLog('[WMSW_ProductHandler] Created import session: ' . json_encode([
                'store_id' => $store_id,
                'import_id' => $import_id,
                'options' => $options
            ]));

            // Log the session creation using logger abstraction
            $this->logger->info('Import session created', [
                'level' => 'info',
                'message' => 'Import session created',
                'import_id' => $import_id,
                'store_id' => $store_id,
                'import_type' => 'products',
                'status' => 'initializing'
            ]);

            return $import_id;
        } else {
            $this->logger->error('Failed to create import session', [
                'level' => 'error',
                'message' => 'Failed to create import session',
                'store_id' => $store_id,
                'import_type' => 'products',
                'options' => $options
            ]);

            return false;
        }
    }

    /**
     * Initialize the import process
     * 
     * @param int $import_id The import session ID
     * @param WMSW_ShopifyStore $store The Shopify store object
     * @param array $options Import options
     * @return array Result information
     */
    private function initializeImport($import_id, $store, $options)
    {

        // Normalize filter keys to match what the API and post-retrieval filters expect
        $normalized_options = $this->normalizeImportOptions($options);
        try {
            // Check if another import is already running (excluding this one)
            $existing_imports = $this->getRunningImportsExcept($import_id);
            if ($existing_imports > 0) {
                $this->logger->warning("Attempted to start a new import while another is in progress");
                return [
                    'success' => false,
                    'message' => __('Another import is already running. Please wait for it to complete.', 'wp-migrate-shopify-woo')
                ];
            }
            // Get Shopify client
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Get total number of products
            $query_params = $this->buildApiQueryParams($normalized_options);

            $count_response = $shopify_client->countProducts($query_params);

            if (!isset($count_response['count'])) {
                $this->logger->error('Failed to get product count: ' . json_encode($count_response));
                return [
                    'success' => false,
                    'message' => __('Failed to get product count', 'wp-migrate-shopify-woo')
                ];
            }

            // Update import session with total count
            $total_products = intval($count_response['count']);
            $this->updateImportSession($import_id, [
                'status' => 'in_progress',
                'items_total' => $total_products,
                'items_processed' => 0,
                "message" => 'Import is in progress',
            ]);            // Track the import attempts in a transient to prevent duplication
            $transient_key = 'wmsw_import_in_progress_' . $import_id;
            if (\get_transient($transient_key)) {
                $this->logger->warning("Import ID: {$import_id} is already being processed. Skipping duplicate execution.");
                // Return as if we successfully started
                return [
                    'success' => true,
                    'total_products' => $total_products,
                    'message' => 'Import already in progress'
                ];
            }

            // Set a transient indicating this import is being processed
            // It will automatically expire after 5 minutes (300 seconds) if something goes wrong
            \set_transient($transient_key, true, 300);

            // CRITICAL: Use a flag to prevent multiple executions
            static $import_executed = [];
            if (!empty($import_executed[$import_id])) {
                $this->logger->warning("Import ID: {$import_id} already executed in this request. Preventing duplicate.");
                return [
                    'success' => true,
                    'total_products' => $total_products,
                    'message' => 'Import already executed'
                ];
            }
            $import_executed[$import_id] = true;

            // Choose only ONE execution method, not both
            $use_cron = true; // Always use cron for background import

            if ($use_cron) {
                // Schedule the actual import to run in background through cron
                \wp_schedule_single_event(
                    time(),
                    'wmsw_process_product_import',
                    [$import_id, $store->get_id(), $normalized_options]
                );

                $this->logger->info("Scheduled import job through cron: import_id={$import_id}, store_id={$store->get_id()}");
            } else {
                // Process immediately
                $this->logger->info("Running import job immediately: import_id={$import_id}, store_id={$store->get_id()}");
                \do_action('wmsw_process_product_import', $import_id, $store->get_id(), $normalized_options);
            }
            // Delete the transient when done
            \delete_transient($transient_key);

            return [
                'success' => true,
                'total_products' => $total_products
            ];
        } catch (\Exception $e) {
            $this->logger->error('Import initialization error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    /**
     * Normalize import options to ensure all filters are available in the expected format
     *
     * @param array $options
     * @return array
     */
    private function normalizeImportOptions($options)
    {
        $map = [
            'product_type' => 'productType',
            'vendor' => 'vendor',
            'tags' => 'tags',
            'min_price' => 'minPrice',
            'max_price' => 'maxPrice',
            'inventory_status' => 'inventoryStatus',
            'product_status' => 'status',
            'draft' => 'includeDrafts',
        ];

        foreach ($map as $from => $to) {
            if (isset($options[$from])) {
                $options[$to] = $options[$from];
                $this->logger->debug("Normalized option: {$from} -> {$to} = " . json_encode($options[$from]));
            }
        }

        // Handle product-specific settings
        $product_settings_map = [
            'import_images' => 'download_images',
            'import_variants' => 'import_variants',
            'import_videos' => 'import_videos',
            'import_descriptions' => 'import_descriptions',
            'import_seo' => 'import_seo',
            'import_collections' => 'import_collections',
            'import_tags' => 'import_tags',
            'import_vendor_as_brand' => 'import_vendor_as_brand',
            'preserve_ids' => 'preserve_ids',
            'import_metafields' => 'import_metafields',
            'create_product_lookup_table' => 'create_product_lookup_table',
            'overwrite_existing' => 'overwrite_existing',
            'skip_no_inventory' => 'skip_no_inventory',
            'sync_inventory' => 'sync_inventory',
            'processing_threads' => 'processing_threads',
            'error_handling' => 'error_handling',
            'import_type' => 'import_type'
        ];

        foreach ($product_settings_map as $from => $to) {
            if (isset($options[$from])) {
                $options[$to] = $options[$from];
                $this->logger->debug("Normalized product setting: {$from} -> {$to} = " . json_encode($options[$from]));
            }
        }

        // Special handling for draft/import_drafts to ensure consistent behavior
        if (isset($options['includeDrafts']) && $options['includeDrafts']) {
            $options['import_drafts'] = true;
            $this->logger->debug('Setting import_drafts=true based on includeDrafts option');
        } else if (isset($options['draft']) && $options['draft']) {
            $options['import_drafts'] = true;
            $options['includeDrafts'] = true;
            $this->logger->debug('Setting import_drafts=true and includeDrafts=true based on draft option');
        }

        // If status is explicitly set to draft, make sure those settings are consistent
        if (isset($options['status']) && $options['status'] === 'draft') {
            $this->logger->debug('Status is set to draft, ensuring proper draft handling options');
        }

        $this->logger->debug('Normalized options: ' . json_encode($options));
        return $options;
    }

    /**
     * Update import session record
     *
     * @param int $import_id The import session ID
     * @param array $data The data to update
     * @return bool Success or failure
     */
    private function updateImportSession($import_id, $data)
    {
        // Find the import session
        $import_session = WMSW_ImportLog::find($import_id);

        if (!$import_session) {
            $this->logger->error('Failed to find import session', [
                'level' => 'error',
                'message' => 'Failed to find import session',
                'import_id' => $import_id
            ]);
            return false;
        }

        $log_message = 'Import session updated';

        $log_context = [
            'level' => 'info',
            'message' => $log_message,
            'import_id' => $import_id
        ];
        $this->logger->info("we are here", $log_context);

        // Update the import session
        $result = $import_session->updateImportSession($data);

        // Log the session update using logger abstraction
        if ($result) {
            $log_message = 'Import session updated';
            $log_context = [
                'level' => 'info',
                'message' => $log_message,
                'import_id' => $import_id
            ];

            // Add specific data to context for better tracking
            if (isset($data['status'])) {
                $log_context['status'] = $data['status'];
                $log_message = "Import session status updated to: {$data['status']}";
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
            $this->logger->error('Failed to update import session', [
                'level' => 'error',
                'message' => 'Failed to update import session',
                'import_id' => $import_id,
                'data' => $data
            ]);
        }

        return $result;
    }

    /**
     * Get the import progress
     * 
     * Handles AJAX request for checking the progress of an ongoing import
     */    public function getImportProgress()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        // Log all $_POST data for debugging (only in debug mode)
        $this->logger->debugLog('POST data in getImportProgress: ' . json_encode($_POST));

        // Validate import ID - check both import_id and task_id
        $import_id = 0;
        if (!empty($_POST['import_id'])) {
            $import_id = intval($_POST['import_id']);
        } elseif (!empty($_POST['task_id'])) {
            // Fallback for old code that might be using task_id
            $import_id = intval($_POST['task_id']);
        } else {
            \wp_send_json_error([
                'message' => \__('Import ID not specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Log the import ID we're using (only in debug mode)
        $this->logger->debugLog('Using import_id: ' . $import_id);

        // Get import status
        $status = $this->getImportStatus($import_id);

        if (!$status) {
            \wp_send_json_error([
                'message' => \__('Import session not found', 'wp-migrate-shopify-woo')
            ]);
            return;
        }        // Calculate percentage
        $percentage = 0;
        if ($status['items_total'] > 0) {
            $percentage = round(($status['items_processed'] / $status['items_total']) * 100);
        }
        \wp_send_json_success([
            'status' => $status['status'],
            'items_total' => $status['items_total'],
            'items_processed' => $status['items_processed'],
            'percentage' => $percentage,
            'is_complete' => in_array($status['status'], ['completed', 'failed']),
            'last_updated' => $status['updated_at'],
            'import_id' => $import_id,  // Include the import_id in the response for continuity
            'debug_info' => [
                'backtrace_import_id' => $this->getBacktraceImportId(),
                'global_import_id' => isset($GLOBALS['wmsw_current_import_id']) ? $GLOBALS['wmsw_current_import_id'] : 'not set'
            ]
        ]);
    }

    /**
     * Check for active imports for a specific store
     * AJAX handler for checking if there are any active imports running
     */
    public function checkActiveImports()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'swi-admin-nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-migrate-shopify-woo')]);
        }

        // Get store ID
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;

        if (!$store_id) {
            wp_send_json_error([
                'message' => __('No store specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Get active import for this store using the model
        $active_import = WMSW_ImportLog::findActiveImportSession($store_id, 'products');

        if ($active_import) {
            // Calculate percentage
            $percentage = 0;
            if ($active_import->getItemsTotal() > 0) {
                $percentage = round(($active_import->getItemsProcessed() / $active_import->getItemsTotal()) * 100);
            }

            $active_import_data = $active_import->toArray();
            $active_import_data['percentage'] = $percentage;
            $active_import_data['is_complete'] = false;
            $active_import_data['import_id'] = $active_import->getId();

            wp_send_json_success([
                'active_import' => $active_import_data,
                'message' => __('Active import found', 'wp-migrate-shopify-woo')
            ]);
        } else {
            wp_send_json_success([
                'active_import' => null,
                'message' => __('No active imports found', 'wp-migrate-shopify-woo')
            ]);
        }
    }

    /**
     * Get the current import status
     * 
     * @param int $import_id The import session ID
     * @return array|false Import status data or false if not found
     */
    private function getImportStatus($import_id)
    {
        // Use the model to find the import session
        $import_session = WMSW_ImportLog::find($import_id);

        if ($import_session) {
            return [
                'id' => $import_session->getId(),
                'status' => $import_session->getStatus(),
                'items_total' => $import_session->getItemsTotal(),
                'items_processed' => $import_session->getItemsProcessed(),
                'updated_at' => $import_session->toArray()['updated_at'] ?? null
            ];
        }

        return false;
    }
    /**
     * Process product import
     * 
     * This is called by the WordPress cron system via the 'wmsw_process_product_import' hook
     * 
     * @param int $import_id ID of the import session
     * @param int $store_id ID of the Shopify store
     * @param array $options Import options
     */
    public function processProductImport($import_id, $store_id, $options)
    {
        $this->logger->info('Starting product import process', [
            'import_id' => $import_id,
            'store_id' => $store_id
        ]);

        // Set global import ID for progress tracking
        $GLOBALS['wmsw_current_import_id'] = $import_id;

        try {
            // Get store
            $store_details = new WMSW_ShopifyStore();
            $store = $store_details->find($store_id);

            if (empty($store->get_id())) {
                $this->logger->error("Store not found: {$store_id}");
                $this->updateImportSession($import_id, [
                    'status' => 'failed',
                    'error' => 'Store not found',
                    'level' => 'error',
                    'message' => 'Store not found',
                    'completed_at' => gmdate('Y-m-d H:i:s')
                ]);
                return;
            }

            // Get current import status
            $current_status = $this->getImportStatus($import_id);
            if (!$current_status || $current_status['status'] !== 'in_progress') {
                $this->logger->info("Updating import status to 'in_progress'");
                $this->updateImportSession($import_id, [
                    'status' => 'in_progress',
                    'started_at' => gmdate('Y-m-d H:i:s'),
                    'items_processed' => 0
                ]);
            }

            // Get Shopify client
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Create product processor
            $product_processor = new WMSW_ProductProcessor($shopify_client, $this->logger);

            // Process the import
            $this->logger->debug('Starting product import with options: ' . json_encode($options));
            $results = $product_processor->import_products($options);

            // Update import status
            $has_next_page = $results['has_next_page'] ?? false;
            $success = $results['success'] ?? false;
            $imported = $results['imported'] ?? 0;
            $updated = $results['updated'] ?? 0;
            $failed = $results['failed'] ?? 0;
            $message = $results['message'] ?? '';

            if ($has_next_page) {
                $status = 'in_progress';
            } elseif ($success) {
                $status = 'completed';
            } else {
                $status = 'failed';
            }

            switch ($status) {
                case 'completed':
                    $level = 'success';
                    $final_message = 'Import completed successfully.';
                    break;
                case 'in_progress':
                    $level = 'info';
                    $final_message = 'Import is still in progress.';
                    break;
                case 'failed':
                default:
                    $level = 'error';
                    $final_message = $message ?: 'Import failed due to an unexpected error.';
                    break;
            }

            $this->updateImportSession($import_id, [
                'status'           => $status,
                'items_processed'  => $imported + $updated,
                'items_succeeded'  => $imported + $updated,
                'items_failed'     => $failed,
                'log_data'         => $status === 'failed' ? json_encode(['message' => $final_message, 'level' => $level]) : null,
                'completed_at'     => (!$has_next_page && $success) ? gmdate('Y-m-d H:i:s') : null,
                'level'            => $level,
                'message'          => $final_message,
            ]);


            $this->logger->info('Product import batch completed', [
                'import_id' => $import_id,
                'imported' => $results['imported'],
                'updated' => $results['updated'],
                'failed' => $results['failed'],
                'has_next_page' => $results['has_next_page'] ?? false,
                'status' => $status,
                'message' => $final_message
            ]);

            // If there are more batches, schedule the next one
            if (!empty($results['has_next_page'])) {
                // Additional safety check: prevent scheduling if same cursor is being used
                $current_cursor = WMSW_PaginationHelper::getCursor('products');
                $next_cursor = $results['next_cursor'] ?? null;

                if ($current_cursor !== $next_cursor && !empty($next_cursor)) {
                    \wp_schedule_single_event(time() + 10, 'wmsw_process_product_import', [$import_id, $store_id, $options]);
                    $this->logger->info('Scheduled next product import batch via cron.', [
                        'import_id' => $import_id,
                        'store_id' => $store_id,
                        'current_cursor' => $current_cursor,
                        'next_cursor' => $next_cursor
                    ]);
                } else {
                    $this->logger->warning('Skipping cron scheduling due to duplicate cursor or missing next cursor', [
                        'import_id' => $import_id,
                        'current_cursor' => $current_cursor,
                        'next_cursor' => $next_cursor
                    ]);

                    // Update import status to completed since we're not scheduling more batches
                    $this->updateImportSession($import_id, [
                        'status' => 'completed',
                        'completed_at' => gmdate('Y-m-d H:i:s'),
                        'message' => 'Import completed (pagination ended due to duplicate cursor)'
                    ]);
                }
            } else {
                // No more pages, mark as completed
                $this->updateImportSession($import_id, [
                    'status' => 'completed',
                    'completed_at' => gmdate('Y-m-d H:i:s'),
                    'message' => 'Import completed successfully'
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in product import: ' . $e->getMessage(), [
                'import_id' => $import_id,
                'exception' => $e->getMessage()
            ]);
            // Update import status
            $this->updateImportSession($import_id, [
                'status' => 'failed',
                'log_data' => json_encode(['error' => $e->getMessage()]),
                'completed_at' => gmdate('Y-m-d H:i:s')
            ]);
        }
    }
    /**
     * Helper method to get import ID from backtrace for debugging
     * 
     * @return string The import ID if found, or a message if not
     */
    private function getBacktraceImportId()
    {
        return 'processProductImport not found in backtrace';
    }

    /**
     * Check if there are any running imports except for the specified one
     *
     * @param int $import_id The current import ID to exclude from check
     * @return int Number of other running imports
     */
    private function getRunningImportsExcept($import_id)
    {
        // Use the model method to count running imports excluding the current one
        return WMSW_ImportLog::countRunningImportsExcept($import_id);
    }

    /**
     * Format product status for consistent results
     *
     * @param array $product The product data from Shopify API
     * @return string Formatted status
     */
    private function getFormattedStatus($product)
    {
        // If no status is set, default to active
        if (!isset($product['status'])) {
            $this->logger->debug('No status field found in product ' . $product['id'] . ' - defaulting to "active"');
            return 'active';
        }

        $status = $product['status'];
        $this->logger->debug('Raw product status for product ' . $product['id'] . ': ' . $status);

        // Handle GraphQL uppercase status values (DRAFT, ACTIVE, ARCHIVED)
        // Convert to lowercase for consistent output
        if ($status === 'DRAFT' || strtolower($status) === 'draft') {
            $this->logger->debug('Normalized status "' . $status . '" to "draft"');
            return 'draft';
        }

        if ($status === 'ACTIVE' || strtolower($status) === 'active') {
            $this->logger->debug('Normalized status "' . $status . '" to "active"');
            return 'active';
        }

        if ($status === 'ARCHIVED' || strtolower($status) === 'archived') {
            $this->logger->debug('Normalized status "' . $status . '" to "archived"');
            return 'archived';
        }

        // Log unexpected status values
        $this->logger->debug('Unexpected product status value: ' . $status);

        // Return the original status if it doesn't match expected values
        return $status;
    }

    /**
     * Handle stuck imports by marking them as failed
     * 
     * @param int $store_id The store ID to check for stuck imports
     */
    private function handleStuckImports($store_id)
    {
        // Find stuck imports (older than 1 hour) for this store using the model
        $stuck_imports = WMSW_ImportLog::findStuckImports($store_id, 'products', '-1 hour');

        if ($stuck_imports) {
            foreach ($stuck_imports as $stuck_import) {
                // Update the import session to mark as failed
                $stuck_import->updateImportSession([
                    'status' => 'failed',
                    'message' => 'Import timed out after 1 hour',
                    'completed_at' => \current_time('mysql')
                ]);
                $this->logger->info('Marked stuck import as failed: ' . $stuck_import->getId());
            }
        }
    }
}

// Note: The handler will be initialized by the autoloader
// Initialization will happen when the plugin loads
