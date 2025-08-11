<?php

namespace ShopifyWooImporter\Helpers;

/**
 * Helper for storing and retrieving pagination cursors per tab (e.g., products, customers)
 */
class WMSW_PaginationHelper
{
    // Import WordPress option functions
    public static function setCursor($tab, $cursor)
    {
        \update_option('wmsw_cursor_' . $tab, $cursor);
    }

    public static function getCursor($tab)
    {
        return \get_option('wmsw_cursor_' . $tab, '');
    }

    public static function deleteCursor($tab)
    {
        \delete_option('wmsw_cursor_' . $tab);
    }
}
