<?php
require __DIR__ . '/bootstrap.php';

ec_test_section('ec_parse_post_types() — pusty input zwraca wszystkie publiczne typy bez page/attachment');

ec_test_reset_state();
ec_test_fire('init'); // rejestruje realne 'event' + taksonomie z event-calendar.php
$types = ec_parse_post_types('');
assert_true(in_array('post', $types, true), 'pusty input: zawiera "post"');
assert_true(in_array('event', $types, true), 'pusty input: zawiera "event" (zarejestrowany przez wtyczkę)');
assert_true(!in_array('page', $types, true), 'pusty input: nie zawiera "page"');
assert_true(!in_array('attachment', $types, true), 'pusty input: nie zawiera "attachment"');

ec_test_section('ec_parse_post_types() — string CSV filtrowany przez post_type_exists()');

ec_test_reset_state();
ec_test_fire('init');
assert_equal(['event'], ec_parse_post_types('event, nieistniejacy_typ'), 'CSV: nieistniejący typ odrzucony, "event" zostaje');
assert_equal(['post', 'event'], ec_parse_post_types('post,event'), 'CSV: dwa poprawne typy zachowane w kolejności wejścia');

ec_test_section('ec_parse_post_types() — same nieistniejące typy -> fallback do wszystkich publicznych');

ec_test_reset_state();
ec_test_fire('init');
$fallback = ec_parse_post_types('nieistniejacy_a,nieistniejacy_b');
assert_true(in_array('event', $fallback, true), 'fallback: same śmieci na wejściu -> lista domyślna zawiera "event"');

ec_test_section('ec_parse_post_types() — tablica na wejściu też działa (nie tylko string)');

ec_test_reset_state();
ec_test_fire('init');
assert_equal(['event'], ec_parse_post_types(['event', 'cos_niepoprawnego']), 'tablica: filtrowanie działa tak samo jak dla CSV');

ec_test_section('ec_parse_single_taxonomy() — format "taxonomy:term1,term2", same liczby -> field=term_id');

ec_test_reset_state();
ec_test_fire('init');
$tq = ec_parse_single_taxonomy('event_category:12,34', 'IN');
assert_true($tq !== null, 'parsuje się poprawnie');
assert_equal('event_category', $tq['taxonomy'], 'taxonomy odczytane poprawnie');
assert_equal('term_id', $tq['field'], 'same liczby na wejściu -> field=term_id');
assert_equal([12, 34], $tq['terms'], 'terms skonwertowane na inty');
assert_equal('IN', $tq['operator'], 'operator domyślny/przekazany zachowany');

ec_test_section('ec_parse_single_taxonomy() — mieszanka liczb i słów -> field=slug, wszystko sanityzowane jako slug');

ec_test_reset_state();
ec_test_fire('init');
$tq2 = ec_parse_single_taxonomy('event_category:12,warsztaty', 'IN');
assert_equal('slug', $tq2['field'], 'mieszanka ID/slug -> traktowane jako slugi (bezpieczny fallback)');
assert_equal(['12', 'warsztaty'], $tq2['terms'], 'slugi zachowane po sanitize_title()');

ec_test_section('ec_parse_single_taxonomy() — walidacja operatora i taksonomii');

ec_test_reset_state();
ec_test_fire('init');
assert_equal(null, ec_parse_single_taxonomy('nieistniejaca_tax:1,2'), 'nieistniejąca taksonomia -> null');
assert_equal(null, ec_parse_single_taxonomy('brak_dwukropka'), 'brak ":" w stringu -> null');
assert_equal(null, ec_parse_single_taxonomy(''), 'pusty string -> null');
$tqBadOp = ec_parse_single_taxonomy('event_category:1', 'DROP TABLE');
assert_equal('IN', $tqBadOp['operator'], 'niepoprawny operator -> cichy fallback do "IN" (nie przechodzi surowy do SQL)');

ec_test_section('ec_build_events_query() — kształt meta_query i domyślny post_type');

ec_test_reset_state();
ec_test_fire('init');
$args = ec_build_events_query([]);
assert_equal('publish', $args['post_status'], 'tylko opublikowane posty');
assert_equal('_event_start', $args['meta_key'], 'sortowanie po _event_start');
assert_equal('ASC', $args['order'], 'sortowanie rosnąco');
assert_equal('AND', $args['meta_query']['relation'], 'relacja meta_query = AND');
assert_equal('_is_event', $args['meta_query'][0]['key'], 'pierwszy warunek: _is_event = 1');
assert_equal('1', $args['meta_query'][0]['value'], 'pierwszy warunek: wartość "1"');
assert_true(in_array('event', $args['post_type'], true), 'domyślnie zapytanie obejmuje "event"');
assert_true(!isset($args['tax_query']), 'bez taxonomy_queries w configu -> brak tax_query w ogóle');

ec_test_section('ec_build_events_query() — zakres dat liczony z $ec_months_back / $ec_months_ahead');

ec_test_reset_state();
ec_test_fire('init');
// Wartości ustawiane raz przy require (apply_filters z domyślnym 2), ale
// funkcja czyta je przez `global`, więc można je podmienić wprost w teście —
// nie trzeba przeładowywać całego pliku ani bawić się w add_filter przed require.
$GLOBALS['ec_months_back'] = 1;
$GLOBALS['ec_months_ahead'] = 1;
$args2 = ec_build_events_query([]);
$expectedStart = date('Y-m-01', strtotime('-1 months'));
$expectedEnd   = date('Y-m-t', strtotime('+1 months'));
assert_equal($expectedStart, $args2['meta_query'][2]['value'], 'start_date = początek miesiąca sprzed 1 miesiąca');
assert_equal($expectedEnd, $args2['meta_query'][3]['value'], 'end_date = koniec miesiąca za 1 miesiąc');
$GLOBALS['ec_months_back'] = 2;
$GLOBALS['ec_months_ahead'] = 2;

ec_test_section('ec_build_events_query() — taxonomy_queries + tax_relation trafiają do tax_query');

ec_test_reset_state();
ec_test_fire('init');
$tq3 = ec_parse_single_taxonomy('event_category:5', 'IN');
$args3 = ec_build_events_query(['taxonomy_queries' => [$tq3], 'tax_relation' => 'OR']);
assert_true(isset($args3['tax_query']), 'taxonomy_queries w configu -> tax_query obecne');
assert_equal('OR', $args3['tax_query']['relation'], 'tax_relation z configu przekazany dalej');
assert_equal('event_category', $args3['tax_query'][0]['taxonomy'], 'sam tax_query zawiera przekazaną taksonomię');

ec_test_section('ec_build_events_query() — filtr ec_events_per_page realnie działa (apply_filters)');

ec_test_reset_state();
add_filter('ec_events_per_page', function () {
    return 50;
});
$args4 = ec_build_events_query([]);
assert_equal(50, $args4['posts_per_page'], 'zarejestrowany filtr zmienia posts_per_page (tak jak zrobiłby to motyw/inna wtyczka)');

$exit_code = ec_test_summary();
exit($exit_code);
