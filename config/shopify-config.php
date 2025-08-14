<?php
/**
 * Shopify Configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return [
    'api_version' => '2024-04',
    'scopes' => [
        'read_products',
        'read_customers',
        'read_orders',
        'read_content',
        'read_discounts'
    ],
    'endpoints' => [
        'products' => 'products',
        'customers' => 'customers',
        'orders' => 'orders',
        'pages' => 'pages',
        'blogs' => 'blogs',
        'articles' => 'blogs/{blog_id}/articles',
        'price_rules' => 'price_rules',
        'discount_codes' => 'price_rules/{price_rule_id}/discount_codes'
    ],
    'webhooks' => [
        'products/create',
        'products/update',
        'products/delete',
        'orders/create',
        'orders/updated',
        'customers/create',
        'customers/update'
    ]
];
