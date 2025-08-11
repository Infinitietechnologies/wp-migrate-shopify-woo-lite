<?php
/**
 * Main Layout Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Layouts
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the main admin layout wrapper
 *
 * @param array $args Layout arguments
 * @return void
 */
function WMSW_main_layout($args = []) {
    $defaults = [
        'title' => '',
        'subtitle' => '',
        'breadcrumbs' => [],
        'actions' => [],
        'tabs' => [],
        'current_tab' => '',
        'content' => '',
        'sidebar' => '',
        'class' => '',
        'show_header' => true,
        'show_tabs' => true,
        'layout' => 'full' // full, sidebar, narrow
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-admin-layout', 'swi-layout-' . $args['layout']];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <?php if ($args['show_header']) : ?>
            <?php WMSW_admin_header($args); ?>
        <?php endif; ?>
        
        <?php if ($args['show_tabs'] && !empty($args['tabs'])) : ?>
            <?php WMSW_admin_tabs($args['tabs'], $args['current_tab']); ?>
        <?php endif; ?>
        
        <div class="swi-admin-body">
            <?php if ($args['layout'] === 'sidebar' && !empty($args['sidebar'])) : ?>
                <div class="swi-admin-content-wrapper">
                    <div class="swi-admin-main-content">
                        <?php echo $args['content']; ?>
                    </div>
                    <div class="swi-admin-sidebar">
                        <?php echo $args['sidebar']; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="swi-admin-main-content">
                    <?php echo $args['content']; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}

/**
 * Render the admin header
 *
 * @param array $args Header arguments
 * @return void
 */
function WMSW_admin_header($args = []) {
    ?>
    <div class="swi-admin-header">
        <div class="swi-header-content">
            <div class="swi-header-title-section">
                <?php if (!empty($args['breadcrumbs'])) : ?>
                    <div class="swi-breadcrumbs">
                        <?php foreach ($args['breadcrumbs'] as $index => $breadcrumb) : ?>
                            <?php if ($index > 0) : ?>
                                <span class="swi-breadcrumb-separator">/</span>
                            <?php endif; ?>
                            
                            <?php if (!empty($breadcrumb['url']) && $index < count($args['breadcrumbs']) - 1) : ?>
                                <a href="<?php echo esc_url($breadcrumb['url']); ?>" class="swi-breadcrumb-link">
                                    <?php echo esc_html($breadcrumb['title']); ?>
                                </a>
                            <?php else : ?>
                                <span class="swi-breadcrumb-current">
                                    <?php echo esc_html($breadcrumb['title']); ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($args['title'])) : ?>
                    <h1 class="swi-page-title">
                        <?php echo esc_html($args['title']); ?>
                    </h1>
                <?php endif; ?>
                
                <?php if (!empty($args['subtitle'])) : ?>
                    <p class="swi-page-subtitle">
                        <?php echo esc_html($args['subtitle']); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($args['actions'])) : ?>
                <div class="swi-header-actions">
                    <?php foreach ($args['actions'] as $action) : ?>
                        <a href="<?php echo esc_url($action['url'] ?? '#'); ?>" 
                           class="button <?php echo esc_attr($action['class'] ?? 'button-secondary'); ?>"
                           <?php echo !empty($action['target']) ? 'target="' . esc_attr($action['target']) . '"' : ''; ?>
                           <?php echo !empty($action['onclick']) ? 'onclick="' . esc_attr($action['onclick']) . '"' : ''; ?>>
                            <?php if (!empty($action['icon'])) : ?>
                                <span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html($action['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render admin tabs
 *
 * @param array $tabs Tab configuration
 * @param string $current_tab Current active tab
 * @return void
 */
function WMSW_admin_tabs($tabs, $current_tab = '') {
    if (empty($tabs)) {
        return;
    }
    ?>
    
    <div class="swi-admin-tabs">
        <div class="swi-tabs-nav">
            <?php foreach ($tabs as $tab_key => $tab) : ?>
                <?php
                $is_active = ($current_tab === $tab_key) || (empty($current_tab) && array_keys($tabs)[0] === $tab_key);
                $tab_classes = ['swi-tab-button'];
                
                if ($is_active) {
                    $tab_classes[] = 'active';
                }
                
                if (!empty($tab['class'])) {
                    $tab_classes[] = $tab['class'];
                }
                
                // Check if tab should be disabled
                $is_disabled = !empty($tab['disabled']);
                if ($is_disabled) {
                    $tab_classes[] = 'disabled';
                }
                ?>
                
                <button type="button" 
                        class="<?php echo esc_attr(implode(' ', $tab_classes)); ?>" 
                        data-tab="<?php echo esc_attr($tab_key); ?>"
                        <?php echo $is_disabled ? 'disabled' : ''; ?>>
                    
                    <?php if (!empty($tab['icon'])) : ?>
                        <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <?php endif; ?>
                    
                    <?php echo esc_html($tab['title']); ?>
                    
                    <?php if (!empty($tab['badge'])) : ?>
                        <span class="swi-tab-badge swi-badge-<?php echo esc_attr($tab['badge']['type'] ?? 'default'); ?>">
                            <?php echo esc_html($tab['badge']['text']); ?>
                        </span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="swi-tabs-content">
            <?php foreach ($tabs as $tab_key => $tab) : ?>
                <?php
                $is_active = ($current_tab === $tab_key) || (empty($current_tab) && array_keys($tabs)[0] === $tab_key);
                $content_classes = ['swi-tab-content'];
                
                if (!$is_active) {
                    $content_classes[] = 'swi-tab-hidden';
                }
                ?>
                
                <div id="swi-tab-<?php echo esc_attr($tab_key); ?>" 
                     class="<?php echo esc_attr(implode(' ', $content_classes)); ?>" 
                     data-tab="<?php echo esc_attr($tab_key); ?>">
                    
                    <?php if (!empty($tab['description'])) : ?>
                        <div class="swi-tab-description">
                            <?php echo wp_kses_post($tab['description']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    if (!empty($tab['content'])) {
                        echo $tab['content'];
                    } elseif (!empty($tab['callback']) && is_callable($tab['callback'])) {
                        call_user_func($tab['callback'], $tab_key, $tab);
                    } elseif (!empty($tab['file']) && file_exists($tab['file'])) {
                        include $tab['file'];
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.swi-tab-button:not(.disabled)').click(function() {
            var tabKey = $(this).data('tab');
            
            // Update button states
            $('.swi-tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Update content visibility
            $('.swi-tab-content').addClass('swi-tab-hidden');
            $('#swi-tab-' + tabKey).removeClass('swi-tab-hidden');
            
            // Trigger custom event
            $(document).trigger('swi:tab-changed', [tabKey]);
            
            // Update URL hash if needed
            if (window.location.hash !== '#' + tabKey) {
                window.location.hash = tabKey;
            }
        });
        
        // Handle initial hash-based tab activation
        if (window.location.hash) {
            var hashTab = window.location.hash.substring(1);
            var hashTabButton = $('.swi-tab-button[data-tab="' + hashTab + '"]');
            if (hashTabButton.length && !hashTabButton.hasClass('disabled')) {
                hashTabButton.click();
            }
        }
    });
    </script>
    
    <?php
}

/**
 * Render a card layout
 *
 * @param array $args Card arguments
 * @return void
 */
function WMSW_card_layout($args = []) {
    $defaults = [
        'title' => '',
        'subtitle' => '',
        'content' => '',
        'footer' => '',
        'class' => '',
        'header_actions' => [],
        'collapsible' => false,
        'collapsed' => false
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-card'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    if ($args['collapsible']) {
        $classes[] = 'swi-card-collapsible';
        if ($args['collapsed']) {
            $classes[] = 'swi-card-collapsed';
        }
    }
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <?php if (!empty($args['title']) || !empty($args['subtitle']) || !empty($args['header_actions'])) : ?>
            <div class="swi-card-header">
                <div class="swi-card-header-content">
                    <?php if (!empty($args['title'])) : ?>
                        <h3 class="swi-card-title">
                            <?php if ($args['collapsible']) : ?>
                                <button type="button" class="swi-card-toggle" aria-expanded="<?php echo $args['collapsed'] ? 'false' : 'true'; ?>">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                            <?php endif; ?>
                            <?php echo esc_html($args['title']); ?>
                        </h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($args['subtitle'])) : ?>
                        <p class="swi-card-subtitle"><?php echo esc_html($args['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($args['header_actions'])) : ?>
                    <div class="swi-card-header-actions">
                        <?php foreach ($args['header_actions'] as $action) : ?>
                            <a href="<?php echo esc_url($action['url'] ?? '#'); ?>" 
                               class="swi-card-action <?php echo esc_attr($action['class'] ?? ''); ?>">
                                <?php if (!empty($action['icon'])) : ?>
                                    <span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span>
                                <?php endif; ?>
                                <?php echo esc_html($action['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="swi-card-body">
            <?php echo $args['content']; ?>
        </div>
        
        <?php if (!empty($args['footer'])) : ?>
            <div class="swi-card-footer">
                <?php echo $args['footer']; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($args['collapsible']) : ?>
        <script>
        jQuery(document).ready(function($) {
            $('.swi-card-toggle').click(function() {
                var card = $(this).closest('.swi-card');
                var isCollapsed = card.hasClass('swi-card-collapsed');
                
                if (isCollapsed) {
                    card.removeClass('swi-card-collapsed');
                    $(this).attr('aria-expanded', 'true');
                } else {
                    card.addClass('swi-card-collapsed');
                    $(this).attr('aria-expanded', 'false');
                }
            });
        });
        </script>
    <?php endif; ?>
    
    <?php
}

/**
 * Render a section layout
 *
 * @param array $args Section arguments
 * @return void
 */
function WMSW_section_layout($args = []) {
    $defaults = [
        'title' => '',
        'description' => '',
        'content' => '',
        'class' => '',
        'level' => 2 // heading level
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-section'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    
    $heading_tag = 'h' . min(6, max(1, intval($args['level'])));
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <?php if (!empty($args['title'])) : ?>
            <<?php echo $heading_tag; ?> class="swi-section-title">
                <?php echo esc_html($args['title']); ?>
            </<?php echo $heading_tag; ?>>
        <?php endif; ?>
        
        <?php if (!empty($args['description'])) : ?>
            <div class="swi-section-description">
                <?php echo wp_kses_post($args['description']); ?>
            </div>
        <?php endif; ?>
        
        <div class="swi-section-content">
            <?php echo $args['content']; ?>
        </div>
    </div>
    
    <?php
}
?>
