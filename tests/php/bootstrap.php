<?php

/**
 * Bootstrap testowy dla wtyczki "Event Calendar".
 *
 * To NIE jest instalacja WordPressa (brak bazy danych, brak wp-env) — to
 * lekkie stuby funkcji WP, których event-calendar.php / inc/*.php faktycznie
 * używa na etapie ładowania pliku, wystarczające żeby wczytać PRAWDZIWY kod
 * wtyczki w gołym PHP CLI i asertować na jego rzeczywistym zachowaniu.
 *
 * add_action()/add_filter() tylko REJESTRUJĄ callbacki (jak w WP), nie
 * wykonują ich automatycznie — więc rejestracja CPT, meta, REST route,
 * shortcode'a itd. (wszystko zawinięte w add_action(...)) nigdy się tu nie
 * odpala i nie wymaga stubowania. Testowane są tylko czyste funkcje
 * zdefiniowane na najwyższym poziomie plików (ec_build_events_query,
 * ec_parse_post_types, ec_parse_single_taxonomy, ec_darken_border,
 * ec_get_click_behavior i renderery ustawień).
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');

// Prawdziwa ścieżka do katalogu wtyczki — tests/php/ -> tests/ -> event-calendar/
define('EC_TEST_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

// ── "opcje" WordPressa jako zwykła zmienna globalna w pamięci ──────────────
$GLOBALS['__wp_options'] = [];

function get_option(string $key, $default = false)
{
    return $GLOBALS['__wp_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool
{
    $GLOBALS['__wp_options'][$key] = $value;
    return true;
}

// ── hooki: rejestrujemy naprawdę, nie wykonujemy automatycznie ─────────────
$GLOBALS['__wp_actions'] = [];
$GLOBALS['__wp_filters'] = [];

function add_action(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['__wp_actions'][$tag][] = $cb;
}

function add_filter(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['__wp_filters'][$tag][] = $cb;
}

/**
 * apply_filters() jest realny (odpala callbacki zarejestrowane przez
 * add_filter powyżej) — dzięki temu np. `ec_events_per_page` da się
 * przetestować tak samo jak w prawdziwym WP. Wyjątek: $ec_months_back /
 * $ec_months_ahead w query-builder.php są liczone RAZ, w momencie require —
 * żeby przetestować inny zakres miesięcy, nadpisz $GLOBALS['ec_months_back']
 * / $GLOBALS['ec_months_ahead'] bezpośrednio w teście (patrz test-query-builder.php).
 */
function apply_filters(string $tag, $value, ...$args)
{
    foreach ($GLOBALS['__wp_filters'][$tag] ?? [] as $cb) {
        $value = $cb($value, ...$args);
    }
    return $value;
}

function ec_test_get_action(string $tag): callable
{
    if (empty($GLOBALS['__wp_actions'][$tag])) {
        throw new RuntimeException("Nie zarejestrowano żadnego callbacku dla akcji '{$tag}'.");
    }
    return $GLOBALS['__wp_actions'][$tag][0];
}

/**
 * Odpala WSZYSTKIE callbacki zarejestrowane dla danego hooka, w kolejności
 * rejestracji — jak prawdziwe do_action(). event-calendar.php rejestruje
 * kilka niezależnych add_action('init', ...) (CPT/taksonomie, textdomain,
 * meta, blok, shortcode); to woła je wszystkie, więc testy nie zależą od
 * pozycji konkretnego callbacku w tablicy.
 */
function ec_test_fire(string $tag, ...$args): void
{
    foreach ($GLOBALS['__wp_actions'][$tag] ?? [] as $cb) {
        $cb(...$args);
    }
}

// ── post types / taxonomie jako proste rejestry w pamięci ──────────────────
$GLOBALS['__wp_post_types'] = [
    'post'       => ['public' => true],
    'page'       => ['public' => true],
    'attachment' => ['public' => true],
];
$GLOBALS['__wp_taxonomies'] = [];

