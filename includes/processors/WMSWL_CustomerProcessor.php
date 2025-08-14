<?php
// We'll be using global namespace functions with backslash prefix
namespace ShopifyWooImporter\Processors;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Services\WMSW_Logger;

// We need to use the global namespace for WordPress functions
use function \wc_create_new_customer;
use function \wc_update_new_customer_past_orders;
use function \wc_get_page_permalink;
use function \get_user_by;
use function \get_bloginfo;
use function \update_user_meta;
use function \wp_update_user;
use function \sanitize_text_field;
use function \sanitize_email;
use function \wp_generate_password;
use function \wp_mail;
use function \esc_html;
use function \esc_url;
use function \esc_url_raw;
use function \is_wp_error;
use function \get_option;
use function \__;

/**
 * Customer Processor
 *
 * Handles importing customers from Shopify to WooCommerce
 */
use ShopifyWooImporter\Helpers\WMSW_PaginationHelper;

class WMSW_CustomerProcessor
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
     * @var int
     */
    private $batch_size;

    /**
     * Constructor
     *
     * @param WMSW_ShopifyClient $shopify_client The Shopify API client
     * @param WMSW_Logger $logger Optional logger instance
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
     * Import customers from Shopify
     *
     * @param array $options Import options
     * @return array Results of the import process
     */
    /**
     * Import customers from Shopify in a batch-by-batch, cursor-based way
     *
     * @param array $options Import options (may include 'after' for cursor)
     * @return array Results of the import process, including has_next_page and next_cursor
     */
    public function import_customers($options = [])
    {
        $this->logger->info('Starting customer import', ['options' => json_encode($options)]);

        // Set default options
        $options = $this->set_default_options($options);

        // Use a unique tab key for customers
        $tab = 'customers';
        // Support resuming from a cursor (from options or stored)
        $cursor = isset($options['after']) ? $options['after'] : WMSW_PaginationHelper::getCursor($tab);
        if (!empty($cursor)) {
            $options['after'] = $cursor;
            $this->logger->debug('Resuming customer import from cursor: ' . $cursor);
        }

        // Get one page of customers from Shopify
        $this->logger->debug('Fetching customers from Shopify API');
        $result = $this->get_shopify_customers($options);
        $customers = $result['customers'];
        $pageInfo = $result['pageInfo'];

        if (empty($customers)) {
            $this->logger->error('No customers found to import');
            // Clean up cursor if nothing found
            WMSW_PaginationHelper::deleteCursor($tab);
            return [
                'success' => false,
                'message' => 'No customers found to import',
                'imported' => 0,
                'updated' => 0,
                'failed' => 0,
                'has_next_page' => false
            ];
        }

        $this->logger->info('Found ' . count($customers) . ' customers to process');

        // Process customers
        $results = $this->process_customers($customers, $options);

        // Handle pagination cursor
        if (!empty($pageInfo['hasNextPage']) && !empty($pageInfo['endCursor'])) {
            WMSW_PaginationHelper::setCursor($tab, $pageInfo['endCursor']);
            $results['has_next_page'] = true;
            $results['next_cursor'] = $pageInfo['endCursor'];
        } else {
            WMSW_PaginationHelper::deleteCursor($tab);
            $results['has_next_page'] = false;
        }

        // Add a summary of the customer emails that were actually processed
        $emails_imported = [];
        $emails_updated = [];
        $emails_failed = [];
        $emails_skipped = [];

        foreach ($customers as $customer) {
            $email = isset($customer['email']) ? $customer['email'] : '';
            if (!$email) continue;
            foreach ($results['log'] as $log_entry) {
                if (strpos($log_entry, $email) !== false) {
                    if (strpos($log_entry, 'Imported new customer') !== false) {
                        $emails_imported[] = $email;
                    } elseif (strpos($log_entry, 'Updated existing customer') !== false) {
                        $emails_updated[] = $email;
                    } elseif (strpos($log_entry, 'Skipped customer') !== false) {
                        $emails_skipped[] = $email;
                    } elseif (strpos($log_entry, 'Failed') !== false) {
                        $emails_failed[] = $email;
                    }
                    break;
                }
            }
        }

        $results['imported_emails'] = $emails_imported;
        $results['updated_emails'] = $emails_updated;
        $results['failed_emails'] = $emails_failed;
        $results['skipped_emails'] = $emails_skipped;

        $this->logger->info('Customer import batch complete', [
            'imported' => $results['imported'] . ' (' . implode(', ', $emails_imported) . ')',
            'updated' => $results['updated'] . ' (' . implode(', ', $emails_updated) . ')',
            'failed' => $results['failed'] . ' (' . implode(', ', $emails_failed) . ')',
            'skipped' => $results['skipped'] . ' (' . implode(', ', $emails_skipped) . ')',
            'has_next_page' => $results['has_next_page'],
            'next_cursor' => $results['has_next_page'] ? $results['next_cursor'] : null
        ]);

        return $results;
    }

    /**
     * Set default import options
     *
     * @param array $options User-provided import options
     * @return array Completed options with defaults
     */
    private function set_default_options($options)
    {
        $defaults = [
            'import_addresses' => true,
            'import_tags' => true,
            'send_welcome_email' => false,
            'batch_size' => $this->batch_size, // Use the batch size from settings or constant
            'customer_state' => '',
            'overwrite_existing' => true
        ];

        return array_merge($defaults, $options);
    }

    /**
     * Retrieve customers from Shopify
     *
     * @param array $options Query options
     * @return array Array of Shopify customers
     */
    /**
     * Retrieve a single page of customers from Shopify using GraphQL pagination
     * Returns ['customers' => [...], 'pageInfo' => [...]]
     */
    private function get_shopify_customers($options)
    {
        try {
            $limit = isset($options['batch_size']) ? (int)$options['batch_size'] : $this->batch_size;
            $after = isset($options['after']) ? $options['after'] : null;
            $filters = [];
            if (!empty($options['customer_state'])) {
                $filters[] = 'state:' . strtoupper(\sanitize_text_field($options['customer_state']));
            }
            if (!empty($options['tags'])) {
                $filters[] = 'tag:' . \sanitize_text_field($options['tags']);
            }
            if (!empty($options['date_from'])) {
                $filters[] = 'created_at:>=' . \sanitize_text_field($options['date_from']);
            }
            if (!empty($options['date_to'])) {
                $filters[] = 'created_at:<=' . \sanitize_text_field($options['date_to']);
            }
            $query = implode(' ', $filters);

            // Build GraphQL query for customers (remove isDefault, ordersCount, totalSpent)
            $gql = [
                'query' => '{
                    customers(first: ' . $limit . ($after ? ', after: "' . $after . '"' : '') . ($query ? ', query: "' . $query . '"' : '') . ') {
                        edges {
                            cursor
                            node {
                                id
                                firstName
                                lastName
                                email
                                phone
                                tags
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
                }'
            ];

            $response = $this->shopify_client->query($gql['query']);
            if (isset($response['errors'])) {
                return ['customers' => [], 'pageInfo' => []];
            }
            
           
            if (!isset($response['customers']['edges'])) {
                $this->logger->error('Invalid customer response from Shopify API');
                return ['customers' => [], 'pageInfo' => []];
            }
            $edges = $response['customers']['edges'];
            $customers = [];
            foreach ($edges as $edge) {
                $node = $edge['node'];
                $node['cursor'] = $edge['cursor'];
                $customers[] = $node;
            }
            $pageInfo = $response['customers']['pageInfo'];
            $this->logger->info('Retrieved ' . count($customers) . ' customers from Shopify API');
            return [
                'customers' => $customers,
                'pageInfo' => $pageInfo
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception retrieving customers: ' . $e->getMessage());
            return ['customers' => [], 'pageInfo' => []];
        }
    }

    /**
     * Process customers for import
     *
     * @param array $customers Array of customers from Shopify
     * @param array $options Import options
     * @return array Results of the import process
     */
    private function process_customers($customers, $options)
    {
        $results = [
            'success' => true,
            'message' => '',
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'log' => [],
            'processed_ids' => [] // Track which customers we've processed
        ];

        // Log the number of customers we're about to process
        $this->logger->info('Processing ' . count($customers) . ' customers');

        foreach ($customers as $customer) {
            try {
                $shopify_customer_id = isset($customer['id']) ? $customer['id'] : 'unknown';
                $email = isset($customer['email']) ? $customer['email'] : 'unknown';

                // Check if we're already processed this customer in this batch
                if (in_array($shopify_customer_id, $results['processed_ids'])) {
                    $this->logger->warning('Skipping duplicate customer in batch', [
                        'shopify_id' => $shopify_customer_id,
                        'email' => $email
                    ]);
                    continue;
                }

                $results['processed_ids'][] = $shopify_customer_id;

                $import_result = $this->import_single_customer($customer, $options);
                $results[$import_result['status']]++;
                $results['log'][] = $import_result['message'];

                // Log each status for debugging
                $this->logger->info('Customer import status: ' . $import_result['status'], [
                    'shopify_id' => $shopify_customer_id,
                    'email' => $email,
                    'message' => $import_result['message']
                ]);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['log'][] = 'Failed to import customer: ' . $e->getMessage();
                $this->logger->error('Customer import error', [
                    'customer_id' => isset($customer['id']) ? $customer['id'] : 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Remove the processed_ids from the results before returning
        unset($results['processed_ids']);

        return $results;
    }

    /**
     * Import a single customer
     *
     * @param array $customer Customer data from Shopify
     * @param array $options Import options
     * @return array Result of the import
     */
    private function import_single_customer($customer, $options)
    {
        // Debug log the raw customer data
        $this->logger->debug("Processing customer import", [
            'raw_data' => json_encode(array_keys($customer)) // Just log keys to avoid sensitive data
        ]);

        // Extract customer ID - handle both numeric IDs and GraphQL IDs
        $shopify_customer_id = '';
        if (isset($customer['id'])) {
            $shopify_customer_id = $customer['id'];
        } elseif (isset($customer['customerId'])) {
            $shopify_customer_id = $customer['customerId'];
        } elseif (isset($customer['customer_id'])) {
            $shopify_customer_id = $customer['customer_id'];
        }

        if (empty($shopify_customer_id)) {
            $this->logger->warning("Missing customer ID in import data");
            return [
                'status' => 'failed',
                'message' => "Failed to import customer: Missing customer ID"
            ];
        }

        // If using GraphQL format (gid://shopify/Customer/12345), extract the numeric portion
        $shopify_customer_id_clean = $shopify_customer_id;
        if (is_string($shopify_customer_id) && strpos($shopify_customer_id, 'gid://shopify/Customer/') === 0) {
            $shopify_customer_id_clean = substr($shopify_customer_id, strlen('gid://shopify/Customer/'));
            $this->logger->debug("Extracted numeric ID {$shopify_customer_id_clean} from GraphQL ID {$shopify_customer_id}");
        }

        $email = isset($customer['email']) ? \sanitize_email($customer['email']) : '';

        if (empty($email)) {
            return [
                'status' => 'skipped',
                'message' => "Skipped customer #{$shopify_customer_id_clean}: Missing email address"
            ];
        }

        // Check if customer already exists in WooCommerce
        $existing_user = \get_user_by('email', $email);

        if ($existing_user) {
            // Customer exists, update if enabled
            if ($options['overwrite_existing']) {
                return $this->update_existing_customer($existing_user->ID, $customer, $options);
            } else {
                return [
                    'status' => 'skipped',
                    'message' => "Skipped customer #{$shopify_customer_id_clean}: Email already exists and overwrite_existing is disabled"
                ];
            }
        } else {
            // Create new customer
            return $this->create_new_customer($customer, $options);
        }
    }

    /**
     * Create a new WooCommerce customer
     *
     * @param array $customer Customer data from Shopify
     * @param array $options Import options
     * @return array Result of the creation
     */
    private function create_new_customer($customer, $options)
    {
        // Extract customer ID - handle both numeric IDs and GraphQL IDs
        $shopify_customer_id = isset($customer['id']) ? $customer['id'] : '';
        // If using GraphQL format (gid://shopify/Customer/12345), extract the numeric portion
        if (strpos($shopify_customer_id, 'gid://shopify/Customer/') === 0) {
            $shopify_customer_id_clean = substr($shopify_customer_id, strlen('gid://shopify/Customer/'));
        } else {
            $shopify_customer_id_clean = $shopify_customer_id;
        }

        $email = isset($customer['email']) ? \sanitize_email($customer['email']) : '';
        $first_name = isset($customer['firstName']) ? \sanitize_text_field($customer['firstName']) : (isset($customer['first_name']) ? \sanitize_text_field($customer['first_name']) : '');
        $last_name = isset($customer['lastName']) ? \sanitize_text_field($customer['lastName']) : (isset($customer['last_name']) ? \sanitize_text_field($customer['last_name']) : '');

        // Support name field if first/last name not found
        if (empty($first_name) && empty($last_name) && isset($customer['name'])) {
            $name_parts = explode(' ', \sanitize_text_field($customer['name']));
            $first_name = $name_parts[0];
            if (count($name_parts) > 1) {
                unset($name_parts[0]);
                $last_name = implode(' ', $name_parts);
            }
        }

        // Generate a password if they don't have one yet
        $password = \wp_generate_password();

        // Log the customer details before creation (include name/email in message for DB log clarity)
        $log_name = trim($first_name . ' ' . $last_name);
        $log_message = 'Creating new customer: ' . ($log_name ?: '(Unnamed)') . ' <' . $email . '> (Shopify ID: ' . $shopify_customer_id_clean . ')';
        $this->logger->debug($log_message, [
            'shopify_id' => $shopify_customer_id,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);

        // Create the customer in WooCommerce
        $new_customer_id = \wc_create_new_customer(
            $email,
            $email, // username = email
            $password,
            [
                'first_name' => $first_name,
                'last_name' => $last_name
            ]
        );

        if (\is_wp_error($new_customer_id)) {
            $this->logger->error('Failed to create customer', [
                'error' => $new_customer_id->get_error_message(),
                'shopify_id' => $shopify_customer_id,
                'email' => $email
            ]);
            return [
                'status' => 'failed',
                'message' => "Failed to create customer #{$shopify_customer_id_clean}: " . $new_customer_id->get_error_message()
            ];
        }

        // Log successful creation
        $this->logger->info('Successfully created customer', [
            'wp_user_id' => $new_customer_id,
            'shopify_id' => $shopify_customer_id,
            'email' => $email
        ]);

        // Set Shopify ID as user meta - store both the full ID and the numeric portion
        \update_user_meta($new_customer_id, '_shopify_customer_id', $shopify_customer_id_clean);
        \update_user_meta($new_customer_id, '_shopify_customer_full_id', $shopify_customer_id);

        // Add phone number
        if (!empty($customer['phone'])) {
            \update_user_meta($new_customer_id, 'billing_phone', \sanitize_text_field($customer['phone']));
            \update_user_meta($new_customer_id, 'shipping_phone', \sanitize_text_field($customer['phone']));
        }

        // Store creation and update dates if available
        if (!empty($customer['createdAt'])) {
            \update_user_meta($new_customer_id, '_shopify_created_at', $customer['createdAt']);
        }

        if (!empty($customer['updatedAt'])) {
            \update_user_meta($new_customer_id, '_shopify_updated_at', $customer['updatedAt']);
        }

        // Import profile image if available
        if (!empty($customer['image']) && !empty($customer['image']['url'])) {
            \update_user_meta($new_customer_id, '_shopify_customer_image_url', \esc_url_raw($customer['image']['url']));

            // If you want to download and attach the avatar to the WP user, you'd need additional code here
            // This would involve downloading the image and using wp_update_user to set 'user_avatar'
        }

        // Import tags if enabled
        if ($options['import_tags'] && !empty($customer['tags'])) {
            // Handle tags as either string or array
            if (is_array($customer['tags'])) {
                // Join array items into comma-separated string
                $tags_string = implode(', ', array_map('\\sanitize_text_field', $customer['tags']));
                \update_user_meta($new_customer_id, '_shopify_customer_tags', $tags_string);

                // Also store the raw array as serialized data if needed
                \update_user_meta($new_customer_id, '_shopify_customer_tags_array', $customer['tags']);
            } else {
                // Handle as string (for backward compatibility)
                \update_user_meta($new_customer_id, '_shopify_customer_tags', \sanitize_text_field($customer['tags']));
            }
        }

        // Import addresses if enabled
        if ($options['import_addresses']) {
            // Check if we have addresses or a default address
            if (!empty($customer['addresses']) && is_array($customer['addresses'])) {
                $this->logger->debug("Importing addresses array", [
                    'user_id' => $new_customer_id,
                    'count' => count($customer['addresses'])
                ]);
                $this->import_customer_addresses($new_customer_id, $customer['addresses']);
            } else if (!empty($customer['default_address'])) {
                // If no addresses array but we have a default_address, use that
                $this->logger->debug("Using default_address directly", [
                    'user_id' => $new_customer_id
                ]);
                $this->import_customer_addresses($new_customer_id, array($customer['default_address']));
            } else {
                $this->logger->warning("No addresses found for customer", [
                    'user_id' => $new_customer_id,
                    'shopify_id' => $shopify_customer_id
                ]);
            }
        }

        // Store orders count and total spent as meta
        if (isset($customer['orders_count'])) {
            \update_user_meta($new_customer_id, '_shopify_orders_count', (int)$customer['orders_count']);
        }

        if (isset($customer['total_spent'])) {
            \update_user_meta($new_customer_id, '_shopify_total_spent', (float)$customer['total_spent']);
        }

        // Send welcome email if enabled
        if ($options['send_welcome_email']) {
            $this->send_welcome_email($new_customer_id, $email, $customer);
        }

        return [
            'status' => 'imported',
            'message' => "Imported new customer #{$shopify_customer_id_clean}: {$first_name} {$last_name} ({$email})"
        ];
    }

    /**
     * Update an existing WooCommerce customer
     *
     * @param int $user_id WooCommerce user ID
     * @param array $customer Customer data from Shopify
     * @param array $options Import options
     * @return array Result of the update
     */
    private function update_existing_customer($user_id, $customer, $options)
    {
        // Extract customer ID - handle both numeric IDs and GraphQL IDs
        $shopify_customer_id = isset($customer['id']) ? $customer['id'] : '';
        // If using GraphQL format (gid://shopify/Customer/12345), extract the numeric portion
        if (strpos($shopify_customer_id, 'gid://shopify/Customer/') === 0) {
            $shopify_customer_id_clean = substr($shopify_customer_id, strlen('gid://shopify/Customer/'));
            $this->logger->debug("Extracted numeric ID {$shopify_customer_id_clean} from GraphQL ID {$shopify_customer_id}");
        } else {
            $shopify_customer_id_clean = $shopify_customer_id;
        }

        $first_name = isset($customer['firstName']) ? \sanitize_text_field($customer['firstName']) : (isset($customer['first_name']) ? \sanitize_text_field($customer['first_name']) : '');
        $last_name = isset($customer['lastName']) ? \sanitize_text_field($customer['lastName']) : (isset($customer['last_name']) ? \sanitize_text_field($customer['last_name']) : '');

        // Support name field if first/last name not found
        if (empty($first_name) && empty($last_name) && isset($customer['name'])) {
            $name_parts = explode(' ', \sanitize_text_field($customer['name']));
            $first_name = $name_parts[0];
            if (count($name_parts) > 1) {
                unset($name_parts[0]);
                $last_name = implode(' ', $name_parts);
            }
        }

        // Update user data
        $user_data = [
            'ID' => $user_id
        ];

        if (!empty($first_name)) {
            $user_data['first_name'] = $first_name;
        }

        if (!empty($last_name)) {
            $user_data['last_name'] = $last_name;
        }

        // Log the customer details before update
        $this->logger->debug('Updating existing customer', [
            'user_id' => $user_id,
            'shopify_id' => $shopify_customer_id,
            'email' => isset($customer['email']) ? $customer['email'] : 'not provided',
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);

        // Update the user
        $updated = \wp_update_user($user_data);

        if (\is_wp_error($updated)) {
            $this->logger->error('Failed to update customer', [
                'error' => $updated->get_error_message(),
                'user_id' => $user_id,
                'shopify_id' => $shopify_customer_id
            ]);
            return [
                'status' => 'failed',
                'message' => "Failed to update customer #{$shopify_customer_id_clean}: " . $updated->get_error_message()
            ];
        }

        // Log successful update for debugging
        $this->logger->info('Successfully updated customer', [
            'user_id' => $user_id,
            'shopify_id' => $shopify_customer_id
        ]);

        // Update Shopify ID - store both the full ID and the numeric portion
        \update_user_meta($user_id, '_shopify_customer_id', $shopify_customer_id_clean);
        \update_user_meta($user_id, '_shopify_customer_full_id', $shopify_customer_id);

        // Update phone number
        if (!empty($customer['phone'])) {
            \update_user_meta($user_id, 'billing_phone', \sanitize_text_field($customer['phone']));
            \update_user_meta($user_id, 'shipping_phone', \sanitize_text_field($customer['phone']));
        }

        // Store/update creation and update dates if available
        if (!empty($customer['createdAt'])) {
            \update_user_meta($user_id, '_shopify_created_at', $customer['createdAt']);
        }

        if (!empty($customer['updatedAt'])) {
            \update_user_meta($user_id, '_shopify_updated_at', $customer['updatedAt']);
        }

        // Update profile image if available
        if (!empty($customer['image']) && !empty($customer['image']['url'])) {
            \update_user_meta($user_id, '_shopify_customer_image_url', \esc_url_raw($customer['image']['url']));
        }

        // Update tags if enabled
        if ($options['import_tags'] && !empty($customer['tags'])) {
            // Handle tags as either string or array
            if (is_array($customer['tags'])) {
                // Join array items into comma-separated string
                $tags_string = implode(', ', array_map('\\sanitize_text_field', $customer['tags']));
                \update_user_meta($user_id, '_shopify_customer_tags', $tags_string);

                // Also store the raw array as serialized data if needed
                \update_user_meta($user_id, '_shopify_customer_tags_array', $customer['tags']);
            } else {
                // Handle as string (for backward compatibility)
                \update_user_meta($user_id, '_shopify_customer_tags', \sanitize_text_field($customer['tags']));
            }
        }

        // Update addresses if enabled
        if ($options['import_addresses']) {
            // Check if we have addresses or a default address
            if (!empty($customer['addresses']) && is_array($customer['addresses'])) {
                $this->logger->debug("Importing addresses array for existing user", [
                    'user_id' => $user_id,
                    'count' => count($customer['addresses'])
                ]);
                $this->import_customer_addresses($user_id, $customer['addresses']);
            } else if (!empty($customer['default_address'])) {
                // If no addresses array but we have a default_address, use that
                $this->logger->debug("Using default_address directly for existing user", [
                    'user_id' => $user_id
                ]);
                $this->import_customer_addresses($user_id, array($customer['default_address']));
            } else {
                $this->logger->warning("No addresses found for existing customer", [
                    'user_id' => $user_id,
                    'shopify_id' => $shopify_customer_id
                ]);
            }
        }

        // Update orders count and total spent as meta
        if (isset($customer['orders_count'])) {
            \update_user_meta($user_id, '_shopify_orders_count', (int)$customer['orders_count']);
        }

        if (isset($customer['total_spent'])) {
            \update_user_meta($user_id, '_shopify_total_spent', (float)$customer['total_spent']);
        }

        return [
            'status' => 'updated',
            'message' => "Updated existing customer #{$shopify_customer_id_clean}: {$first_name} {$last_name}"
        ];
    }

    /**
     * Import customer addresses
     *
     * @param int $user_id WooCommerce user ID
     * @param array $addresses Array of addresses from Shopify
     */
    private function import_customer_addresses($user_id, $addresses)
    {
        if (empty($addresses)) {
            $this->logger->warning("No addresses to import for user {$user_id}");
            return;
        }

        // Make sure we have an array
        if (!is_array($addresses)) {
            $this->logger->warning("Addresses is not an array for user {$user_id}", [
                'type' => gettype($addresses),
                'value' => json_encode($addresses)
            ]);

            // If it's not an array, try to convert it or return
            if (is_object($addresses)) {
                $addresses = (array) $addresses;
            } else {
                return;
            }
        }

        // Filter out null address entries and empty arrays
        $addresses = array_filter($addresses, function ($addr) {
            return $addr !== null && (!is_array($addr) || !empty($addr));
        });

        // If we ended up with no addresses after filtering
        if (count($addresses) === 0) {
            $this->logger->warning("No valid addresses after filtering for user {$user_id}");
            return;
        }

        // First, check if customer has a default_address property directly
        $default_address = null;

        // Log all addresses received for debugging
        $this->logger->debug("Customer addresses", [
            'user_id' => $user_id,
            'address_count' => count($addresses),
            'addresses' => json_encode($addresses)
        ]);

        // Check if we have a "default" flag or property in each address
        foreach ($addresses as $address) {
            if (is_array($address) && isset($address['default']) && $address['default'] === true) {
                $default_address = $address;
                $this->logger->debug("Found default address by 'default' flag", [
                    'user_id' => $user_id,
                    'address' => json_encode($default_address)
                ]);
                break;
            }
            // Also check for camelCase 'isDefault' (GraphQL API)
            if (is_array($address) && isset($address['isDefault']) && $address['isDefault'] === true) {
                $default_address = $address;
                $this->logger->debug("Found default address by 'isDefault' flag", [
                    'user_id' => $user_id,
                    'address' => json_encode($default_address)
                ]);
                break;
            }
        }

        // If no default address was found, use the first valid one
        if ($default_address === null && !empty($addresses)) {
            // Find first non-empty address
            foreach ($addresses as $addr) {
                if (is_array($addr) && !empty($addr)) {
                    $default_address = $addr;
                    $this->logger->debug("No default address flag found, using first non-empty address", [
                        'user_id' => $user_id
                    ]);
                    break;
                }
            }

            // If still no valid address found, use the first one regardless
            if ($default_address === null) {
                $default_address = reset($addresses); // Use reset() to get first element safely
                $this->logger->debug("No valid address found, using first address entry", [
                    'user_id' => $user_id
                ]);
            }
        }

        // If we still don't have a valid address
        if ($default_address === null) {
            $this->logger->warning("No valid default address could be determined for user {$user_id}");
            return;
        }

        // Log that we're using this address
        $this->logger->debug('Using customer address for import', [
            'address' => json_encode($default_address),
            'user_id' => $user_id
        ]);

        // Import as both billing and shipping address
        $this->save_customer_address($user_id, $default_address, 'billing');
        $this->save_customer_address($user_id, $default_address, 'shipping');

        // Store raw addresses for reference - sanitize and remove any null values
        $sanitized_addresses = array_values(array_filter($addresses, function ($addr) {
            return $addr !== null;
        }));

        \update_user_meta($user_id, '_shopify_customer_addresses', $sanitized_addresses);
    }

    /**
     * Save customer address to user meta
     *
     * @param int $user_id WooCommerce user ID
     * @param array $address Address data from Shopify
     * @param string $type Address type (billing|shipping)
     */
    private function save_customer_address($user_id, $address, $type)
    {
        // Validate incoming address data
        if (!is_array($address)) {
            $this->logger->warning("Invalid address format for {$type}", [
                'user_id' => $user_id,
                'type' => gettype($address),
                'address' => json_encode($address)
            ]);
            return; // Exit if address is not an array
        }

        // Log full incoming address for debugging
        $this->logger->debug("Raw address data for {$type}", [
            'address_data' => json_encode($address),
            'user_id' => $user_id
        ]);

        // Helper function to safely get address field with fallbacks and type handling
        $get_address_field = function ($primary_key, $fallback_key = null, $default = '') use ($address) {
            // Check primary key first
            if (isset($address[$primary_key]) && $address[$primary_key] !== null) {
                $value = $address[$primary_key];
            } 
            // Then check fallback key
            elseif ($fallback_key && isset($address[$fallback_key]) && $address[$fallback_key] !== null) {
                $value = $address[$fallback_key];
            }
            // Use default if neither exists or both are null
            else {
                return $default;
            }
            
            // Handle non-string values uniformly
            if (!is_string($value)) {
                if (is_array($value) || is_object($value)) {
                    return ''; // Skip complex values
                }
                return (string) $value; // Convert numbers and booleans to string
            }
            
            return $value;
        };

        // Support both snake_case and camelCase field names in Shopify API responses
        $first_name = $get_address_field('firstName', 'first_name');
        $last_name = $get_address_field('lastName', 'last_name');
        $company = $get_address_field('company');
        $address1 = $get_address_field('address1', 'address_1');
        $address2 = $get_address_field('address2', 'address_2');
        $city = $get_address_field('city');
        $zip = $get_address_field('zip', 'postcode');
        $country_code = $get_address_field('countryCodeV2', 'countryCodeV2');
        $province_code = $get_address_field('provinceCode', 'province_code');
        $phone = $get_address_field('phone');

        // If country/province codes are not available, try using full names 
        if (empty($country_code)) {
            $country_code = $get_address_field('country');
        }

        if (empty($province_code)) {
            $province_code = $get_address_field('province', 'state');
        }

        // Map and sanitize all fields - add address1 and address_1 both as fallbacks
        $meta = [
            $type . '_first_name' => $first_name,
            $type . '_last_name' => $last_name,
            $type . '_company' => $company,
            $type . '_address_1' => $address1, // WooCommerce field
            $type . '_address_2' => $address2, // WooCommerce field
            $type . '_city' => $city,
            $type . '_postcode' => $zip,
            $type . '_country' => $country_code,
            $type . '_state' => $province_code,
        ];

        // Add phone if available
        if (!empty($phone)) {
            $meta[$type . '_phone'] = $phone;
        }

        // Important fields that should always be saved even if empty
        $important_fields = [
            'billing_country', 'shipping_country',
            'billing_postcode', 'shipping_postcode',
            'billing_city', 'shipping_city',
            'billing_state', 'shipping_state'
        ];
        
        // Save each meta field
        foreach ($meta as $key => $value) {
            // Skip null values entirely
            if ($value === null) {
                continue;
            }

            try {
                // Convert any non-string values to strings
                if (!is_string($value)) {
                    $value = (string) $value;
                }
                
                // Either save if value is not empty, OR if it's one of the important fields
                if (!empty($value) || in_array($key, $important_fields)) {
                    // Sanitize and save
                    $sanitized_value = \sanitize_text_field($value);
                    \update_user_meta($user_id, $key, $sanitized_value);
                }
            } catch (\Exception $e) {
                // Log the error but continue with other fields
                $this->logger->warning("Failed to save {$key}: " . $e->getMessage(), [
                    'user_id' => $user_id
                ]);
            }
        }
        
        // Update user data directly to ensure the lookup table is populated
        // Only update for billing address since that's what WooCommerce uses for the lookup table
        try {
            if ($type === 'billing') {
                // Map fields from meta to WooCommerce user data format
                $user_data = [
                    'country' => isset($meta['billing_country']) ? $meta['billing_country'] : '',
                    'city' => isset($meta['billing_city']) ? $meta['billing_city'] : '',
                    'state' => isset($meta['billing_state']) ? $meta['billing_state'] : '',
                    'postcode' => isset($meta['billing_postcode']) ? $meta['billing_postcode'] : ''
                ];                    // If we have data to update, update the user
                // Always update the user data to ensure lookup table is populated
                $user_data['ID'] = $user_id;
                \wp_update_user($user_data);
                
                // Update WooCommerce lookup tables - try WC_Customer class first
                if (class_exists('WC_Customer')) {
                    $customer = new \WC_Customer($user_id);
                    $customer->save();
                } elseif (function_exists('wc_update_customer_lookup_tables')) {
                    \wc_update_customer_lookup_tables($user_id, true);
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to update lookup table data", [
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ]);
        }

        // Log completion of address import
        $this->logger->debug("Address field import complete", [
            'user_id' => $user_id,
            'type' => $type
        ]);
    }

    /**
     * Send welcome email to newly imported customer
     *
     * @param int $user_id The WP user ID of the imported customer
     * @param string $email Customer's email address
     * @param array $customer Original customer data from Shopify
     * @return bool Whether the email was sent successfully
     */
    private function send_welcome_email($user_id, $email, $customer)
    {
        try {
            // Get customer first name
            $first_name = '';
            if (isset($customer['firstName'])) {
                $first_name = \sanitize_text_field($customer['firstName']);
            } elseif (isset($customer['first_name'])) {
                $first_name = \sanitize_text_field($customer['first_name']);
            }

            // Get site info
            $site_title = \get_bloginfo('name');
            $admin_email = \get_bloginfo('admin_email');

            $subject = sprintf(
                /* translators: %s: site title */
                \__('Welcome to %s', 'wp-migrate-shopify-woo'), 
                $site_title
            );

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_title . ' <' . $admin_email . '>',
            ];

            // Build the email message
            $message = '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
            $message .= '<h2>' . sprintf(
                /* translators: %s: site title */
                \__('Welcome to %s!', 'wp-migrate-shopify-woo'), 
                $site_title
            ) . '</h2>';

            if (!empty($first_name)) {
                $message .= '<p>' . sprintf(
                    /* translators: %s: customer first name */
                    \__('Hello %s,', 'wp-migrate-shopify-woo'), 
                    \esc_html($first_name)
                ) . '</p>';
            } else {
                $message .= '<p>' . \__('Hello,', 'wp-migrate-shopify-woo') . '</p>';
            }

            $message .= '<p>' . \__('Your account has been created on our store. You can now log in to manage your account, view orders, and more.', 'wp-migrate-shopify-woo') . '</p>';
            $message .= '<p>' . \__('Please use the password reset feature on the login page to set your password.', 'wp-migrate-shopify-woo') . '</p>';

            // Add link to my account page
            $account_url = \wc_get_page_permalink('myaccount');
            if ($account_url) {
                $message .= '<p><a href="' . \esc_url($account_url) . '" style="display: inline-block; padding: 10px 15px; background-color: #7f54b3; color: white; text-decoration: none; border-radius: 3px;">' . \__('Visit Your Account', 'wp-migrate-shopify-woo') . '</a></p>';
            }

            $message .= '<p>' . \__('Thank you for being our customer!', 'wp-migrate-shopify-woo') . '</p>';
            $message .= '<p>' . sprintf(
                /* translators: %s: site title */
                \__('The %s Team', 'wp-migrate-shopify-woo'), 
                $site_title
            ) . '</p>';
            $message .= '</div>';

            // Send email
            $sent = \wp_mail($email, $subject, $message, $headers);

            if ($sent) {
                $this->logger->info('Sent welcome email', [
                    'user_id' => $user_id,
                    'email' => $email
                ]);
                return true;
            } else {
                $this->logger->warning('Failed to send welcome email', [
                    'user_id' => $user_id,
                    'email' => $email
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error sending welcome email: ' . $e->getMessage(), [
                'user_id' => $user_id,
                'email' => $email
            ]);
            return false;
        }
    }

    /**
     * Force update WooCommerce customer lookup table
     * This helps ensure the customer data we imported appears in the WC table
     *
     * @param int $user_id
     * @return bool Whether update was successful
     */
    // Methods related to customer lookup table updates have been simplified and inlined
}
