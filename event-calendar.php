<?php

/**
 * Plugin Name: Event Calendar
 * Description: Lightweight, flexible WordPress plugin to create and display events — custom CPT, meta fields, Gutenberg block, shortcode, REST API
 * Version: 1.0.0
 * Author: Katarzyna Niklas
 * Text Domain: event-calendar
 * Domain Path: /languages
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

/* =========================
    VERSION
========================= */
// Single source of truth: read straight from the docblock above, so every
// wp_enqueue_script/style() call below stays in sync with the "Version:"
// header just by bumping it there — nothing else to touch.
define('EC_VERSION', get_file_data(__FILE__, ['Version' => 'Version'])['Version']);

/* =========================
    PLUGIN ROW LINKS ("Settings" / "Docs" next to Deactivate)
========================= */

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $extra = [
        'settings' => sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('edit.php?post_type=event&page=event-calendar-settings')),
            esc_html__('Settings', 'event-calendar')
        ),
        'docs' => sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://github.com/katjaen/event-calendar#readme'),
            esc_html__('Docs', 'event-calendar')
        ),
    ];

    return array_merge($extra, $links);
});

/* =========================
    CPT + TAXONOMIES
========================= */

add_action('init', function () {

    register_post_type('event', [
        'labels' => [
            'name'          => __('Events', 'event-calendar'),
            'singular_name' => __('Event', 'event-calendar'),
            'add_new_item'  => __('Add New Event', 'event-calendar'),
            'edit_item'     => __('Edit Event', 'event-calendar'),
            'all_items'     => __('All Events', 'event-calendar'),
            'not_found'     => __('No events found.', 'event-calendar'),
        ],
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => ['slug' => 'event'],
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-calendar-alt',
        'supports'           => ['title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields'],
        'show_in_rest'       => true,
    ]);

    register_taxonomy('event_category', ['event'], [
        'label'             => __('Event Categories', 'event-calendar'),
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'event-category'],
    ]);

    register_taxonomy('event_location', ['event'], [
        'label'             => __('Event Locations', 'event-calendar'),
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'event-location'],
    ]);
});

/* =========================
    INCLUDES
========================= */
require_once plugin_dir_path(__FILE__) . 'inc/query-builder.php';
require_once plugin_dir_path(__FILE__) . 'inc/settings.php';

/* =========================
    COLOR CONFIGURATION
========================= */

// color darken boost in event background
define('EVENT_CALENDAR_START_DAY_SOURCE', 'wp'); // you can use 'wp' or 'locale'
define('EC_BORDER_DARKEN_BOOST', 20);


/* =========================
    COLOR HELPERS
========================= */

