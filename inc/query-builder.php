<?php

/**
 * Query Builder for Event Calendar
 * Shared logic for shortcode, block, and REST API
 */

if (!defined('ABSPATH')) exit;

/**
 * Domyśle granice zakresu dat kalendarza (ile miesięcy wstecz/w przód od
 * dziś wciągamy wydarzenia). Dwa sposoby nadpisania:
 *
 *  1) Filtr w motywie/child-theme (przeżywa aktualizacje wtyczki):
 *     add_filter('ec_event_months_back', fn() => 1);
 *     add_filter('ec_event_months_ahead', fn() => 3);
 *
 *  2) Zmiana stałych poniżej wprost w tym pliku — szybsze przy jednorazowym
 *     dostosowaniu, ale aktualizacja wtyczki nadpisze plik i przywróci
 *     wartości domyślne.
 */
define('EC_DEFAULT_MONTHS_BACK', 2);
define('EC_DEFAULT_MONTHS_AHEAD', 2);

/**
 * Build WP_Query arguments for events
 * 
 * @param array $config {
 *     Configuration array
 *     @type string|array $post_type    Post type(s) to query
 *     @type array        $taxonomy_queries Array of tax_query arrays
 *     @type string       $tax_relation 'AND' or 'OR'
 * }
 * @return array WP_Query arguments
 */
function ec_build_events_query($config = [])
{
    // How many months to display back and ahead. Resolved here (call time,
    // i.e. during shortcode/REST render) rather than at file-include time —
    // add_filter() calls in a theme's functions.php run after plugins are
    // loaded, so evaluating apply_filters() at the top of this file would
    // run before any override had a chance to register.
    $ec_months_back  = apply_filters('ec_event_months_back', EC_DEFAULT_MONTHS_BACK);
    $ec_months_ahead = apply_filters('ec_event_months_ahead', EC_DEFAULT_MONTHS_AHEAD);

    $defaults = [
        'post_type' => '',
        'taxonomy_queries' => [],
        'tax_relation' => 'AND',
    ];

    $config = wp_parse_args($config, $defaults);

    // Determine start and end dates for query
    $start_date = date('Y-m-01', strtotime("-$ec_months_back months"));
    $end_date   = date('Y-m-t', strtotime("+$ec_months_ahead months"));

    // Determine post types
    $post_types = ec_parse_post_types($config['post_type']);

    // Base query args
    $args = [
        'post_type'      => $post_types,
        'posts_per_page' => apply_filters('ec_events_per_page', 500),
        'post_status'    => 'publish',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_is_event',
                'value'   => '1',
                'compare' => '='
            ],
            [
                'key'     => '_event_start',
                'compare' => 'EXISTS'
            ],
            // Granica "w przód": kiedy wydarzenie się zaczyna.
            [
                'key'     => '_event_start',
                'value'   => $end_date,
                'compare' => '<=',
                'type'    => 'DATETIME'
            ],
            // Granica "wstecz": liczona od _event_end (żeby wielodniowe
            // wydarzenie, które zaczęło się dawno, ale skończyło niedawno,
            // nie znikało z kalendarza) — z fallbackiem na _event_start,
            // gdy _event_end jest puste/nieustawione (typowe dla wydarzeń
            // jednodniowych; sidebar Gutenberga zapisuje wtedy pusty string,
            // patrz gutenberg-event-sidebar.js).
            [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    [
                        'key'     => '_event_end',
                        'value'   => '',
                        'compare' => '!=',
                    ],
                    [
                        'key'     => '_event_end',
                        'value'   => $start_date,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ],
                ],
                [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key'     => '_event_end',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => '_event_end',
                            'value'   => '',
                            'compare' => '=',
                        ],
                    ],
                    [
                        'key'     => '_event_start',
                        'value'   => $start_date,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ],
                ],
            ],
        ],
        'orderby'  => 'meta_value',
        'meta_key' => '_event_start',
        'order'    => 'ASC',
    ];

    // Add taxonomy queries if provided
    if (!empty($config['taxonomy_queries']) && is_array($config['taxonomy_queries'])) {
        $args['tax_query'] = $config['taxonomy_queries'];
        $args['tax_query']['relation'] = $config['tax_relation'];
    }

    return apply_filters('ec_events_query_args', $args, $config);
}

/**
 * Parse and validate post types
 * 
 * @param string|array $input Post type(s)
 * @return array Valid post type names
 */
function ec_parse_post_types($input)
{
    // If empty, get all public post types except attachment and page
    if (empty($input)) {
        $types = get_post_types(['public' => true], 'names');
        return array_values(array_diff($types, ['attachment', 'page']));
    }

    // Convert string to array
    if (is_string($input)) {
        $input = array_map('trim', explode(',', $input));
    }

    // Validate each post type exists
    $valid_types = array_filter($input, 'post_type_exists');

    // Fallback to all if none valid
    if (empty($valid_types)) {
        $types = get_post_types(['public' => true], 'names');
        return array_values(array_diff($types, ['attachment', 'page']));
    }

    return array_values($valid_types);
}

/**
 * Parse single taxonomy query from shortcode format
 * Format: "taxonomy_slug:term1,term2"
 * 
 * @param string $input Taxonomy query string
 * @return array|null Tax query array or null if invalid
 */
function ec_parse_single_taxonomy($input, $operator = 'IN')
{
    if (empty($input) || !is_string($input)) {
        return null;
    }

    $parts = explode(':', $input);
    if (count($parts) < 2) {
        return null;
    }

    $taxonomy = sanitize_key(trim($parts[0]));
    if (!taxonomy_exists($taxonomy)) {
        return null;
    }

    $terms = array_map('trim', explode(',', $parts[1]));
    $terms = array_filter($terms);

    if (empty($terms)) {
        return null;
    }

    $term_ids = array_filter(array_map('absint', $terms));
    $use_ids = (count($term_ids) === count($terms));

    // Validate operator
    $valid_operators = ['IN', 'NOT IN', 'AND'];
    $operator = strtoupper($operator);
    if (!in_array($operator, $valid_operators)) {
        $operator = 'IN';
    }

    return [
        'taxonomy' => $taxonomy,
        'field' => $use_ids ? 'term_id' : 'slug',
        'terms' => $use_ids ? $term_ids : array_map('sanitize_title', $terms),
        'operator' => $operator
    ];
}
