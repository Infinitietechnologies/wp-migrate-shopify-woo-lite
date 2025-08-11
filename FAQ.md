# Frequently Asked Questions (FAQ)

## 🚀 General Questions

### What is WP Migrate & Import Shopify to WooCommerce?

**WP Migrate & Import Shopify to WooCommerce** is a professional WordPress plugin that enables seamless migration of data from Shopify stores to WooCommerce. It supports importing products, customers, orders, pages, blogs, and coupons with advanced mapping and conflict resolution features.

### What data can I import from Shopify?

The plugin supports importing:
- **Products**: All product types, variants, images, and metadata
- **Customers**: Customer profiles, addresses, and order history
- **Orders**: Complete order data with line items and shipping
- **Pages**: Static pages and content
- **Blogs**: Blog posts and articles
- **Coupons**: Discount codes and promotional offers

### Do I need WooCommerce installed?

Yes, WooCommerce is required. The plugin requires WooCommerce version 4.0 or higher to be installed and activated on your WordPress site.

### What are the system requirements?

**Minimum Requirements:**
- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- 256MB PHP memory limit

**Recommended Requirements:**
- WordPress 6.0 or higher
- WooCommerce 8.0 or higher
- PHP 8.0 or higher
- MySQL 8.0 or higher
- 512MB PHP memory limit

## 🔧 Installation & Setup

### How do I install the plugin?

**Method 1: WordPress Admin (Recommended)**
1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

**Method 2: FTP Upload**
1. Extract the ZIP file
2. Upload the folder to `/wp-content/plugins/`
3. Activate via WordPress admin

### How do I set up Shopify API access?

1. **Create a Shopify App:**
   - Login to your Shopify admin
   - Go to Apps → Develop apps
   - Click "Create an app"
   - Enter app name and create

2. **Configure Permissions:**
   - Go to Configuration tab
   - Set Admin API access scopes:
     - `read_products`
     - `read_customers`
     - `read_orders`
     - `read_pages`
     - `read_blogs`
     - `read_discounts`

3. **Install and Get Token:**
   - Go to API credentials tab
   - Click "Install app"
   - Copy the Admin API access token

### How do I connect my first store?

1. Go to Shopify to WooCommerce → Dashboard
2. Click "Connect your first store"
3. Enter store details:
   - Store Name: Friendly name
   - Shop Domain: Your Shopify domain (e.g., `mystore.myshopify.com`)
   - Access Token: API token from step above
   - API Version: Latest stable (e.g., 2023-10)
4. Test connection and save

### Can I connect multiple Shopify stores?

Yes! The plugin supports multiple store connections. You can:
- Add multiple stores
- Set one as default
- Import from different stores
- Manage all stores from one interface

## 📦 Data Import

### How long does an import take?

Import time depends on:
- **Data Volume**: Number of items to import
- **Server Performance**: PHP memory and execution time
- **API Rate Limits**: Shopify API restrictions
- **Batch Size**: Items processed per batch

**Typical Times:**
- 100 products: 2-5 minutes
- 1,000 products: 10-30 minutes
- 10,000 products: 1-3 hours

### Can I import specific products only?

Yes! You can filter imports by:
- **Product Status**: Active, draft, archived
- **Collections**: Specific product collections
- **Tags**: Products with specific tags
- **Date Range**: Products created/updated within range

### What happens if I have duplicate products?

The plugin offers several conflict resolution options:
- **Skip**: Skip conflicting products
- **Update**: Update existing products
- **Replace**: Replace existing products
- **Create New**: Create new products with modified data

### Can I import product images?

Yes! The plugin imports:
- Product images
- Variant images
- Image metadata
- Image URLs and alt text

### How are customer passwords handled?

You can choose how to handle customer passwords:
- **Generate New**: Create new random passwords
- **Send Reset Links**: Email password reset links
- **Skip Password**: Leave password field empty
- **Use Default**: Use a default password

### Can I import orders with existing customers?

Yes! The plugin can:
- Link orders to existing WooCommerce customers
- Create new customers if they don't exist
- Match customers by email address
- Handle guest orders

## ⚙️ Configuration & Settings

### How do I customize field mapping?

1. Go to Settings → Field Mapping
2. Select data type (products, customers, etc.)
3. Map Shopify fields to WooCommerce fields
4. Configure transformation functions
5. Save mapping configuration

### What transformation functions are available?