function register_post_type(string $post_type, array $args = [])
{
    $GLOBALS['__wp_post_types'][$post_type] = $args;
    return (object) array_merge(['name' => $post_type], $args);
}

function get_post_types(array $args = [], string $output = 'names')
{
    $matching = array_filter($GLOBALS['__wp_post_types'], function ($def) use ($args) {
        foreach ($args as $key => $value) {
            if (($def[$key] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    });
    return $output === 'names' ? array_keys($matching) : $matching;
}

function post_type_exists(string $post_type): bool
{
    return isset($GLOBALS['__wp_post_types'][$post_type]);
}

function register_taxonomy(string $taxonomy, $object_type, array $args = []): void
{
    $GLOBALS['__wp_taxonomies'][$taxonomy] = $args;
}

function taxonomy_exists(string $taxonomy): bool
{
    return isset($GLOBALS['__wp_taxonomies'][$taxonomy]);
}

function ec_test_reset_post_types(): void
{
    // reset do stanu domyślnego + pomocnik do testów ec_parse_post_types()
    $GLOBALS['__wp_post_types'] = [
        'post'       => ['public' => true],
        'page'       => ['public' => true],
        'attachment' => ['public' => true],
    ];
}

// ── sanitizery / walidatory (uproszczone, ale zgodne z zachowaniem WP) ─────
function sanitize_key($key): string
{
    $key = strtolower((string) $key);
    return preg_replace('/[^a-z0-9_\-]/', '', $key);
}

function sanitize_text_field($str): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string) $str)));
}

function sanitize_title($title): string
{
    $map = ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z'];
    $title = strtolower(strtr((string) $title, $map));
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);
    return trim($title, '-');
}

function absint($maybeint): int
{
    return abs((int) $maybeint);
}

/**
 * Kopia logiki prawdziwego sanitize_hex_color() z WP core: puste zostaje
 * puste, poprawny #rgb/#rrggbb przechodzi bez zmian, wszystko inne -> null.
 */
function sanitize_hex_color($color)
{
    if ('' === $color) {
        return '';
    }
    if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color)) {
        return $color;
    }
    return null;
}

function wp_parse_args($args, $defaults = [])
{
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (!is_array($args)) {
        parse_str((string) $args, $args);
    }
    return array_merge($defaults, $args);
}

// ── i18n / output — passthrough, bez faktycznego tłumaczenia ───────────────
function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return htmlspecialchars($text, ENT_QUOTES);
}

function esc_html_e(string $text, string $domain = 'default'): void
{
    echo htmlspecialchars($text, ENT_QUOTES);
}

