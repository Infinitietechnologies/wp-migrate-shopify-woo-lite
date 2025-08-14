# Installation Guide

## 📋 Prerequisites

### System Requirements

Before installing the plugin, ensure your system meets these requirements:

#### WordPress Requirements
- **WordPress Version**: 5.0 or higher
- **PHP Version**: 7.4 or higher
- **MySQL Version**: 5.6 or higher
- **Memory Limit**: 256MB minimum (512MB recommended)
- **Execution Time**: 300 seconds minimum
- **Upload Limit**: 64MB minimum

#### WooCommerce Requirements
- **WooCommerce Version**: 4.0 or higher
- **WooCommerce Status**: Active and properly configured
- **Product Types**: Standard, variable, grouped, and external products supported

#### Server Requirements
- **cURL Extension**: Enabled
- **JSON Extension**: Enabled
- **OpenSSL Extension**: Enabled
- **File Uploads**: Enabled
- **Cron Jobs**: Enabled (for background processing)
- **SSL Certificate**: Valid SSL certificate (recommended)

### Pre-Installation Checklist

- [ ] WordPress is up to date
- [ ] WooCommerce is installed and activated
- [ ] PHP version is 7.4 or higher
- [ ] Sufficient server resources available
- [ ] Backup of current site created
- [ ] SSL certificate installed (recommended)

## 🚀 Installation Methods

### Method 1: WordPress Admin (Recommended)

#### Step 1: Download the Plugin
1. Purchase and download the plugin ZIP file
2. Save the file to your computer
3. Ensure the file is not corrupted

#### Step 2: Upload via WordPress Admin
1. **Login** to your WordPress admin panel
2. **Navigate** to Plugins → Add New
3. **Click** "Upload Plugin" button
4. **Choose File** and select the downloaded ZIP
5. **Click** "Install Now"
6. **Wait** for installation to complete
7. **Click** "Activate Plugin"

#### Step 3: Verify Installation
1. **Check** that the plugin appears in the plugins list
2. **Verify** that "Shopify to WooCommerce" menu appears in admin
3. **Test** the plugin activation

### Method 2: FTP Upload

#### Step 1: Prepare Files
1. **Extract** the plugin ZIP file on your computer
2. **Locate** the `wp-migrate-shopify-woo-lite` folder
3. **Ensure** all files are present

#### Step 2: Upload via FTP
1. **Connect** to your server via FTP
2. **Navigate** to `/wp-content/plugins/`
3. **Upload** the `wp-migrate-shopify-woo-lite` folder
4. **Verify** all files uploaded successfully

#### Step 3: Activate Plugin
1. **Login** to WordPress admin
2. **Navigate** to Plugins → Installed Plugins
3. **Find** "WP Migrate & Import Shopify to WooCommerce"
4. **Click** "Activate"

### Method 3: Composer Installation

#### Step 1: Install Composer
```bash
# Install Composer (if not already installed)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

#### Step 2: Add to WordPress Project
```bash
# Navigate to your WordPress root directory
cd /path/to/wordpress

# Install the plugin via Composer
composer require wp-migrate-shopify-woo-lite/wp-migrate-shopify-woo-lite
```

#### Step 3: Activate Plugin
1. **Login** to WordPress admin
2. **Navigate** to Plugins → Installed Plugins
3. **Activate** the plugin

## ⚙️ Post-Installation Setup

### Step 1: Plugin Activation

#### Automatic Setup
1. **Activate** the plugin
2. **Wait** for database tables to be created
3. **Check** for any activation errors
4. **Verify** plugin menu appears

#### Manual Database Setup (if needed)
```sql
-- Check if tables exist
SHOW TABLES LIKE 'wp_wmsw_%';

