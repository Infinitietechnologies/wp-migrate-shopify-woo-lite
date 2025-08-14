<?php

namespace ShopifyWooImporter\Helpers;

use function sanitize_title;
use function get_page_by_path;
use function get_posts;
use function wp_unique_post_slug;
use function current_time;
use function __;
use function sprintf;

/**
 * Slug Conflict Resolver
 * 
 * Handles all slug conflicts and edge cases for blog imports
 */
class WMSW_SlugConflictResolver
{
    /**
     * WordPress reserved words that cannot be used as post slugs
     */
    private static $reserved_words = [
        'admin', 'login', 'wp-admin', 'feed', 'trackback', 'wp-content',
        'wp-includes', 'wp-json', 'xmlrpc', 'rss', 'rss2', 'rdf', 'atom',
        'comments', 'embed', 'favicon', 'robots', 'sitemap', 'wp-cron',
        'wp-login', 'wp-mail', 'wp-signup', 'wp-activate', 'wp-links-opml',
        'author', 'category', 'tag', 'search', 'page', 'archives'
    ];

    /**
     * Check if a slug conflicts with existing WordPress content
     *
     * @param string $slug The proposed slug
     * @param string $post_type Post type to check against
     * @param int $post_id Exclude this post ID from conflict check
     * @return array Conflict information
     */
    public static function checkSlugConflict($slug, $post_type = 'post', $post_id = 0)
    {
        $sanitized_slug = sanitize_title($slug);
        $conflicts = [];

        // Check for reserved words
        if (self::isReservedWord($sanitized_slug)) {
            $conflicts[] = [
                'type' => 'reserved_word',
                'slug' => $sanitized_slug,
                'message' => sprintf(
                    /* translators: %s: reserved word */
                    __('"%s" is a WordPress reserved word', 'wp-migrate-shopify-woo-lite'), 
                    $sanitized_slug
                )
            ];
        }

        // Check for existing posts/pages
        $existing = self::findExistingContent($sanitized_slug, $post_type, $post_id);
        if ($existing) {
            $conflicts = array_merge($conflicts, $existing);
        }

        return [
            'has_conflicts' => !empty($conflicts),
            'original_slug' => $slug,
            'sanitized_slug' => $sanitized_slug,
            'conflicts' => $conflicts
        ];
    }

    /**
     * Resolve slug conflicts based on resolution strategy
     *
     * @param string $slug Original slug
     * @param string $resolution_strategy How to resolve conflicts
     * @param string $post_type Post type
     * @param array $context Additional context for resolution
     * @return string Resolved slug
     */
    public static function resolveSlugConflict($slug, $resolution_strategy, $post_type = 'post', $context = [])
    {
        $sanitized_slug = sanitize_title($slug);

        switch ($resolution_strategy) {
            case 'append_counter':
                return self::appendCounter($sanitized_slug, $post_type);

            case 'append_date':
                return self::appendDate($sanitized_slug, $post_type);

            case 'append_prefix':
                $prefix = $context['prefix'] ?? 'shopify';
                return self::appendPrefix($sanitized_slug, $prefix, $post_type);

            case 'append_suffix':
                $suffix = $context['suffix'] ?? 'import';
                return self::appendSuffix($sanitized_slug, $suffix, $post_type);

            case 'unique_wordpress':
                return self::generateWordPressUniqueSlug($sanitized_slug, $post_type);

            case 'manual':
                return $context['manual_slug'] ?? $sanitized_slug;

            default:
                return self::appendCounter($sanitized_slug, $post_type);
        }
    }

    /**
     * Generate multiple resolution options for a conflicted slug
     *
     * @param string $slug Original slug
     * @param string $post_type Post type
     * @param array $context Additional context
     * @return array Array of resolution options
     */
    public static function generateResolutionOptions($slug, $post_type = 'post', $context = [])
    {
        $sanitized_slug = sanitize_title($slug);
        $options = [];

        // Option 1: Append counter
        $options['append_counter'] = [
            'strategy' => 'append_counter',
            'slug' => self::appendCounter($sanitized_slug, $post_type),
            'label' => __('Append number (e.g., slug-2)', 'wp-migrate-shopify-woo-lite'),
            'description' => __('Add a number to make the slug unique', 'wp-migrate-shopify-woo-lite')
        ];

        // Option 2: Append date
        $options['append_date'] = [
            'strategy' => 'append_date',
            'slug' => self::appendDate($sanitized_slug, $post_type),
            'label' => __('Append date (e.g., slug-jul2025)', 'wp-migrate-shopify-woo-lite'),
            'description' => __('Add current month/year to make unique', 'wp-migrate-shopify-woo-lite')
        ];

        // Option 3: Add prefix
        $prefix = $context['prefix'] ?? 'shopify';
        $options['append_prefix'] = [
            'strategy' => 'append_prefix',
            'slug' => self::appendPrefix($sanitized_slug, $prefix, $post_type),
            'label' => sprintf(
                /* translators: %s: prefix text */
                __('Add prefix (%s-slug)', 'wp-migrate-shopify-woo-lite'), 
                $prefix
            ),
            'description' => __('Add prefix to identify as imported content', 'wp-migrate-shopify-woo-lite')
        ];

        // Option 4: WordPress unique slug generator
        $options['unique_wordpress'] = [
            'strategy' => 'unique_wordpress',
            'slug' => self::generateWordPressUniqueSlug($sanitized_slug, $post_type),
            'label' => __('WordPress auto-generated unique slug', 'wp-migrate-shopify-woo-lite'),
            'description' => __('Let WordPress generate a unique slug automatically', 'wp-migrate-shopify-woo-lite')
        ];

        // Option 5: Manual entry
        $options['manual'] = [
            'strategy' => 'manual',
            'slug' => '',
            'label' => __('Enter custom slug manually', 'wp-migrate-shopify-woo-lite'),
            'description' => __('Specify a custom slug for this content', 'wp-migrate-shopify-woo-lite')
        ];

        return $options;
    }

