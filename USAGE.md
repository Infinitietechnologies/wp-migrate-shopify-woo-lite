# Usage Guide

## 🎯 Getting Started

### Dashboard Overview

The plugin dashboard provides a comprehensive overview of your migration status and quick access to all features.

#### Accessing the Dashboard
1. **Login** to your WordPress admin panel
2. **Navigate** to Shopify to WooCommerce → Dashboard
3. **Review** the overview information

#### Dashboard Sections
- **Connected Stores**: List of all connected Shopify stores
- **Recent Activity**: Latest import activities and status
- **Quick Actions**: Fast access to common tasks
- **System Status**: Current system health and requirements
- **Statistics**: Import statistics and performance metrics

### First Steps

#### 1. Connect Your First Store
1. **Click** "Connect your first store" on the dashboard
2. **Enter** your Shopify store details
3. **Test** the connection
4. **Save** the store configuration

#### 2. Configure Basic Settings
1. **Navigate** to Settings → General
2. **Set** default import options
3. **Configure** conflict resolution
4. **Save** settings

#### 3. Run Your First Import
1. **Choose** a data type (products, customers, etc.)
2. **Select** your store
3. **Configure** import options
4. **Start** the import process

## 🏪 Store Management

### Adding Stores

#### Single Store Setup
1. **Navigate** to Stores → Manage Stores
2. **Click** "Add New Store"
3. **Fill** in store information:
   - **Store Name**: Friendly name for identification
   - **Shop Domain**: Your Shopify store domain
   - **Access Token**: API access token
   - **API Version**: Latest stable version
4. **Test** connection
5. **Save** store

#### Multiple Store Setup
1. **Repeat** single store setup for each store
2. **Set** one store as default (optional)
3. **Configure** store-specific settings
4. **Test** all connections

### Managing Stores

#### Store Actions
- **Edit**: Modify store configuration
- **Test Connection**: Verify API connectivity
- **Activate/Deactivate**: Enable or disable store
- **Set Default**: Make store the primary store
- **Delete**: Remove store (with confirmation)

#### Store Status
- **Active**: Store is connected and ready
- **Inactive**: Store is disabled
- **Error**: Connection issues detected
- **Testing**: Connection test in progress

### Store Configuration

#### General Settings
- **Store Name**: Display name for the store
- **Shop Domain**: Shopify store domain
- **Access Token**: API authentication token
- **API Version**: Shopify API version

#### Advanced Settings
- **Import Limits**: Batch size and timeout settings
- **Rate Limiting**: API call rate limits
- **Caching**: Store-specific caching options
- **Logging**: Store-specific logging settings

## 📦 Data Import

### Products Import

#### Basic Product Import
1. **Navigate** to Import → Products
2. **Select** source store
3. **Choose** import type:
   - **All Products**: Import all products
   - **By Collection**: Import specific collections
   - **By Status**: Import by product status
4. **Configure** import options
5. **Start** import

#### Advanced Product Import
1. **Set** import filters:
   - **Status**: Active, draft, archived
   - **Collections**: Specific product collections
   - **Tags**: Products with specific tags
   - **Date Range**: Products created/updated within range
2. **Configure** mapping options
3. **Set** conflict resolution
4. **Start** import

#### Product Mapping
1. **Navigate** to Settings → Field Mapping → Products
2. **Map** Shopify fields to WooCommerce fields:
   - **Title**: Product title mapping
   - **Description**: Product description
   - **Price**: Product pricing
   - **Images**: Product images
   - **Variants**: Product variations
   - **Categories**: Product categories
3. **Configure** transformation functions
4. **Save** mapping configuration

#### Product Variants
- **Simple Products**: Single variant products
- **Variable Products**: Multiple variant products
- **Grouped Products**: Product groups
- **External Products**: External/affiliate products

### Customers Import

#### Basic Customer Import
1. **Navigate** to Import → Customers
2. **Select** source store
3. **Choose** import scope:
   - **All Customers**: Import all customers
   - **By Status**: Import by customer status
   - **By Tags**: Import customers with specific tags
4. **Configure** import options
5. **Start** import

