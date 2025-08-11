<?php
/**
 * Notification Component Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Components
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display a notification message
 *
 * @param string $message The notification message
 * @param string $type The notification type (success, error, warning, info)
 * @param array $args Additional arguments
 * @return void
 */
function WMSW_notification($message, $type = 'info', $args = []) {
    $defaults = [
        'dismissible' => true,
        'class' => '',
        'id' => '',
        'icon' => true,
        'title' => '',
        'actions' => []
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Build CSS classes
    $classes = ['swi-notification', 'swi-notification-' . $type];
    
    if ($args['dismissible']) {
        $classes[] = 'swi-notification-dismissible';
    }
    
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    
    // Icon mapping
    $icons = [
        'success' => 'dashicons-yes-alt',
        'error' => 'dashicons-dismiss',
        'warning' => 'dashicons-warning',
        'info' => 'dashicons-info'
    ];
    
    $id_attr = !empty($args['id']) ? 'id="' . esc_attr($args['id']) . '"' : '';
    ?>
    
    <div <?php echo wp_kses_post($id_attr); ?> class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="swi-notification-content">
            <?php if ($args['icon'] && isset($icons[$type])) : ?>
                <div class="swi-notification-icon">
                    <span class="dashicons <?php echo esc_attr($icons[$type]); ?>"></span>
                </div>
            <?php endif; ?>
            
            <div class="swi-notification-message">
                <?php if (!empty($args['title'])) : ?>
                    <div class="swi-notification-title">
                        <?php echo esc_html($args['title']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="swi-notification-text">
                    <?php echo wp_kses_post($message); ?>
                </div>
                
                <?php if (!empty($args['actions'])) : ?>
                    <div class="swi-notification-actions">
                        <?php foreach ($args['actions'] as $action) : ?>
                            <a href="<?php echo esc_url($action['url'] ?? '#'); ?>" 
                               class="swi-notification-action <?php echo esc_attr($action['class'] ?? ''); ?>"
                               <?php echo !empty($action['target']) ? 'target="' . esc_attr($action['target']) . '"' : ''; ?>>
                                <?php echo esc_html($action['label'] ?? ''); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($args['dismissible']) : ?>
            <button type="button" class="swi-notification-dismiss" aria-label="<?php esc_attr_e('Dismiss notification', 'wp-migrate-shopify-woo'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        <?php endif; ?>
    </div>
    
    <?php
}

/**
 * Display a toast notification (JavaScript-based)
 *
 * @param string $message The notification message
 * @param string $type The notification type
 * @param array $args Additional arguments
 * @return void
 */
function WMSW_toast_notification($message, $type = 'info', $args = []) {
    $defaults = [
        'duration' => 5000, // milliseconds
        'position' => 'top-right', // top-left, top-right, bottom-left, bottom-right, top-center, bottom-center
        'class' => '',
        'id' => 'swi-toast-' . uniqid()
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Enqueue the toast data for JavaScript
    wp_localize_script('swi-backend-js', 'wmsw_toast_data', [
        'message' => $message,
        'type' => $type,
        'duration' => $args['duration'],
        'position' => $args['position'],
        'class' => $args['class'],
        'id' => $args['id']
    ]);
}

/**
 * Display a banner notification
 *
 * @param string $message The notification message
 * @param string $type The notification type
 * @param array $args Additional arguments
 * @return void
 */
function WMSW_banner_notification($message, $type = 'info', $args = []) {
    $defaults = [
        'dismissible' => true,
        'class' => '',
        'id' => '',
        'full_width' => true,
        'sticky' => false
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-banner-notification', 'swi-banner-' . $type];
    
    if ($args['full_width']) {
        $classes[] = 'swi-banner-full-width';
    }
    
    if ($args['sticky']) {
        $classes[] = 'swi-banner-sticky';
    }
    
    if ($args['dismissible']) {
        $classes[] = 'swi-banner-dismissible';
    }
    
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    
    $id_attr = !empty($args['id']) ? 'id="' . esc_attr($args['id']) . '"' : '';
    ?>
    
    <div <?php echo wp_kses_post($id_attr); ?> class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="swi-banner-content">
            <?php echo wp_kses_post($message); ?>
        </div>
        
        <?php if ($args['dismissible']) : ?>
            <button type="button" class="swi-banner-dismiss" aria-label="<?php esc_attr_e('Dismiss banner', 'wp-migrate-shopify-woo'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        <?php endif; ?>
    </div>
    
    <?php
}

/**
 * Display an inline notification
 *
 * @param string $message The notification message
 * @param string $type The notification type
 * @param array $args Additional arguments
 * @return void
 */
function WMSW_inline_notification($message, $type = 'info', $args = []) {
    $defaults = [
        'class' => '',
        'icon' => true,
        'compact' => false
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-inline-notification', 'swi-inline-' . $type];
    
    if ($args['compact']) {
        $classes[] = 'swi-inline-compact';
    }
    
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    
    $icons = [
        'success' => 'dashicons-yes-alt',
        'error' => 'dashicons-dismiss',
        'warning' => 'dashicons-warning',
        'info' => 'dashicons-info'
    ];
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <?php if ($args['icon'] && isset($icons[$type])) : ?>
            <span class="dashicons <?php echo esc_attr($icons[$type]); ?>"></span>
        <?php endif; ?>
        <span class="swi-inline-message"><?php echo wp_kses_post($message); ?></span>
    </div>
    
    <?php
}

/**
 * Display notification based on WordPress admin notices
 *
 * @param string $message The notification message
 * @param string $type The notification type
 * @param array $args Additional arguments
 * @return void
 */
function WMSW_admin_notice($message, $type = 'info', $args = []) {
    $defaults = [
        'dismissible' => true,
        'class' => '',
        'id' => ''
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Map types to WordPress notice classes
    $type_mapping = [
        'success' => 'notice-success',
        'error' => 'notice-error',
        'warning' => 'notice-warning',
        'info' => 'notice-info'
    ];
    
    $notice_type = $type_mapping[$type] ?? 'notice-info';
    
    $classes = ['notice', $notice_type];
    
    if ($args['dismissible']) {
        $classes[] = 'is-dismissible';
    }
    
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    
    $id_attr = !empty($args['id']) ? 'id="' . esc_attr($args['id']) . '"' : '';
    ?>
    
    <div <?php echo wp_kses_post($id_attr); ?> class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <p><?php echo wp_kses_post($message); ?></p>
    </div>
    
    <?php
}

/**
 * JavaScript function to create dynamic notifications
 */
function WMSW_notification_script() {
    ?>
    <script>
    window.SWI = window.SWI || {};
    
    SWI.showNotification = function(message, type, options) {
        type = type || 'info';
        options = options || {};
        
        var defaults = {
            duration: 5000,
            position: 'top-right',
            dismissible: true,
            icon: true
        };
        
        var settings = Object.assign({}, defaults, options);
        
        // Create notification element
        var notification = document.createElement('div');
        notification.className = 'swi-toast-notification swi-toast-' + type + ' swi-toast-' + settings.position;
        
        if (settings.dismissible) {
            notification.className += ' swi-toast-dismissible';
        }
        
        var iconHtml = '';
        if (settings.icon) {
            var icons = {
                'success': 'dashicons-yes-alt',
                'error': 'dashicons-dismiss',
                'warning': 'dashicons-warning',
                'info': 'dashicons-info'
            };
            
            if (icons[type]) {
                iconHtml = '<span class="dashicons ' + icons[type] + '"></span>';
            }
        }
        
        var dismissHtml = '';
        if (settings.dismissible) {
            dismissHtml = '<button type="button" class="swi-toast-dismiss"><span class="dashicons dashicons-no-alt"></span></button>';
        }
        
        notification.innerHTML = '<div class="swi-toast-content">' + iconHtml + '<span class="swi-toast-message">' + message + '</span></div>' + dismissHtml;
        
        // Add to DOM
        var container = document.querySelector('.swi-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'swi-toast-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);
        
        // Add dismiss functionality
        if (settings.dismissible) {
            var dismissBtn = notification.querySelector('.swi-toast-dismiss');
            dismissBtn.addEventListener('click', function() {
                SWI.dismissNotification(notification);
            });
        }
        
        // Auto-dismiss
        if (settings.duration > 0) {
            setTimeout(function() {
                SWI.dismissNotification(notification);
            }, settings.duration);
        }
        
        return notification;
    };
    
    SWI.dismissNotification = function(notification) {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        
        setTimeout(function() {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    };
    
    // Handle WordPress-style dismissible notices
    jQuery(document).ready(function($) {
        $(document).on('click', '.swi-notification-dismiss, .swi-banner-dismiss', function() {
            $(this).closest('.swi-notification, .swi-banner-notification').fadeOut();
        });
    });
    </script>
    <?php
}

// Add the notification script to admin footer
add_action('admin_footer', 'wmsw_notification_script');
?>
