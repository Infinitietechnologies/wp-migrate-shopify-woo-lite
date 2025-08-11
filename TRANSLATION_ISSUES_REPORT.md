# Translation Issues Report - WordPress Plugin

## Overview
This report identifies all hardcoded text strings in the codebase that need to be made translatable and properly escaped according to WordPress coding standards.

## Critical Issues Found

### 1. PHP Files - Hardcoded Strings

#### includes/handlers/WMSW_BlogHandler.php
**Line 1218**: Non-translatable string in sprintf
```php
$message = sprintf('Blog import step: %s - %s', $step, $status);
```
**Fix**: Should be:
```php
$message = sprintf(__('Blog import step: %s - %s', 'wp-migrate-shopify-woo'), $step, $status);
```

#### includes/handlers/WMSW_CouponHandler.php
**Lines 1165-1185**: Multiple hardcoded strings in sample coupon data
```php
'code' => 'DEMO10',
'description' => 'Demo 10% discount coupon',
'code' => 'WELCOME20', 
'description' => 'Welcome 20% discount for new customers',
'code' => 'FIXED5',
'description' => 'Fixed $5 off any order',
```
**Fix**: Should be:
```php
'code' => 'DEMO10',
'description' => __('Demo 10% discount coupon', 'wp-migrate-shopify-woo'),
'code' => 'WELCOME20',
'description' => __('Welcome 20% discount for new customers', 'wp-migrate-shopify-woo'),
'code' => 'FIXED5', 
'description' => __('Fixed $5 off any order', 'wp-migrate-shopify-woo'),
```

**Line 1170**: Hardcoded exception message
```php
throw new Exception('WooCommerce is not active or WC_Coupon class not found');
```
**Fix**: Should be:
```php
throw new Exception(__('WooCommerce is not active or WC_Coupon class not found', 'wp-migrate-shopify-woo'));
```

#### includes/core/WMSW_ShopifyClient.php
**Line 125**: Hardcoded error message
```php
$error_message = $response['error_message'] ?? 'Unknown error';
```
**Fix**: Should be:
```php
$error_message = $response['error_message'] ?? __('Unknown error', 'wp-migrate-shopify-woo');
```

**Line 135**: Hardcoded error message
```php
$error_message = "GraphQL query failed: {$errors}";
```
**Fix**: Should be:
```php
$error_message = sprintf(__('GraphQL query failed: %s', 'wp-migrate-shopify-woo'), $errors);
```

**Line 176**: Hardcoded error message
```php
$error_message = "GraphQL API request failed with status {$status_code}: {$body}";
```
**Fix**: Should be:
```php
$error_message = sprintf(__('GraphQL API request failed with status %s: %s', 'wp-migrate-shopify-woo'), $status_code, $body);
```

**Line 213**: Hardcoded return value
```php
return 'Unknown error';
```
**Fix**: Should be:
```php
return __('Unknown error', 'wp-migrate-shopify-woo');
```

**Line 224**: Hardcoded message concatenation
```php
$message .= " (line {$location['line']}, column {$location['column']})";
```
**Fix**: Should be:
```php
$message .= sprintf(__(' (line %s, column %s)', 'wp-migrate-shopify-woo'), $location['line'], $location['column']);
```

**Line 405**: Hardcoded status message
```php
$has_more = $has_next_page ? "has more pages" : "complete";
```
**Fix**: Should be:
```php
$has_more = $has_next_page ? __('has more pages', 'wp-migrate-shopify-woo') : __('complete', 'wp-migrate-shopify-woo');
```

#### includes/processors/WMSW_ProductProcessor.php
**Line 47**: Hardcoded tab name
```php
$tab = 'products';
```
**Fix**: Should be:
```php
$tab = __('products', 'wp-migrate-shopify-woo');
```

**Lines 211, 215, 219, 224**: Hardcoded status values
```php
$query_params['query'] = 'status:DRAFT';
$query_params['query'] = 'status:ACTIVE';
$query_params['query'] = 'status:ARCHIVED';
```
**Fix**: These appear to be API parameters and may not need translation, but should be reviewed.

#### includes/processors/WMSW_PageProcessor.php
**Line 164**: Hardcoded completion message
```php
$completion_message = "Page import completed! Imported: {$total_imported}, Updated: {$total_updated}, Failed: {$total_failed}, Skipped: {$total_skipped}";
```
**Fix**: Should be:
```php
$completion_message = sprintf(__('Page import completed! Imported: %d, Updated: %d, Failed: %d, Skipped: %d', 'wp-migrate-shopify-woo'), $total_imported, $total_updated, $total_failed, $total_skipped);
```

**Lines 223, 225**: Hardcoded status values
```php
$query_params['published_status'] = 'published';
$query_params['published_status'] = 'unpublished';
```
**Fix**: These appear to be API parameters and may not need translation, but should be reviewed.

**Lines 342, 358, 373**: Hardcoded status values
```php
return 'skipped';
$result = 'updated';
$result = 'imported';
```
**Fix**: These appear to be internal status values and may not need translation, but should be reviewed.

**Line 410**: Hardcoded return value
```php
return 'SKIP'; // Special return value to indicate skip
```
**Fix**: Should be:
```php
return __('SKIP', 'wp-migrate-shopify-woo'); // Special return value to indicate skip
```

**Lines 489, 492, 495**: Hardcoded post type and status values
```php
$post_type = !empty($options['convert_to_posts']) ? 'post' : 'page';
$post_status = !empty($page['published_at']) ? 'publish' : 'draft';
$content = isset($page['body_html']) ? $page['body_html'] : '';
```
**Fix**: These appear to be WordPress post types and may not need translation, but should be reviewed.

