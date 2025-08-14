<?php

namespace ShopifyWooImporter\Core;

use ShopifyWooImporter\Services\WMSW_Logger;

/**
 * Shopify API Client using GraphQL
 */
class WMSW_ShopifyClient
{
    private $shop_domain;
    private $access_token;
    private $api_version;
    private $graphql_endpoint;
    private $logger;

    // Constant for WordPress debug mode check
    private $debug_mode;

    public function __construct($shop_domain, $access_token, $api_version = null)
    {
        // Clean and validate shop domain
        $this->shop_domain = $this->clean_shop_domain($shop_domain);
        $this->access_token = $access_token;
        $this->api_version = $api_version;
        if ($this->api_version === null) {
            if (defined('wmsw_SHOPIFY_API_VERSION')) {
                $this->api_version = WMSW_SHOPIFY_API_VERSION;
            } else {
                $this->api_version = '2023-10';
            }
        }

        // Check if WordPress debug mode is active
        $this->debug_mode = defined('WP_DEBUG') && \WP_DEBUG;

        // Initialize logger
        $this->logger = new WMSW_Logger();

        // Set GraphQL endpoint
        $this->graphql_endpoint = $this->build_graphql_url();
    }

    /**
     * Clean shop domain to ensure proper format
     */
    private function clean_shop_domain($domain)
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/(https?:\/\/)/', '', $domain);
        $domain = preg_replace('/\/.*/', '', $domain);

        // Remove .myshopify.com if it's already in the domain to prevent duplication
        $domain = preg_replace('/\.myshopify\.com$/', '', $domain);

