<?php

/**
 * Event Calendar Block - No build registration
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
	// Register the block
	register_block_type(__DIR__ . '/block.json');
});

add_action('enqueue_block_editor_assets', function () {
	// Enqueue editor script with inline React component
	wp_enqueue_script(
		'event-calendar-block-editor',
		plugin_dir_url(__FILE__) . 'editor.js',
		[
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-data',
			'wp-i18n'
		],
		'1.0.0',
		true
	);

	// Pass translated strings to JS
	wp_localize_script('event-calendar-block-editor', 'eventCalendarBlockLabels', [
		'calendarSettings' => __('Calendar Settings', 'event-calendar'),
		'view' => __('View', 'event-calendar'),
		'month' => __('Month', 'event-calendar'),
		'week' => __('Week', 'event-calendar'),
		'day' => __('Day', 'event-calendar'),
		'postType' => __('Post Type', 'event-calendar'),
		'allPostTypes' => __('All post types', 'event-calendar'),
		'postTypeHelp' => __('Select which post type to display events from', 'event-calendar'),
		'filters' => __('Filters', 'event-calendar'),
		'taxonomy' => __('Taxonomy', 'event-calendar'),
		'noTaxonomy' => __('No taxonomy filter', 'event-calendar'),
		'taxonomyHelp' => __('Filter by taxonomy', 'event-calendar'),
		'terms' => __('Terms', 'event-calendar'),
		'termsHelp' => __('Enter term IDs or slugs, comma-separated', 'event-calendar'),
		'operator' => __('Operator', 'event-calendar'),
		'operatorIN' => __('IN (any of)', 'event-calendar'),
		'operatorAND' => __('AND (all of)', 'event-calendar'),
		'operatorNOTIN' => __('NOT IN (none of)', 'event-calendar'),
		'eventCalendar' => __('Event Calendar', 'event-calendar'),
	]);
});