**Text Functions:**
- Uppercase, lowercase, capitalize
- Remove HTML tags
- Trim whitespace

**Number Functions:**
- Format numbers
- Currency conversion
- Round numbers

**Date Functions:**
- Date formatting
- Timezone conversion
- Relative dates

**Custom Functions:**
- Custom PHP functions
- Conditional logic
- Data validation

### Can I schedule automatic imports?

Yes! You can schedule imports to run:
- **Hourly**: Every hour
- **Daily**: Once per day
- **Weekly**: Once per week
- **Custom**: Custom cron schedule

### How do I enable background processing?

1. Go to Settings → Advanced
2. Enable "Background Processing"
3. Configure batch sizes
4. Set processing intervals
5. Monitor background jobs

## 🚨 Troubleshooting

### Plugin won't activate

**Common Causes:**
1. **PHP Version**: Must be 7.4 or higher
2. **WordPress Version**: Must be 5.0 or higher
3. **WooCommerce**: Must be installed and activated
4. **Memory Limit**: Increase PHP memory limit

**Solutions:**
1. Check system requirements
2. Update WordPress and WooCommerce
3. Increase PHP memory limit to 512MB
4. Check error logs for specific issues

### Cannot connect to Shopify

**Common Causes:**
1. **Invalid Shop Domain**: Check domain format
2. **Invalid Access Token**: Verify token is correct
3. **API Permissions**: Check app permissions
4. **Network Issues**: Check server connectivity

**Solutions:**
1. Verify shop domain format (e.g., `mystore.myshopify.com`)
2. Regenerate API access token
3. Check app permissions in Shopify
4. Test API access manually

### Import fails or times out

**Common Causes:**
1. **Memory Limit**: Insufficient PHP memory
2. **Execution Time**: PHP timeout
3. **Batch Size**: Too many items per batch
4. **Server Resources**: Limited server capacity

**Solutions:**
1. Increase PHP memory limit to 512MB+
2. Increase execution time to 300 seconds
3. Reduce batch size in settings
4. Enable background processing

### API rate limit exceeded

**Common Causes:**
1. **Too Many Requests**: Exceeding Shopify API limits
2. **Concurrent Imports**: Multiple imports running
3. **Large Batches**: Processing too many items at once

**Solutions:**
1. Enable rate limiting in settings
2. Reduce concurrent API calls
3. Use background processing
4. Wait for rate limit reset

### Import data is missing

**Common Causes:**
1. **API Permissions**: Insufficient app permissions
2. **Data Filters**: Import filters too restrictive
3. **API Errors**: Failed API requests
4. **Mapping Issues**: Incorrect field mapping

**Solutions:**
1. Check app permissions in Shopify
2. Review import filters
3. Check import logs for errors
4. Verify field mapping configuration

### Duplicate data created

**Common Causes:**
1. **Conflict Resolution**: Wrong conflict handling
2. **Multiple Imports**: Running same import multiple times
3. **Mapping Issues**: Incorrect field mapping

**Solutions:**
1. Configure conflict resolution settings
2. Check for existing data before import
3. Review field mapping configuration
4. Use unique identifiers for matching

## 📊 Performance & Optimization

### How can I speed up imports?

**Optimization Tips:**
1. **Increase Memory**: Set PHP memory limit to 512MB+
2. **Enable Background Processing**: Process imports in background
3. **Optimize Batch Size**: Use appropriate batch sizes
4. **Enable Caching**: Cache API responses
5. **Reduce Concurrent Jobs**: Limit simultaneous imports

### What's the optimal batch size?

**Recommended Batch Sizes:**
- **Small Server**: 25-50 items per batch
- **Medium Server**: 50-100 items per batch
- **Large Server**: 100-200 items per batch

**Factors to Consider:**
- Server memory capacity
- API rate limits
- Import complexity
- Server performance

### How do I monitor import progress?

**Monitoring Options:**
1. **Dashboard**: Real-time progress overview
2. **Progress Bars**: Visual import progress
3. **Status Indicators**: Success, error, processing status
4. **Import Logs**: Detailed log entries
5. **Email Notifications**: Import completion notifications

### Can I pause and resume imports?

Yes! The plugin supports:
- **Pause Imports**: Temporarily stop imports
- **Resume Imports**: Continue from where you left off
- **Cancel Imports**: Stop imports completely
- **Retry Failed Items**: Retry failed imports

