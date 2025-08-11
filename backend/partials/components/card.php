<?php
/**
 * Card Template for Tab Content
 * 
 * @param string $title Card title
 * @param string $content Card content
 * @param array $footer_actions Array of footer actions (optional)
 * @param array $additional_classes Additional CSS classes for the card (optional)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set default values
$title = isset($title) ? $title : '';
$content = isset($content) ? $content : '';
$footer_actions = isset($footer_actions) ? $footer_actions : [];
$additional_classes = isset($additional_classes) ? $additional_classes : [];

// Combine classes
$card_classes = ['swi-card'];
if (!empty($additional_classes) && is_array($additional_classes)) {
    $card_classes = array_merge($card_classes, $additional_classes);
}
?>

<div class="<?php echo esc_attr(implode(' ', $card_classes)); ?>">
    <?php if (!empty($title)): ?>
    <div class="swi-card-header">
        <h3 class="swi-card-title"><?php echo esc_html($title); ?></h3>
    </div>
    <?php endif; ?>
    
    <div class="swi-card-body">
        <?php echo wp_kses_post($content); ?>
    </div>
    
    <?php if (!empty($footer_actions)): ?>
    <div class="swi-card-footer">
        <?php foreach ($footer_actions as $action): ?>
            <?php
            $action_url = isset($action['url']) ? $action['url'] : '#';
            $action_text = isset($action['text']) ? $action['text'] : '';
            $action_class = isset($action['class']) ? $action['class'] : 'swi-btn-secondary';
            $action_icon = isset($action['icon']) ? $action['icon'] : '';
            ?>
            <a href="<?php echo esc_url($action_url); ?>" class="swi-btn <?php echo esc_attr($action_class); ?>">
                <?php if (!empty($action_icon)): ?>
                <span class="dashicons dashicons-<?php echo esc_attr($action_icon); ?>"></span>
                <?php endif; ?>
                <?php echo esc_html($action_text); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