#### Advanced Customer Import
1. **Set** import filters:
   - **Status**: Active, disabled, or all
   - **Tags**: Customers with specific tags
   - **Date Range**: Customers created within range
   - **Orders**: Customers with/without orders
2. **Configure** customer mapping
3. **Set** password handling
4. **Start** import

#### Customer Mapping
1. **Navigate** to Settings → Field Mapping → Customers
2. **Map** customer fields:
   - **Name**: First and last name
   - **Email**: Email address
   - **Phone**: Phone number
   - **Address**: Billing and shipping addresses
   - **Tags**: Customer tags
   - **Notes**: Customer notes
3. **Configure** data transformation
4. **Save** mapping

#### Password Handling
- **Generate New**: Create new passwords
- **Send Reset Links**: Email password reset links
- **Skip Password**: Leave password field empty
- **Use Default**: Use default password

### Orders Import

#### Basic Order Import
1. **Navigate** to Import → Orders
2. **Select** source store
3. **Choose** import scope:
   - **All Orders**: Import all orders
   - **By Status**: Import by order status
   - **By Date**: Import orders within date range
4. **Configure** import options
5. **Start** import

#### Advanced Order Import
1. **Set** import filters:
   - **Status**: Open, closed, cancelled, pending
   - **Date Range**: Orders within specific period
   - **Customer**: Orders for specific customers
   - **Products**: Orders containing specific products
2. **Configure** order mapping
3. **Set** customer linking
4. **Start** import

#### Order Mapping
1. **Navigate** to Settings → Field Mapping → Orders
2. **Map** order fields:
   - **Order Number**: Order identification
   - **Customer**: Customer information
   - **Products**: Order items
   - **Shipping**: Shipping information
   - **Taxes**: Tax calculations
   - **Payment**: Payment information
3. **Configure** data transformation
4. **Save** mapping

#### Order Status Mapping
- **Open**: Processing orders
- **Closed**: Completed orders
- **Cancelled**: Cancelled orders
- **Pending**: Pending orders

### Pages Import

#### Basic Page Import
1. **Navigate** to Import → Pages
2. **Select** source store
3. **Choose** import scope:
   - **All Pages**: Import all pages
   - **By Status**: Import by page status
   - **Specific Pages**: Import selected pages
4. **Configure** import options
5. **Start** import

#### Advanced Page Import
1. **Set** import filters:
   - **Status**: Published, draft, or all
   - **Templates**: Specific page templates
   - **Date Range**: Pages created within range
2. **Configure** page mapping
3. **Set** slug conflict resolution
4. **Start** import

#### Page Mapping
1. **Navigate** to Settings → Field Mapping → Pages
2. **Map** page fields:
   - **Title**: Page title
   - **Content**: Page content
   - **Slug**: Page URL slug
   - **Template**: Page template
   - **Meta**: Page meta data
3. **Configure** content transformation
4. **Save** mapping

### Blogs Import

#### Basic Blog Import
1. **Navigate** to Import → Blogs
2. **Select** source store
3. **Choose** import type:
   - **Blogs Only**: Import blog structure
   - **Articles Only**: Import blog articles
   - **Both**: Import blogs and articles
4. **Configure** import options
5. **Start** import

#### Advanced Blog Import
1. **Set** import filters:
   - **Blogs**: Specific blogs to import
   - **Articles**: Articles within date range
   - **Status**: Published, draft, or all
2. **Configure** WordPress mapping
3. **Set** category handling
4. **Start** import

#### Blog Mapping
1. **Navigate** to Settings → Field Mapping → Blogs
2. **Map** blog fields:
   - **Title**: Blog title
   - **Description**: Blog description
   - **Articles**: Blog articles
   - **Categories**: Blog categories
3. **Configure** WordPress post type
4. **Save** mapping

#### WordPress Integration
- **Posts**: Import as WordPress posts
- **Pages**: Import as WordPress pages
- **Custom Post Types**: Import as custom post types
- **Categories**: Create WordPress categories

### Coupons Import

#### Basic Coupon Import
1. **Navigate** to Import → Coupons
2. **Select** source store
3. **Choose** import scope:
   - **All Coupons**: Import all coupons
   - **By Type**: Import by coupon type
   - **By Status**: Import by coupon status
