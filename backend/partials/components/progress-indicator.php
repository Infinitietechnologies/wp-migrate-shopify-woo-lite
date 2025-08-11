<?php
/**
 * Progress Indicator Component Partial
 *
 * @package ShopifyWooImporter\Backend\Partials\Components
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display a progress indicator
 *
 * @param array $args Progress indicator arguments
 * @return void
 */
function WMSW_progress_indicator($args = []) {
    $defaults = [
        'progress' => 0,
        'total' => 100,
        'label' => '',
        'show_percentage' => true,
        'show_count' => false,
        'class' => '',
        'size' => 'medium', // small, medium, large
        'color' => 'blue', // blue, green, orange, red
        'animated' => false,
        'striped' => false
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Calculate percentage
    $percentage = 0;
    if ($args['total'] > 0) {
        $percentage = min(100, max(0, ($args['progress'] / $args['total']) * 100));
    }
    
    // Build CSS classes
    $classes = ['swi-progress-indicator'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    $classes[] = 'swi-progress-' . $args['size'];
    $classes[] = 'swi-progress-' . $args['color'];
    
    if ($args['animated']) {
        $classes[] = 'swi-progress-animated';
    }
    if ($args['striped']) {
        $classes[] = 'swi-progress-striped';
    }
    
    $class_string = implode(' ', $classes);
    ?>
    
    <div class="<?php echo esc_attr($class_string); ?>">
        <?php if (!empty($args['label'])) : ?>
            <div class="swi-progress-label">
                <?php echo esc_html($args['label']); ?>
                <?php if ($args['show_count']) : ?>
                    <span class="swi-progress-count">
                        (<?php echo esc_html(number_format($args['progress'])); ?> / <?php echo esc_html(number_format($args['total'])); ?>)
                    </span>
                <?php endif; ?>
                <?php if ($args['show_percentage']) : ?>
                    <span class="swi-progress-percentage">
                        <?php echo esc_html(round($percentage, 1)); ?>%
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="swi-progress-bar">
            <div class="swi-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%">
                <?php if (empty($args['label']) && $args['show_percentage']) : ?>
                    <span class="swi-progress-text"><?php echo esc_html(round($percentage, 1)); ?>%</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($args['label']) && $args['show_count'] && !$args['show_percentage']) : ?>
            <div class="swi-progress-info">
                <?php echo esc_html(number_format($args['progress'])); ?> / <?php echo esc_html(number_format($args['total'])); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
}

/**
 * Display a circular progress indicator
 *
 * @param array $args Progress indicator arguments
 * @return void
 */
function WMSW_circular_progress($args = []) {
    $defaults = [
        'progress' => 0,
        'total' => 100,
        'size' => 60, // diameter in pixels
        'stroke_width' => 4,
        'color' => '#0073aa',
        'background_color' => '#e0e0e0',
        'show_percentage' => true,
        'class' => ''
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Calculate percentage and circumference
    $percentage = 0;
    if ($args['total'] > 0) {
        $percentage = min(100, max(0, ($args['progress'] / $args['total']) * 100));
    }
    
    $radius = ($args['size'] - $args['stroke_width']) / 2;
    $circumference = 2 * pi() * $radius;
    $stroke_dasharray = $circumference;
    $stroke_dashoffset = $circumference - ($percentage / 100) * $circumference;
    
    $classes = ['swi-circular-progress'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="width: <?php echo esc_attr($args['size']); ?>px; height: <?php echo esc_attr($args['size']); ?>px;">
        <svg width="<?php echo esc_attr($args['size']); ?>" height="<?php echo esc_attr($args['size']); ?>" viewBox="0 0 <?php echo esc_attr($args['size']); ?> <?php echo esc_attr($args['size']); ?>">
            <!-- Background circle -->
            <circle
                cx="<?php echo esc_attr($args['size'] / 2); ?>"
                cy="<?php echo esc_attr($args['size'] / 2); ?>"
                r="<?php echo esc_attr($radius); ?>"
                fill="none"
                stroke="<?php echo esc_attr($args['background_color']); ?>"
                stroke-width="<?php echo esc_attr($args['stroke_width']); ?>"
            />
            <!-- Progress circle -->
            <circle
                cx="<?php echo esc_attr($args['size'] / 2); ?>"
                cy="<?php echo esc_attr($args['size'] / 2); ?>"
                r="<?php echo esc_attr($radius); ?>"
                fill="none"
                stroke="<?php echo esc_attr($args['color']); ?>"
                stroke-width="<?php echo esc_attr($args['stroke_width']); ?>"
                stroke-linecap="round"
                stroke-dasharray="<?php echo esc_attr($stroke_dasharray); ?>"
                stroke-dashoffset="<?php echo esc_attr($stroke_dashoffset); ?>"
                transform="rotate(-90 <?php echo esc_attr($args['size'] / 2); ?> <?php echo esc_attr($args['size'] / 2); ?>)"
                class="swi-progress-circle"
            />
        </svg>
        
        <?php if ($args['show_percentage']) : ?>
            <div class="swi-circular-progress-text">
                <?php echo esc_html(round($percentage)); ?>%
            </div>
        <?php endif; ?>
    </div>
    
    <?php
}

/**
 * Display a step progress indicator
 *
 * @param array $args Step progress arguments
 * @return void
 */
function WMSW_step_progress($args = []) {
    $defaults = [
        'steps' => [],
        'current_step' => 0,
        'class' => '',
        'show_labels' => true,
        'show_numbers' => true
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    if (empty($args['steps'])) {
        return;
    }
    
    $classes = ['swi-step-progress'];
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="swi-step-progress-line"></div>
        
        <?php foreach ($args['steps'] as $index => $step) : 
            $step_number = $index + 1;
            $is_completed = $step_number < $args['current_step'];
            $is_current = $step_number == $args['current_step'];
            $is_future = $step_number > $args['current_step'];
            
            $step_classes = ['swi-step'];
            if ($is_completed) {
                $step_classes[] = 'swi-step-completed';
            } elseif ($is_current) {
                $step_classes[] = 'swi-step-current';
            } else {
                $step_classes[] = 'swi-step-future';
            }
        ?>
            <div class="<?php echo esc_attr(implode(' ', $step_classes)); ?>">
                <div class="swi-step-indicator">
                    <?php if ($args['show_numbers']) : ?>
                        <?php if ($is_completed) : ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php else : ?>
                            <?php echo esc_html($step_number); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($args['show_labels'] && !empty($step['label'])) : ?>
                    <div class="swi-step-label">
                        <?php echo esc_html($step['label']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($step['description'])) : ?>
                    <div class="swi-step-description">
                        <?php echo esc_html($step['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php
}

/**
 * Display a loading spinner
 *
 * @param array $args Spinner arguments
 * @return void
 */
function WMSW_loading_spinner($args = []) {
    $defaults = [
        'size' => 'medium', // small, medium, large
        'color' => 'blue', // blue, green, orange, red, white
        'class' => '',
        'inline' => false
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $classes = ['swi-loading-spinner'];
    $classes[] = 'swi-spinner-' . $args['size'];
    $classes[] = 'swi-spinner-' . $args['color'];
    
    if ($args['inline']) {
        $classes[] = 'swi-spinner-inline';
    }
    
    if (!empty($args['class'])) {
        $classes[] = $args['class'];
    }
    ?>
    
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="swi-spinner"></div>
    </div>
    
    <?php
}
?>