function esc_html($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_attr($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_url($url): string
{
    return htmlspecialchars((string) $url, ENT_QUOTES);
}

function selected($a, $b = true, bool $echo = true): string
{
    $result = ((string) $a === (string) $b) ? ' selected="selected"' : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

function current_user_can(string $cap): bool
{
    return $GLOBALS['__wp_current_user_can'] ?? true;
}

// ── ścieżki / metadane pliku wtyczki ────────────────────────────────────────
function plugin_dir_path(string $file): string
{
    return EC_TEST_PLUGIN_DIR;
}

function plugin_dir_url(string $file): string
{
    return 'http://example.test/wp-content/plugins/event-calendar/';
}

function plugin_basename(string $file): string
{
    return basename(dirname($file)) . '/' . basename($file);
}

function admin_url(string $path = ''): string
{
    return 'http://example.test/wp-admin/' . $path;
}

/**
 * Uproszczona wersja WP get_file_data(): czyta prawdziwy docblock na górze
 * event-calendar.php, żeby define('EC_VERSION', ...) w pliku głównym
 * dostał realną wartość zamiast fatalnego błędu z powodu brakującej funkcji.
 */
function get_file_data(string $file, array $fields): array
{
    $header = substr((string) @file_get_contents($file), 0, 8192);
    $data = [];
    foreach ($fields as $field => $name) {
        if (preg_match('/^[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $header, $m)) {
            $data[$field] = trim($m[1]);
        } else {
            $data[$field] = '';
        }
    }
    return $data;
}

// ── reszta: rejestracje, których body nigdy się tu nie odpala (add_action
// je tylko przechowuje) — stuby jako no-op, żeby require_once nie fatalował
// jeśli jakiś przyszły test jednak odpali dany hook ─────────────────────────
function register_post_meta(...$args): void {}
function register_rest_route(...$args): void {}
function register_block_type(...$args): void {}
function add_shortcode(...$args): void {}
function add_submenu_page(...$args): void {}
function register_setting(...$args): void {}
function add_settings_section(...$args): void {}
function add_settings_field(...$args): void {}
function settings_fields(...$args): void {}
function do_settings_sections(...$args): void {}
function submit_button(...$args): void {}
function wp_enqueue_script(...$args): void {}
function wp_enqueue_style(...$args): void {}
function wp_localize_script(...$args): void {}
function wp_add_inline_script(...$args): void {}
function load_plugin_textdomain(...$args): void {}
function get_posts(...$args): array { return []; }
function update_meta_cache(...$args): void {}
function wp_list_pluck($list, $field) { return array_column((array) $list, $field); }
function get_post_meta(...$args): array { return []; }
function get_permalink(...$args): string { return ''; }
function wp_hash($data): string { return md5((string) $data); }
function set_transient(...$args): bool { return true; }
function get_transient(...$args) { return false; }
function date_i18n(string $format, $timestamp = null, bool $gmt = false): string
{
    return $gmt ? gmdate($format, $timestamp ?? time()) : date($format, $timestamp ?? time());
}
function wp_timezone_string(): string { return 'UTC'; }
function get_locale(): string { return 'pl_PL'; }
function rest_ensure_response($data) { return $data; }
function wp_json_encode($data, int $flags = 0) { return json_encode($data, $flags); }

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;
        public $data;
        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }
}

// ── wczytanie prawdziwego kodu wtyczki ──────────────────────────────────────
require EC_TEST_PLUGIN_DIR . 'event-calendar.php';

// ═══════════════════════════════════════════════════════════════════════════
//  MIKRO-FRAMEWORK ASERCJI (identyczny kontrakt jak w katjaen-global-fields)
// ═══════════════════════════════════════════════════════════════════════════

$GLOBALS['__ec_test_pass'] = 0;
$GLOBALS['__ec_test_fail'] = 0;

function ec_test_reset_state(): void
{
    $GLOBALS['__wp_options'] = [];
    $GLOBALS['__wp_current_user_can'] = true;
    ec_test_reset_post_types();
}

function assert_true($cond, string $msg): void
{
    if ($cond) {
        $GLOBALS['__ec_test_pass']++;
    } else {
        $GLOBALS['__ec_test_fail']++;
        echo "  ✗ FAIL: {$msg}\n";
    }
}

function assert_equal($expected, $actual, string $msg): void
{
    $ok = $expected === $actual;
    if (!$ok) {
        $msg .= ' (oczekiwano ' . var_export($expected, true) . ', otrzymano ' . var_export($actual, true) . ')';
    }
    assert_true($ok, $msg);
}

function assert_count(int $expected, $arrayOrCountable, string $msg): void
{
    assert_equal($expected, count($arrayOrCountable), $msg);
}

function ec_test_section(string $title): void
{
    echo "\n== {$title} ==\n";
}

function ec_test_summary(): int
{
    $pass = $GLOBALS['__ec_test_pass'];
    $fail = $GLOBALS['__ec_test_fail'];
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "WYNIK: {$pass} passed, {$fail} failed (razem " . ($pass + $fail) . ")\n";
    return $fail === 0 ? 0 : 1;
}
