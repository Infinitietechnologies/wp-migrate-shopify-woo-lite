<?php

namespace ShopifyWooImporter\Models;

/**
 * Settings Model
 * Handles CRUD operations for plugin settings (global and per-store)
 */
class WMSW_Settings
{
    private $id;
    private $storeId;
    private $settingKey;
    private $settingValue;
    private $settingType;
    private $isGlobal;
    private $createdAt;
    private $updatedAt;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    public function populate($data)
    {
        $this->id = $data['id'] ?? null;
        $this->storeId = $data['store_id'] ?? null;
        $this->settingKey = $data['setting_key'] ?? '';
        $this->settingValue = $data['setting_value'] ?? null;
        $this->settingType = $data['setting_type'] ?? 'string';
        $this->isGlobal = $data['is_global'] ?? 0;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public function save()
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_SETTINGS_TABLE;

        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
            $logger = new \ShopifyWooImporter\Services\WMSW_Logger();
            $logger->debug("[WMSW_Settings::save] Saving setting: " . json_encode([
                'id' => $this->id,
                'key' => $this->settingKey,
                'storeId' => $this->storeId,
                'isGlobal' => $this->isGlobal
            ], true));

            try {
                $data = [
                    'store_id' => $this->storeId,
                    'setting_key' => $this->settingKey,
                    'setting_value' => is_array($this->settingValue) || is_object($this->settingValue) ? serialize($this->settingValue) : $this->settingValue,
                    'setting_type' => $this->settingType,
                    'is_global' => $this->isGlobal,
                    'updated_at' => current_time('mysql')
                ];

                if ($this->id) {
                    if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                        $logger = new \ShopifyWooImporter\Services\WMSW_Logger();
                        $logger->debug("[WMSW_Settings::save] Updating existing record ID: " . $this->id);
                    }
                    $result = $wpdb->update($table, $data, ['id' => $this->id]);
                    if ($result === false) {
                        if (class_exists('ShopifyWooImporter\\Services\\WMSW_Logger') && \ShopifyWooImporter\Services\WMSW_Logger::isDebugModeEnabled()) {
                            $logger = new \ShopifyWooImporter\Services\WMSW_Logger();
                            $logger->error("[WMSW_Settings::save] Update failed. SQL Error: " . $wpdb->last_error);
                        }
                        return false;
                    }
                } else {
                    $data['created_at'] = current_time('mysql');
                    $result = $wpdb->insert($table, $data);
                    if ($result === false) {
                        return false;
                    }
                    $this->id = $wpdb->insert_id;
                }
            } catch (\Throwable $e) {
            }
        }
    }

    public static function get($key, $storeId = null, $isGlobal = false)
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_SETTINGS_TABLE;
        $where = 'setting_key = %s';
        $params = [$key];
        if ($isGlobal) {
            $where .= ' AND is_global = 1';
        } elseif ($storeId) {
            $where .= ' AND store_id = %d';
            $params[] = $storeId;
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . esc_sql($wpdb->prefix . WMSW_SETTINGS_TABLE) . " WHERE %s ORDER BY updated_at DESC LIMIT 1", $where), 'ARRAY_A');
        if ($row) {
            $row['setting_value'] = self::maybeUnserialize($row['setting_value']);
            return new self($row);
        }
        return null;
    }

    /**
     * Helper: unserialize if needed
     */
    private static function maybeUnserialize($value)
    {
        // Use WP's is_serialized if available, otherwise fallback
        // Use WP's is_serialized if available, otherwise fallback
        // Inline is_serialized logic for compatibility
        $is_serialized = false;
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === 'N;') {
                $is_serialized = true;
            } elseif (preg_match('/^([adObis]):/', $trim)) {
                $unser = @unserialize($trim);
                if ($unser !== false || $trim === 'b:0;') {
                    $is_serialized = true;
                }
            }
        }
        if ($is_serialized) {
            return unserialize($value);
        }
        return $value;
    }

    public static function update($key, $value, $storeId = null, $isGlobal = false, $type = 'string')
    {
        // Debug: Log every update attempt (only in debug mode)
        $setting = self::get($key, $storeId, $isGlobal);
        if ($setting) {
            $setting->settingValue = $value;
            $setting->settingType = $type;
            $setting->isGlobal = $isGlobal ? 1 : 0;
            $result = $setting->save();
            return $result;
        } else {
            $setting = new self([
                'store_id' => $storeId,
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => $type,
                'is_global' => $isGlobal ? 1 : 0,
            ]);
            $result = $setting->save();
            return $result;
        }
    }

    public static function delete($key, $storeId = null, $isGlobal = false)
    {
        global $wpdb;
        $table = $wpdb->prefix . WMSW_SETTINGS_TABLE;
        $where = ['setting_key' => $key];
        if ($isGlobal) {
            $where['is_global'] = 1;
        } elseif ($storeId) {
            $where['store_id'] = $storeId;
        }
        return $wpdb->delete($table, $where) !== false;
    }

    // Getters
    public function getId()
    {
        return $this->id;
    }
    public function getStoreId()
    {
        return $this->storeId;
    }
    public function getSettingKey()
    {
        return $this->settingKey;
    }
    public function getSettingValue()
    {
        return $this->settingValue;
    }
    public function getSettingType()
    {
        return $this->settingType;
    }
    public function getIsGlobal()
    {
        return $this->isGlobal;
    }
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
