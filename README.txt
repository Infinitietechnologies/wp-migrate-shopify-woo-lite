=== WP Migrate & Import Shopify to WC Lite ===
Contributors: infinitietech
Tags: shopify, WooCommerce, migration, import, ecommerce
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional multi-store migration tool for seamless data transfer from Shopify to WooCommerce with advanced mapping, batch processing, and comprehensive logging.

== Description ==

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-4.0+-green.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%20or%20later-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 🚀 Overview

**WP Migrate & Import Shopify to WooCommerce** is a professional-grade migration tool that enables seamless data transfer from Shopify stores to WooCommerce. Built with enterprise-level features, it supports multiple store connections, batch processing, and comprehensive data mapping.

### ✨ Key Features

- **Multi-Store Support**: Connect and manage multiple Shopify stores
- **Complete Data Migration**: Products, Customers, Orders, Pages, Blogs, and Coupons
- **Advanced Mapping**: Custom field mapping and data transformation
- **Batch Processing**: Handle large datasets with background processing
- **Conflict Resolution**: Intelligent handling of duplicate content
- **Real-time Logging**: Comprehensive import logs and error tracking
- **Scheduling**: Automated import scheduling and synchronization
- **API Integration**: Secure Shopify API integration with rate limiting
- **Translation Ready**: Full internationalization support

## 📋 Requirements

### System Requirements
- **WordPress**: 5.0 or higher
- **WooCommerce**: 4.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory Limit**: 256MB minimum (512MB recommended)
- **Execution Time**: 300 seconds minimum

### Server Requirements
- **cURL**: Enabled
- **JSON**: Enabled
- **OpenSSL**: Enabled
- **File Uploads**: Enabled
- **Cron Jobs**: Enabled (for background processing)

## 🛠️ Installation

### Method 1: WordPress Admin (Recommended)

1. **Download** the plugin ZIP file
2. **Login** to your WordPress admin panel
3. **Navigate** to Plugins → Add New → Upload Plugin
4. **Choose File** and select the downloaded ZIP
5. **Click** "Install Now"
6. **Activate** the plugin

### Method 2: FTP Upload

1. **Extract** the plugin ZIP file
2. **Upload** the `wp-migrate-shopify-woo` folder to `/wp-content/plugins/`
3. **Activate** the plugin via WordPress admin

### Method 3: Composer Installation

```bash
composer require wp-migrate-shopify-woo/wp-migrate-shopify-woo
```

## ⚙️ Configuration

### 1. Initial Setup

1. **Navigate** to Shopify to WooCommerce → Dashboard
2. **Click** "Connect your first store"
3. **Enter** your Shopify store details:
   - **Shop Domain**: Your Shopify store domain (e.g., `mystore.myshopify.com`)
   - **Access Token**: Your Shopify API access token
   - **API Version**: Latest stable version (recommended: 2023-10)

### 2. Shopify API Setup

#### Creating Shopify Access Token

1. **Login** to your Shopify admin panel
2. **Navigate** to Apps → Develop apps
3. **Click** "Create an app"
4. **Configure** app permissions:
   - **Products**: Read access
   - **Customers**: Read access
   - **Orders**: Read access
   - **Pages**: Read access
   - **Blogs**: Read access
   - **Discounts**: Read access
5. **Install** the app in your store
6. **Copy** the Admin API access token

### 3. Plugin Settings

#### General Settings
- **Default Store**: Set your primary Shopify store
- **Import Limits**: Configure batch sizes and timeouts
- **Conflict Resolution**: Choose default conflict handling

#### Advanced Settings
- **Background Processing**: Enable/disable background jobs
- **Logging Level**: Set logging verbosity
- **API Rate Limiting**: Configure API call limits
- **Data Retention**: Set log and cache retention periods

## 📊 Usage Guide

### 1. Connecting Stores

#### Single Store Setup
1. **Go to** Shopify to WooCommerce → Stores
2. **Click** "Add New Store"
3. **Fill** in store details:
   - **Store Name**: Friendly name for identification
   - **Shop Domain**: Your Shopify store domain
   - **Access Token**: API access token
   - **API Version**: Latest stable version
4. **Test Connection** to verify credentials
5. **Save** the store configuration

#### Multiple Store Setup
1. **Repeat** the single store setup for each store
2. **Set** one store as default (optional)
3. **Configure** store-specific settings
4. **Test** all connections