4. **Configure** import options
5. **Start** import

#### Advanced Coupon Import
1. **Set** import filters:
   - **Type**: Percentage, fixed amount, shipping
   - **Status**: Active, inactive, or all
   - **Date Range**: Coupons created within range
2. **Configure** coupon mapping
3. **Set** code conflict resolution
4. **Start** import

#### Coupon Mapping
1. **Navigate** to Settings → Field Mapping → Coupons
2. **Map** coupon fields:
   - **Code**: Coupon code
   - **Type**: Coupon type
   - **Amount**: Discount amount
   - **Usage**: Usage limits
   - **Expiry**: Expiration date
3. **Configure** currency conversion
4. **Save** mapping

## ⚙️ Advanced Features

### Field Mapping

#### Custom Field Mapping
1. **Navigate** to Settings → Field Mapping
2. **Select** data type (products, customers, etc.)
3. **Map** custom fields:
   - **Source Field**: Shopify field name
   - **Target Field**: WooCommerce field name
   - **Transformation**: Data transformation function
4. **Save** mapping configuration

#### Transformation Functions
- **Text Functions**: Uppercase, lowercase, capitalize
- **Number Functions**: Format numbers, currency conversion
- **Date Functions**: Date formatting, timezone conversion
- **Custom Functions**: Custom PHP functions

#### Mapping Templates
- **Default Template**: Standard field mapping
- **Custom Template**: User-defined mapping
- **Import Template**: Import mapping from file
- **Export Template**: Export mapping to file

### Conflict Resolution

#### Product Conflicts
- **Skip**: Skip conflicting products
- **Update**: Update existing products
- **Replace**: Replace existing products
- **Create New**: Create new products with modified data

#### Customer Conflicts
- **Skip**: Skip conflicting customers
- **Update**: Update existing customers
- **Merge**: Merge customer data
- **Create New**: Create new customers

#### Order Conflicts
- **Skip**: Skip conflicting orders
- **Update**: Update existing orders
- **Replace**: Replace existing orders
- **Create New**: Create new orders

#### Page Conflicts
- **Skip**: Skip conflicting pages
- **Update**: Update existing pages
- **Replace**: Replace existing pages
- **Modify Slug**: Modify page slug

### Batch Processing

#### Background Processing
1. **Enable** background processing in settings
2. **Configure** batch sizes
3. **Set** processing intervals
4. **Monitor** background jobs

#### Queue Management
- **View Queue**: Check pending jobs
- **Pause Queue**: Pause processing
- **Resume Queue**: Resume processing
- **Clear Queue**: Clear all pending jobs

#### Performance Optimization
- **Batch Size**: Adjust based on server capacity
- **Memory Limit**: Configure memory usage
- **Timeout Settings**: Set processing timeouts
- **Concurrent Jobs**: Limit concurrent processing

### Scheduling

#### Automated Imports
1. **Navigate** to Settings → Scheduling
2. **Configure** import schedules:
   - **Frequency**: Hourly, daily, weekly, custom
   - **Time**: Specific time for imports
   - **Data Types**: Which data to import
3. **Enable** scheduled imports
4. **Monitor** scheduled jobs

#### Schedule Types
- **Hourly**: Run every hour
- **Daily**: Run once per day
- **Weekly**: Run once per week
- **Custom**: Custom cron schedule

#### Schedule Management
- **View Schedules**: List all scheduled jobs
- **Edit Schedule**: Modify schedule settings
- **Pause Schedule**: Temporarily disable schedule
- **Delete Schedule**: Remove schedule

## 📊 Monitoring & Logs

### Import Monitoring

#### Real-time Progress
- **Progress Bars**: Visual import progress
- **Status Indicators**: Success, error, processing
- **Item Counts**: Items processed, remaining, failed
- **Time Estimates**: Estimated completion time

#### Import Statistics
- **Total Items**: Total items to import
- **Processed Items**: Successfully imported items
- **Failed Items**: Items that failed to import
- **Skipped Items**: Items skipped due to conflicts

### Log Management

