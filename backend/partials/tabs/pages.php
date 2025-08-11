<?php

/**
 * Pages Tab - Locked in Lite Version
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wmsw-locked-feature-container">
    <div class="wmsw-locked-feature">
        <div class="wmsw-lock-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10z" />
            </svg>
        </div>
        
        <h2 class="wmsw-locked-title"><?php esc_html_e('Pages Import', 'wp-migrate-shopify-woo'); ?></h2>
        
        <p class="wmsw-locked-description">
            <?php esc_html_e('Convert Shopify pages into WordPress pages with professional SEO optimization and content preservation.', 'wp-migrate-shopify-woo'); ?>
        </p>

        <!-- Feature Highlights Grid -->
        <div class="wmsw-feature-highlights">
            <div class="wmsw-feature-highlight">
                <div class="wmsw-feature-icon">
                    <span class="dashicons dashicons-admin-page"></span>
                </div>
                <span><?php esc_html_e('Smart Import', 'wp-migrate-shopify-woo'); ?></span>
            </div>
            <div class="wmsw-feature-highlight">
                <div class="wmsw-feature-icon">
                    <span class="dashicons dashicons-search"></span>
                </div>
                <span><?php esc_html_e('SEO Ready', 'wp-migrate-shopify-woo'); ?></span>
            </div>
            <div class="wmsw-feature-highlight">
                <div class="wmsw-feature-icon">
                    <span class="dashicons dashicons-admin-links"></span>
                </div>
                <span><?php esc_html_e('Link Fix', 'wp-migrate-shopify-woo'); ?></span>
            </div>
            <div class="wmsw-feature-highlight">
                <div class="wmsw-feature-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <span><?php esc_html_e('Auto Fix', 'wp-migrate-shopify-woo'); ?></span>
            </div>
        </div>
        
        <div class="wmsw-locked-features">
            <ul>
                <li><?php esc_html_e('✓ Full page content import with HTML preservation', 'wp-migrate-shopify-woo'); ?></li>
                <li><?php esc_html_e('✓ SEO metadata preservation & optimization', 'wp-migrate-shopify-woo'); ?></li>
                <li><?php esc_html_e('✓ Automatic slug conflict resolution', 'wp-migrate-shopify-woo'); ?></li>
                <li><?php esc_html_e('✓ Bulk import with real-time progress tracking', 'wp-migrate-shopify-woo'); ?></li>
                <li><?php esc_html_e('✓ Content quality validation & cleanup', 'wp-migrate-shopify-woo'); ?></li>
                <li><?php esc_html_e('✓ Link validation & automatic fixing', 'wp-migrate-shopify-woo'); ?></li>
            </ul>
        </div>

        <!-- Call to Action Section -->
        <div class="wmsw-cta-section">
            <div class="wmsw-cta-text">
                <strong><?php esc_html_e('Ready to unlock the full potential?', 'wp-migrate-shopify-woo'); ?></strong>
                <p><?php esc_html_e('Join thousands of successful migrations with our Pro version.', 'wp-migrate-shopify-woo'); ?></p>
            </div>
            
            <a href="https://codecanyon.net/item/wp-shopify-to-woocommerce-migrate-import-shopify-store-to-wordpress/59187755?s_rank=1" 
               target="_blank" 
               class="wmsw-upgrade-button">
                <span class="dashicons dashicons-star-filled"></span>
                <?php esc_html_e('Upgrade to Pro', 'wp-migrate-shopify-woo'); ?>
            </a>
        </div>
    </div>
</div>
