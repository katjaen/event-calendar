<?php

/**
 * Settings page for Event Calendar.
 * One option so far (click behavior); add more fields the same way —
 * register_setting() + a field in the same section — no new page needed.
 */

if (!defined('ABSPATH')) exit;

define('EC_OPTION_CLICK_BEHAVIOR', 'ec_click_behavior');

/**
 * Current click-behavior setting, always a valid value even if the option
 * row doesn't exist yet (fresh install) or holds something stale.
 */
function ec_get_click_behavior(): string
{
    $value = get_option(EC_OPTION_CLICK_BEHAVIOR, 'link');
    return in_array($value, ['link'], true) ? $value : 'link';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=event',
        __('Calendar Settings', 'event-calendar'),
        __('Settings', 'event-calendar'),
        'manage_options',
        'event-calendar-settings',
        'ec_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('event_calendar_settings', EC_OPTION_CLICK_BEHAVIOR, [
        'type' => 'string',
        'sanitize_callback' => function ($value) {
            return in_array($value, ['link'], true) ? $value : 'link';
        },
        'default' => 'link',
    ]);

    add_settings_section(
        'ec_main_section',
        __('Behavior', 'event-calendar'),
        '__return_false',
        'event-calendar-settings'
    );

    add_settings_field(
        EC_OPTION_CLICK_BEHAVIOR,
        __('On event click', 'event-calendar'),
        'ec_render_click_behavior_field',
        'event-calendar-settings',
        'ec_main_section'
    );
});

function ec_render_click_behavior_field(): void
{
    $current = ec_get_click_behavior();
?>
    <select name="<?= esc_attr(EC_OPTION_CLICK_BEHAVIOR) ?>">
        <option value="link" <?php selected($current, 'link'); ?>>
            <?php esc_html_e('Go to the event\'s post', 'event-calendar'); ?>
        </option>
    </select>
<?php
}

function ec_render_settings_page(): void
{
?>
    <div class="wrap">
        <h1><?php esc_html_e('Event Calendar Settings', 'event-calendar'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('event_calendar_settings');
            do_settings_sections('event-calendar-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php
}
