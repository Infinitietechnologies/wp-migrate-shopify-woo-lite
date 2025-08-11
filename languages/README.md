# Language Files

This directory contains the translation files for the WP Migrate & Import Shopify to WooCommerce plugin.

## Files

- `wp-migrate-shopify-woo.pot` - The template file containing all translatable strings
- `wp-migrate-shopify-woo-fr_FR.po` - French translation file (example)
- `wp-migrate-shopify-woo-fr_FR.mo` - Compiled French translation file

## Adding New Translations

### Method 1: Using Poedit

1. **Download the .pot file**: Copy `wp-migrate-shopify-woo.pot` to your local machine
2. **Open with Poedit**: Use Poedit to open the .pot file
3. **Create new translation**: Save as a new .po file with your language code
   - Example: `wp-migrate-shopify-woo-fr_FR.po` for French
   - Example: `wp-migrate-shopify-woo-es_ES.po` for Spanish
4. **Translate strings**: Translate all the strings in the file
5. **Save and compile**: Poedit will automatically create the .mo file
6. **Upload files**: Upload both .po and .mo files to this directory

### Method 2: Using WP-CLI

If you have WP-CLI installed, you can use these commands:

```bash
# Generate .pot file
wp i18n make-pot . languages/wp-migrate-shopify-woo.pot --domain=wp-migrate-shopify-woo

# Create .po file for a specific language
wp i18n make-po languages/wp-migrate-shopify-woo.pot languages/wp-migrate-shopify-woo-fr_FR.po

# Compile .po to .mo
wp i18n make-mo languages/wp-migrate-shopify-woo-fr_FR.po languages/
```

### Method 3: Manual Translation

1. Copy the .pot file and rename it with your language code
2. Edit the header information in the .po file
3. Translate each `msgstr` field
4. Use a tool like `msgfmt` to compile the .mo file

## File Structure

```
languages/
├── wp-migrate-shopify-woo.pot          # Template file
├── wp-migrate-shopify-woo-fr_FR.po     # French translation (example)
├── wp-migrate-shopify-woo-fr_FR.mo     # Compiled French translation
└── README.md                           # This file
```

## Translation Guidelines

### Text Domain
Always use the text domain `wp-migrate-shopify-woo` in your translation functions:

```php
__('Your text here', 'wp-migrate-shopify-woo')
esc_html__('Your text here', 'wp-migrate-shopify-woo')
sprintf(__('Your text with %s', 'wp-migrate-shopify-woo'), $variable)
```

### Plural Forms
For plural forms, use:

```php
_n('1 item', '%d items', $count, 'wp-migrate-shopify-woo')
```

### Context
For context-specific translations, use:

```php
_x('Your text', 'context', 'wp-migrate-shopify-woo')
```

### Escaping
Always escape output appropriately:

```php
esc_html__('Your text', 'wp-migrate-shopify-woo')
esc_attr__('Your text', 'wp-migrate-shopify-woo')
```

## Testing Translations

1. Upload your .po and .mo files to this directory
2. Set your WordPress language to the target language
3. Test the plugin to ensure translations appear correctly
4. Check for any missing translations or formatting issues

## Contributing Translations

If you'd like to contribute a translation:

1. Create the translation files following the guidelines above
2. Test thoroughly in your target language
3. Submit a pull request with your translation files
4. Include a brief description of the translation and any special considerations

## Support

For questions about translations or to report issues:

- Create an issue on the plugin's GitHub repository
- Contact the plugin author through the support channels

**Note**: This plugin uses the text domain `wp-migrate-shopify-woo`. Make sure your translation files use this exact text domain. 