function ec_darken_border($hex_color, $darken_percent = EC_BORDER_DARKEN_BOOST)
{
    if (empty($hex_color)) {
        return '#9b7bc4';
    }

    $hex = ltrim($hex_color, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Mix z czarnym
    $r = round($r * (1 - $darken_percent / 100));
    $g = round($g * (1 - $darken_percent / 100));
    $b = round($b * (1 - $darken_percent / 100));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/* =========================
    INIT
========================= */

// Load Text Domain
add_action('init', function () {
    load_plugin_textdomain('event-calendar', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 0);

// Register meta fields (Gutenberg + REST API)
// Priority 20 (not the default 10, and definitely not an earlier one): must
// run AFTER every custom post type on the site has been registered on
// 'init', otherwise get_post_types() below won't see them yet and their
// meta never gets exposed to REST/Gutenberg for that post type — the
// sidebar UI still renders (its own enqueue isn't gated on this), but
// anything typed into it silently fails to save.
add_action('init', function () {
    $post_types = get_post_types(['public' => true], 'names');
    $post_types = array_diff($post_types, ['page', 'attachment']);

    foreach ($post_types as $post_type) {
        register_post_meta($post_type, '_is_event', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'default' => '0',
            'sanitize_callback' => function ($value) {
                return $value === '1' ? '1' : '0';
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta($post_type, '_event_start', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta($post_type, '_event_end', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta($post_type, '_event_location', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function ($value) {
                return mb_substr(sanitize_text_field($value), 0, 255);
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta($post_type, '_event_all_day', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'default' => '0',
            'sanitize_callback' => function ($value) {
                return $value === '1' ? '1' : '0';
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta($post_type, '_event_color', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'default' => '#d3c1ef',
            'sanitize_callback' => function ($value) {
                if (!empty($value) && $value[0] !== '#') {
                    $value = '#' . $value;
                }
                $clean = sanitize_hex_color($value);
                if (empty($clean)) {
                    return '#d3c1ef';
                }
                return $clean;
            },
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}, 20);

/* =========================
    GUTENBERG SIDEBAR
========================= */

add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'event-calendar-sidebar',
        plugin_dir_url(__FILE__) . 'assets/js/gutenberg-event-sidebar.js',
        [
            'wp-plugins',
            'wp-editor',
            'wp-components',
            'wp-data',
            'wp-element',
        ],
        EC_VERSION,
        true
    );

    // Labels from PHP → translations works immediately
    wp_localize_script('event-calendar-sidebar', 'eventCalendarSidebarLabels', [
        'title'         => __('Event Details', 'event-calendar'),
        'includeLabel'  => __('Include in Calendar?', 'event-calendar'),
        'includeHelp'   => __('Check to include this post in the calendar', 'event-calendar'),
        'allDayLabel'   => __('All day', 'event-calendar'),
        'allDayHelp'    => __('This event lasts all day', 'event-calendar'),
        'startLabel'    => __('Start Date & Time', 'event-calendar'),
        'startDate'     => __('Start Date', 'event-calendar'),
        'endLabel'      => __('End Date & Time', 'event-calendar'),
        'endDate'       => __('End Date', 'event-calendar'),
        'endDateHelp'   => __('Must be on or after the start date', 'event-calendar'),
        'locationLabel' => __('Location', 'event-calendar'),
        'colorLabel'    => __('Color', 'event-calendar'),
        'pageNotice'    => __('Events are not available for pages. Use posts or custom post types instead.', 'event-calendar'),
        'mirroredNotice' => __('This post type already has its own event fields elsewhere on this screen — they get mirrored here automatically. Edit them there instead.', 'event-calendar'),
        'endBeforeStartWarning' => __('End date must be after start date', 'event-calendar'),
    ]);

    // Post types that manage their own event fields and mirror them into
    // _event_* meta via their own save hook (see e.g. core-gmina/includes/
    // calendar.php) — for these, showing this sidebar too would just be a
    // second, disconnected place to type the same thing. Empty by default;
    // other plugins opt their own post types out via this filter.
    wp_localize_script('event-calendar-sidebar', 'eventCalendarMirroredPostTypes', apply_filters('ec_mirrored_post_types', []));
});

/* =========================
    REST API
========================= */

add_action('rest_api_init', function () {
    register_rest_route('event-calendar/v1', '/events', [
        'methods' => 'GET',
        'callback' => function ($request) {

            // --- Check if query_id is provided (used by blocks/shortcodes) ---
            $query_id = $request->get_param('query_id');
            if (!empty($query_id)) {
                // Only ever look up transients this plugin itself created
                // (see the 'ec_' . wp_hash(...) below) — without this check
                // a caller could pass the name of *any* transient on the
                // site and have its value fed into get_posts().
                if (strpos($query_id, 'ec_') !== 0) {
                    return new WP_Error(
                        'invalid_query',
                        'Query not found or expired',
                        ['status' => 404]
                    );
                }

                $args = get_transient($query_id);
                if (!is_array($args)) {
                    return new WP_Error(
                        'invalid_query',
                        'Query not found or expired',
                        ['status' => 404]
                    );
                }
            } else {
                // Default query: all events
                $args = ec_build_events_query([]);
            }

            // --- Fetch events ---
            $events = get_posts($args);

            // --- Load all post meta in bulk to prevent N+1 queries ---
            update_meta_cache('post', wp_list_pluck($events, 'ID'));

            $data = [];
            foreach ($events as $event) {
                $meta = get_post_meta($event->ID);

                $allDay   = isset($meta['_event_all_day'][0]) && $meta['_event_all_day'][0] === '1';
                $start    = isset($meta['_event_start'][0]) ? $meta['_event_start'][0] : '';
                $end      = isset($meta['_event_end'][0]) ? $meta['_event_end'][0] : '';
                $color    = isset($meta['_event_color'][0]) ? $meta['_event_color'][0] : '';
                $location = isset($meta['_event_location'][0]) ? $meta['_event_location'][0] : '';

                if (empty($start)) continue;
                if (empty($end)) $end = $start;

                $data[] = [
                    'id'              => $event->ID,
                    'title'           => $event->post_title ?: __('Untitled', 'event-calendar'),
                    'allDay'          => $allDay,
                    'start'           => $allDay ? substr($start, 0, 10) : $start,
                    'end'             => $allDay ? substr($end, 0, 10) : $end,
                    'location'        => $location,
                    'description'     => $event->post_content ?: '',
                    'backgroundColor' => $color ?: '#d3c1ef',
                    'borderColor'     => ec_darken_border($color ?: '#d3c1ef'),
                    'url'             => get_permalink($event),
                ];
            }

            // --- Prepare REST response with caching headers ---
            $response = rest_ensure_response($data);
            $response->header('Cache-Control', 'public, max-age=300'); // cache for 5 minutes

            return $response;
        },

        // --- Permissions: only allow public read access ---
        'permission_callback' => '__return_true'
    ]);
});

/* =========================
    GUTENBERG BLOCK
========================= */

require_once plugin_dir_path(__FILE__) . 'blocks/calendar/index.php';

/* =========================
    SHORTCODE
========================= */

add_action('init', function () {
    add_shortcode('event_calendar', function ($atts) {
        static $calendar_count = 0;
        $calendar_count++;

        $atts = shortcode_atts([
            'view' => 'month',
            'post_type' => '',
            'taxonomy' => '',
            'terms' => '',
            'operator' => 'IN',

        ], $atts);

        // Build query config
        $config = [
            'post_type' => $atts['post_type']
        ];

        // Single taxonomy support
        if (!empty($atts['taxonomy']) && !empty($atts['terms'])) {
            $tax_query = ec_parse_single_taxonomy(
                $atts['taxonomy'] . ':' . $atts['terms'],
                $atts['operator']
            );
            if ($tax_query) {
                $config['taxonomy_queries'] = [$tax_query];
            }
        }

        // Build query args
        $query_args = ec_build_events_query($config);

        // Store in transient for REST API to retrieve
        $query_id = 'ec_' . substr(wp_hash($calendar_count . serialize($query_args)), 0, 12);
        set_transient($query_id, $query_args, 12 * HOUR_IN_SECONDS);

        // Enqueue scripts and styles
        wp_enqueue_script(
            'tui-calendar',
            plugin_dir_url(__FILE__) . 'assets/js/toastui-calendar.min.js',
            [],
            '2.1.3',
            true
        );
        wp_enqueue_style(
            'toastui-calendar',
            plugin_dir_url(__FILE__) . 'assets/css/toastui-calendar.min.css',
            [],
            '2.1.3'
        );
        wp_enqueue_style(
            'event-calendar',
            plugin_dir_url(__FILE__) . 'assets/css/calendar.css',
            ['toastui-calendar'],
            EC_VERSION
        );
        wp_enqueue_script(
            'event-calendar-init',
            plugin_dir_url(__FILE__) . 'assets/js/calendar-init.js',
            ['tui-calendar'],
            EC_VERSION,
            true
        );

        // Generate day names in natural order (Sunday = 0)
        // IMPORTANT: Must use GMT to ensure Sunday starts at index 0
        // Otherwise timezone offset can shift the array
        $day_names_short = [];
        $sunday_timestamp = 1672531200; // 2023-01-01 00:00:00 UTC (Sunday)

        for ($i = 0; $i < 7; $i++) {
            $timestamp = $sunday_timestamp + ($i * DAY_IN_SECONDS);
            // Third parameter = true forces GMT, preventing timezone shifts
            $day_names_short[] = date_i18n('D', $timestamp, true);
        }

        // Get WordPress settings
        $start_of_week = (int) get_option('start_of_week', 1); // Force integer
        $time_format = get_option('time_format');
        $is_24_hour = (strpos($time_format, 'H') !== false || strpos($time_format, 'G') !== false);

        $calendar_data = [
            'apiUrl' => rest_url('event-calendar/v1/events'),
            'queryId' => $query_id,
            'timeFormat' => $time_format,
            'use24Hour' => $is_24_hour,
            'timezone' => wp_timezone_string(),
            'dateFormat' => get_option('date_format'),
            'startOfWeek' => $start_of_week,
            'dayNamesShort' => $day_names_short,
            'allDayLabel' => __('All day', 'event-calendar'),
            'todayLabel' => __('Today', 'event-calendar'),
            'prevLabel' => __('Previous', 'event-calendar'),
            'nextLabel' => __('Next', 'event-calendar'),
            'locale' => str_replace('_', '-', get_locale()),
            'startDaySource' => EVENT_CALENDAR_START_DAY_SOURCE,
            'clickBehavior' => ec_get_click_behavior(),
        ];

        wp_add_inline_script(
            'event-calendar-init',
            sprintf(
                'window.eventCalendarData = window.eventCalendarData || {};
                window.eventCalendarData[%d] = %s;',
                $calendar_count,
                wp_json_encode($calendar_data)
            ),
            'before'
        );

        // Normalize the initial view the same way calendar-init.js does
        // (normalizeView()) so the view-switcher's active button always
        // matches whatever TUI Calendar actually renders.
        $initial_view = in_array($atts['view'], ['day', 'week', 'month'], true)
            ? $atts['view']
            : 'month';

        $view_labels = [
            'day' => esc_html__('Day', 'event-calendar'),
            'week' => esc_html__('Week', 'event-calendar'),
            'month' => esc_html__('Month', 'event-calendar'),
        ];

        $view_buttons = '';
        foreach ($view_labels as $view_key => $view_label) {
            $view_buttons .= sprintf(
                '<button type="button" data-action="view" data-view="%1$s" class="%2$s" aria-pressed="%3$s">%4$s</button>',
                esc_attr($view_key),
                $view_key === $initial_view ? 'is-active' : '',
                $view_key === $initial_view ? 'true' : 'false',
                $view_label
            );
        }

        // Navigation HTML
        $navigation = sprintf(
            '<div class="ec-calendar-nav" data-calendar-target="%1$d">
                <button data-action="today">%2$s</button>
                <div style="display: flex; gap: 4px;">
                    <button data-action="prev">‹</button>
                    <button data-action="next">›</button>
                </div>
                <span class="ec-calendar-current-date" data-calendar-id="%1$d"></span>
                <div class="ec-calendar-view-switch" role="group" aria-label="%3$s">%4$s</div>
            </div>',
            $calendar_count,
            esc_html__('Today', 'event-calendar'),
            esc_attr__('View', 'event-calendar'),
            $view_buttons
        );

        // Calendar container
        return $navigation . sprintf(
            '<div class="ec-calendar" data-calendar-view="%s" data-calendar-id="%d"></div>',
            esc_attr($atts['view']),
            $calendar_count
        );
    });
});

