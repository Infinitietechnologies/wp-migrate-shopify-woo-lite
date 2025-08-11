<?php

namespace ShopifyWooImporter\Handlers;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Models\WMSW_ShopifyStore;
use ShopifyWooImporter\Processors\WMSW_ProductProcessor;
use ShopifyWooImporter\Services\WMSW_Logger;
use ShopifyWooImporter\Helpers\WMSW_SecurityHelper;
use ShopifyWooImporter\Processors\WMSW_CustomerProcessor;

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

/**
 * Customer Handler
 *
 * Handles customer preview and import functionality
 */
class WMSW_CustomerHandler
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
        // Register AJAX handler for customer preview
        add_action('wp_ajax_wmsw_preview_customers', [$this, 'previewCustomers']);
        add_action('wp_ajax_wmsw_start_customers_import', [$this, 'startCustomersImport']);
        add_action('wp_ajax_wmsw_get_customer_import_progress', [$this, 'getImportProgress']);
        add_action('wp_ajax_wmsw_check_active_customer_imports', [$this, 'checkActiveImports']);

        // Register handler for background customer import
        add_action('wmsw_process_customer_import', [$this, 'processCustomerImport'], 10, 3);
    }

    /**
     * Handle AJAX request for customer preview
     */
    public function previewCustomers()
    {
        // Verify security
        WMSW_SecurityHelper::verifyAdminRequest();

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

        // Check if store exists and has required fields
        if (empty($store->get_id())) {
            wp_send_json_error([
                'message' => __('Store not found', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Get options from request
        $options = isset($_POST['options']) ? array_map('sanitize_text_field', $_POST['options']) : [];

        // Set preview limit
        $options['limit'] = isset($options['preview_limit']) ? intval($options['preview_limit']) : 10;
        $options['preview_mode'] = true;

        // Log all filter options for debugging
        $this->logger->debug('Filter options: ' . json_encode($options));

        try {
            // Get Shopify client
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Get customers for preview
            $customers = $this->fetchPreviewCustomers($shopify_client, $options);

            if (empty($customers)) {
                wp_send_json_error([
                    'message' => __('No customers found matching your criteria', 'wp-migrate-shopify-woo')
                ]);
                return;
            }

            // Format customers for preview display
            $preview_data = $this->formatCustomersForPreview($customers);

            // Get a sample of the formatted data for debugging
            $sample_data = !empty($preview_data) ? $preview_data[0] : [];
            $this->logger->debug('Sample of formatted preview data: ' . json_encode($sample_data));

            // Send success response with debug info
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %d: number of customers found */
                    __('Found %d customers matching your criteria', 'wp-migrate-shopify-woo'),
                    count($customers)
                ),
                'preview_data' => $preview_data,
                'total_count' => count($customers)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Customer preview error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch customers from Shopify API for preview
     *
     * @param WMSW_ShopifyClient $client Shopify API client
     * @param array $options Query options
     * @return array Array of customers
     */
    private function fetchPreviewCustomers($client, $options)
    {
        // Build query parameters for Shopify API
        $query_params = $this->buildApiQueryParams($options);

        // Log the query parameters for debugging
        $this->logger->debug('Shopify API query parameters: ' . json_encode($query_params));

        // Get customers from Shopify API
        $response = $client->get_paginated('customers', $query_params);

        // Log the API response summary
        $customer_count = isset($response['customers']) ? count($response['customers']) : 0;
        $this->logger->debug('Shopify API returned ' . $customer_count . ' customers');

        // Only log detailed API response in debug mode
        if ($this->logger->isDebugEnabled()) {
            error_log('Shopify API response: ' . print_r($response, true));
        }

        if (isset($response['errors'])) {
            $this->logger->error('Error retrieving customers: ' . print_r($response['errors'], true));
            return [];
        }

        if (!isset($response['customers']) || !is_array($response['customers'])) {
            $this->logger->error('Invalid customer response from Shopify API');
            return [];
        }

        return $response['customers'];
    }

    /**
     * Build query parameters for Shopify API based on filter options
     *
     * @param array $options Filter options from form
     * @return array Query parameters for Shopify API
     */
    private function buildApiQueryParams($options)
    {
        $params = [
            'limit' => isset($options['limit']) ? intval($options['limit']) : 50,
            'fields' => 'id,firstName,lastName,email,phone,tags,addresses,default_address,created_at,updated_at,orders_count,total_spent,state'
        ];

        // Add any additional filters based on options
        if (!empty($options['since_id'])) {
            $params['since_id'] = $options['since_id'];
        }

        // Add customer state filter if provided
        if (!empty($options['customer_state'])) {
            $params['status'] = sanitize_text_field($options['customer_state']);
        }

        // Add tags filter if provided
        if (!empty($options['tags'])) {
            $params['tags'] = sanitize_text_field($options['tags']);
        }

        // Add date range filters if provided
        if (!empty($options['date_from'])) {
            $params['created_at_min'] = sanitize_text_field($options['date_from']) . 'T00:00:00+00:00';
        }

        if (!empty($options['date_to'])) {
            $params['created_at_max'] = sanitize_text_field($options['date_to']) . 'T23:59:59+00:00';
        }

        return $params;
    }

    /**
     * Format customers data for preview display
     *
     * @param array $customers Raw customers data from Shopify API
     * @return array Formatted customers data for preview
     */
    private function formatCustomersForPreview($customers)
    {
        $formatted_customers = [];

        foreach ($customers as $customer) {
            // Format name with support for multiple key styles
            $first_name = $customer['firstName'] ?? $customer['first_name'] ?? '';
            $last_name = $customer['lastName'] ?? $customer['last_name'] ?? '';
            $full_name = trim($first_name . ' ' . $last_name);

            // Fallback to 'name' field if first + last is not available
            if (empty($full_name)) {
                $full_name = trim($customer['name'] ?? '');
            }

            // Final fallback
            $name = $full_name ?: 'Unnamed Customer';

            // Format addresses - prefer default_address if available
            $primary_address = [];

            // First check if there's a default_address field
            if (!empty($customer['default_address'])) {
                $primary_address = $customer['default_address'];
            }
            // Otherwise check addresses array for a default address
            elseif (!empty($customer['addresses'])) {
                foreach ($customer['addresses'] as $addr) {
                    if (!empty($addr['default']) && $addr['default'] === true) {
                        $primary_address = $addr;
                        break;
                    }
                }

                // If no default address found, use the first one
                if (empty($primary_address) && count($customer['addresses']) > 0) {
                    $primary_address = $customer['addresses'][0];
                }
            }

            $address = '';
            if (!empty($primary_address)) {
                $address_parts = [];

                // Support both camelCase and snake_case fields
                $address1 = !empty($primary_address['address1']) ? $primary_address['address1'] : (!empty($primary_address['address_1']) ? $primary_address['address_1'] : '');

                $city = !empty($primary_address['city']) ? $primary_address['city'] : '';

                $province = !empty($primary_address['province']) ? $primary_address['province'] : (!empty($primary_address['state']) ? $primary_address['state'] : '');

                $country = !empty($primary_address['country']) ? $primary_address['country'] : '';

                if (!empty($address1)) $address_parts[] = $address1;
                if (!empty($city)) $address_parts[] = $city;
                if (!empty($province)) $address_parts[] = $province;
                if (!empty($country)) $address_parts[] = $country;

                $address = implode(', ', $address_parts);

                // Log what address we're using for debug
                $this->logger->debug('Customer address for preview', [
                    'address_parts' => $address_parts,
                    'formatted_address' => $address,
                    'customer_id' => $customer['id']
                ]);
            }

            // Format customer for preview
            $formatted_customers[] = [
                'id' => $customer['id'],
                'name' => $name,
                'image' => isset($customer['image']['url']) ? trim($customer['image']['url']) : '',
                'altText' => isset($customer['image']['altText']) ? trim($customer['image']['altText']) : '',
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'address' => $address,
                'tags' => $customer['tags'] ?? '',
                'orders_count' => $customer['orders_count'] ?? 0,
                'total_spent' => number_format((float)($customer['totalSpent'] ?? $customer['total_spent'] ?? 0), 2),
                'created_at' => isset($customer['createdAt']) ? gmdate('M j, Y', strtotime($customer['createdAt'])) : (isset($customer['created_at']) ? gmdate('M j, Y', strtotime($customer['created_at'])) : ''),
                'status' => $customer['state'] ?? 'enabled'
            ];
        }

        return $formatted_customers;
    }


    /**
     * Start customer import process
     */
    public function startCustomersImport()
    {
        // Verify security
        WMSW_SecurityHelper::verifyAdminRequest();

        // Validate store ID
        if (empty($_POST['store_id'])) {
            wp_send_json_error([
                'message' => __('No store selected.', 'wp-migrate-shopify-woo')
            ]);
        }

        $store_id = intval($_POST['store_id']);
        $store_details = new WMSW_ShopifyStore();
        $store = $store_details->find($store_id);

        if (empty($store->get_id())) {
            wp_send_json_error([
                'message' => __('Invalid store selected.', 'wp-migrate-shopify-woo')
            ]);
        }

        // Collect import options
        $options = [
            'import_addresses' => isset($_POST['import_addresses']) && $_POST['import_addresses'] == '1',
            'import_tags' => isset($_POST['import_tags']) && $_POST['import_tags'] == '1',
            'send_welcome_email' => isset($_POST['send_welcome_email']) && $_POST['send_welcome_email'] == '1',
            'batch_size' => isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10,
            'customer_state' => !empty($_POST['customer_state']) ? sanitize_text_field($_POST['customer_state']) : '',
            'tags' => !empty($_POST['tags']) ? sanitize_text_field($_POST['tags']) : '',
            'date_from' => !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'overwrite_existing' => true, // Set to true by default to match processor's default
        ];

        // Generate a unique job ID for tracking this import
        $job_id = 'customer_import_' . uniqid();

        // Store import options and status in a transient for progress tracking
        $import_data = [
            'store_id' => $store_id,
            'options' => $options,
            'status' => [
                'total' => 0,
                'imported' => 0,
                'updated' => 0, // Ensure 'updated' key is always present
                'skipped' => 0,
                'failed' => 0,
                'percentage' => 0,
                'completed' => false,
                'last_message' => __('Starting import...', 'wp-migrate-shopify-woo'),
                'start_time' => time(),
                'last_update' => time()
            ],
            'log' => []
        ];

        \set_transient($job_id, $import_data, 6 * 3600); // 6 hours

        // Schedule the background process
        wp_schedule_single_event(time(), 'wmsw_process_customer_import', [
            $store_id,
            $options,
            $job_id
        ]);

        // Log the start of the import process
        $this->logger->info(
            'Customer import started',
            [
                'store_id' => $store_id,
                'job_id' => $job_id,
                'options' => $options
            ]
        );

        wp_send_json_success([
            'message' => __('Customer import started successfully! You can close this page and the import will continue in the background.', 'wp-migrate-shopify-woo'),
            'job_id' => $job_id
        ]);
    }

    // Implementation is moved to the bottom of the class

    /**
     * Process customer import (background process)
     */
    public function processCustomerImport($store_id, $options, $job_id)
    {
        // Get the import data from transient
        $import_data = \get_transient($job_id);

        if (!$import_data) {
            $this->logger->error('Import data not found for job: ' . $job_id);
            return;
        }

        // Start by logging the process (always include job_id in message for DB log clarity)
        $this->logger->info('Starting customer import process for job: ' . $job_id . ' (store_id: ' . $store_id . ')', [
            'job_id' => $job_id,
            'store_id' => $store_id,
        ]);

        // Get store details
        $store_details = new WMSW_ShopifyStore();
        $store = $store_details->find($store_id);

        if (empty($store->get_id())) {
            $this->logger->error('Store not found: ' . $store_id);
            $import_data['status']['completed'] = true;
            $import_data['status']['last_message'] = __('Store not found', 'wp-migrate-shopify-woo');
            \set_transient($job_id, $import_data, 6 * 3600);
            return;
        }

        try {
            // Get Shopify client
            $shopify_client = new WMSW_ShopifyClient(
                $store->get_shop_domain(),
                $store->get_access_token(),
                $store->get_api_version()
            );

            // Use a logger instance tied to this job_id for DB logging
            $logger = new WMSW_Logger($job_id);
            $processor = new WMSW_CustomerProcessor($shopify_client, $logger);

            // Update the status
            $import_data['status']['last_message'] = __('Fetching customers from Shopify...', 'wp-migrate-shopify-woo');
            \set_transient($job_id, $import_data, 6 * 3600);

            // Process the import (one batch)
            $results = $processor->import_customers($options);

            // Update status counts
            $import_data['status']['imported'] += isset($results['imported']) ? $results['imported'] : 0;
            $import_data['status']['updated']   += isset($results['updated'])   ? $results['updated']   : 0;
            $import_data['status']['failed']    += isset($results['failed'])    ? $results['failed']    : 0;
            $import_data['status']['skipped']   += isset($results['skipped'])   ? $results['skipped']   : 0;
            // Ensure all keys exist to avoid undefined array key warnings
            foreach (['imported','updated','failed','skipped'] as $k) {
                if (!isset($import_data['status'][$k])) $import_data['status'][$k] = 0;
            }
            $import_data['status']['total'] = $import_data['status']['imported'] + $import_data['status']['updated'] +
                $import_data['status']['failed'] + $import_data['status']['skipped'];

            // Add log messages if available
            if (!empty($results['log'])) {
                $import_data['log'] = array_merge($import_data['log'], $results['log']);
            }

            // If there are more customers, schedule the next batch
            if (!empty($results['has_next_page']) && !empty($results['next_cursor'])) {
                $import_data['status']['last_message'] = __('Continuing customer import...', 'wp-migrate-shopify-woo');
                $import_data['status']['percentage'] = min(99, $import_data['status']['percentage'] + 1); // crude progress
                $import_data['status']['last_update'] = time();
                \set_transient($job_id, $import_data, 6 * 3600);
                // Schedule next batch with updated cursor
                $next_options = $options;
                $next_options['after'] = $results['next_cursor'];
                \wp_schedule_single_event(time() + 2, 'wmsw_process_customer_import', [
                    $store_id,
                    $next_options,
                    $job_id
                ]);
                $this->logger->info('Scheduled next customer import batch', [
                    'job_id' => $job_id,
                    'next_cursor' => $results['next_cursor']
                ]);
            } else {
                // Import complete
                $import_data['status']['percentage'] = 100;
                $import_data['status']['completed'] = true;
                $import_data['status']['last_message'] = sprintf(
                    /* translators: %1$d: number of imported customers, %2$d: number of updated customers, %3$d: number of failed imports, %4$d: number of skipped customers */
                    __('Import complete. Imported: %1$d, Updated: %2$d, Failed: %3$d, Skipped: %4$d', 'wp-migrate-shopify-woo'),
                    $import_data['status']['imported'],
                    $import_data['status']['updated'],
                    $import_data['status']['failed'],
                    $import_data['status']['skipped']
                );
                $import_data['status']['last_update'] = time();
                \set_transient($job_id, $import_data, 6 * 3600);
                $this->logger->info('Customer import completed', [
                    'job_id' => $job_id,
                    'results' => $results
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in customer import process: ' . $e->getMessage(), [
                'job_id' => $job_id,
                'exception' => $e
            ]);

            // Update status with error
            $import_data['status']['completed'] = true;
            $import_data['status']['last_message'] = __('Error: ', 'wp-migrate-shopify-woo') . $e->getMessage();
            $import_data['log'][] = 'Error: ' . $e->getMessage();
            \set_transient($job_id, $import_data, 6 * 3600);
        }
    }

    /**
     * Get import progress
     */
    public function getImportProgress()
    {
        // Verify security
        WMSW_SecurityHelper::verifyAdminRequest();

        // Check job ID
        if (empty($_POST['job_id'])) {
            \wp_send_json_error([
                'message' => __('Missing job ID', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        $job_id = sanitize_text_field($_POST['job_id']);

        // Get import data from transient
        $import_data = \get_transient($job_id);

        if (!$import_data) {
            \wp_send_json_error([
                'message' => __('Import job not found', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Get the last log message if available
        $last_log = '';
        if (!empty($import_data['log'])) {
            $last_log = end($import_data['log']);
        }

        // Return the progress data
        \wp_send_json_success([
            'percentage' => isset($import_data['status']['percentage']) ? $import_data['status']['percentage'] : 0,
            'message' => isset($import_data['status']['last_message']) ? $import_data['status']['last_message'] : __('Processing...', 'wp-migrate-shopify-woo'),
            'log_message' => $last_log,
            'completed' => isset($import_data['status']['completed']) ? $import_data['status']['completed'] : false,
            'imported' => isset($import_data['status']['imported']) ? $import_data['status']['imported'] : 0,
            'updated' => isset($import_data['status']['updated']) ? $import_data['status']['updated'] : 0,
            'failed' => isset($import_data['status']['failed']) ? $import_data['status']['failed'] : 0,
            'skipped' => isset($import_data['status']['skipped']) ? $import_data['status']['skipped'] : 0,
        ]);
    }

    /**
     * Check for active customer imports for a specific store
     * AJAX handler for checking if there are any active customer imports running
     */
    public function checkActiveImports()
    {
        // Verify security
        WMSW_SecurityHelper::verifyAdminRequest();

        // Get store ID
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        
        if (!$store_id) {
            wp_send_json_error([
                'message' => __('No store specified', 'wp-migrate-shopify-woo')
            ]);
            return;
        }

        // Check for active imports using transients (similar to how customer imports work)
        $active_job = null;
        
        // Search through transients for active customer import jobs for this store
        global $wpdb;
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_WMSW_customer_import_%'"
        );

        foreach ($transients as $transient) {
            $job_id = str_replace('_transient_WMSW_customer_import_', '', $transient->option_name);
            $job_data = maybe_unserialize($transient->option_value);
            
            if (is_array($job_data) && 
                isset($job_data['store_id']) && 
                $job_data['store_id'] == $store_id &&
                (!isset($job_data['status']['completed']) || !$job_data['status']['completed'])) {
                
                $active_job = [
                    'job_id' => $job_id,
                    'percentage' => isset($job_data['status']['percentage']) ? $job_data['status']['percentage'] : 0,
                    'message' => isset($job_data['status']['last_message']) ? $job_data['status']['last_message'] : 'Processing...',
                    'imported' => isset($job_data['status']['imported']) ? $job_data['status']['imported'] : 0,
                    'updated' => isset($job_data['status']['updated']) ? $job_data['status']['updated'] : 0,
                    'failed' => isset($job_data['status']['failed']) ? $job_data['status']['failed'] : 0,
                    'skipped' => isset($job_data['status']['skipped']) ? $job_data['status']['skipped'] : 0,
                    'completed' => false
                ];
                break;
            }
        }

        if ($active_job) {
            wp_send_json_success([
                'active_import' => $active_job,
                'message' => __('Active customer import found', 'wp-migrate-shopify-woo')
            ]);
        } else {
            wp_send_json_success([
                'active_import' => null,
                'message' => __('No active customer imports found', 'wp-migrate-shopify-woo')
            ]);
        }
    }
}
