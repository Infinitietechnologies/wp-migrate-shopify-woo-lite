<?php

namespace ShopifyWooImporter\Models;

use ShopifyWooImporter\Core\WMSW_ShopifyClient;
use ShopifyWooImporter\Helpers\WMSW_EncryptionHelper;

/**
 * Shopify Store Model
 */
class WMSW_ShopifyStore
{
    private $id;
    private $store_name;
    private $shop_domain;
    private $access_token;
    private $api_version;
    private $is_active;
    private $is_default;
    private $last_sync;
    private $created_at;
    private $updated_at;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->populate($data);
        }
    }
    /**
     * Populate model with data
     */
    public function populate($data)
    {
        $this->id = $data['id'] ?? null;
        $this->store_name = $data['store_name'] ?? '';
        $this->shop_domain = $data['shop_domain'] ?? '';

        // Decrypt access token if it's encrypted
        $access_token = $data['access_token'] ?? '';
        if (!empty($access_token) && WMSW_EncryptionHelper::is_encrypted($access_token)) {
            $this->access_token = WMSW_EncryptionHelper::decrypt($access_token);
        } else {
            $this->access_token = $access_token;
        }

        $this->api_version = $data['api_version'] ?? WMSW_SHOPIFY_API_VERSION;
        $this->is_active = $data['is_active'] ?? 1;
        $this->is_default = $data['is_default'] ?? 0;
        $this->last_sync = $data['last_sync'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    /**
     * Save store to database
     */
    public function save()
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_STORES_TABLE;

        // Encrypt access token before saving
        $encrypted_token = '';
        if (!empty($this->access_token)) {
            $encrypted_token = WMSW_EncryptionHelper::encrypt($this->access_token);
        }

        $data = [
            'store_name' => $this->store_name,
            'shop_domain' => $this->shop_domain,
            'access_token' => $encrypted_token,
            'api_version' => $this->api_version,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'last_sync' => $this->last_sync
        ];

        if ($this->id) {
            // Update existing
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $this->id],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table,
                $data,
                ['%s', '%s', '%s', '%s', '%d', '%d', '%s']
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            }
        }

        return $result !== false;
    }

    /**
     * Delete store from database
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . WMSW_STORES_TABLE,
            ['id' => $this->id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Find store by ID
     */
    public static function find($id)
    {
        global $wpdb;

        $table = $wpdb->prefix . WMSW_STORES_TABLE;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM". esc_sql($wpdb->prefix . WMSW_STORES_TABLE)." WHERE id = %d", $id),
            \ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Find store by shop domain
     */
    public static function find_by_domain($shop_domain)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ". esc_sql($wpdb->prefix . WMSW_STORES_TABLE) ." WHERE shop_domain = %s", $shop_domain),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get all active stores
     */
    public static function get_active()
    {
        global $wpdb;

        $table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM ".esc_sql($wpdb->prefix . WMSW_STORES_TABLE) ." WHERE is_active = %d ORDER BY store_name", 1),
            ARRAY_A
        );

        $stores = [];
        foreach ($rows as $row) {
            $stores[] = new self($row);
        }

        return $stores;
    }
    /**
     * Test connection to Shopify
     */
    public function test_connection()
    {
        try {
            $client = new WMSW_ShopifyClient(
                $this->shop_domain,
                $this->access_token,
                $this->api_version
            );

            return $client->test_connection();
        } catch (\Exception $e) {
            return false;
        }
    }
    /**
     * Get all stores (for AJAX dropdowns)
     */
    public static function get_all()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);
        $rows = $wpdb->get_results("SELECT * FROM ". esc_sql($wpdb->prefix . WMSW_STORES_TABLE) ." ORDER BY store_name", ARRAY_A);
        $stores = [];
        foreach ($rows as $row) {
            $stores[] = [
                'id' => $row['id'],
                'store_name' => $row['store_name'],
                'shop_domain' => $row['shop_domain'],
                'is_active' => $row['is_active'],
                'is_default' => $row['is_default']
            ];
        }
        return $stores;
    }
    /**
     * Get store by ID (for AJAX)
     */
    public static function get($id)
    {
        return self::find($id);
    }

    // Getters
    public function get_id()
    {
        return $this->id;
    }
    public function get_store_name()
    {
        return $this->store_name;
    }
    public function get_shop_domain()
    {
        return $this->shop_domain;
    }
    public function get_access_token()
    {
        return $this->access_token;
    }
    public function get_api_version()
    {
        return $this->api_version;
    }
    public function get_is_active()
    {
        return $this->is_active;
    }
    public function get_is_default()
    {
        return $this->is_default;
    }
    public function get_last_sync()
    {
        return $this->last_sync;
    }
    public function get_created_at()
    {
        return $this->created_at;
    }
    public function get_updated_at()
    {
        return $this->updated_at;
    }

    // Setters
    public function set_store_name($store_name)
    {
        $this->store_name = $store_name;
    }
    public function set_shop_domain($shop_domain)
    {
        $this->shop_domain = $shop_domain;
    }
    public function set_access_token($access_token)
    {
        $this->access_token = $access_token;
    }
    public function set_api_version($api_version)
    {
        $this->api_version = $api_version;
    }
    public function set_is_active($is_active)
    {
        $this->is_active = $is_active;
    }
    public function set_is_default($is_default)
    {
        $this->is_default = $is_default;
    }
    public function set_last_sync($last_sync)
    {
        $this->last_sync = $last_sync;
    }

    /**
     * Migrate existing plaintext tokens to encrypted format
     * This method should be called once during plugin update
     */
    public static function migrate_tokens_to_encrypted()
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_STORES_TABLE;

        // Get all stores with non-empty access tokens
        $stores = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, access_token FROM ". esc_sql($wpdb->prefix . WMSW_STORES_TABLE) ." WHERE access_token != %s AND access_token IS NOT NULL",
                ''
            ),
            ARRAY_A
        );

        $migrated_count = 0;

        foreach ($stores as $store) {
            $token = $store['access_token'];

            // Skip if already encrypted
            if (WMSW_EncryptionHelper::is_encrypted($token)) {
                continue;
            }

            // Encrypt the token
            $encrypted_token = WMSW_EncryptionHelper::encrypt($token);

            // Update the database
            $result = $wpdb->update(
                $table,
                ['access_token' => $encrypted_token],
                ['id' => $store['id']],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                $migrated_count++;
            }
        }

        return $migrated_count;
    }

    /**
     * Get decrypted access token for internal use
     * This ensures we always get the plaintext token for API calls
     */
    public function get_decrypted_access_token()
    {
        return $this->access_token; // Already decrypted in populate()
    }

    /**
     * Check if current access token is valid (not empty)
     */
    public function has_valid_token()
    {
        return !empty($this->access_token);
    }

    public static function get_all_active_stores(int $get_active = 1)
    {
        global $wpdb;

        $table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);

        // Get all column names
        $columns = $wpdb->get_col("SHOW COLUMNS FROM ". esc_sql($wpdb->prefix . WMSW_STORES_TABLE) .");

        // Remove access_token column
        $columns = array_diff($columns, ['access_token']);

        // Build SELECT list
        $select_columns = implode(', ', array_map('esc_sql', $columns));

        // Query only active stores
        if ($get_active == 1) {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT %s FROM " . esc_sql($wpdb->prefix . WMSW_STORES_TABLE) . "WHERE is_active = %d", $select_columns, 1),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT %s FROM " . esc_sql($wpdb->prefix . WMSW_STORES_TABLE), $select_columns),
                ARRAY_A
            );
        }

        return $rows;
    }


    public static function get_all_stores_count(int $get_active = 1)
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . WMSW_STORES_TABLE);
        if ($get_active == 1) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($wpdb->prefix . WMSW_STORES_TABLE) . " WHERE is_active = 1");
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($wpdb->prefix . WMSW_STORES_TABLE));
        }
        return $count;
    }
}
