<?php

/**
 * Render callback for Event Calendar block
 * 
 * @param array $attributes Block attributes
 * @return string Rendered block HTML
 */

if (!defined('ABSPATH')) exit;

// Extract attributes
$view = isset($attributes['view']) ? $attributes['view'] : 'month';
$post_type = isset($attributes['postType']) ? $attributes['postType'] : '';
$taxonomy = isset($attributes['taxonomy']) ? $attributes['taxonomy'] : '';
$terms = isset($attributes['terms']) && is_array($attributes['terms'])
    ? array_map('intval', $attributes['terms'])
    : [];
$operator = isset($attributes['operator']) ? $attributes['operator'] : 'IN';

// Build shortcode attributes
$shortcode_atts = sprintf(
    'view="%s"',
    esc_attr($view)
);

if (!empty($post_type)) {
    $shortcode_atts .= sprintf(' post_type="%s"', esc_attr($post_type));
}

if (!empty($taxonomy) && !empty($terms)) {
    $shortcode_atts .= sprintf(
        ' taxonomy="%s" terms="%s" operator="%s"',
        esc_attr($taxonomy),
        esc_attr(implode(',', $terms)),
        esc_attr($operator)
    );
}

// Use existing shortcode
echo do_shortcode("[event_calendar {$shortcode_atts}]");