### 2. Importing Data

#### Products Import
1. **Navigate** to Import → Products
2. **Select** source store
3. **Configure** import options:
   - **Import Type**: All products or specific collections
   - **Status Filter**: Active, draft, or archived
   - **Date Range**: Import products created/updated within date range
   - **Conflict Resolution**: Skip, update, or replace existing
4. **Map** custom fields (optional)
5. **Start** import process

#### Customers Import
1. **Navigate** to Import → Customers
2. **Select** source store
3. **Configure** import options:
   - **Status Filter**: Active, disabled, or all customers
   - **Tags Filter**: Import customers with specific tags
   - **Date Range**: Import customers created within date range
   - **Password Handling**: Generate new passwords or use reset links
4. **Start** import process

#### Orders Import
1. **Navigate** to Import → Orders
2. **Select** source store
3. **Configure** import options:
   - **Status Filter**: Open, closed, cancelled, or all orders
   - **Date Range**: Import orders within date range
   - **Customer Mapping**: Link to existing WooCommerce customers
   - **Product Mapping**: Link to existing WooCommerce products
4. **Start** import process

#### Pages Import
1. **Navigate** to Import → Pages
2. **Select** source store
3. **Configure** import options:
   - **Page Selection**: All pages or specific pages
   - **Status Filter**: Published, draft, or all pages
   - **Conflict Resolution**: Handle duplicate slugs
4. **Start** import process

#### Blogs Import
1. **Navigate** to Import → Blogs
2. **Select** source store
3. **Configure** import options:
   - **Import Type**: Blogs only, articles only, or both
   - **WordPress Mapping**: Posts or pages
   - **Category Handling**: Create categories or use existing
4. **Start** import process

#### Coupons Import
1. **Navigate** to Import → Coupons
2. **Select** source store
3. **Configure** import options:
   - **Code Conflict Resolution**: Skip or modify duplicate codes
   - **Currency Conversion**: Handle currency differences
   - **Tax Handling**: Preserve or recalculate taxes
4. **Start** import process

### 3. Monitoring Imports

#### Real-time Progress
- **Dashboard**: View overall import status
- **Progress Bars**: Real-time import progress
- **Status Indicators**: Success, error, or processing status

#### Import Logs
1. **Navigate** to Logs → Import Logs
2. **Filter** by:
   - **Date Range**: Specific time period
   - **Log Level**: Error, warning, info, or debug
   - **Store**: Specific store logs
   - **Task Type**: Import type
3. **View** detailed log entries
4. **Export** logs for analysis

#### Error Handling
- **Automatic Retry**: Failed items are retried automatically
- **Manual Retry**: Manually retry failed imports
- **Error Details**: Detailed error messages and solutions
- **Skip Options**: Skip problematic items and continue

### 4. Advanced Features

#### Custom Field Mapping
1. **Navigate** to Settings → Field Mapping
2. **Select** data type (products, customers, etc.)
3. **Map** Shopify fields to WooCommerce fields
4. **Configure** transformation functions
5. **Save** mapping configuration

#### Scheduled Imports
1. **Navigate** to Settings → Scheduling
2. **Configure** import schedules:
   - **Frequency**: Hourly, daily, weekly, or custom
   - **Time**: Specific time for imports
   - **Data Types**: Which data to import
3. **Enable** scheduled imports
4. **Monitor** scheduled job status

#### Background Processing
- **Automatic**: Large imports run in background
- **Queue Management**: Monitor and manage import queues
- **Resource Optimization**: Efficient memory and CPU usage
- **Timeout Handling**: Graceful handling of timeouts

## 🔧 Troubleshooting

### Common Issues

#### Connection Problems
**Issue**: Cannot connect to Shopify store
**Solutions**:
1. **Verify** shop domain format (e.g., `mystore.myshopify.com`)
2. **Check** access token validity
3. **Ensure** API permissions are correct
4. **Test** connection with Shopify admin

#### Import Failures
**Issue**: Imports fail or timeout
**Solutions**:
1. **Increase** PHP memory limit to 512MB
2. **Extend** execution time to 300 seconds
3. **Reduce** batch size in settings
4. **Check** server resources

#### Memory Issues
**Issue**: Out of memory errors
**Solutions**:
1. **Increase** PHP memory limit
2. **Reduce** import batch size
3. **Enable** background processing
4. **Close** other applications

