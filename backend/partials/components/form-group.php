<?php

/**
 * Form Group Template for Tab Content
 * 
 * @param string $label Input label
 * @param string $name Input name
 * @param string $type Input type (text, number, email, etc.)
 * @param array $attributes Additional input attributes
 * @param string $description Help text description
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set default values
$label = isset($label) ? $label : '';
$name = isset($name) ? $name : '';
$id = isset($id) ? $id : $name;
$type = isset($type) ? $type : 'text';
$value = isset($value) ? $value : '';
$attributes = isset($attributes) ? $attributes : [];
$description = isset($description) ? $description : '';

// Build attributes string
$attr_str = '';
foreach ($attributes as $key => $val) {
    $attr_str .= ' ' . $key . '="' . $val . '"';
}
?>

<div class="swi-form-group">
    <?php if (!empty($label)): ?>
        <label for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($label); ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" <?php echo esc_attr($attr_str); ?>><?php echo esc_textarea($value); ?></textarea>
    <?php elseif ($type === 'select' && !empty($options)): ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" <?php echo esc_attr($attr_str); ?>>
            <?php foreach ($options as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'checkbox'): ?>
        <div class="checkbox-group">
            <label>
                <input type="checkbox" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="1" <?php checked($value, '1'); ?> <?php echo esc_attr($attr_str); ?>>
                <?php echo esc_html($label); ?>
            </label>
        </div>
    <?php elseif ($type === 'radio' && !empty($options)): ?>
        <div class="radio-group">
            <?php foreach ($options as $option_value => $option_label): ?>
                <label>
                    <input type="radio" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id . '_' . $option_value); ?>" value="<?php echo esc_attr($option_value); ?>" <?php checked($value, $option_value); ?> <?php echo esc_attr($attr_str); ?>>
                    <?php echo esc_html($option_label); ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" <?php echo esc_attr($attr_str); ?>>
    <?php endif; ?>

    <?php if (!empty($description)): ?>
        <p class="description"><?php echo wp_kses_post($description); ?></p>
    <?php endif; ?>
</div>