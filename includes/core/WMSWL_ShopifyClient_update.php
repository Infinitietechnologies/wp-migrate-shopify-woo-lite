<?php

/**
 * Update to WMSW_ShopifyClient class to support both edges->node and direct nodes response structures
 * 
 * This update adds support for processing both types of GraphQL response structures:
 * 1. The typical "edges" -> "node" structure: { edges: [{ node: { id: '...', ... } }] }
 * 2. The direct "nodes" structure: { nodes: [{ id: '...', ... }] }
 * 
 * Shopify's GraphQL API sometimes returns different structures depending on the query type
 * and version of the API. This update ensures our plugin can handle both formats.
 */

/**
 * Modified transformation function for processing GraphQL responses that might have
 * either edges->node structure or direct nodes structure
 * 
 * @param array $data The GraphQL response section to transform
 * @return array Flattened array of items
 */
function transform_graphql_data($data) {
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
 * regardless of whether they use edges->node or direct nodes structure
 * 
 * @param array $product The product node to process
 * @return array Processed product with flattened variants and images
 */
function process_product_node($product) {
    // Process variants - handle both structures
    if (isset($product['variants'])) {
        if (isset($product['variants']['edges'])) {
            // Traditional edges->node structure
            $variants = [];
            foreach ($product['variants']['edges'] as $variantEdge) {
                if (isset($variantEdge['node'])) {
                    $variants[] = $variantEdge['node'];
                }
            }
            $product['variants'] = $variants;
        } elseif (isset($product['variants']['nodes'])) {
            // Direct nodes structure
            $product['variants'] = $product['variants']['nodes'];
        }
        // If neither structure is present, leave as is (might be already flattened)
    } else {
        $product['variants'] = [];
    }
    
    // Process images - handle both structures
    if (isset($product['images'])) {
        if (isset($product['images']['edges'])) {
            // Traditional edges->node structure
            $images = [];
            foreach ($product['images']['edges'] as $imageEdge) {
                if (isset($imageEdge['node'])) {
                    $images[] = $imageEdge['node'];
                }
            }
            $product['images'] = $images;
        } elseif (isset($product['images']['nodes'])) {
            // Direct nodes structure
            $product['images'] = $product['images']['nodes'];
        }
        // If neither structure is present, leave as is (might be already flattened)
        
        // Set first image as main product image for compatibility
        if (!empty($product['images'])) {
            $product['image'] = $product['images'][0];
        }
    } else {
        $product['images'] = [];
    }
    
    return $product;
}