        return $domain;
    }

    /**
     * Build GraphQL API URL
     */
    private function build_graphql_url()
    {
        // Build the full GraphQL API URL
        $domain = $this->shop_domain;
        if (strpos($domain, '.myshopify.com') === false) {
            $domain .= '.myshopify.com';
        }

        return sprintf(
            'https://%s/admin/api/%s/graphql.json',
            $domain,
            $this->api_version
        );
    }

    /**
     * Execute a GraphQL query
     *
     * @param string $query The GraphQL query string
     * @param array $variables Variables for the GraphQL query
     * @return array Response from the GraphQL API
     */
    public function query($query, $variables = [])
    {
        // Validate inputs
        if (empty($query)) {
            throw new \Exception(\esc_html__('GraphQL query cannot be empty', 'wp-migrate-shopify-woo'));
        }

        if (empty($this->shop_domain) || empty($this->access_token)) {
            throw new \Exception(\esc_html__('Shop domain and access token are required', 'wp-migrate-shopify-woo'));
        }

        return $this->make_graphql_request($query, $variables);
    }

    /**
     * Make a GraphQL request to the Shopify API
     */
    private function make_graphql_request($query, $variables = [])
    {
        // Prepare request arguments
        $args = [
            'method' => 'POST',
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => defined('wmsw_SHOPIFY_TIMEOUT') ? WMSW_SHOPIFY_TIMEOUT : 30,
            'body' => json_encode([
                'query' => $query,
                // Shopify expects variables as an object, not an array
                'variables' => (object) $variables
            ])
        ];

        // Log the request for debugging (in development)
        if ($this->debug_mode) {
            $this->logger->debug("Shopify GraphQL Request: " . substr($query, 0, 200));
            if (!empty($variables)) {
                $this->logger->debug("Variables: " . json_encode($variables));
            }
        }

        // Using WordPress functions without namespace issues
        $response = $this->wp_api_request($this->graphql_endpoint, $args);

        // Check for errors in the response
        if (!$response['success']) {
            $error_message = $response['error_message'] ?? 'Unknown error';
            throw new \Exception(\esc_html($error_message));
        }

        // Process the response data
        $data = $response['data'];

        // Check for GraphQL errors
        if (isset($data['errors'])) {
            $errors = $this->format_graphql_errors($data['errors']);
            $error_message = "GraphQL query failed: {$errors}";
            if ($this->debug_mode) {
                $this->logger->debug("Shopify GraphQL API Query Error: {$error_message}");
            }
            throw new \Exception(\esc_html($error_message));
        }

        return $data['data'] ?? [];
    }

    /**
     * Wrapper for WordPress HTTP API functions to avoid namespace issues
     */
    private function wp_api_request($url, $args)
    {
        // Call WordPress functions from global namespace
        $response = call_user_func('wp_remote_request', $url, $args);

        // Check for WordPress errors
        if (call_user_func('is_wp_error', $response)) {
            $error_message = 'GraphQL API request failed: ' . $response->get_error_message();
            if ($this->debug_mode) {
                $this->logger->debug("Shopify GraphQL API Error: {$error_message}");
            }
            return [
                'success' => false,
                'error_message' => $error_message
            ];
        }

        // Get response data
        $body = call_user_func('wp_remote_retrieve_body', $response);
        $status_code = call_user_func('wp_remote_retrieve_response_code', $response);        // Log response for debugging (in development)
        if ($this->debug_mode) {
            $this->logger->debug("Shopify GraphQL API Response: Status {$status_code}");
            // Log first 1000 chars of body to avoid overly large logs
            $this->logger->debug("Response Body (truncated): " . substr($body, 0, 1000));
        }

        // Check for HTTP errors
        if ($status_code >= 400) {
            $error_message = "GraphQL API request failed with status {$status_code}: {$body}";
            if ($this->debug_mode) {
                $this->logger->debug("Shopify GraphQL API HTTP Error: {$error_message}");
            }
            return [
                'success' => false,
                'error_message' => $error_message
            ];
        }

        // Decode JSON response
        $decoded = json_decode($body, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Failed to decode JSON response: ' . json_last_error_msg();
            if ($this->debug_mode) {
                $this->logger->debug("Shopify GraphQL API JSON Error: {$error_message}");
            }
            return [
                'success' => false,
                'error_message' => $error_message
            ];
        }

        return [
            'success' => true,
            'data' => $decoded
        ];
    }

    /**
     * Format GraphQL errors for better readability
     */
    private function format_graphql_errors($errors)
    {
        if (!is_array($errors)) {
            return 'Unknown error';
        }

        $messages = [];
        foreach ($errors as $error) {
            if (isset($error['message'])) {
                $message = $error['message'];

                // Add location info if available
                if (isset($error['locations'])) {
                    $location = reset($error['locations']);
                    $message .= " (line {$location['line']}, column {$location['column']})";
                }

                $messages[] = $message;
            }
        }

        return implode('; ', $messages);
    }

    /**
     * Test connection using GraphQL
     */
    public function test_connection()
    {
        try {
            $query = '
            {
              shop {
                name
                id
              }
            }';

            $response = $this->query($query);
            return isset($response['shop']) && isset($response['shop']['id']);
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Get a single page of resources from Shopify API with proper cursor handling
     *
     * @param string $resource The resource type (products, customers, orders)
     * @param array $params Query parameters
     * @param int $first Number of items to fetch per page
     * @return array Array with items and pageInfo for single page
     * @throws \Exception If resource is not found in response
     */
    public function get_single_page($resource, $params = [], $first = null)
    {
        // Use the provided page size, or check settings, or fall back to constant, or default to 250
        if ($first === null) {
            // First, check if we have a resource-specific batch size setting
            $batch_setting_key = 'wmsw_batch_size_' . strtolower($resource);
            $batch_from_settings = $this->get_setting($batch_setting_key);

            if ($batch_from_settings !== null && is_numeric($batch_from_settings) && $batch_from_settings > 0) {
                $first = (int)$batch_from_settings;
            } else {
                // Otherwise check resource-specific constant
                $constant_name = 'wmsw_BATCH_SIZE_' . strtoupper($resource);
                if (defined($constant_name)) {
                    $first = constant($constant_name);
                } else {
                    $first = defined('wmsw_MAX_IMPORT_ITEMS') ? WMSW_MAX_IMPORT_ITEMS : 250;
                }
            }
        }

        // Initialize variables for the GraphQL query
        $variables = ['first' => $first];

        // Build filters for the GraphQL query
        $variables = $this->build_graphql_filters($params, $variables);

        // Build the appropriate GraphQL query based on resource type
        $query = $this->get_resource_query($resource, isset($variables['query']));

        // Execute the GraphQL query for a single page
        $response = $this->query($query, $variables);

        if (!isset($response[$resource])) {
            throw new \Exception(\esc_html("Resource '{$resource}' not found in GraphQL response"));
        }

        $current = $response[$resource];

        // Process the current page of results
        $items = $this->process_graphql_page($current, $resource, []);

        // Get pagination info for next page
        $page_info = $current['pageInfo'] ?? null;

        // Format the response to match expected format
        if ($resource === 'products') {
            return ['products' => $items, 'pageInfo' => $page_info];
        } elseif ($resource === 'customers') {
            return ['customers' => $items, 'pageInfo' => $page_info];
        } elseif ($resource === 'orders') {
            return ['orders' => $items, 'pageInfo' => $page_info];
        } else {
            return ['items' => $items, 'pageInfo' => $page_info];
        }
    }

    public function get_paginated($resource, $params = [], $first = null)
    {
        $all_items = [];
        $has_next_page = true;
        $after = null;


        // Use the provided page size, or check settings, or fall back to constant, or default to 250
        if ($first === null) {
            // First, check if we have a resource-specific batch size setting
            $batch_setting_key = 'wmsw_batch_size_' . strtolower($resource);
            $batch_from_settings = $this->get_setting($batch_setting_key);

            if ($batch_from_settings !== null && is_numeric($batch_from_settings) && $batch_from_settings > 0) {
                // Use the setting if available
                $first = (int)$batch_from_settings;

                if ($this->debug_mode) {
                    $this->logger->debug("Using batch size from settings for {$resource}: {$first}");
                }
            } else {
                // Otherwise check resource-specific constant
                $constant_name = 'wmsw_BATCH_SIZE_' . strtoupper($resource);
                if (defined($constant_name)) {
                    $first = constant($constant_name);

                    if ($this->debug_mode) {
                        $this->logger->debug("Using batch size from constant {$constant_name}: {$first}");
                    }
                } else {
                    // Fall back to general max import setting or default
                    $first = defined('wmsw_MAX_IMPORT_ITEMS') ? WMSW_MAX_IMPORT_ITEMS : 250;

                    if ($this->debug_mode) {
                        $this->logger->debug("Using default batch size for {$resource}: {$first}");
                    }
                }
            }
        }

        // Log final batch size when in debug mode
        if ($this->debug_mode) {
            $this->logger->debug("Starting {$resource} pagination with batch size: {$first}");
        }

        // Initialize variables for the GraphQL query
        $variables = ['first' => $first];

        // Build filters for the GraphQL query
        $variables = $this->build_graphql_filters($params, $variables);

        // Build the appropriate GraphQL query based on resource type
        $query = $this->get_resource_query($resource, isset($variables['query']));

        // The path to access in the GraphQL response
        $path = $resource;

        // Paginate through all available pages
        while ($has_next_page) {
            if ($after) {
                $variables['after'] = $after;
            }

            // Execute the GraphQL query
            $response = $this->query($query, $variables);

            if (!isset($response[$path])) {
                throw new \Exception(\esc_html("Resource '{$path}' not found in GraphQL response"));
            }

            $current = $response[$path];

            // Process the current page of results
            $all_items = $this->process_graphql_page($current, $resource, $all_items);

            // Get pagination info for next page
            $page_info = $current['pageInfo'] ?? null;
            $has_next_page = $page_info && isset($page_info['hasNextPage']) ? $page_info['hasNextPage'] : false;
            $after = $has_next_page && isset($page_info['endCursor']) ? $page_info['endCursor'] : null;

            // Log pagination progress when in debug mode
            if ($this->debug_mode) {
                $total_so_far = count($all_items);
                $has_more = $has_next_page ? "has more pages" : "complete";
                $this->logger->debug("Paginated {$resource}: {$total_so_far} items collected so far, {$has_more}");
            }
        }

        // Format the response to match REST API format expected by calling code
        if ($resource === 'products') {
            return ['products' => $all_items];
        } elseif ($resource === 'customers') {
            return ['customers' => $all_items];
        } elseif ($resource === 'orders') {
            return ['orders' => $all_items];
        } else {
            return $all_items;
        }
    }

    /**
     * Count products in the store using GraphQL with configurable batch size
     *
     * @param array $filters GraphQL filter variables
     * @return array Product count with both 'count' and 'totalCount' keys for compatibility
     */
    public function countProducts($filters = [])
    {
        // Build query arguments based on filters
        $filterQuery = '';
        $variables = [];

        // Create filter conditions if any are provided
        if (!empty($filters['query'])) {
            $filterQuery = 'query: $query';
            $variables['query'] = $filters['query'];
        }

        // Determine batch size from settings or defaults
        $batch_setting_key = 'wmsw_batch_size_count_products';
        $batch_size = $this->get_setting($batch_setting_key);

        if (!$batch_size || !is_numeric($batch_size) || $batch_size < 1) {
            // Try resource-specific count constant
            if (defined('wmsw_COUNT_BATCH_SIZE_PRODUCTS')) {
                $batch_size = WMSW_COUNT_BATCH_SIZE_PRODUCTS;

                if ($this->debug_mode) {
                    $this->logger->debug("Using product count batch size from constant: {$batch_size}");
                }
            } else {
                // Fall back to generic count constant or default
                $batch_size = defined('wmsw_MAX_COUNT_ITEMS') ? WMSW_MAX_COUNT_ITEMS : 250;

                if ($this->debug_mode) {
                    $this->logger->debug("Using default count batch size: {$batch_size}");
                }
            }
        } else {
            $batch_size = (int)$batch_size;

            if ($this->debug_mode) {
                $this->logger->debug("Using product count batch size from settings: {$batch_size}");
            }
        }

        // Since neither totalCount nor count are available directly on ProductConnection,
        // we'll use a different approach: query for nodes with configured batch size but only select id
        $query = '
        query countProducts' . (!empty($variables) ? '($query: String)' : '') . ' {
          products(' . $filterQuery . ', first: ' . $batch_size . ') {
            pageInfo {
              hasNextPage
            }
            nodes {
              id
            }
          }
        }';

        $response = $this->query($query, $variables);

        // Count the returned nodes
        $count = 0;
        if (isset($response['products']['nodes'])) {
            $count = count($response['products']['nodes']);

            // If there are more pages, we need to indicate this is a partial count
            if (
                isset($response['products']['pageInfo']['hasNextPage']) &&
                $response['products']['pageInfo']['hasNextPage']
            ) {
                if ($this->debug_mode) {
                    $this->logger->debug("Product count exceeded {$batch_size} items, returning partial count.");
                }

                // Add a note that this is a partial count
                return [
                    'count' => $count,
                    'totalCount' => $count,
                    'is_partial' => true,
                    'message' => "Count exceeds {$batch_size} items and is an approximation"
                ];
            }
        }

        // Return count with both count and totalCount keys for backwards compatibility
        return ['count' => $count, 'totalCount' => $count];
    }

    /**
     * Count customers in the store using GraphQL with configurable batch size
     *
     * @param array $filters GraphQL filter variables
     * @return array Customer count with both 'count' and 'totalCount' keys for compatibility
     */
    public function countCustomers($filters = [])
    {
        // Build query arguments based on filters
        $filterQuery = '';
        $variables = [];

        // Create filter conditions if any are provided
        if (!empty($filters['query'])) {
            $filterQuery = 'query: $query';
            $variables['query'] = $filters['query'];
        }

        // Determine batch size from settings or defaults
        $batch_setting_key = 'wmsw_batch_size_count_customers';
        $batch_size = $this->get_setting($batch_setting_key);

        if (!$batch_size || !is_numeric($batch_size) || $batch_size < 1) {
            // Try resource-specific count constant
            if (defined('wmsw_COUNT_BATCH_SIZE_CUSTOMERS')) {
                $batch_size = WMSW_COUNT_BATCH_SIZE_CUSTOMERS;

                if ($this->debug_mode) {
                    $this->logger->debug("Using customer count batch size from constant: {$batch_size}");
                }
            } else {
                // Fall back to generic count constant or default
                $batch_size = defined('wmsw_MAX_COUNT_ITEMS') ? WMSW_MAX_COUNT_ITEMS : 250;

                if ($this->debug_mode) {
                    $this->logger->debug("Using default count batch size for customers: {$batch_size}");
                }
            }
        } else {
            $batch_size = (int)$batch_size;

            if ($this->debug_mode) {
                $this->logger->debug("Using customer count batch size from settings: {$batch_size}");
            }
        }

        // Query for customers with a high limit but only select id
        $query = '
        query countCustomers' . (!empty($variables) ? '($query: String)' : '') . ' {
          customers(' . $filterQuery . ', first: ' . $batch_size . ') {
            pageInfo {
              hasNextPage
            }
            nodes {
              id
            }
          }
        }';

        $response = $this->query($query, $variables);

        // Count the returned nodes
        $count = 0;
        if (isset($response['customers']['nodes'])) {
            $count = count($response['customers']['nodes']);

            // If there are more pages, we need to indicate this is a partial count
            if (
                isset($response['customers']['pageInfo']['hasNextPage']) &&
                $response['customers']['pageInfo']['hasNextPage']
            ) {
                if ($this->debug_mode) {
                    $this->logger->debug("Customer count exceeded {$batch_size} items, returning partial count.");
                }

                // Add a note that this is a partial count
                return [
                    'count' => $count,
                    'totalCount' => $count,
                    'is_partial' => true,
                    'message' => 'Count exceeds ' . $batch_size . ' items and is an approximation'
                ];
            }
        }

        // Return count with both count and totalCount keys for backwards compatibility
        return ['count' => $count, 'totalCount' => $count];
    }

    /**
     * Count orders in the store using GraphQL with configurable batch size
     *
     * @param array $filters GraphQL filter variables
     * @return array Order count with both 'count' and 'totalCount' keys for compatibility
     */
    public function countOrders($filters = [])
    {
        // Build query arguments based on filters
        $filterQuery = '';
        $variables = [];

        // Create filter conditions if any are provided
        if (!empty($filters['query'])) {
            $filterQuery = 'query: $query';
            $variables['query'] = $filters['query'];
        }

        // Determine batch size from settings or defaults
        $batch_setting_key = 'wmsw_batch_size_count_orders';
        $batch_size = $this->get_setting($batch_setting_key);

        if (!$batch_size || !is_numeric($batch_size) || $batch_size < 1) {
            // Try resource-specific count constant
            if (defined('wmsw_COUNT_BATCH_SIZE_ORDERS')) {
                $batch_size = WMSW_COUNT_BATCH_SIZE_ORDERS;

                if ($this->debug_mode) {
                    $this->logger->debug("Using order count batch size from constant: {$batch_size}");
                }
            } else {
                // Fall back to generic count constant or default
                $batch_size = defined('wmsw_MAX_COUNT_ITEMS') ? WMSW_MAX_COUNT_ITEMS : 250;

                if ($this->debug_mode) {
                    $this->logger->debug("Using default count batch size for orders: {$batch_size}");
                }
            }
        } else {
            $batch_size = (int)$batch_size;

            if ($this->debug_mode) {
                $this->logger->debug("Using order count batch size from settings: {$batch_size}");
            }
        }

        // Query for orders with a high limit but only select id
        $query = '
        query countOrders' . (!empty($variables) ? '($query: String)' : '') . ' {
          orders(' . $filterQuery . ', first: ' . $batch_size . ') {
            pageInfo {
              hasNextPage
            }
            nodes {
              id
            }
          }
        }';

        $response = $this->query($query, $variables);

        // Count the returned nodes
        $count = 0;
        if (isset($response['orders']['nodes'])) {
            $count = count($response['orders']['nodes']);

            // If there are more pages, we need to indicate this is a partial count
            if (
                isset($response['orders']['pageInfo']['hasNextPage']) &&
                $response['orders']['pageInfo']['hasNextPage']
            ) {
                if ($this->debug_mode) {
                    $this->logger->debug("Order count exceeded {$batch_size} items, returning partial count.");
                }

                // Add a note that this is a partial count
                return [
                    'count' => $count,
                    'totalCount' => $count,
                    'is_partial' => true,
                    'message' => 'Count exceeds ' . $batch_size . ' items and is an approximation'
                ];
            }
        }

        // Return count with both count and totalCount keys for backwards compatibility
        return ['count' => $count, 'totalCount' => $count];
    }

    /**
     * Get a single product by ID using GraphQL
     *
     * @param string $id Product ID (must be a Shopify global ID)
     * @return array Product data
     */
    public function getProduct($id)
    {
        $query = '
        query getProduct($id: ID!) {
          product(id: $id) {
            id
            title
            handle
            description
            descriptionHtml
            createdAt
            updatedAt
            publishedAt
            vendor
            productType
            tags            variants(first: 50) {
              nodes {
                id
                title
                price
                compareAtPrice
                sku
                position
                inventoryQuantity
                taxable
                availableForSale
              }
            }
            images(first: 50) {
              nodes {
                id
                src
                altText
                width
                height
              }
            }
          }
        }';
        $response = $this->query($query, ['id' => $id]);
        // Transform GraphQL response to REST API format
        if (isset($response['product'])) {
            $product = $response['product'];

            // Process the product node using our helper method
            $product = $this->processProductNode($product);

            return $product;
        }

        return [];
    }

    /**
     * Get Shopify resource via GraphQL
     *
     * This method provides backwards compatibility for REST-style API calls
     * but uses GraphQL under the hood
     *
     * @param string $resource Resource name (e.g. 'shop', 'products', etc.)
     * @param int|string $id Optional resource ID
     * @param array $params Optional query parameters
     * @return array Response data
     */
    public function get($resource, $id = null, $params = [])
    {
        switch ($resource) {
            case 'shop':
                return $this->getShop();

            case 'products':
                if ($id) {
                    return ['product' => $this->getProduct($id)];
                } else if (isset($params['count_only']) && $params['count_only']) {
                    // Return only the count of products
                    return $this->countProducts($params);
                } else {
                    // Use our unified pagination system which properly handles
                    // all the cursor-based pagination and status handling
                    return $this->get_paginated('products', $params);
                }

            case 'customers':
                if (isset($params['count_only']) && $params['count_only']) {
                    // Return only the count of customers
                    return $this->countCustomers($params);
                } else {
                    return $this->getCustomers($params);
                }

            case 'orders':
                if (isset($params['count_only']) && $params['count_only']) {
                    // Return only the count of orders
                    return $this->countOrders($params);
                } else {
                    return $this->getOrders($params);
                }

            default:
                throw new \Exception(wp_kses("Resource type '{$resource}' is not supported in GraphQL implementation"));
        }
    }

    /**
     * Get shop information using GraphQL
     *
     * @return array Shop data
     */
    public function getShop()
    {
        $query = '
        {
          shop {
            id
            name
            email
            myshopifyDomain
            primaryDomain {
              url
              host
            }
            plan {
              displayName
              shopifyPlus
            }
          }
        }';

        $response = $this->query($query);

        if (isset($response['shop'])) {
            // Transform GraphQL response to match REST API format for backward compatibility
            return [
                'shop' => [
                    'id' => $response['shop']['id'],
                    'name' => $response['shop']['name'],
                    'email' => $response['shop']['email'],
                    'domain' => $response['shop']['myshopifyDomain'],
                    'shop_owner' => null, // Not available in basic GraphQL query
                    'plan_name' => $response['shop']['plan']['displayName'] ?? null,
                    'is_plus' => $response['shop']['plan']['shopifyPlus'] ?? false
                ]
            ];
        }

        return [];
    }


    /**
     * Get customers using GraphQL with configurable batch sizes
     *
     * @param array $params Query parameters
     * @return array Customer data
     */
    public function getCustomers($params = [])
    {
        // Get batch size from parameters, settings, or constants
        $first = isset($params['limit']) ? (int)$params['limit'] : null;

        // If no explicit limit provided, check for customer-specific batch size setting
        if ($first === null) {
            $batch_setting_key = 'wmsw_batch_size_customers';
            $batch_from_settings = $this->get_setting($batch_setting_key);

            if ($batch_from_settings !== null && is_numeric($batch_from_settings) && $batch_from_settings > 0) {
                $first = (int)$batch_from_settings;

                if ($this->debug_mode) {
                    $this->logger->debug("Using customer batch size from settings: {$first}");
                }
            } elseif (defined('wmsw_BATCH_SIZE_CUSTOMERS')) {
                $first = WMSW_BATCH_SIZE_CUSTOMERS;

                if ($this->debug_mode) {
                    $this->logger->debug("Using customer batch size from constant: {$first}");
                }
            }
        }

        // If single-page request with pagination cursor
        if (isset($params['page_info'])) {
            // This is a legacy single-page request with pagination token
            $params['after'] = $params['page_info'];

            // Log if in debug mode
            if ($this->debug_mode) {
                $this->logger->debug("Legacy getCustomers pagination request with cursor: " . $params['page_info']);
            }
        }

        // Use the get_paginated helper for consistent pagination implementation
        return $this->get_paginated('customers', $params, $first);
    }

    /**
     * Get orders using GraphQL with configurable batch sizes
     *
     * @param array $params Query parameters
     * @return array Order data
     */
    public function getOrders($params = [])
    {
        // Get batch size from parameters, settings, or constants
        $first = isset($params['limit']) ? (int)$params['limit'] : null;

        // If no explicit limit provided, check for order-specific batch size setting
        if ($first === null) {
            $batch_setting_key = 'wmsw_batch_size_orders';
            $batch_from_settings = $this->get_setting($batch_setting_key);

            if ($batch_from_settings !== null && is_numeric($batch_from_settings) && $batch_from_settings > 0) {
                $first = (int)$batch_from_settings;

                if ($this->debug_mode) {
                    $this->logger->debug("Using order batch size from settings: {$first}");
                }
            } elseif (defined('wmsw_BATCH_SIZE_ORDERS')) {
                $first = WMSW_BATCH_SIZE_ORDERS;

                if ($this->debug_mode) {
                    $this->logger->debug("Using order batch size from constant: {$first}");
                }
            }
        }

        // If single-page request with pagination cursor
        if (isset($params['page_info'])) {
            // This is a legacy single-page request with pagination token
            $params['after'] = $params['page_info'];

            // Log if in debug mode
            if ($this->debug_mode) {
                $this->logger->debug("Legacy getOrders pagination request with cursor: " . $params['page_info']);
            }
        }

        // Use the get_paginated helper for consistent pagination implementation
        return $this->get_paginated('orders', $params, $first);
    }

    /**
     * Get GraphQL query for a specific resource type
     *
     * @param string $resource The resource type (products, customers, orders)
     * @param bool $hasQueryFilter Whether a query filter is being applied
     * @return string The GraphQL query
     * @throws \Exception If resource type is not supported
     */
    private function get_resource_query($resource, $hasQueryFilter = false)
    {
        $queryParam = $hasQueryFilter ? ', $query: String' : '';
        $queryFilter = $hasQueryFilter ? ', query: $query' : '';

        switch ($resource) {
            case 'products':
                return '
                query getProducts($first: Int!, $after: String' . $queryParam . ') {
                  products(first: $first, after: $after' . $queryFilter . ') {
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                    nodes {
                      id
                      title
                      handle
                      description
                      descriptionHtml
                      createdAt
                      updatedAt
                      publishedAt
                      vendor
                      productType
                      tags
                      status
                      variants(first: 10) {
                        nodes {
                          id
                          title
                          price
                          compareAtPrice
                          sku
                          position
                          inventoryQuantity
                          availableForSale
                          taxable
                        }
                      }
                      images(first: 10) {
                        nodes {
                          id
                          src
                          altText
                          width
                          height
                          url
                        }
                      }
                      collections(first: 20) {
                        nodes {
                          id
                          title
                          handle
                          description
                        }
                      }
                    }
                  }
                }';

            case 'customers':
                return '
                query getCustomers($first: Int!, $after: String' . $queryParam . ') {
                  customers(first: $first, after: $after' . $queryFilter . ') {
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                      startCursor
                      endCursor
                    }
                    edges {
                      cursor
                      node {
                        id
                        firstName
                        lastName
                        email
                        phone
                        createdAt
                        updatedAt
                        tags
                        image {
                          url
                          altText
                        }
                        addresses {
                          firstName
                          lastName
                          company
                          address1
                          address2
                          city
                          zip
                          country
                          countryCodeV2
                          province
                          provinceCode
                          phone
                        }
                        defaultAddress {
                          firstName
                          lastName
                          company
                          address1
                          address2
                          city
                          zip
                          country
                          countryCodeV2
                          province
                          provinceCode
                          phone
                        }
                        createdAt
                        updatedAt
                        state
                        image { url }
                      }
                    }
                    pageInfo {
                      hasNextPage
                      endCursor
                    }
                  }
                }';

            case 'orders':
                return '
                query getOrders($first: Int!, $after: String' . $queryParam . ') {
                  orders(first: $first, after: $after' . $queryFilter . ') {
                    pageInfo {
                      hasNextPage
                      endCursor
                    }
                    nodes {
                      id
                      name
                      createdAt
                      displayFinancialStatus
                      displayFulfillmentStatus
                      currentTotalPriceSet {
                        shopMoney {
                          amount
                          currencyCode
                        }
                      }
                      email
                      phone
                      note
                      customer {
                        id
                        email
                        firstName
                        lastName
                        phone
                      }
                      billingAddress {
                        firstName
                        lastName
                        company
                        address1
                        address2
                        city
                        province
                        country
                        zip
                        phone
                      }
                      shippingAddress {
                        firstName
                        lastName
                        company
                        address1
                        address2
                        city
                        province
                        country
                        zip
                        phone
                      }
                      lineItems(first: 20) {
                        nodes {
                          id
                          title
                          quantity
                          originalUnitPriceSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          variant {
                            id
                            sku
                            product {
                              id
                            }
                          }
                        }
                      }
                      shippingLines(first: 5) {
                        nodes {
                          title
                          originalPriceSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                        }
                      }
                      taxLines {
                        title
                        priceSet {
                          shopMoney {
                            amount
                            currencyCode
                          }
                        }
                      }
                    }
                  }
                }';

            default:
                throw new \Exception(wp_kses("Resource type '{$resource}' is not supported in GraphQL pagination"));
        }
    }

    /**
     * Transform GraphQL data from either edges->node or direct nodes structure
     *
     * @param array $data The GraphQL response section to transform
     * @return array Flattened array of items
     */
    private function transformGraphqlData($data)
    {
        $result = [];

        // Case 1: We have a direct 'nodes' array (flat structure)
        if (isset($data['nodes']) && is_array($data['nodes'])) {
            return $data['nodes']; // Already in the format we want
        }

        // Case 2: We have the traditional edges -> node structure
        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as $edge) {
                if (isset($edge['node'])) {
                    $result[] = $edge['node'];
                }
            }
            return $result;
        }

        // If neither structure is found, return empty array
        return [];
    }

    /**
     * Process a product node to ensure variants and images are properly flattened
     *
     * @param array $product The product node to process
     * @return array Processed product with flattened variants and images
     */
    private function processProductNode($product)
    {
        if ($this->debug_mode) {
            $this->logger->debug("Processing product node: " . substr(json_encode($product), 0, 100) . "...");
        }

        // Process variants - handle both structures
        if (isset($product['variants'])) {
            $product['variants'] = $this->transformGraphqlData($product['variants']);
        } else {
            $product['variants'] = [];
        }

        // Process images - handle both structures
        if (isset($product['images'])) {
            $product['images'] = $this->transformGraphqlData($product['images']);

            // Set first image as main product image for compatibility
            if (!empty($product['images'])) {
                $first_image = $product['images'][0];
                // Prefer 'url', fallback to 'src' for legacy/REST compatibility
                if (isset($first_image['url']) && !empty($first_image['url'])) {
                    $product['image'] = [
                        'url' => $first_image['url'],
                        'altText' => $first_image['altText'] ?? '',
                        'width' => $first_image['width'] ?? null,
                        'height' => $first_image['height'] ?? null
                    ];
                } elseif (isset($first_image['src']) && !empty($first_image['src'])) {
                    $product['image'] = [
                        'url' => $first_image['src'],
                        'altText' => $first_image['altText'] ?? '',
                        'width' => $first_image['width'] ?? null,
                        'height' => $first_image['height'] ?? null
                    ];
                } else {
                    $product['image'] = $first_image;
                }
            }
        } else {
            $product['images'] = [];
        }

        // Process collections - handle both structures
        if (isset($product['collections'])) {
            $product['collections'] = $this->transformGraphqlData($product['collections']);
        } else {
            $product['collections'] = [];
        }

        return $product;
    }

    /**
     * Transform GraphQL response data for line items (used for orders)
     *
     * @param array $order The order data containing line items to transform
     * @return array Order with transformed line items
     */
    private function processOrderLineItems($order)
    {
        if (isset($order['lineItems'])) {
            $order['lineItems'] = $this->transformGraphqlData($order['lineItems']);

            // Process each line item further if needed
            foreach ($order['lineItems'] as &$item) {
                // Additional line item processing can be added here if needed
            }
        } else {
            $order['lineItems'] = [];
        }

        return $order;
    }
    /**
     * Get collections (categories) from Shopify
     */
    public function get_collections()
    {
        $query = '
        {
          collections(first: 100) {
            nodes {
              id
              title
              handle
              description
              productsCount
              updatedAt
              image {
                url
                altText
              }
            }
          }
        }';
        $response = $this->query($query);
        if (isset($response['collections']['nodes'])) {
            $categories = [];
            foreach ($response['collections']['nodes'] as $node) {
                $categories[] = [
                    'id' => $node['id'],
                    'title' => $node['title'],
                    'handle' => $node['handle'],
                    'description' => $node['description'],
                    'products_count' => $node['productsCount'],
                    'updated_at' => $node['updatedAt'],
                    'image' => isset($node['image']['url']) ? $node['image']['url'] : null
                ];
            }
            return $categories;
        }
        return [];
    }


    /**
     * Import selected collections (categories) to WooCommerce
     */
    public function import_collections($category_ids)
    {
        // Fetch all collections
        $all = $this->get_collections();
        $imported = [];
        foreach ($all as $cat) {
            if (in_array($cat['id'], $category_ids)) {
                // Import to WooCommerce as product_cat - use global namespace to avoid conflicts
                $term = \wp_insert_term($cat['title'], 'product_cat', [
                    'description' => $cat['description'],
                    'slug' => $cat['handle']
                ]);

                if (!\is_wp_error($term)) {
                    // Set category image if available
                    if (!empty($cat['image'])) {
                        // Download and attach the image if available
                        $image_id = $this->maybe_download_image($cat['image']);
                        if ($image_id) {
                            \update_term_meta($term['term_id'], 'thumbnail_id', $image_id);
                        }
                    }

                    $imported[] = [
                        'title' => $cat['title'],
                        'term_id' => $term['term_id'],
                        'term_taxonomy_id' => $term['term_taxonomy_id'],
                        'products_count' => $cat['products_count']
                    ];
                }
            }
        }
        return $imported;
    }

    /**
     * Download an image from URL and attach it to the media library
     *
     * @param string $url Image URL
     * @return int|false Attachment ID or false on failure
     */
    private function maybe_download_image($url)
    {
        // Skip if empty URL
        if (empty($url)) {
            return false;
        }

        // Required for image handling functions
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download and add to media library
        $temp = \download_url($url);
        if (\is_wp_error($temp)) {
            return false;
        }

        $file_array = [
            'name' => \basename($url),
            'tmp_name' => $temp
        ];

        // Do the validation and storage stuff
        $id = \media_handle_sideload($file_array, 0);

        // Clean up the temporary file
        if (\is_file($temp)) {
            \wp_delete_file($temp);
        }

        return \is_wp_error($id) ? false : $id;
    }

    /**
     * Build GraphQL filter variables from parameters
     *
     * @param array $params Original query parameters
     * @param array $variables Current GraphQL variables
     * @return array Updated variables with query filters
     */
    private function build_graphql_filters($params, $variables = [])
    {
        // Build the GraphQL query filter string
        $queryParts = [];

        if ($this->debug_mode) {
            $this->logger->debug("Building GraphQL filters with params: " . json_encode($params));
        }

        // Handle order status filtering (for orders)
        if (!empty($params['order_status']) && is_array($params['order_status'])) {
            $status_conditions = [];
            foreach ($params['order_status'] as $status) {
                $status_conditions[] = "status:{$status}";
            }
            $queryParts[] = '(' . implode(' OR ', $status_conditions) . ')';
            if ($this->debug_mode) {
                $this->logger->debug("Adding order status filter: " . implode(' OR ', $status_conditions));
            }
        }
        // Handle raw query filter (for orders or products)
        elseif (!empty($params['query'])) {
            $queryParts[] = $params['query'];
            if ($this->debug_mode) {
                $this->logger->debug("Using raw query filter: " . $params['query']);
            }
        }

        // Handle product status filtering (for products)
        if (!empty($params['status']) || !empty($params['product_status'])) {
            // Direct status handling
            if ($params['status'] === 'draft' || $params['product_status'] === 'draft') {
                $queryParts[] = "status:draft";
                if ($this->debug_mode) {
                    $this->logger->debug("Adding status filter for draft products (via status): status:draft");
                }
            } elseif ($params['status'] === 'active' || $params['product_status'] === 'active') {
                $queryParts[] = "status:active";
                if ($this->debug_mode) {
                    $this->logger->debug("Adding status filter for active products (via status): status:active");
                }
            } else {
                // For any other status values
                $queryParts[] = "status:" . $params['status'];
                if ($this->debug_mode) {
                    $this->logger->debug("Adding direct status filter: status:" . $params['status']);
                }
            }
        } elseif (!empty($params['published_status'])) {
            // Published status handling
            if ($params['published_status'] === 'unpublished') {
                $queryParts[] = "status:draft";
                if ($this->debug_mode) {
                    $this->logger->debug("Adding status filter for draft products (via published_status): published_status:draft");
                }
            } elseif ($params['published_status'] === 'published' || $params['published_status'] === 'active') {
                $queryParts[] = "status:active";
                if ($this->debug_mode) {
                    $this->logger->debug("Adding status filter for active products (via published_status): published_status:active");
                }
            }
            // 'any' doesn't need a filter as it returns all products
        }

        // Handle other filters that can be applied through the query parameter
        if (!empty($params['product_type'])) {
            $queryParts[] = 'product_type:"' . addslashes($params['product_type']) . '"';
        }

        if (!empty($params['vendor'])) {
            $queryParts[] = 'vendor:"' . addslashes($params['vendor']) . '"';
        }

        if (!empty($params['tag'])) {
            $queryParts[] = 'tag:"' . addslashes($params['tag']) . '"';
        }

        // Combine all query parts and set the query variable if we have any filters
        if (!empty($queryParts)) {
            $variables['query'] = implode(' AND ', $queryParts);
            if ($this->debug_mode) {
                $this->logger->debug("GraphQL query filter constructed: " . $variables['query']);
            }
        }

        return $variables;
    }


    /**
     * Process a page of GraphQL results and extract/transform item data
     *
     * @param array $page Current page of GraphQL results
     * @param string $resource Resource type being processed (products, customers, orders)
     * @param array $all_items Existing collected items to append to
     * @return array Updated collection with new items added
     */
    private function process_graphql_page($page, $resource, $all_items)
    {
        // Extract items from the GraphQL nodes structure
        $items = $this->transformGraphqlData($page);

        // Add debug logging when in debug mode
        if ($this->debug_mode) {
            $this->logger->debug("Processing " . count($items) . " {$resource} from GraphQL page");
        }

        // Process items based on resource type
        foreach ($items as &$item) {
            switch ($resource) {
                case 'products':
                    $item = $this->processProductNode($item);
                    break;

                case 'orders':
                    $item = $this->processOrderLineItems($item);
                    break;

                    // No special processing needed for customers and other types
            }
        }

        // Add items to the collection
        return array_merge($all_items, $items);
    }

    /**
     * Get a plugin setting from WordPress options
     *
     * @param string $key The setting key to retrieve
     * @param mixed $default Default value if setting not found
     * @return mixed The setting value or default if not found
     */
    private function get_setting($key, $default = null)
    {
        // Try to get setting directly
        $value = call_user_func('get_option', $key, null);

        if ($value !== null) {
            return $value;
        }

        // If not found, try to get from the main plugin settings array
        $all_settings = call_user_func('get_option', 'wmsw_settings', []);

        if (is_array($all_settings) && isset($all_settings[$key])) {
            return $all_settings[$key];
        }

        return $default;
    }

    /**
     * Get pages using REST API (pages don't support GraphQL)
     *
     * @param array $params Query parameters
     * @return array Array containing pages and pagination info
     */
    public function get_pages_rest($params = [])
    {
        $url = "https://{$this->shop_domain}.myshopify.com/admin/api/{$this->api_version}/pages.json";

        // Build query parameters
        $query_params = [];

        // Handle limit
        if (isset($params['limit'])) {
            $query_params['limit'] = min((int)$params['limit'], 250); // Shopify max is 250
        } else {
            $query_params['limit'] = 50; // Default limit
        }

        // Handle pagination
        if (isset($params['since_id'])) {
            $query_params['since_id'] = $params['since_id'];
        }

        // Handle published status filter
        if (isset($params['published_status'])) {
            if ($params['published_status'] === 'published') {
                $query_params['published_status'] = 'published';
            } elseif ($params['published_status'] === 'unpublished') {
                $query_params['published_status'] = 'unpublished';
            }
        }

        // Handle fields selection
        if (isset($params['fields'])) {
            $query_params['fields'] = $params['fields'];
        }

        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($this->debug_mode) {
            $this->logger->debug("Making REST API request for pages: " . $url);
        }

        // Make REST API request
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API request failed: " . $response->get_error_message());
            }
            return [
                'pages' => [],
                'errors' => ['Failed to connect to Shopify API: ' . $response->get_error_message()]
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API returned status code: {$status_code}, body: " . $body);
            }
            return [
                'pages' => [],
                'errors' => ["API request failed with status {$status_code}"]
            ];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug_mode) {
                $this->logger->debug("Failed to decode JSON response: " . json_last_error_msg());
            }
            return [
                'pages' => [],
                'errors' => ['Failed to decode API response']
            ];
        }

        if (!isset($data['pages']) || !is_array($data['pages'])) {
            if ($this->debug_mode) {
                $this->logger->debug("Invalid response structure: " . json_encode($data, JSON_PRETTY_PRINT));
            }
            return [
                'pages' => [],
                'errors' => ['Invalid response from Shopify API']
            ];
        }

        $pages = $data['pages'];

        if ($this->debug_mode) {
            $this->logger->debug("Retrieved " . count($pages) . " pages from REST API");
        }

        return [
            'pages' => $pages
        ];
    }

    /**
     * Get blogs using REST API
     *
     * @param array $params Query parameters
     * @return array Array containing blogs and pagination info
     */
    public function get_blogs_rest($params = [])
    {
        $url = "https://{$this->shop_domain}.myshopify.com/admin/api/{$this->api_version}/blogs.json";

        // Build query parameters
        $query_params = [];

        // Handle limit
        if (isset($params['limit'])) {
            $query_params['limit'] = min((int)$params['limit'], 250); // Shopify max is 250
        } else {
            $query_params['limit'] = 50; // Default limit
        }

        // Handle pagination
        if (isset($params['since_id'])) {
            $query_params['since_id'] = $params['since_id'];
        }

        // Handle page_info pagination (newer method)
        if (isset($params['page_info'])) {
            $query_params['page_info'] = $params['page_info'];
        }

        // Handle title filter
        if (isset($params['title'])) {
            $query_params['title'] = $params['title'];
        }

        // Handle handle filter
        if (isset($params['handle'])) {
            $query_params['handle'] = $params['handle'];
        }

        // Handle created date filters
        if (isset($params['created_at_min'])) {
            $query_params['created_at_min'] = $params['created_at_min'];
        }

        if (isset($params['created_at_max'])) {
            $query_params['created_at_max'] = $params['created_at_max'];
        }

        // Handle updated date filters
        if (isset($params['updated_at_min'])) {
            $query_params['updated_at_min'] = $params['updated_at_min'];
        }

        if (isset($params['updated_at_max'])) {
            $query_params['updated_at_max'] = $params['updated_at_max'];
        }

        // Handle fields selection
        if (isset($params['fields'])) {
            $query_params['fields'] = $params['fields'];
        }

        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($this->debug_mode) {
            $this->logger->debug("Making REST API request for blogs: " . $url);
        }

        // Make REST API request
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API request failed: " . $response->get_error_message());
            }
            return [
                'blogs' => [],
                'errors' => ['Failed to connect to Shopify API: ' . $response->get_error_message()]
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API returned status code: {$status_code}, body: " . $body);
            }
            return [
                'blogs' => [],
                'errors' => ["API request failed with status {$status_code}"]
            ];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug_mode) {
                $this->logger->debug("Failed to decode JSON response: " . json_last_error_msg());
            }
            return [
                'blogs' => [],
                'errors' => ['Failed to decode API response']
            ];
        }

        if (!isset($data['blogs']) || !is_array($data['blogs'])) {
            if ($this->debug_mode) {
                $this->logger->debug("Invalid response structure: " . json_encode($data, JSON_PRETTY_PRINT));
            }
            return [
                'blogs' => [],
                'errors' => ['Invalid response from Shopify API']
            ];
        }

        $blogs = $data['blogs'];

        if ($this->debug_mode) {
            $this->logger->debug("Retrieved " . count($blogs) . " blogs from REST API");
        }

        return [
            'blogs' => $blogs
        ];
    }

    /**
     * Get articles for a specific blog using REST API
     * 
     * @param int $blog_id Blog ID
     * @param array $params Optional parameters
     * @return array Array containing articles and pagination info
     */
    public function get_articles_rest($blog_id, $params = [])
    {
        $url = "https://{$this->shop_domain}.myshopify.com/admin/api/{$this->api_version}/blogs/{$blog_id}/articles.json";

        // Build query parameters
        $query_params = [];

        // Handle limit
        if (isset($params['limit'])) {
            $query_params['limit'] = min((int)$params['limit'], 250); // Shopify max is 250
        } else {
            $query_params['limit'] = 50; // Default limit
        }

        // Handle pagination
        if (isset($params['since_id'])) {
            $query_params['since_id'] = $params['since_id'];
        }

        // Handle page_info pagination (newer method)
        if (isset($params['page_info'])) {
            $query_params['page_info'] = $params['page_info'];
        }

        // Handle published status filter
        if (isset($params['published_status'])) {
            $query_params['published_status'] = $params['published_status'];
        }

        // Handle created date filters
        if (isset($params['created_at_min'])) {
            $query_params['created_at_min'] = $params['created_at_min'];
        }

        if (isset($params['created_at_max'])) {
            $query_params['created_at_max'] = $params['created_at_max'];
        }

        // Handle updated date filters
        if (isset($params['updated_at_min'])) {
            $query_params['updated_at_min'] = $params['updated_at_min'];
        }

        if (isset($params['updated_at_max'])) {
            $query_params['updated_at_max'] = $params['updated_at_max'];
        }

        // Handle fields selection
        if (isset($params['fields'])) {
            $query_params['fields'] = $params['fields'];
        }

        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($this->debug_mode) {
            $this->logger->debug("Making REST API request for articles: " . $url);
        }

        // Make REST API request
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API request failed: " . $response->get_error_message());
            }
            return [
                'success' => false,
                'articles' => [],
                'errors' => ['Failed to connect to Shopify API: ' . $response->get_error_message()]
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            if ($this->debug_mode) {
                $this->logger->debug("REST API returned status code: {$status_code}, body: " . $body);
            }
            return [
                'success' => false,
                'articles' => [],
                'errors' => ["API request failed with status {$status_code}"]
            ];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug_mode) {
                $this->logger->debug("Failed to decode JSON response: " . json_last_error_msg());
            }
            return [
                'success' => false,
                'articles' => [],
                'errors' => ['Failed to decode API response']
            ];
        }

        if (!isset($data['articles']) || !is_array($data['articles'])) {
            if ($this->debug_mode) {
                $this->logger->debug("Invalid response structure: " . json_encode($data, JSON_PRETTY_PRINT));
            }
            return [
                'success' => false,
                'articles' => [],
                'errors' => ['Invalid response from Shopify API']
            ];
        }

        $articles = $data['articles'];

        if ($this->debug_mode) {
            $this->logger->debug("Retrieved " . count($articles) . " articles from REST API");
        }

        return [
            'success' => true,
            'articles' => $articles
        ];
    }

    /**
     * Get price rules using REST API
     *
     * @param array $params Query parameters
     * @return array Array containing price rules
     */
    public function get_price_rules($params = [])
    {
        $url = "https://{$this->shop_domain}.myshopify.com/admin/api/{$this->api_version}/price_rules.json";

        // Build query parameters
        $query_params = [];

        // Handle limit
        if (isset($params['limit'])) {
            $query_params['limit'] = min((int)$params['limit'], 250); // Shopify max is 250
        } else {
            $query_params['limit'] = 50; // Default limit
        }

        // Handle pagination
        if (isset($params['since_id'])) {
            $query_params['since_id'] = $params['since_id'];
        }

        // Handle date filters
        if (isset($params['starts_at_min'])) {
            $query_params['starts_at_min'] = $params['starts_at_min'];
        }
        if (isset($params['starts_at_max'])) {
            $query_params['starts_at_max'] = $params['starts_at_max'];
        }
        if (isset($params['ends_at_min'])) {
            $query_params['ends_at_min'] = $params['ends_at_min'];
        }
        if (isset($params['ends_at_max'])) {
            $query_params['ends_at_max'] = $params['ends_at_max'];
        }

        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($this->debug_mode) {
            $this->logger->debug("Making REST API request for price rules: " . $url);
        }

        // Make REST API request
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(wp_kses('Failed to fetch price rules: ' . $response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception(wp_kses('Failed to fetch price rules. Status: ' . $status_code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['price_rules'])) {
            throw new \Exception(wp_kses('Invalid response format from Shopify API'));
        }

        $price_rules = $data['price_rules'];

        if ($this->debug_mode) {
            $this->logger->debug("Retrieved " . count($price_rules) . " price rules from REST API");
        }

        return $price_rules;
    }

    /**
     * Get discount codes for a specific price rule using REST API
     *
     * @param int $price_rule_id The price rule ID
     * @param array $params Query parameters
     * @return array Array containing discount codes
     */
    public function get_discount_codes($price_rule_id, $params = [])
    {
        $url = "https://{$this->shop_domain}.myshopify.com/admin/api/{$this->api_version}/price_rules/{$price_rule_id}/discount_codes.json";

        // Build query parameters
        $query_params = [];

        // Handle limit
        if (isset($params['limit'])) {
            $query_params['limit'] = min((int)$params['limit'], 250); // Shopify max is 250
        } else {
            $query_params['limit'] = 50; // Default limit
        }

        // Handle pagination
        if (isset($params['since_id'])) {
            $query_params['since_id'] = $params['since_id'];
        }

        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($this->debug_mode) {
            $this->logger->debug("Making REST API request for discount codes: " . $url);
        }

        // Make REST API request
        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $this->access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(wp_kses('Failed to fetch discount codes: ' . $response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception(wp_kses('Failed to fetch discount codes. Status: ' . $status_code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['discount_codes'])) {
            throw new \Exception(wp_kses('Invalid response format from Shopify API'));
        }

        $discount_codes = $data['discount_codes'];

        if ($this->debug_mode) {
            $this->logger->debug("Retrieved " . count($discount_codes) . " discount codes for price rule {$price_rule_id}");
        }

        return $discount_codes;
    }
}