#### API Rate Limits
**Issue**: Shopify API rate limit exceeded
**Solutions**:
1. **Enable** rate limiting in settings
2. **Reduce** concurrent API calls
3. **Use** background processing
4. **Wait** for rate limit reset

### Performance Optimization

#### Server Optimization
- **PHP Memory**: Increase to 512MB or higher
- **Execution Time**: Set to 300 seconds or higher
- **Database**: Optimize MySQL configuration
- **Caching**: Enable WordPress caching

#### Plugin Optimization
- **Batch Size**: Adjust based on server capacity
- **Background Processing**: Enable for large imports
- **Logging Level**: Reduce for production
- **Data Retention**: Clean up old logs regularly

### Debug Mode

#### Enable Debug Logging
1. **Navigate** to Settings → Advanced
2. **Set** logging level to "Debug"
3. **Enable** detailed error reporting
4. **Check** logs for specific issues

#### Common Debug Information
- **API Responses**: Raw Shopify API responses
- **Database Queries**: SQL query execution
- **Memory Usage**: Memory consumption tracking
- **Execution Time**: Import timing information

## 🔒 Security

### Data Protection
- **Encryption**: Sensitive data is encrypted
- **Access Control**: Role-based permissions
- **Audit Logging**: Complete activity logging
- **Secure Storage**: Secure credential storage

### API Security
- **HTTPS Only**: All API calls use HTTPS
- **Token Management**: Secure token handling
- **Rate Limiting**: API rate limit protection
- **Error Handling**: Secure error responses

### WordPress Security
- **Nonce Verification**: All forms use nonces
- **Capability Checks**: Proper permission checks
- **Input Sanitization**: All inputs are sanitized
- **Output Escaping**: All outputs are escaped

## 🌐 Internationalization

### Translation Support
- **Text Domain**: `wp-migrate-shopify-woo`
- **Translation Files**: Located in `/languages/`
- **RTL Support**: Right-to-left language support
- **Locale Support**: Multiple locale support

### Adding Translations
1. **Copy** `.pot` file to your locale
2. **Translate** strings using Poedit
3. **Generate** `.mo` file
4. **Upload** to `/languages/` directory

## 📈 Performance

### Optimization Tips
- **Batch Processing**: Use appropriate batch sizes
- **Background Jobs**: Enable for large imports
- **Caching**: Enable WordPress caching
- **Database**: Regular database optimization

### Monitoring
- **Import Speed**: Monitor import performance
- **Memory Usage**: Track memory consumption
- **API Calls**: Monitor API usage
- **Error Rates**: Track error frequencies

## 🔄 Updates & Maintenance

### Plugin Updates
- **Automatic Updates**: WordPress automatic updates
- **Manual Updates**: Manual update process
- **Backup**: Always backup before updates
- **Testing**: Test updates in staging environment

### Database Maintenance
- **Regular Cleanup**: Clean old logs and cache
- **Optimization**: Regular database optimization
- **Backup**: Regular database backups
- **Monitoring**: Monitor database performance

## 📞 Support

### Documentation
- **User Guide**: Complete usage documentation
- **API Reference**: Developer documentation
- **FAQ**: Frequently asked questions
- **Tutorials**: Step-by-step tutorials

### Support Channels
- **Email Support**: support@infinitietech.com
- **Documentation**: https://infinitietech.com/docs
- **Community**: WordPress.org forums
- **GitHub**: Issue tracking and contributions

### Premium Support
- **Priority Support**: Faster response times
- **Custom Development**: Custom feature development
- **Installation Service**: Professional installation
- **Training**: User training sessions

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Infinitietech

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 🙏 Credits

- **WordPress**: https://wordpress.org/
- **WooCommerce**: https://woocommerce.com/
- **Shopify**: https://shopify.com/
- **Infinitietech**: https://infinitietech.com/

## 📝 Changelog

### Version 1.0.0 (2024-01-01)
- **Initial Release**
- Multi-store Shopify to WooCommerce migration
- Complete data import (products, customers, orders, pages, blogs, coupons)
- Advanced field mapping and conflict resolution
- Background processing and scheduling
- Comprehensive logging and error handling
- Full internationalization support
- Security and performance optimizations

---

**Need Help?** Contact us at support@infinitietech.com or visit https://infinitietech.com/support 