-- If tables don't exist, deactivate and reactivate plugin
```

### Step 2: Initial Configuration

#### Access Plugin Dashboard
1. **Navigate** to Shopify to WooCommerce → Dashboard
2. **Review** the welcome screen
3. **Check** system requirements
4. **Verify** WooCommerce integration

#### System Check
1. **Click** "System Check" button
2. **Review** all requirements
3. **Address** any failed checks
4. **Proceed** to store setup

### Step 3: Shopify API Setup

#### Create Shopify App
1. **Login** to your Shopify admin panel
2. **Navigate** to Apps → Develop apps
3. **Click** "Create an app"
4. **Enter** app name (e.g., "WooCommerce Migration")
5. **Click** "Create app"

#### Configure App Permissions
1. **Navigate** to "Configuration" tab
2. **Set** Admin API access scopes:
   - `read_products`
   - `read_customers`
   - `read_orders`
   - `read_pages`
   - `read_blogs`
   - `read_discounts`
3. **Save** configuration

#### Install App and Get Token
1. **Navigate** to "API credentials" tab
2. **Click** "Install app"
3. **Authorize** the app in your store
4. **Copy** the Admin API access token
5. **Store** token securely

### Step 4: Connect First Store

#### Store Configuration
1. **Navigate** to Shopify to WooCommerce → Stores
2. **Click** "Add New Store"
3. **Fill** in store details:
   - **Store Name**: Friendly name (e.g., "My Main Store")
   - **Shop Domain**: Your Shopify domain (e.g., `mystore.myshopify.com`)
   - **Access Token**: Paste the API token from step 3
   - **API Version**: Latest stable (e.g., 2023-10)
4. **Click** "Test Connection"
5. **Verify** connection success
6. **Save** store configuration

## 🔧 Configuration

### General Settings

#### Access Plugin Settings
1. **Navigate** to Shopify to WooCommerce → Settings
2. **Review** all available options
3. **Configure** according to your needs

#### Default Settings
- **Default Store**: Select your primary store
- **Import Limits**: Set batch sizes (recommended: 50-100)
- **Timeout Settings**: Set appropriate timeouts
- **Conflict Resolution**: Choose default behavior

### Advanced Settings

#### Performance Settings
- **Background Processing**: Enable for large imports
- **Memory Management**: Configure memory limits
- **API Rate Limiting**: Set rate limits
- **Caching**: Enable/disable caching

#### Logging Settings
- **Log Level**: Set logging verbosity
- **Log Retention**: Set log retention period
- **Error Reporting**: Configure error reporting
- **Debug Mode**: Enable for troubleshooting

### Security Settings

#### Access Control
- **User Roles**: Configure access permissions
- **Capabilities**: Set user capabilities
- **API Security**: Configure API security settings
- **Data Protection**: Set data protection options

## 🧪 Testing Installation

### Basic Functionality Test

#### Test Store Connection
1. **Navigate** to Stores → Manage Stores
2. **Click** "Test Connection" for your store
3. **Verify** connection success
4. **Check** API response time

#### Test Import Functionality
1. **Navigate** to Import → Products
2. **Select** your store
3. **Configure** a small test import
4. **Run** test import
5. **Verify** import success

### Advanced Testing

#### Performance Testing
1. **Import** small dataset (10-20 items)
2. **Monitor** import speed
3. **Check** memory usage
4. **Verify** data accuracy

#### Error Handling Test
1. **Test** with invalid credentials
2. **Verify** error messages
3. **Test** network interruptions
4. **Check** recovery mechanisms

## 🚨 Troubleshooting

### Common Installation Issues

#### Plugin Won't Activate
**Symptoms**: Plugin activation fails
**Solutions**:
1. **Check** PHP version (must be 7.4+)
2. **Verify** WordPress version (must be 5.0+)
3. **Check** WooCommerce is active
4. **Review** error logs
5. **Increase** PHP memory limit

#### Database Table Creation Fails
**Symptoms**: Tables not created on activation
**Solutions**:
1. **Check** database permissions
2. **Verify** MySQL version (5.6+)
3. **Check** database character set
4. **Manually** create tables if needed

#### Menu Not Appearing
**Symptoms**: Plugin menu doesn't appear in admin
**Solutions**:
1. **Check** user capabilities
2. **Verify** plugin activation
3. **Clear** browser cache
4. **Check** for conflicts

### Connection Issues

#### Cannot Connect to Shopify
**Symptoms**: Connection test fails
**Solutions**:
1. **Verify** shop domain format
2. **Check** access token validity
3. **Ensure** app permissions are correct
4. **Test** API access manually

#### API Rate Limit Issues
**Symptoms**: API calls fail with rate limit errors
**Solutions**:
1. **Enable** rate limiting in settings
2. **Reduce** concurrent API calls
3. **Use** background processing
4. **Wait** for rate limit reset

### Performance Issues

#### Slow Imports
**Symptoms**: Imports take too long
**Solutions**:
1. **Increase** PHP memory limit
2. **Reduce** batch size
3. **Enable** background processing
4. **Optimize** server configuration

#### Memory Exhaustion
**Symptoms**: Out of memory errors
**Solutions**:
1. **Increase** PHP memory limit to 512MB+
2. **Reduce** import batch size
3. **Enable** background processing
4. **Close** other applications

### Data Issues

#### Import Data Missing
**Symptoms**: Some data not imported
**Solutions**:
1. **Check** import logs for errors
2. **Verify** API permissions
3. **Check** data availability in Shopify
4. **Retry** failed imports

#### Duplicate Data
**Symptoms**: Duplicate items created
**Solutions**:
1. **Configure** conflict resolution
2. **Check** import settings
3. **Review** mapping configuration
4. **Clean** duplicate data

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

#### Before Contacting Support
1. **Check** this installation guide
2. **Review** troubleshooting section
3. **Check** system requirements
4. **Gather** error logs and details
5. **Test** in different environment

### Debug Information

#### Enable Debug Mode
1. **Navigate** to Settings → Advanced
2. **Enable** debug mode
3. **Set** logging level to "Debug"
4. **Reproduce** the issue
5. **Collect** debug logs

#### Information to Provide
- **WordPress Version**: Current WordPress version
- **PHP Version**: PHP version and extensions
- **Plugin Version**: Plugin version number
- **Error Logs**: Relevant error messages
- **System Info**: Server configuration
- **Steps to Reproduce**: Detailed reproduction steps

## ✅ Installation Checklist

### Pre-Installation
- [ ] System requirements met
- [ ] WordPress and WooCommerce updated
- [ ] Site backup created
- [ ] SSL certificate installed (recommended)

### Installation
- [ ] Plugin uploaded successfully
- [ ] Plugin activated without errors
- [ ] Database tables created
- [ ] Menu appears in admin

### Configuration
- [ ] Shopify app created and configured
- [ ] API access token obtained
- [ ] Store connection tested
- [ ] Basic settings configured

### Testing
- [ ] Store connection works
- [ ] Test import successful
- [ ] Error handling works
- [ ] Performance acceptable

### Post-Installation
- [ ] Advanced settings configured
- [ ] Security settings applied
- [ ] Backup strategy implemented
- [ ] Documentation reviewed

---

**Need Help?** Contact us at support@infinitietech.com or visit https://infinitietech.com/support 