#### Viewing Logs
1. **Navigate** to Logs → Import Logs
2. **Filter** logs by:
   - **Date Range**: Specific time period
   - **Log Level**: Error, warning, info, debug
   - **Store**: Specific store logs
   - **Task Type**: Import type
3. **View** detailed log entries

#### Log Levels
- **Error**: Critical errors that prevent import
- **Warning**: Issues that may affect import
- **Info**: General information about import
- **Debug**: Detailed debugging information

#### Log Actions
- **Export Logs**: Download log files
- **Clear Logs**: Remove old log entries
- **Search Logs**: Search for specific entries
- **Filter Logs**: Filter by various criteria

### Error Handling

#### Common Errors
- **API Errors**: Shopify API connection issues
- **Validation Errors**: Data validation failures
- **Database Errors**: Database operation failures
- **Memory Errors**: Insufficient memory

#### Error Recovery
- **Automatic Retry**: Failed items retried automatically
- **Manual Retry**: Manually retry failed imports
- **Skip Errors**: Skip problematic items
- **Error Reporting**: Detailed error information

## 🔧 Settings & Configuration

### General Settings

#### Import Settings
- **Default Batch Size**: Default items per batch
- **Timeout Settings**: Import timeout values
- **Memory Limits**: Memory usage limits
- **Error Handling**: Error handling behavior

#### Performance Settings
- **Background Processing**: Enable/disable background jobs
- **Caching**: Enable/disable caching
- **Rate Limiting**: API rate limit settings
- **Concurrent Jobs**: Number of concurrent jobs

### Advanced Settings

#### API Settings
- **API Version**: Default API version
- **Rate Limits**: API call rate limits
- **Timeout Values**: API timeout settings
- **Retry Logic**: API retry configuration

#### Database Settings
- **Table Prefix**: Custom table prefix
- **Connection Settings**: Database connection options
- **Query Limits**: Database query limits
- **Optimization**: Database optimization settings

#### Security Settings
- **Access Control**: User role permissions
- **API Security**: API security settings
- **Data Protection**: Data protection options
- **Audit Logging**: Security audit logging

### User Management

#### User Roles
- **Administrator**: Full access to all features
- **Shop Manager**: Access to import and management features
- **Editor**: Limited access to import features
- **Author**: Basic access to import features

#### Capabilities
- **Manage Imports**: Control import operations
- **Manage Stores**: Manage store connections
- **View Logs**: Access to log files
- **Manage Settings**: Configure plugin settings

## 🚨 Troubleshooting

### Common Issues

#### Import Failures
**Symptoms**: Imports fail or timeout
**Solutions**:
1. **Check** server resources
2. **Increase** memory limits
3. **Reduce** batch sizes
4. **Enable** background processing

#### Connection Issues
**Symptoms**: Cannot connect to Shopify
**Solutions**:
1. **Verify** API credentials
2. **Check** API permissions
3. **Test** network connectivity
4. **Review** error logs

#### Performance Issues
**Symptoms**: Slow imports or timeouts
**Solutions**:
1. **Optimize** server configuration
2. **Enable** caching
3. **Reduce** concurrent jobs
4. **Monitor** resource usage

### Debug Mode

#### Enable Debug Mode
1. **Navigate** to Settings → Advanced
2. **Enable** debug mode
3. **Set** logging level to "Debug"
4. **Reproduce** the issue
5. **Collect** debug information

#### Debug Information
- **System Information**: Server and PHP details
- **Plugin Information**: Plugin version and settings
- **Error Logs**: Detailed error messages
- **Performance Data**: Import performance metrics

## 📞 Support

### Getting Help

#### Documentation
- **User Guide**: Complete usage documentation
- **FAQ**: Frequently asked questions
- **Tutorials**: Step-by-step guides
- **API Reference**: Developer documentation

#### Support Channels
- **Email Support**: support@infinitietech.com
- **Documentation**: https://infinitietech.com/docs
- **Community**: WordPress.org forums
- **GitHub**: Issue tracking

### Before Contacting Support
1. **Check** this usage guide
2. **Review** troubleshooting section
3. **Enable** debug mode
4. **Collect** error logs
5. **Test** in different environment

---

**Need Help?** Contact us at support@infinitietech.com or visit https://infinitietech.com/support 