## 🔒 Security & Privacy

### Is my data secure?

**Security Features:**
1. **Encryption**: Sensitive data is encrypted
2. **Secure Storage**: Credentials stored securely
3. **Access Control**: Role-based permissions
4. **Audit Logging**: Complete activity logging
5. **HTTPS Only**: All API calls use HTTPS

### What permissions does the plugin need?

**Required Permissions:**
- **Administrator**: Full access to all features
- **Shop Manager**: Import and management features
- **Editor**: Limited import access
- **Author**: Basic import access

### How are API credentials stored?

**Security Measures:**
1. **Encryption**: Tokens encrypted in database
2. **Access Control**: Only authorized users can access
3. **Audit Trail**: All access is logged
4. **Secure Transmission**: HTTPS for all API calls

### Can I export my data?

Yes! The plugin supports:
- **Export Logs**: Download import logs
- **Export Settings**: Backup configuration
- **Export Mappings**: Save field mappings
- **Data Export**: Export imported data

## 🌐 Internationalization

### Does the plugin support multiple languages?

Yes! The plugin is fully internationalized:
- **Translation Ready**: Complete i18n support
- **RTL Support**: Right-to-left language support
- **Locale Support**: Multiple locale support
- **Custom Translations**: Add custom translations

### How do I add translations?

1. **Copy POT File**: Copy `.pot` file to your locale
2. **Translate Strings**: Use Poedit or similar tool
3. **Generate MO File**: Create compiled translation file
4. **Upload**: Place files in `/languages/` directory

### What languages are supported?

The plugin includes:
- **English**: Default language
- **French**: French translation
- **Custom**: Add your own translations

## 📞 Support

### How do I get help?

**Support Channels:**
1. **Email Support**: support@infinitietech.com
2. **Documentation**: Complete user guides
3. **Community**: WordPress.org forums
4. **GitHub**: Issue tracking

### What information should I provide?

**Include:**
1. **WordPress Version**: Current WordPress version
2. **Plugin Version**: Plugin version number
3. **PHP Version**: PHP version and extensions
4. **Error Logs**: Relevant error messages
5. **Steps to Reproduce**: Detailed reproduction steps
6. **System Info**: Server configuration

### Is there premium support available?

Yes! Premium support includes:
- **Priority Support**: Faster response times
- **Custom Development**: Custom feature development
- **Installation Service**: Professional installation
- **Training**: User training sessions

### How do I report bugs?

**Bug Reporting:**
1. **Enable Debug Mode**: Set logging to debug
2. **Reproduce Issue**: Document reproduction steps
3. **Collect Logs**: Gather error logs
4. **Submit Report**: Include all relevant information

## 🔄 Updates & Maintenance

### How often is the plugin updated?

**Update Schedule:**
- **Security Updates**: As needed
- **Feature Updates**: Monthly
- **Bug Fixes**: Weekly
- **Major Releases**: Quarterly

### How do I update the plugin?

**Update Methods:**
1. **Automatic Updates**: WordPress automatic updates
2. **Manual Updates**: Download and upload new version
3. **Composer Updates**: Update via Composer

### Should I backup before updating?

**Always backup:**
1. **Database**: Complete database backup
2. **Files**: Plugin files backup
3. **Settings**: Export plugin settings
4. **Test Environment**: Test updates in staging

### What happens to my data during updates?

**Data Safety:**
1. **Data Preserved**: All imported data is preserved
2. **Settings Maintained**: Configuration settings kept
3. **Mappings Retained**: Field mappings preserved
4. **Logs Preserved**: Import logs maintained

## 💰 Pricing & Licensing

### What license does the plugin use?

The plugin uses the **GPL v2 or later** license, which means:
- **Free to Use**: Use in any project
- **Modify**: Modify the code as needed
- **Distribute**: Share with others
- **Commercial Use**: Use in commercial projects

### Is there a free version?

The plugin is available as a **premium plugin** with:
- **Complete Features**: All features included
- **Premium Support**: Professional support
- **Regular Updates**: Continuous development
- **Documentation**: Comprehensive guides

### Can I use it on multiple sites?

**License Terms:**
- **Single Site**: One license per site
- **Multi-Site**: Separate license for each site
- **Development**: Free for development/testing
- **Staging**: Free for staging environments

---

**Still Have Questions?** Contact us at support@infinitietech.com or visit https://infinitietech.com/support 