#### includes/processors/WMSW_OrderProcessor.php
**Line 628**: Hardcoded status mapping
```php
return $status_map[$shopify_status] ?? 'pending';
```
**Fix**: Should be:
```php
return $status_map[$shopify_status] ?? __('pending', 'wp-migrate-shopify-woo');
```

**Line 832**: Hardcoded log message
```php
$log_context['message'] = "Order import status: {$data['status']}";
```
**Fix**: Should be:
```php
$log_context['message'] = sprintf(__('Order import status: %s', 'wp-migrate-shopify-woo'), $data['status']);
```

**Lines 1118, 1121**: Hardcoded value types
```php
$normalized_discount['value_type'] = 'fixed_amount';
$normalized_discount['value_type'] = 'percentage';
```
**Fix**: These appear to be internal values and may not need translation, but should be reviewed.

#### includes/processors/WMSW_LogProcessor.php
**Line 127**: Hardcoded user name fallback
```php
$user_name = $user_data ? $user_data->display_name : 'System';
```
**Fix**: Should be:
```php
$user_name = $user_data ? $user_data->display_name : __('System', 'wp-migrate-shopify-woo');
```

**Line 149**: Hardcoded CSV header
```php
$csv = "ID,Level,Message,Context,Task ID,Created At\n";
```
**Fix**: Should be:
```php
$csv = sprintf(__('ID,Level,Message,Context,Task ID,Created At', 'wp-migrate-shopify-woo')) . "\n";
```

#### includes/processors/WMSW_CustomerProcessor.php
**Line 86**: Hardcoded tab name
```php
$tab = 'customers';
```
**Fix**: Should be:
```php
$tab = __('customers', 'wp-migrate-shopify-woo');
```

**Lines 331, 332**: Hardcoded fallback values
```php
$shopify_customer_id = isset($customer['id']) ? $customer['id'] : 'unknown';
$email = isset($customer['email']) ? $customer['email'] : 'unknown';
```
**Fix**: Should be:
```php
$shopify_customer_id = isset($customer['id']) ? $customer['id'] : __('unknown', 'wp-migrate-shopify-woo');
$email = isset($customer['email']) ? $customer['email'] : __('unknown', 'wp-migrate-shopify-woo');
```

**Line 475**: Hardcoded log message
```php
$log_message = 'Creating new customer: ' . ($log_name ?: '(Unnamed)') . ' <' . $email . '> (Shopify ID: ' . $shopify_customer_id_clean . ')';
```
**Fix**: Should be:
```php
$log_message = sprintf(__('Creating new customer: %s <%s> (Shopify ID: %s)', 'wp-migrate-shopify-woo'), ($log_name ?: __('(Unnamed)', 'wp-migrate-shopify-woo')), $email, $shopify_customer_id_clean);
```

### 2. Backend Views - Missing Escaping

#### backend/views/dashboard.php
**Lines 40-50**: All strings are properly translatable and escaped ✓

#### backend/views/settings.php  
**Lines 40-60**: All strings are properly translatable and escaped ✓

#### backend/partials/tables/import-log.php
**Lines 80-130**: All strings are properly translatable and escaped ✓

#### backend/partials/components/status-badge.php
**Lines 15-35**: All strings are properly translatable and escaped ✓

### 3. JavaScript Files - Hardcoded Strings

#### backend/assets/js/components/coupon-importer.js
**Lines 1-100**: Configuration strings that should be localized
```javascript
formSelector: '#coupons-import-form',
previewSelector: '#coupons-preview',
progressSelector: '#coupons-import-progress',
```
**Fix**: These selectors should be localized via wp_localize_script().

### 4. Vendor Files
**backend/assets/vendors/notiflix.js**: This is a third-party library and should not be modified. The strings in this file are part of the library's functionality.

## Summary of Required Actions

### High Priority (User-facing strings)
1. **WMSW_BlogHandler.php** - Line 1218: Make sprintf translatable
2. **WMSW_CouponHandler.php** - Lines 1165-1185: Make sample coupon descriptions translatable
3. **WMSW_ShopifyClient.php** - Lines 125, 135, 176, 213, 224, 405: Make error messages translatable
4. **WMSW_PageProcessor.php** - Line 164: Make completion message translatable
5. **WMSW_LogProcessor.php** - Lines 127, 149: Make fallback values and CSV header translatable
6. **WMSW_CustomerProcessor.php** - Lines 331, 332, 475: Make fallback values and log messages translatable

### Medium Priority (Internal strings that may need translation)
1. **WMSW_ProductProcessor.php** - Line 47: Review if tab name needs translation
2. **WMSW_OrderProcessor.php** - Line 628: Review if status fallback needs translation
3. **Various processors** - Status values and API parameters: Review if these need translation

### Low Priority (Configuration and technical strings)
1. **JavaScript selectors** - Should be localized via wp_localize_script()
2. **Database table names and API parameters** - These typically don't need translation

## Recommendations

1. **Immediate Action**: Fix all high-priority user-facing strings
2. **Review**: Assess medium-priority strings for translation needs
3. **Localization**: Set up proper JavaScript localization for selectors and messages
4. **Testing**: Test all translations after implementation
5. **Documentation**: Update translation guidelines to prevent future issues

## Files That Are Already Compliant ✓
- backend/views/dashboard.php
- backend/views/settings.php
- backend/partials/tables/import-log.php
- backend/partials/components/status-badge.php
- backend/partials/components/form-group.php
- Most of the main plugin file (shopify-woo-importer.php)

The plugin has good translation practices in most areas, but needs attention to error messages, log messages, and some hardcoded user-facing strings. 