    /**
     * Check if slug is a WordPress reserved word
     *
     * @param string $slug
     * @return bool
     */
    private static function isReservedWord($slug)
    {
        return in_array(strtolower($slug), self::$reserved_words, true);
    }

    /**
     * Find existing content with the same slug
     *
     * @param string $slug
     * @param string $post_type
     * @param int $exclude_post_id
     * @return array
     */
    private static function findExistingContent($slug, $post_type, $exclude_post_id = 0)
    {
        $conflicts = [];

        // Check posts of the same type
        $existing_post = get_page_by_path($slug, OBJECT, $post_type);
        if ($existing_post && $existing_post->ID !== $exclude_post_id) {
            $conflicts[] = [
                'type' => 'same_post_type',
                'post_id' => $existing_post->ID,
                'post_title' => $existing_post->post_title,
                'post_type' => $existing_post->post_type,
                'message' => sprintf(
                    /* translators: 1: post type, 2: post title */
                    __('A %1$s with this slug already exists: "%2$s"', 'wp-migrate-shopify-woo-lite'),
                    $post_type,
                    $existing_post->post_title
                )
            ];
        }

        // Check other post types
        $other_types = ['post', 'page', 'product'];
        foreach ($other_types as $type) {
            if ($type === $post_type) continue;

            $existing = get_page_by_path($slug, OBJECT, $type);
            if ($existing) {
                $conflicts[] = [
                    'type' => 'cross_post_type',
                    'post_id' => $existing->ID,
                    'post_title' => $existing->post_title,
                    'post_type' => $existing->post_type,
                    'message' => sprintf(
                        /* translators: 1: post type, 2: post title */
                        __('A %1$s with this slug already exists: "%2$s"', 'wp-migrate-shopify-woo-lite'),
                        $type,
                        $existing->post_title
                    )
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Append counter to slug to make it unique
     *
     * @param string $slug
     * @param string $post_type
     * @return string
     */
    private static function appendCounter($slug, $post_type)
    {
        $counter = 2;
        $new_slug = $slug . '-' . $counter;

        while (get_page_by_path($new_slug, OBJECT, $post_type)) {
            $counter++;
            $new_slug = $slug . '-' . $counter;

            // Safety break
            if ($counter > 1000) {
                break;
            }
        }

        return $new_slug;
    }

    /**
     * Append date to slug
     *
     * @param string $slug
     * @param string $post_type
     * @return string
     */
    private static function appendDate($slug, $post_type)
    {
        $date_suffix = strtolower(current_time('My')); // e.g., "jul2025"
        $new_slug = $slug . '-' . $date_suffix;

        // If still conflicts, append counter
        if (get_page_by_path($new_slug, OBJECT, $post_type)) {
            return self::appendCounter($new_slug, $post_type);
        }

        return $new_slug;
    }

    /**
     * Add prefix to slug
     *
     * @param string $slug
     * @param string $prefix
     * @param string $post_type
     * @return string
     */
    private static function appendPrefix($slug, $prefix, $post_type)
    {
        $new_slug = $prefix . '-' . $slug;

        // If still conflicts, append counter
        if (get_page_by_path($new_slug, OBJECT, $post_type)) {
            return self::appendCounter($new_slug, $post_type);
        }

        return $new_slug;
    }

    /**
     * Add suffix to slug
     *
     * @param string $slug
     * @param string $suffix
     * @param string $post_type
     * @return string
     */
    private static function appendSuffix($slug, $suffix, $post_type)
    {
        $new_slug = $slug . '-' . $suffix;

        // If still conflicts, append counter
        if (get_page_by_path($new_slug, OBJECT, $post_type)) {
            return self::appendCounter($new_slug, $post_type);
        }

        return $new_slug;
    }

    /**
     * Use WordPress built-in unique slug generator
     *
     * @param string $slug
     * @param string $post_type
     * @return string
     */
    private static function generateWordPressUniqueSlug($slug, $post_type)
    {
        return wp_unique_post_slug($slug, 0, 'publish', $post_type, 0);
    }

    /**
     * Batch check multiple slugs for conflicts
     *
     * @param array $slugs Array of slugs to check
     * @param string $post_type Post type
     * @return array Results for each slug
     */
    public static function batchCheckSlugs($slugs, $post_type = 'post')
    {
        $results = [];

        foreach ($slugs as $key => $slug) {
            $results[$key] = self::checkSlugConflict($slug, $post_type);
        }

        return $results;
    }

    /**
     * Get summary of conflicts for reporting
     *
     * @param array $conflict_results Results from batchCheckSlugs
     * @return array Summary statistics
     */
    public static function getConflictSummary($conflict_results)
    {
        $total = count($conflict_results);
        $conflicts = 0;
        $reserved_words = 0;
        $existing_content = 0;

        foreach ($conflict_results as $result) {
            if ($result['has_conflicts']) {
                $conflicts++;

                foreach ($result['conflicts'] as $conflict) {
                    if ($conflict['type'] === 'reserved_word') {
                        $reserved_words++;
                    } else {
                        $existing_content++;
                    }
                }
            }
        }

        return [
            'total_checked' => $total,
            'total_conflicts' => $conflicts,
            'clean_slugs' => $total - $conflicts,
            'reserved_words' => $reserved_words,
            'existing_content' => $existing_content,
            'conflict_percentage' => $total > 0 ? round(($conflicts / $total) * 100, 2) : 0
        ];
    }
}
