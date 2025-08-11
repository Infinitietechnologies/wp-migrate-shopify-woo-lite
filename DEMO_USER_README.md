# Demo User System for Shopify to WooCommerce Importer

## Overview

The Shopify to WooCommerce Importer plugin now includes a comprehensive demo user system that creates a dedicated user account with appropriate permissions for testing and demonstration purposes. **Demo users will only see the Shopify Importer plugin in the admin area - all other WordPress admin features are hidden.**

## Features

### Automatic Creation
- **Demo Role**: A custom WordPress role called "Shopify Importer Demo" is created
- **Demo User**: A demo user account is automatically created upon plugin activation
- **Full Plugin Access**: The demo user has access to all plugin features and pages
- **Restricted Admin**: Demo users only see the Shopify Importer plugin - all other WordPress admin features are hidden

### Demo User Credentials
- **Username**: `shopify_importer_demo`
- **Password**: `12345678`
- **Email**: `demo@shopify-importer.local`
- **Display Name**: Shopify Importer Demo

### Admin Experience for Demo Users

When logged in as a demo user, the WordPress admin area is completely customized:

#### Hidden Elements:
- **WordPress Core**: Dashboard, Posts, Media, Pages, Comments, Appearance, Plugins, Users, Tools, Settings
- **WooCommerce**: All WooCommerce admin menus and pages
- **Other Plugins**: Jetpack, ACF, and other common plugin menus
- **Admin Bar**: Standard WordPress admin bar items (replaced with custom demo user info)

#### Visible Elements:
- **Shopify Importer**: Complete plugin with all features
- **Custom Admin Bar**: Shows "Demo User - Shopify Importer" with logout option
- **Minimal Interface**: Clean, focused experience for demonstrations

#### Automatic Redirects:
- Demo users are automatically redirected to the Shopify Importer dashboard if they try to access restricted pages
- All attempts to access other admin areas lead back to the plugin

### Capabilities Granted

The demo role includes the following plugin-specific capabilities:

#### Core Plugin Capabilities
- `wmsw_manage_importer` - Manage the importer
- `wmsw_run_imports` - Run import operations
- `wmsw_view_logs` - View import logs
- `wmsw_manage_stores` - Manage Shopify stores
- `wmsw_view_dashboard` - Access the dashboard
- `wmsw_access_import_pages` - Access import pages
- `wmsw_view_import_logs` - View import logs
- `wmsw_manage_scheduled_tasks` - Manage scheduled tasks

#### Import-Specific Capabilities
- `wmsw_import_products` - Import products
- `wmsw_import_customers` - Import customers
- `wmsw_import_orders` - Import orders
- `wmsw_import_coupons` - Import coupons
- `wmsw_import_pages` - Import pages
- `wmsw_import_blogs` - Import blogs

#### Settings Capabilities
- `wmsw_manage_settings` - Manage plugin settings

### Minimal Additional Permissions

The demo role includes only essential capabilities:
- **File Upload**: For importing images and media
- **Unfiltered HTML**: For rich content handling

## Implementation Details

### Files Created/Modified

1. **`includes/handlers/WMSW_DemoUserHandler.php`** - Main demo user management class
2. **`includes/handlers/WMSW_ActivationHandler.php`** - Updated to create demo user on activation
3. **`backend/WMSW_Backend.php`** - Updated to use plugin-specific capabilities and display demo user notice
4. **`includes/handlers/WMSW_DeactivationHandler.php`** - Updated to clean up demo user on deactivation

### Key Methods

#### Demo User Handler
- `create_demo_role_and_user()` - Creates both role and user
- `create_demo_role()` - Creates the custom role with minimal capabilities
- `create_demo_user()` - Creates the demo user account
- `display_demo_user_notice()` - Shows admin notice with credentials
- `remove_demo_role_and_user()` - Cleanup method for uninstallation
- `hide_admin_menus_for_demo()` - Hides all admin menus except the plugin
- `redirect_demo_users()` - Redirects demo users away from restricted pages
- `customize_admin_bar_for_demo()` - Customizes the admin bar for demo users
- `is_demo_user()` - Checks if current user is the demo user

#### Integration Points
- **Activation**: Demo user is created when plugin is activated
- **Admin Notice**: Credentials are displayed in admin area after creation
- **Menu Access**: All plugin menus use plugin-specific capabilities
- **Security**: All AJAX handlers use plugin-specific capability checks
- **Restrictions**: Admin menus are hidden and redirects are enforced for demo users

## Usage

### For Testing
1. Activate the plugin
2. Look for the admin notice with demo user credentials
3. Log in with the demo user account
4. Experience a clean, focused admin interface with only the plugin visible
5. Test all plugin features with full access

### For Demonstration
1. Use the demo user to showcase plugin functionality
2. All features are accessible without administrator privileges
3. Clean, distraction-free interface perfect for client demonstrations
4. No risk of users accessing other WordPress features

### For Development
1. The demo user provides a consistent testing environment
2. All plugin capabilities are properly tested
3. Easy to verify permission-based access control
4. Simulates real-world usage scenarios

## Security Considerations

- Demo user has minimal access compared to administrator
- Plugin-specific capabilities provide granular control
- Demo user cannot access WordPress core admin features
- All other admin areas are completely hidden and inaccessible
- Credentials are clearly marked as demo/test purposes
- Automatic redirects prevent access to restricted areas

## Cleanup

The demo user and role can be removed using:
```php
\ShopifyWooImporter\Handlers\WMSW_DemoUserHandler::remove_demo_role_and_user();
```

This is typically called during plugin uninstallation.

## Customization

### Adding New Capabilities
To add new capabilities to the demo role, update the `$plugin_capabilities` array in `WMSW_DemoUserHandler.php`.

### Modifying Demo User Details
Update the constants in `WMSW_DemoUserHandler.php`:
- `DEMO_USER_LOGIN`
- `DEMO_USER_EMAIL`
- `DEMO_USER_PASSWORD`

### Changing Role Name
Update the constants:
- `DEMO_ROLE_NAME`
- `DEMO_ROLE_DISPLAY_NAME`

### Customizing Restrictions
Modify the `hide_admin_menus_for_demo()` method to show/hide specific admin menus as needed.

## Troubleshooting

### Demo User Not Created
- Check if the activation hook is properly registered
- Verify that the `WMSW_DemoUserHandler` class is loaded
- Check WordPress error logs for any activation errors

### Permission Issues
- Ensure plugin capabilities are properly added to the demo role
- Verify that the demo user has the correct role assigned
- Check if any security plugins are blocking role creation

### Notice Not Displayed
- Verify that the `display_demo_user_notice` method is hooked to `admin_notices`
- Check if the demo user actually exists before displaying notice
- Ensure the notice is only shown to users with appropriate permissions

### Admin Menus Still Visible
- Check if the `apply_demo_user_restrictions` method is properly hooked
- Verify that the demo user is correctly identified
- Ensure no other plugins are re-adding the menus after removal 